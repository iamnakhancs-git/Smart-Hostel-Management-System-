<?php
// ============================================================
// Mess Incharge — Record Daily Attendance
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin([ROLE_MESS, ROLE_ADMIN]);
$role = $_SESSION['role'];
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart($role);

$conn      = getDBConnection();
$pageTitle = 'Record Attendance';
$dashboardUrl = $role === ROLE_ADMIN ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/mess/dashboard.php';

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || date('Y-m-d', strtotime($date)) !== $date) { $date = date('Y-m-d'); }
if (!shmsColumnExists('mess_attendance', 'breakfast_rate')) {
    shmsBackendError('Database setup is incomplete. For this fresh-install build, recreate the database using sql/shms_database.sql.', 500);
}
if (!shmsColumnExists('mess_daily_menu', 'breakfast_enabled')) {
    shmsBackendError('Database setup is incomplete. Recreate the database using the updated sql/shms_database.sql to enable optional meals.', 500);
}
$allMeals = ['breakfast','lunch','snacks','dinner'];

// A new attendance entry always captures the price saved for this exact date.
$loadDailyRates = static function (mysqli $conn, string $selectedDate): ?array {
    $stmt = $conn->prepare('SELECT * FROM mess_daily_menu WHERE menu_date=? LIMIT 1');
    $stmt->bind_param('s', $selectedDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
};
$dailyRates = $loadDailyRates($conn, $date);
$enabledMeals = $dailyRates ? array_values(array_filter($allMeals, static function ($meal) use ($dailyRates) {
    return (int)($dailyRates[$meal . '_enabled'] ?? 0) === 1;
})) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $date       = sanitize($_POST['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || date('Y-m-d', strtotime($date)) !== $date) { throw new InvalidArgumentException('Choose a valid attendance date.'); }
    $inchargeId = (int)$_SESSION['user_id'];
    $dailyRates = $loadDailyRates($conn, $date);
    $enabledMeals = $dailyRates ? array_values(array_filter($allMeals, static function ($meal) use ($dailyRates) {
        return (int)($dailyRates[$meal . '_enabled'] ?? 0) === 1;
    })) : [];
    if (!$dailyRates || !$enabledMeals) {
        setFlash('error', 'Enable at least one meal in Mess Menu for this date before recording attendance.');
        header('Location: attendance.php?date=' . urlencode($date)); exit;
    }

    $existing = [];
    $stmt = $conn->prepare('SELECT * FROM mess_attendance WHERE date=?');
    $stmt->bind_param('s', $date); $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $existing[(int)$row['resident_id']] = $row; }
    $stmt->close();

    $billedResidents = [];
    $billMonth = (int)date('n', strtotime($date));
    $billYear = (int)date('Y', strtotime($date));
    $stmt = $conn->prepare('SELECT resident_id FROM bills WHERE month=? AND year=?');
    $stmt->bind_param('ii', $billMonth, $billYear); $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $billedResidents[(int)$row['resident_id']] = true; }
    $stmt->close();

    $residents  = $conn->query("SELECT id FROM residents WHERE status='active'");
    $conn->begin_transaction();
    try {
        $save = $conn->prepare(
            "INSERT INTO mess_attendance
                (resident_id,date,breakfast,lunch,snacks,dinner,breakfast_rate,lunch_rate,snacks_rate,dinner_rate,recorded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE breakfast=VALUES(breakfast),lunch=VALUES(lunch),snacks=VALUES(snacks),dinner=VALUES(dinner),
                breakfast_rate=VALUES(breakfast_rate),lunch_rate=VALUES(lunch_rate),snacks_rate=VALUES(snacks_rate),
                dinner_rate=VALUES(dinner_rate),recorded_by=VALUES(recorded_by)"
        );
        while ($r = $residents->fetch_assoc()) {
            $rid = (int)$r['id'];
            $old = $existing[$rid] ?? [];
            $flags = [];
            $rates = [];
            foreach ($allMeals as $meal) {
                if (isset($billedResidents[$rid])) {
                    $flags[$meal] = (int)($old[$meal] ?? 0);
                    $rates[$meal] = $old[$meal . '_rate'] ?? null;
                    continue;
                }
                if (!in_array($meal, $enabledMeals, true)) {
                    // Disabled meals cannot be selected or charged.
                    $flags[$meal] = 0;
                    $rates[$meal] = null;
                    continue;
                }
                $flags[$meal] = isset($_POST[$meal][$rid]) ? 1 : 0;
                if (!$flags[$meal]) {
                    $rates[$meal] = null;
                } elseif (!empty($old[$meal]) && $old[$meal . '_rate'] !== null) {
                    // Re-saving attendance must never silently re-price a meal already consumed.
                    $rates[$meal] = (float)$old[$meal . '_rate'];
                } else {
                    $rates[$meal] = (float)$dailyRates[$meal . '_price'];
                }
            }
            $save->bind_param('isiiiiddddi', $rid, $date,
                $flags['breakfast'], $flags['lunch'], $flags['snacks'], $flags['dinner'],
                $rates['breakfast'], $rates['lunch'], $rates['snacks'], $rates['dinner'], $inchargeId);
            $save->execute();
        }
        $save->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    setFlash('success','Attendance recorded for '.date('d M Y',strtotime($date)).'.');
    header("Location: attendance.php?date=$date"); exit;
}

// Load active residents with existing attendance
$residents = $conn->query(
    "SELECT r.id, r.name, rm.room_number,
            COALESCE(ma.breakfast,0) AS breakfast,
            COALESCE(ma.lunch,0)     AS lunch,
            COALESCE(ma.snacks,0)    AS snacks,
            COALESCE(ma.dinner,0)    AS dinner,
            ma.breakfast_rate, ma.lunch_rate, ma.snacks_rate, ma.dinner_rate,
            b.id AS bill_id
     FROM residents r
     LEFT JOIN room_allocations ra ON ra.resident_id=r.id AND ra.is_active=1
     LEFT JOIN rooms rm ON ra.room_id=rm.id
     LEFT JOIN mess_attendance ma ON ma.resident_id=r.id AND ma.date='$date'
     LEFT JOIN bills b ON b.resident_id=r.id AND b.month=MONTH('$date') AND b.year=YEAR('$date')
     WHERE r.status='active'
     ORDER BY r.name"
);

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include ($role === ROLE_ADMIN ? __DIR__ . '/../includes/sidebar_admin.php' : __DIR__ . '/../includes/sidebar_mess.php');
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-calendar-check mr-2"></i>Record Attendance</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= $dashboardUrl ?>">Dashboard</a></li>
          <li class="breadcrumb-item active">Attendance</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>

    <!-- Date selector -->
    <div class="card"><div class="card-body">
      <form method="get" class="form-inline">
        <label class="mr-2 font-weight-bold">Date:</label>
        <input type="date" name="date" class="form-control mr-2" value="<?= htmlspecialchars($date) ?>">
        <button class="btn btn-primary">Load</button>
      </form>
    </div></div>

    <div class="card">
      <div class="card-header bg-success text-white">
        <h3 class="card-title">
          <i class="fas fa-utensils mr-2"></i>Attendance — <?= date('l, d M Y',strtotime($date)) ?>
        </h3>
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead class="thead-dark">
              <tr>
                <th>Resident</th><th>Room</th>
                <?php foreach ($enabledMeals as $meal): ?>
                <th class="text-center"><?= ucfirst($meal) ?><br><small>Rs. <?= $dailyRates ? number_format((float)$dailyRates[$meal . '_price'], 2) : '—' ?></small></th>
                <?php endforeach; ?>
                <th class="text-center">
                  <div>All Meals</div>
                  <input type="checkbox" id="selectAll" title="Toggle All">
                </th>
              </tr>
            </thead>
            <tbody>
            <?php while ($r=$residents->fetch_assoc()): $rid=$r['id']; ?>
              <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['room_number'] ?? '—') ?></td>
                <?php foreach ($enabledMeals as $meal): ?>
                <td class="text-center">
                  <input type="checkbox" name="<?= $meal ?>[<?= $rid ?>]"
                         class="row-<?= $meal ?>"
                         <?= $r['bill_id'] ? 'disabled' : '' ?>
                         <?= $r[$meal] ? 'checked' : '' ?>>
                  <?php if ($r[$meal] && $r[$meal . '_rate'] !== null): ?><br><small class="text-muted">charged <?= number_format((float)$r[$meal . '_rate'],2) ?></small><?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td class="text-center">
                  <input type="checkbox" class="row-all"
                         data-rid="<?= $rid ?>"
                         <?= $r['bill_id'] ? 'disabled title="Bill already generated; attendance is locked"' : '' ?>
                         <?= ($enabledMeals && count(array_filter($enabledMeals, static fn($meal) => !empty($r[$meal]))) === count($enabledMeals)) ? 'checked' : '' ?>>
                  <?php if ($r['bill_id']): ?><br><small class="text-warning"><i class="fas fa-lock"></i> billed</small><?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-success" <?= (!$dailyRates || !$enabledMeals) ? 'disabled' : '' ?>>
            <i class="fas fa-save mr-1"></i>Save Attendance
          </button>
        </div>
      </form>
    </div>
    <?php if (!$dailyRates || !$enabledMeals): ?>
      <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>No meals are enabled for this date. Open <a href="menu.php?month=<?= (int)date('n',strtotime($date)) ?>&year=<?= (int)date('Y',strtotime($date)) ?>">Mess Menu</a> and enable the available meals first.</div>
    <?php endif; ?>
  </div></section>
</div>
<script>
var mealNames = <?= json_encode(array_values($enabledMeals)) ?>;

function syncHeaderAllMeals() {
  var editableRows = $('.row-all:not(:disabled)');
  $('#selectAll').prop('checked', editableRows.length > 0 && editableRows.filter(':checked').length === editableRows.length);
}

function syncRowAllMeals(rid) {
  var allChecked = mealNames.every(function(meal) {
    return $('input[name="'+meal+'['+rid+']"]').is(':checked');
  });
  $('.row-all[data-rid="'+rid+'"]').prop('checked', allChecked);
  syncHeaderAllMeals();
}

// Toggle all meals for a single row
$(document).on('change', '.row-all', function() {
  var rid   = $(this).data('rid');
  mealNames.forEach(function(m) {
    $('input[name="'+m+'['+rid+']"]').prop('checked', $(this).is(':checked'));
  }.bind(this));
  syncHeaderAllMeals();
});

// If one meal is corrected after saving (for example Snacks is unticked),
// immediately update the row's All Meals indicator as well.
$(document).on('change', 'input[class^="row-"]:not(.row-all)', function() {
  var match = this.name.match(/\[(\d+)\]$/);
  if (match) syncRowAllMeals(match[1]);
});
// Select-all header checkbox
$('#selectAll').on('change', function() {
  var checked = $(this).is(':checked');
  $('.row-all:not(:disabled)').prop('checked', checked).trigger('change');
});

syncHeaderAllMeals();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
