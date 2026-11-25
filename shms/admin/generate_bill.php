<?php
// ============================================================
// Admin — Generate Monthly Bills
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Generate Bills';
$role      = ROLE_ADMIN;
$msg       = '';
if (!shmsTableExists('bill_mess_details') || !shmsColumnExists('bills', 'previous_dues')) {
    shmsBackendError('Database setup is incomplete. Import sql/upgrade_previous_dues.sql once, or recreate a fresh database using sql/shms_database.sql.', 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $month = (int)($_POST['month'] ?? date('n'));
    $year  = (int)($_POST['year']  ?? date('Y'));
    $dueDate = trim((string)($_POST['due_date'] ?? ''));
    if ($dueDate === '') {
        throw new InvalidArgumentException('Choose one common due date for the generated bills.');
    }
    shmsDate($dueDate);
    $conn->begin_transaction();

    // Get all active residents
    $residents = $conn->query("SELECT id FROM residents WHERE status='active' ORDER BY id FOR UPDATE");
    $count = 0;
    while ($r = $residents->fetch_assoc()) {
        $rid = $r['id'];

        // Check if bill already exists
        $chk = $conn->query("SELECT id FROM bills WHERE resident_id=$rid AND month=$month AND year=$year");
        if ($chk->num_rows) continue; // skip already generated

        $roomRent  = getRoomRent($rid);
        $previousDues = getPreviousUnpaidDue($rid, $month, $year);
        $miscAmt   = calcMiscCharges($rid, $month, $year);
        $messDetails = getMessChargeDetails($rid, $month, $year);
        $messAmt = 0.0;
        foreach ($messDetails as $detail) { $messAmt += (float)$detail['charged_rate']; }
        $messAmt = round($messAmt, 2);
        $total     = $roomRent + $previousDues + $miscAmt + $messAmt;

        $stmt = $conn->prepare(
            "INSERT INTO bills (resident_id,month,year,room_rent,previous_dues,misc_charges,mess_charges,total_amount,payment_status,due_date)
             VALUES (?,?,?,?,?,?,?,?,'unpaid',?)"
        );
        $stmt->bind_param('iiiddddds',$rid,$month,$year,$roomRent,$previousDues,$miscAmt,$messAmt,$total,$dueDate);
        $stmt->execute();
        $billId = (int)$conn->insert_id;
        $stmt->close();
        if ($messDetails) {
            $detailStmt = $conn->prepare('INSERT INTO bill_mess_details (bill_id,attendance_date,meal_type,charged_rate) VALUES (?,?,?,?)');
            foreach ($messDetails as $detail) {
                $detailDate = $detail['attendance_date'];
                $detailMeal = $detail['meal_type'];
                $detailRate = (float)$detail['charged_rate'];
                $detailStmt->bind_param('issd', $billId, $detailDate, $detailMeal, $detailRate);
                $detailStmt->execute();
            }
            $detailStmt->close();
        }
        $count++;
    }
    $conn->commit();
    $msg = "$count bill(s) generated for ".monthName($month)." $year with common due date ".date('d M Y', strtotime($dueDate)).".";
    setFlash('success', $msg);
    header('Location: bills.php'); exit;
}

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-file-invoice mr-2"></i>Generate Monthly Bills</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Generate Bills</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card" style="max-width:500px;">
      <div class="card-header bg-danger text-white">
        <h3 class="card-title"><i class="fas fa-calculator mr-2"></i>Generate Bills for Month</h3>
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="card-body">
          <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i>
            Bills are generated automatically using:
            <ul class="mb-0 mt-2">
              <li>Room Rent (from room allocation)</li>
              <li>Previous Dues (latest previous bill, only if it is still Unpaid)</li>
              <li>Miscellaneous Charges (added by admin)</li>
              <li>Mess Charges (only enabled meals marked present, at their captured date-wise rate)</li>
              <li>One common Due Date selected below for every newly generated bill</li>
            </ul>
            Bills already generated for a resident in that month will be skipped.
          </div>
          <div class="form-group">
            <label>Month <span class="text-danger">*</span></label>
            <select name="month" class="form-control">
              <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $m==date('n')?'selected':'' ?>><?= monthName($m) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Year <span class="text-danger">*</span></label>
            <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099">
          </div>
          <div class="form-group">
            <label>Common Due Date <span class="text-danger">*</span></label>
            <input type="date" name="due_date" class="form-control"
              value="<?= h(date('Y-m-d', strtotime('+7 days'))) ?>" required>
            <small class="form-text text-muted">
              This same due date will be saved on every newly generated resident bill.
            </small>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-danger"
            onclick="return confirm('Generate bills for all active residents with the selected common due date?')">
            <i class="fas fa-bolt mr-1"></i>Generate Bills
          </button>
          <a href="bills.php" class="btn btn-secondary ml-2">Cancel</a>
        </div>
      </form>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
