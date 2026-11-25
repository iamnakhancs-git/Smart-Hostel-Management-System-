<?php
// ============================================================
// Resident Dashboard — SHMS
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_RESIDENT);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_RESIDENT);

$conn      = getDBConnection();
$pageTitle = 'My Dashboard';
$role      = ROLE_RESIDENT;
$rid       = (int)$_SESSION['user_id'];

// Resident info
$resident = $conn->query("SELECT * FROM residents WHERE id=$rid")->fetch_assoc();

// Allocated room
$roomRes = $conn->query(
    "SELECT rm.room_number, rt.type_name, rt.beds, rt.price
     FROM room_allocations ra
     JOIN rooms rm     ON ra.room_id=rm.id
     JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE ra.resident_id=$rid AND ra.is_active=1 LIMIT 1"
);
$room = $roomRes->fetch_assoc();

// Latest bill
$billRes = $conn->query(
    "SELECT * FROM bills WHERE resident_id=$rid ORDER BY year DESC, month DESC LIMIT 1"
);
$latestBill = $billRes->fetch_assoc();

// Pending complaints
$pendingComplaints = $conn->query(
    "SELECT COUNT(*) FROM complaints WHERE resident_id=$rid AND status='pending'"
)->fetch_row()[0];

// Today attendance
$todayAtt = $conn->query(
    "SELECT * FROM mess_attendance WHERE resident_id=$rid AND date=CURDATE() LIMIT 1"
)->fetch_assoc();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_resident.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-home mr-2"></i>Welcome, <?= htmlspecialchars($resident['name']) ?></h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right"><li class="breadcrumb-item active">Dashboard</li></ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>

    <!-- Stats -->
    <div class="row">
      <div class="col-lg-4 col-md-4 col-sm-6">
        <div class="info-box bg-primary">
          <span class="info-box-icon"><i class="fas fa-door-closed"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">My Room</span>
            <span class="info-box-number"><?= $room ? htmlspecialchars($room['room_number']) : 'Not Allocated' ?></span>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-4 col-sm-6">
        <div class="info-box bg-<?= $latestBill && $latestBill['payment_status']==='unpaid' ? 'danger' : 'success' ?>">
          <span class="info-box-icon"><i class="fas fa-file-invoice-dollar"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Latest Bill</span>
            <span class="info-box-number">
              <?= $latestBill ? formatCurrency($latestBill['total_amount']).' ('.ucfirst($latestBill['payment_status']).')' : 'No Bills' ?>
            </span>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-4 col-sm-6">
        <div class="info-box bg-warning">
          <span class="info-box-icon"><i class="fas fa-exclamation-circle"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Pending Complaints</span>
            <span class="info-box-number"><?= $pendingComplaints ?></span>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-4 col-sm-6">
        <div class="info-box bg-info">
          <span class="info-box-icon"><i class="fas fa-utensils"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Today's Mess</span>
            <span class="info-box-number"><?= $todayAtt ? 'Recorded' : 'Not Recorded' ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Room & Quick Links -->
    <div class="row">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-door-closed mr-2"></i>My Room Details</h3></div>
          <div class="card-body">
            <?php if ($room): ?>
              <table class="table table-borderless">
                <tr><th>Room Number</th><td><?= htmlspecialchars($room['room_number']) ?></td></tr>
                <tr><th>Room Type</th><td>Type <?= htmlspecialchars($room['type_name']) ?> (<?= $room['beds'] ?> bed(s))</td></tr>
                <tr><th>Monthly Rent</th><td><?= formatCurrency($room['price']) ?></td></tr>
              </table>
              <a href="my_room.php" class="btn btn-sm btn-primary"><i class="fas fa-eye mr-1"></i>View Details</a>
            <?php else: ?>
              <p class="text-muted">No room allocated yet. Contact the admin.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3></div>
          <div class="card-body">
            <a href="complaints.php?action=new" class="btn btn-warning mb-2 mr-2">
              <i class="fas fa-plus mr-1"></i>Submit Complaint
            </a>
            <a href="my_bills.php" class="btn btn-danger mb-2 mr-2">
              <i class="fas fa-file-invoice mr-1"></i>View My Bills
            </a>
            <a href="mess_menu.php" class="btn btn-info mb-2 mr-2">
              <i class="fas fa-clipboard-list mr-1"></i>View Mess Menu
            </a>
            <a href="profile.php" class="btn btn-secondary mb-2">
              <i class="fas fa-user mr-1"></i>My Profile
            </a>
          </div>
        </div>
      </div>
    </div>

  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
