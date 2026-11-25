<?php
// Mess In-charge — date-wise monthly menu and prices, grouped by week.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin([ROLE_MESS, ROLE_ADMIN]);
$role = $_SESSION['role'];
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart($role);

$conn = getDBConnection();
$pageTitle = 'Mess Menu';
$dashboardUrl = $role === ROLE_ADMIN ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/mess/dashboard.php';
$month = (int)(($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST['month'] ?? null : $_GET['month'] ?? null) ?: date('n'));
$year = (int)(($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST['year'] ?? null : $_GET['year'] ?? null) ?: date('Y'));
$weeks = shmsMonthWeeks($month, $year);
$meals = ['breakfast','lunch','snacks','dinner'];

if (!shmsTableExists('mess_daily_menu')) {
    shmsBackendError('Database setup is incomplete. For this fresh-install build, recreate the database using sql/shms_database.sql.', 500);
}
if (!shmsColumnExists('mess_attendance', 'breakfast_rate')) {
    shmsBackendError('Database setup is incomplete. For this fresh-install build, recreate the database using sql/shms_database.sql.', 500);
}
if (!shmsColumnExists('mess_daily_menu', 'breakfast_enabled')) {
    shmsBackendError('Database setup is incomplete. Recreate the database using the updated sql/shms_database.sql to enable optional meals.', 500);
}

$daily = [];
$stmt = $conn->prepare('SELECT * FROM mess_daily_menu WHERE YEAR(menu_date)=? AND MONTH(menu_date)=?');
$stmt->bind_param('ii', $year, $month); $stmt->execute();
$result = $stmt->get_result();
while ($row=$result->fetch_assoc()) { $daily[$row['menu_date']] = $row; }
$stmt->close();

// Legacy monthly menu and global prices are only initial defaults for dates not saved yet.
$legacy = [];
$stmt = $conn->prepare('SELECT * FROM mess_menu WHERE month=? AND year=?');
$stmt->bind_param('ii', $month, $year); $stmt->execute();
$result = $stmt->get_result();
while ($row=$result->fetch_assoc()) { $legacy[$row['day_of_week']] = $row; }
$stmt->close();
$defaults = [];
$result = $conn->query('SELECT meal_type,price FROM meal_prices');
while ($row=$result->fetch_assoc()) { $defaults[$row['meal_type']] = $row['price']; }

// Once attendance has been saved for a date, every enabled meal and its
// date-wise price are locked, even if all residents were marked absent.
$locks = [];
$stmt = $conn->prepare('SELECT ma.date,
                               MAX(dm.breakfast_enabled) breakfast,
                               MAX(dm.lunch_enabled) lunch,
                               MAX(dm.snacks_enabled) snacks,
                               MAX(dm.dinner_enabled) dinner
                        FROM mess_attendance ma
                        JOIN mess_daily_menu dm ON dm.menu_date=ma.date
                        WHERE YEAR(ma.date)=? AND MONTH(ma.date)=?
                        GROUP BY ma.date');
$stmt->bind_param('ii', $year, $month); $stmt->execute();
$result = $stmt->get_result();
while ($row=$result->fetch_assoc()) { $locks[$row['date']] = $row; }
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { shmsBackendError('Invalid or expired form token. Reload and try again.', 403); }
    $inchargeId = (int)$_SESSION['user_id'];
    $validDates = [];
    foreach ($weeks as $dates) { foreach ($dates as $date) { $validDates[$date] = true; } }
    $sql = "INSERT INTO mess_daily_menu
              (menu_date,breakfast,lunch,snacks,dinner,breakfast_enabled,lunch_enabled,snacks_enabled,dinner_enabled,
               breakfast_price,lunch_price,snacks_price,dinner_price,updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE breakfast=VALUES(breakfast),lunch=VALUES(lunch),snacks=VALUES(snacks),dinner=VALUES(dinner),
              breakfast_enabled=VALUES(breakfast_enabled),lunch_enabled=VALUES(lunch_enabled),
              snacks_enabled=VALUES(snacks_enabled),dinner_enabled=VALUES(dinner_enabled),
              breakfast_price=VALUES(breakfast_price),lunch_price=VALUES(lunch_price),snacks_price=VALUES(snacks_price),
              dinner_price=VALUES(dinner_price),updated_by=VALUES(updated_by)";
    $stmt = $conn->prepare($sql);
    $conn->begin_transaction();
    try {
        foreach (array_keys($validDates) as $date) {
            $items = [];
            $prices = [];
            $enabled = [];
            foreach ($meals as $meal) {
                $enabled[$meal] = isset($_POST[$meal . '_enabled'][$date]) ? 1 : 0;
                // A consumed meal cannot be removed from that date, otherwise historical attendance becomes misleading.
                if (!empty($locks[$date][$meal])) { $enabled[$meal] = 1; }
                $item = sanitize($_POST[$meal][$date] ?? '');
                if (strlen($item) > 500) { throw new InvalidArgumentException(ucfirst($meal) . ' menu text is too long.'); }
                if ($enabled[$meal] && $item === '') { throw new InvalidArgumentException('Enter the ' . ucfirst($meal) . ' menu for ' . date('d M Y', strtotime($date)) . '.'); }
                $items[$meal] = $enabled[$meal] ? $item : '';
                if (!empty($locks[$date][$meal])) {
                    $priceKey = $meal . '_price';
                    $prices[$meal] = (float)($daily[$date][$priceKey] ?? $defaults[$meal] ?? 0);
                } elseif (!$enabled[$meal]) {
                    $prices[$meal] = 0.0;
                } else {
                    $prices[$meal] = shmsMoney($_POST[$meal . '_price'][$date] ?? '', true);
                }
            }
            $stmt->bind_param('sssssiiiiddddi', $date, $items['breakfast'], $items['lunch'], $items['snacks'], $items['dinner'],
                $enabled['breakfast'], $enabled['lunch'], $enabled['snacks'], $enabled['dinner'],
                $prices['breakfast'], $prices['lunch'], $prices['snacks'], $prices['dinner'], $inchargeId);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        $stmt->close();
        $conn->rollback();
        throw $e;
    }
    setFlash('success', 'Week-wise menu and daily prices saved for ' . monthName($month) . ' ' . $year . '.');
    header("Location: menu.php?month=$month&year=$year"); exit;
}

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include ($role === ROLE_ADMIN ? __DIR__ . '/../includes/sidebar_admin.php' : __DIR__ . '/../includes/sidebar_mess.php');
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><div class="row mb-2">
    <div class="col-sm-6"><h1><i class="fas fa-clipboard-list mr-2"></i>Manage Mess Menu</h1></div>
    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?= $dashboardUrl ?>">Dashboard</a></li><li class="breadcrumb-item active">Mess Menu</li></ol></div>
  </div></div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card"><div class="card-body">
      <form method="get" class="form-inline">
        <label class="mr-2 font-weight-bold">Month/Year:</label>
        <select name="month" class="form-control mr-2"><?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= monthName($m) ?></option><?php endfor; ?></select>
        <input type="number" name="year" class="form-control mr-2" value="<?= $year ?>" style="width:100px" min="2020" max="2099">
        <button class="btn btn-primary">Load Month</button>
      </form>
    </div></div>

    <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Enable only the meals available on each date. Disabled meals remain hidden from attendance and resident menus and are not billed. After attendance is recorded, that meal and its price are locked.</div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="month" value="<?= $month ?>">
      <input type="hidden" name="year" value="<?= $year ?>">
      <?php foreach ($weeks as $weekNo=>$dates): ?>
      <div class="card card-primary">
        <div class="card-header"><h3 class="card-title">Week <?= $weekNo ?> <small class="ml-2"><?= date('d M', strtotime($dates[0])) ?> – <?= date('d M Y', strtotime(end($dates))) ?></small></h3></div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead class="thead-light"><tr><th style="min-width:140px">Date</th><th>Available Meals</th></tr></thead>
            <tbody>
            <?php foreach ($dates as $date):
              $row = $daily[$date] ?? [];
              $dayName = date('l', strtotime($date));
              $fallback = $legacy[$dayName] ?? [];
            ?>
              <tr>
                <td class="align-middle"><strong><?= date('d M Y', strtotime($date)) ?></strong><br><span class="text-muted"><?= $dayName ?></span></td>
                <td><div class="row">
                <?php foreach ($meals as $meal):
                  $text = array_key_exists($meal, $row) ? $row[$meal] : ($fallback[$meal] ?? '');
                  $priceKey = $meal . '_price';
                  $price = array_key_exists($priceKey, $row) ? $row[$priceKey] : ($defaults[$meal] ?? 0);
                  $isLocked = !empty($locks[$date][$meal]);
                  $enabledKey = $meal . '_enabled';
                  $isEnabled = array_key_exists($enabledKey, $row) ? (bool)$row[$enabledKey] : false;
                  if ($isLocked) { $isEnabled = true; }
                ?>
                <div class="col-xl-3 col-md-6 mb-2">
                  <div class="border rounded p-2 h-100">
                    <div class="custom-control custom-switch mb-2">
                      <input type="checkbox" class="custom-control-input meal-enabled" id="<?= $meal ?>_enabled_<?= $date ?>" name="<?= $meal ?>_enabled[<?= $date ?>]" data-target="#<?= $meal ?>_panel_<?= $date ?>" <?= $isEnabled ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                      <label class="custom-control-label font-weight-bold" for="<?= $meal ?>_enabled_<?= $date ?>"><?= ucfirst($meal) ?></label>
                      <?php if ($isLocked): ?><input type="hidden" name="<?= $meal ?>_enabled[<?= $date ?>]" value="1"><?php endif; ?>
                    </div>
                    <div class="meal-panel" id="<?= $meal ?>_panel_<?= $date ?>" <?= $isEnabled ? '' : 'style="display:none"' ?>>
                      <input type="text" name="<?= $meal ?>[<?= $date ?>]" class="form-control form-control-sm mb-1" maxlength="500" value="<?= htmlspecialchars($text) ?>" placeholder="Meal name/details">
                      <div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">Rs.</span></div><input type="number" name="<?= $meal ?>_price[<?= $date ?>]" class="form-control <?= $isLocked ? 'bg-light' : '' ?>" min="0" step="0.01" value="<?= number_format((float)$price,2,'.','') ?>" <?= $isLocked ? 'readonly title="Locked because attendance exists"' : '' ?>></div>
                      <?php if ($isLocked): ?><small class="text-warning"><i class="fas fa-lock mr-1"></i>Meal and price locked</small><?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
                </div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="mb-4"><button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save mr-1"></i>Save Complete Month</button></div>
    </form>
  </div></section>
</div>
<script>
$(document).on('change', '.meal-enabled', function () {
  $($(this).data('target')).toggle(this.checked);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
