<?php
// ============================================================
// Mess Incharge Dashboard — SHMS
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_MESS);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_MESS);

$conn      = getDBConnection();
$pageTitle = 'Mess Dashboard';
$role      = ROLE_MESS;

$totalResidents    = $conn->query("SELECT COUNT(*) FROM residents WHERE status='active'")->fetch_row()[0];
$todayAttendance   = $conn->query("SELECT COUNT(DISTINCT resident_id) FROM mess_attendance WHERE date=CURDATE() AND (breakfast=1 OR lunch=1 OR snacks=1 OR dinner=1)")->fetch_row()[0];
$menuThisMonth     = $conn->query("SELECT COUNT(*) FROM mess_daily_menu WHERE MONTH(menu_date)=MONTH(CURDATE()) AND YEAR(menu_date)=YEAR(CURDATE()) AND (breakfast_enabled=1 OR lunch_enabled=1 OR snacks_enabled=1 OR dinner_enabled=1)")->fetch_row()[0];
$daysThisMonth     = (int)date('t');

$recentAttendance  = $conn->query(
    "SELECT ma.date, COUNT(DISTINCT ma.resident_id) AS count
     FROM mess_attendance ma
     WHERE ma.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY ma.date ORDER BY ma.date DESC LIMIT 7"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_mess.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-tachometer-alt mr-2"></i>Mess Dashboard</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right"><li class="breadcrumb-item active">Dashboard</li></ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="row">
      <div class="col-md-4">
        <div class="info-box bg-primary">
          <span class="info-box-icon"><i class="fas fa-users"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Active Residents</span>
            <span class="info-box-number"><?= $totalResidents ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="info-box bg-success">
          <span class="info-box-icon"><i class="fas fa-calendar-check"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Today's Meal Consumers</span>
            <span class="info-box-number"><?= $todayAttendance ?>/<?= $totalResidents ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="info-box bg-warning">
          <span class="info-box-icon"><i class="fas fa-clipboard-list"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Menu Days This Month</span>
            <span class="info-box-number"><?= $menuThisMonth ?>/<?= $daysThisMonth ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-2"></i>Last 7 Days Attendance</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>Date</th><th>Residents Attended</th></tr></thead>
              <tbody>
              <?php while ($a=$recentAttendance->fetch_assoc()): ?>
                <tr>
                  <td><?= date('D, d M',strtotime($a['date'])) ?></td>
                  <td><?= $a['count'] ?></td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3></div>
          <div class="card-body">
            <a href="attendance.php" class="btn btn-success mb-2 mr-2 btn-block">
              <i class="fas fa-calendar-check mr-1"></i>Record Today's Attendance
            </a>
            <a href="menu.php" class="btn btn-primary mb-2 mr-2 btn-block">
              <i class="fas fa-clipboard-list mr-1"></i>Update Mess Menu
            </a>
          </div>
        </div>
      </div>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
