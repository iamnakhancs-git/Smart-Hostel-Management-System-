<?php
// Admin — Monitor and Correct Mess Attendance
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn = getDBConnection();
$pageTitle = 'Mess Attendance';
$role = ROLE_ADMIN;
$meals = ['breakfast','lunch','snacks','dinner'];
$filterDate = sanitize(($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['date'] ?? null) : ($_GET['date'] ?? null)) ?: date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate) || date('Y-m-d', strtotime($filterDate)) !== $filterDate) { $filterDate = date('Y-m-d'); }
if (!shmsColumnExists('mess_attendance', 'breakfast_rate') || !shmsColumnExists('mess_daily_menu', 'breakfast_enabled') || !shmsTableExists('bill_mess_details') || !shmsColumnExists('bills', 'previous_dues')) {
    shmsBackendError('Database setup is incomplete. Import sql/upgrade_previous_dues.sql once, or recreate the database using sql/shms_database.sql.', 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { shmsBackendError('Invalid or expired form token. Reload and try again.', 403); }
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'correct_attendance') {
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        $meal = strtolower(sanitize($_POST['meal_type'] ?? ''));
        $newStatusRaw = $_POST['attendance_status'] ?? '';
        if (!in_array($meal, $meals, true) || !in_array((string)$newStatusRaw, ['0','1'], true)) { throw new InvalidArgumentException('Invalid attendance correction request.'); }
        $newStatus = (int)$newStatusRaw;
        $stmt = $conn->prepare('SELECT ma.*,r.name resident_name,dm.' . $meal . '_enabled meal_enabled,dm.' . $meal . '_price locked_price FROM mess_attendance ma JOIN residents r ON r.id=ma.resident_id LEFT JOIN mess_daily_menu dm ON dm.menu_date=ma.date WHERE ma.id=? LIMIT 1');
        $stmt->bind_param('i', $attendanceId); $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$record) { throw new InvalidArgumentException('Attendance record was not found.'); }
        if (empty($record['meal_enabled'])) { throw new InvalidArgumentException(ucfirst($meal) . ' was not enabled in the menu for this date.'); }
        $statusColumn = $meal;
        $rateColumn = $meal . '_rate';
        $oldStatus = (int)$record[$statusColumn];
        if ($oldStatus === $newStatus) {
            setFlash('info', 'No change was required for ' . $record['resident_name'] . '.');
            header('Location: mess_attendance.php?date=' . urlencode($filterDate)); exit;
        }
        // Status can change, but the date-wise meal price remains locked.
        $newRate = null;
        if ($newStatus === 1) {
            $newRate = $record[$rateColumn] !== null ? (float)$record[$rateColumn] : (float)$record['locked_price'];
        }
        $conn->begin_transaction();
        try {
            if ($newStatus === 1) {
                $stmt = $conn->prepare("UPDATE mess_attendance SET `$statusColumn`=1, `$rateColumn`=? WHERE id=?");
                $stmt->bind_param('di', $newRate, $attendanceId);
            } else {
                $stmt = $conn->prepare("UPDATE mess_attendance SET `$statusColumn`=0, `$rateColumn`=NULL WHERE id=?");
                $stmt->bind_param('i', $attendanceId);
            }
            $stmt->execute(); $stmt->close(); $conn->commit();
        } catch (Throwable $e) { $conn->rollback(); throw $e; }

        $billMonth = (int)date('n', strtotime($record['date']));
        $billYear = (int)date('Y', strtotime($record['date']));
        $residentId = (int)$record['resident_id'];
        $stmt = $conn->prepare('SELECT id FROM bills WHERE resident_id=? AND month=? AND year=? LIMIT 1');
        $stmt->bind_param('iii', $residentId, $billMonth, $billYear); $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $message = ucfirst($meal) . ' attendance corrected to ' . ($newStatus ? 'Present' : 'Absent') . ' for ' . $record['resident_name'] . '.';
        if ($bill) {
            setFlash('warning', $message . ' Bill #' . (int)$bill['id'] . ' is now outdated; click Regenerate Bill to apply this correction.');
        } else { setFlash('success', $message); }
        header('Location: mess_attendance.php?date=' . urlencode($filterDate)); exit;
    }

    if ($action === 'regenerate_bill') {
        $billId = (int)($_POST['bill_id'] ?? 0);
        if ($billId < 1) { throw new InvalidArgumentException('Invalid bill.'); }
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT * FROM bills WHERE id=? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('i', $billId); $stmt->execute();
            $bill = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$bill) { throw new InvalidArgumentException('Bill was not found.'); }
            $residentId = (int)$bill['resident_id'];
            $billMonth = (int)$bill['month'];
            $billYear = (int)$bill['year'];
            $roomRent = getRoomRent($residentId);
            $previousDues = getPreviousUnpaidDue($residentId, $billMonth, $billYear);
            $miscAmount = calcMiscCharges($residentId, $billMonth, $billYear);
            $messDetails = getMessChargeDetails($residentId, $billMonth, $billYear);
            $messAmount = 0.0;
            foreach ($messDetails as $detail) { $messAmount += (float)$detail['charged_rate']; }
            $messAmount = round($messAmount, 2);
            $totalAmount = round($roomRent + $previousDues + $miscAmount + $messAmount, 2);
            $amountChanged = abs((float)$bill['total_amount'] - $totalAmount) > 0.009;
            $paymentStatus = ($bill['payment_status'] === 'paid' && $amountChanged) ? 'unpaid' : $bill['payment_status'];
            $paymentDate = $paymentStatus === 'paid' ? $bill['payment_date'] : null;
            $stmt = $conn->prepare('UPDATE bills SET room_rent=?,previous_dues=?,misc_charges=?,mess_charges=?,total_amount=?,payment_status=?,payment_date=?,generated_at=CURRENT_TIMESTAMP WHERE id=?');
            $stmt->bind_param('dddddssi', $roomRent, $previousDues, $miscAmount, $messAmount, $totalAmount, $paymentStatus, $paymentDate, $billId);
            $stmt->execute(); $stmt->close();
            $stmt = $conn->prepare('DELETE FROM bill_mess_details WHERE bill_id=?');
            $stmt->bind_param('i', $billId); $stmt->execute(); $stmt->close();
            if ($messDetails) {
                $stmt = $conn->prepare('INSERT INTO bill_mess_details (bill_id,attendance_date,meal_type,charged_rate) VALUES (?,?,?,?)');
                foreach ($messDetails as $detail) {
                    $detailDate = $detail['attendance_date']; $detailMeal = $detail['meal_type']; $detailRate = (float)$detail['charged_rate'];
                    $stmt->bind_param('issd', $billId, $detailDate, $detailMeal, $detailRate); $stmt->execute();
                }
                $stmt->close();
            }
            $conn->commit();
        } catch (Throwable $e) { $conn->rollback(); throw $e; }
        $message = 'Bill #' . $billId . ' regenerated using corrected attendance. New total: Rs. ' . number_format($totalAmount, 2) . '.';
        if ($bill['payment_status'] === 'paid' && $amountChanged) { $message .= ' Because its amount changed, payment status was reset to Unpaid.'; }
        setFlash('success', $message);
        header('Location: mess_attendance.php?date=' . urlencode($filterDate)); exit;
    }
    throw new InvalidArgumentException('Invalid action.');
}

$stmt = $conn->prepare('SELECT * FROM mess_daily_menu WHERE menu_date=? LIMIT 1');
$stmt->bind_param('s', $filterDate); $stmt->execute();
$menuForDate = $stmt->get_result()->fetch_assoc(); $stmt->close();
$visibleMeals = $menuForDate ? array_values(array_filter($meals, static function ($meal) use ($menuForDate) { return !empty($menuForDate[$meal . '_enabled']); })) : [];

$stmt = $conn->prepare('SELECT ma.*,r.name resident_name,rm.room_number,b.id bill_id,b.payment_status,b.total_amount FROM mess_attendance ma JOIN residents r ON ma.resident_id=r.id LEFT JOIN room_allocations ra ON ra.resident_id=r.id AND ra.is_active=1 LEFT JOIN rooms rm ON ra.room_id=rm.id LEFT JOIN bills b ON b.resident_id=ma.resident_id AND b.month=MONTH(ma.date) AND b.year=YEAR(ma.date) WHERE ma.date=? ORDER BY r.name');
$stmt->bind_param('s', $filterDate); $stmt->execute(); $attendance = $stmt->get_result(); $stmt->close();

$editRecord = null;
$editId = (int)($_GET['edit_id'] ?? 0);
$editMeal = strtolower(sanitize($_GET['meal'] ?? ''));
if ($editId && in_array($editMeal, $meals, true)) {
    $editStmt = $conn->prepare('SELECT ma.*,r.name resident_name,dm.' . $editMeal . '_enabled meal_enabled,dm.' . $editMeal . '_price locked_price FROM mess_attendance ma JOIN residents r ON r.id=ma.resident_id LEFT JOIN mess_daily_menu dm ON dm.menu_date=ma.date WHERE ma.id=? AND ma.date=? LIMIT 1');
    $editStmt->bind_param('is', $editId, $filterDate); $editStmt->execute();
    $editRecord = $editStmt->get_result()->fetch_assoc(); $editStmt->close();
    if ($editRecord && empty($editRecord['meal_enabled'])) { $editRecord = null; }
}
$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><div class="row mb-2">
    <div class="col-sm-6"><h1><i class="fas fa-utensils mr-2"></i>Mess Attendance</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Mess Attendance</li></ol></div>
  </div></div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card"><div class="card-body"><form method="get" class="form-inline"><label class="mr-2">Date:</label><input type="date" name="date" class="form-control mr-2" value="<?= htmlspecialchars($filterDate) ?>"><button class="btn btn-primary"><i class="fas fa-search mr-1"></i>View</button></form></div></div>
    <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Admin can correct Present/Absent attendance. Meal prices remain locked. If a monthly bill already exists, regenerate it after the correction.</div>
    <div class="card"><div class="card-header"><h3 class="card-title">Attendance for <?= date('d M Y',strtotime($filterDate)) ?></h3></div><div class="card-body p-0 table-responsive">
      <?php if (!$visibleMeals): ?><div class="alert alert-info m-3">No meals are enabled for this date.</div><?php else: ?>
      <table class="table table-hover data-table"><thead><tr><th>Resident</th><th>Room</th><?php foreach ($visibleMeals as $meal): ?><th><?= ucfirst($meal) ?></th><?php endforeach; ?><th>Monthly Bill</th></tr></thead><tbody>
      <?php while ($a=$attendance->fetch_assoc()): ?><tr><td><?= htmlspecialchars($a['resident_name']) ?></td><td><?= htmlspecialchars($a['room_number'] ?? '—') ?></td>
        <?php foreach ($visibleMeals as $meal): ?><td>
          <?php if ($a[$meal]): ?><span class="badge badge-success">Present</span><br><small>Rs. <?= number_format((float)$a[$meal . '_rate'],2) ?></small><?php else: ?><span class="badge badge-secondary">Absent</span><?php endif; ?>
          <a class="btn btn-xs btn-outline-primary ml-1" title="Correct attendance" href="?date=<?= urlencode($filterDate) ?>&edit_id=<?= (int)$a['id'] ?>&meal=<?= $meal ?>"><i class="fas fa-edit"></i></a>
        </td><?php endforeach; ?>
        <td><?php if ($a['bill_id']): ?>
          <div class="mb-1"><strong>#<?= (int)$a['bill_id'] ?></strong> · Rs. <?= number_format((float)$a['total_amount'],2) ?> <span class="badge badge-<?= $a['payment_status']==='paid'?'success':'warning' ?>"><?= ucfirst($a['payment_status']) ?></span></div>
          <form method="post" class="d-inline" onsubmit="return confirm('Recalculate this bill using the latest attendance, room rent and charges?')"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="regenerate_bill"><input type="hidden" name="date" value="<?= htmlspecialchars($filterDate) ?>"><input type="hidden" name="bill_id" value="<?= (int)$a['bill_id'] ?>"><button class="btn btn-xs btn-warning"><i class="fas fa-sync-alt mr-1"></i>Regenerate Bill</button></form>
        <?php else: ?><span class="text-muted">Not generated</span><?php endif; ?></td>
      </tr><?php endwhile; ?></tbody></table>
      <?php endif; ?>
    </div></div>
    <?php if ($editRecord): $currentStatus=(int)$editRecord[$editMeal]; $displayRate=$editRecord[$editMeal.'_rate']!==null?(float)$editRecord[$editMeal.'_rate']:(float)$editRecord['locked_price']; ?>
    <div class="card card-primary"><div class="card-header"><h3 class="card-title">Correct <?= ucfirst($editMeal) ?> Attendance</h3></div><div class="card-body">
      <p><strong>Resident:</strong> <?= htmlspecialchars($editRecord['resident_name']) ?> &nbsp; <strong>Date:</strong> <?= date('d M Y',strtotime($editRecord['date'])) ?> &nbsp; <strong>Locked meal rate:</strong> Rs. <?= number_format($displayRate,2) ?></p>
      <form method="post" class="form-row align-items-end"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="correct_attendance"><input type="hidden" name="date" value="<?= htmlspecialchars($filterDate) ?>"><input type="hidden" name="attendance_id" value="<?= (int)$editRecord['id'] ?>"><input type="hidden" name="meal_type" value="<?= $editMeal ?>">
        <div class="form-group col-md-3"><label>Current Status</label><input class="form-control" readonly value="<?= $currentStatus?'Present':'Absent' ?>"></div>
        <div class="form-group col-md-4"><label>Correct Status</label><select class="form-control" name="attendance_status" required><option value="1" <?= $currentStatus?'selected':'' ?>>Present</option><option value="0" <?= !$currentStatus?'selected':'' ?>>Absent</option></select></div>
        <div class="form-group col-md-5"><button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Attendance Correction</button> <a href="?date=<?= urlencode($filterDate) ?>" class="btn btn-secondary">Cancel</a></div>
      </form><small class="text-muted"><i class="fas fa-lock mr-1"></i>This correction changes attendance only; the meal price cannot be edited.</small>
    </div></div><?php endif; ?>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
