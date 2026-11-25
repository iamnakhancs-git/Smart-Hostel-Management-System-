<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_RESIDENT);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_RESIDENT);

$conn = getDBConnection();
$pageTitle = 'Mess Menu';
$role = ROLE_RESIDENT;
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$weeks = shmsMonthWeeks($month, $year);
$meals = ['breakfast','lunch','snacks','dinner'];
$daily = [];
$setupNeeded = !shmsTableExists('mess_daily_menu') || !shmsColumnExists('mess_daily_menu', 'breakfast_enabled');

if (!$setupNeeded) {
    $stmt = $conn->prepare('SELECT * FROM mess_daily_menu WHERE YEAR(menu_date)=? AND MONTH(menu_date)=?');
    $stmt->bind_param('ii', $year, $month); $stmt->execute();
    $result = $stmt->get_result();
    while ($row=$result->fetch_assoc()) { $daily[$row['menu_date']] = $row; }
    $stmt->close();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_resident.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><div class="row mb-2">
    <div class="col-sm-6"><h1><i class="fas fa-clipboard-list mr-2"></i>Mess Menu</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Mess Menu</li></ol></div>
  </div></div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card"><div class="card-body"><form method="get" class="form-inline">
      <select name="month" class="form-control mr-2"><?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= monthName($m) ?></option><?php endfor; ?></select>
      <input type="number" name="year" class="form-control mr-2" value="<?= $year ?>" min="2020" max="2099" style="width:100px">
      <button class="btn btn-primary">View Month</button>
    </form></div></div>

    <?php if ($setupNeeded): ?>
      <div class="alert alert-warning">Database setup is incomplete. Recreate the database using the complete <strong>sql/shms_database.sql</strong> file.</div>
    <?php elseif (!$daily): ?>
      <div class="alert alert-info">No menu has been published for <?= monthName($month).' '.$year ?> yet.</div>
    <?php else: ?>
      <?php foreach ($weeks as $weekNo=>$dates): ?>
      <div class="card card-info">
        <div class="card-header"><h3 class="card-title">Week <?= $weekNo ?> <small class="ml-2"><?= date('d M', strtotime($dates[0])) ?> – <?= date('d M Y', strtotime(end($dates))) ?></small></h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-bordered mb-0">
            <thead class="thead-light"><tr><th style="min-width:140px">Date</th><th>Available Meals</th></tr></thead>
            <tbody>
            <?php foreach ($dates as $date): $row = $daily[$date] ?? null; ?>
              <tr>
                <td><strong><?= date('d M Y', strtotime($date)) ?></strong><br><span class="text-muted"><?= date('l', strtotime($date)) ?></span></td>
                <td><div class="row">
                  <?php $shown = 0; if ($row): foreach ($meals as $meal): if (!empty($row[$meal . '_enabled'])): $shown++; ?>
                    <div class="col-lg-3 col-md-6 mb-2"><div class="border rounded p-2 h-100">
                      <strong><?= ucfirst($meal) ?></strong>
                      <div><?= htmlspecialchars($row[$meal] ?? '') ?></div>
                      <span class="badge badge-light mt-1"><?= formatCurrency($row[$meal.'_price'] ?? 0) ?></span>
                    </div></div>
                  <?php endif; endforeach; endif; ?>
                  <?php if (!$shown): ?><div class="col-12 text-muted py-2">No meals scheduled.</div><?php endif; ?>
                </div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
