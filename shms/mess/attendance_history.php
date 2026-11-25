<?php
// ============================================================
// Mess Incharge — Attendance History
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin([ROLE_MESS, ROLE_ADMIN]);
$role = $_SESSION['role'];
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart($role);

$conn      = getDBConnection();
$pageTitle = 'Attendance History';
$dashboardUrl = $role === ROLE_ADMIN ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/mess/dashboard.php';
if (!shmsColumnExists('mess_attendance', 'breakfast_rate') || !shmsColumnExists('mess_daily_menu', 'breakfast_enabled')) {
    shmsBackendError('Database setup is incomplete. For this fresh-install build, recreate the database using sql/shms_database.sql.', 500);
}

$filterRes   = (int)($_GET['resident_id'] ?? 0);
$filterMonth = (int)($_GET['month'] ?? date('n'));
$filterYear  = (int)($_GET['year']  ?? date('Y'));
$allMeals = ['breakfast','lunch','snacks','dinner'];

$where = "MONTH(ma.date)=$filterMonth AND YEAR(ma.date)=$filterYear";
if ($filterRes) $where .= " AND ma.resident_id=$filterRes";

$history = $conn->query(
    "SELECT ma.*, r.name AS resident_name,
            COALESCE(dm.breakfast_enabled,0) breakfast_enabled,
            COALESCE(dm.lunch_enabled,0) lunch_enabled,
            COALESCE(dm.snacks_enabled,0) snacks_enabled,
            COALESCE(dm.dinner_enabled,0) dinner_enabled
     FROM mess_attendance ma
     JOIN residents r ON ma.resident_id=r.id
     LEFT JOIN mess_daily_menu dm ON dm.menu_date=ma.date
     WHERE $where
     ORDER BY ma.date DESC, r.name"
);

$stmt = $conn->prepare('SELECT MAX(breakfast_enabled) breakfast,MAX(lunch_enabled) lunch,MAX(snacks_enabled) snacks,MAX(dinner_enabled) dinner FROM mess_daily_menu WHERE MONTH(menu_date)=? AND YEAR(menu_date)=?');
$stmt->bind_param('ii', $filterMonth, $filterYear); $stmt->execute();
$monthAvailability = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
$visibleMeals = array_values(array_filter($allMeals, static function ($meal) use ($monthAvailability) {
    return !empty($monthAvailability[$meal]);
}));

$residents = $conn->query("SELECT id,name FROM residents WHERE status='active' ORDER BY name");

include __DIR__ . '/../includes/header.php';
include ($role === ROLE_ADMIN ? __DIR__ . '/../includes/sidebar_admin.php' : __DIR__ . '/../includes/sidebar_mess.php');
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-history mr-2"></i>Attendance History</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= $dashboardUrl ?>">Dashboard</a></li>
          <li class="breadcrumb-item active">History</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card"><div class="card-body">
      <form method="get" class="form-inline flex-wrap">
        <select name="resident_id" class="form-control mr-2 mb-2">
          <option value="">All Residents</option>
          <?php while ($r=$residents->fetch_assoc()): ?>
            <option value="<?= $r['id'] ?>" <?= $filterRes==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
          <?php endwhile; ?>
        </select>
        <select name="month" class="form-control mr-2 mb-2">
          <?php for ($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $m===$filterMonth?'selected':'' ?>><?= monthName($m) ?></option>
          <?php endfor; ?>
        </select>
        <input type="number" name="year" class="form-control mr-2 mb-2" value="<?= $filterYear ?>" style="width:90px;">
        <button class="btn btn-primary mb-2"><i class="fas fa-filter mr-1"></i>Filter</button>
      </form>
    </div></div>

    <div class="card"><div class="card-body p-0">
      <table class="table table-hover data-table">
        <thead>
          <tr>
            <th>Date</th><th>Resident</th>
            <?php foreach ($visibleMeals as $meal): ?><th class="text-center"><?= ucfirst($meal) ?></th><?php endforeach; ?>
            <th class="text-center">Total Meals</th>
            <th class="text-right">Captured Charge</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($a=$history->fetch_assoc()):
          $total = 0;
          $charge = 0;
          foreach ($visibleMeals as $m) { if (!empty($a[$m . '_enabled']) && $a[$m]) { $total++; $charge += (float)$a[$m . '_rate']; } }
        ?>
          <tr>
            <td><?= date('d M Y',strtotime($a['date'])) ?></td>
            <td><?= htmlspecialchars($a['resident_name']) ?></td>
            <?php foreach ($visibleMeals as $meal): ?>
            <td class="text-center">
              <?php if (empty($a[$meal . '_enabled'])): ?><span class="text-muted">—</span>
              <?php elseif ($a[$meal]): ?><i class="fas fa-check-circle text-success"></i><br><small>Rs. <?= number_format((float)$a[$meal . '_rate'],2) ?></small><?php else: ?><i class="fas fa-times-circle text-secondary"></i><?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="text-center"><strong><?= $total ?>/<?= count(array_filter($visibleMeals, static fn($meal) => !empty($a[$meal . '_enabled']))) ?></strong></td>
            <td class="text-right"><strong>Rs. <?= number_format($charge,2) ?></strong></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div></div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
