<?php
require_once __DIR__ . '/../includes/enhancements.php';
requireLogin(ROLE_ADMIN);
$stats = [
    'residents' => shmsValue("SELECT COUNT(*) FROM residents WHERE status='active'"),
    'available' => shmsValue("SELECT COUNT(*) FROM rooms rm JOIN room_types rt ON rt.id=rm.room_type_id WHERE rm.status<>'maintenance' AND (SELECT COUNT(*) FROM room_allocations a WHERE a.room_id=rm.id AND a.is_active=1)<rt.beds"),
    'complaints' => shmsValue("SELECT COUNT(*) FROM complaints WHERE status='pending'"),
    'unpaid' => shmsValue("SELECT COUNT(*) FROM bills WHERE payment_status='unpaid'"),
    'mess' => shmsValue('SELECT COALESCE(SUM(breakfast+lunch+snacks+dinner),0) FROM mess_attendance WHERE date=CURDATE()'),
    'rooms' => shmsValue('SELECT COUNT(*) FROM rooms'),
];
$recentComplaints = shmsRows('SELECT c.subject,c.status,c.created_at,r.name FROM complaints c JOIN residents r ON r.id=c.resident_id ORDER BY c.created_at DESC,c.id DESC LIMIT 5');
$recentBills = shmsRows('SELECT b.month,b.year,b.total_amount,b.payment_status,r.name FROM bills b JOIN residents r ON r.id=b.resident_id ORDER BY b.generated_at DESC,b.id DESC LIMIT 5');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Super Admin Dashboard | SHMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/select2/select2.min.css">
  <link rel="stylesheet" href="/shms/assets/css/custom.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button">
        <i class="fas fa-bars"></i>
      </a>
    </li>
  </ul>
  <ul class="navbar-nav ml-auto">
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#">
        <i class="fas fa-user-circle fa-lg"></i>
        <span class="ml-1"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <i class="fas fa-caret-down ml-1"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <a href="/shms/admin/profile.php" class="dropdown-item">
          <i class="fas fa-id-card mr-2"></i>Profile
        </a>
        <div class="dropdown-divider"></div>
        <a href="/shms/logout.php" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt mr-2"></i>Logout
        </a>
      </div>
    </li>
  </ul>
</nav>
<!-- Sidebar -->
<?php require __DIR__ . '/../includes/sidebar_admin.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item active">Dashboard</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">

      <!-- Stats Row -->
      <div class="row">
        <div class="col-lg-4 col-md-4 col-sm-6">
          <div class="info-box bg-primary">
            <span class="info-box-icon"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Residents</span>
              <span class="info-box-number"><?= (int)$stats['residents'] ?></span>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
          <div class="info-box bg-success">
            <span class="info-box-icon"><i class="fas fa-door-open"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Rooms with Free Beds</span>
              <span class="info-box-number"><?= (int)$stats['available'] ?></span>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
          <div class="info-box bg-warning">
            <span class="info-box-icon"><i class="fas fa-exclamation-circle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Pending Complaints</span>
              <span class="info-box-number"><?= (int)$stats['complaints'] ?></span>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
          <div class="info-box bg-danger">
            <span class="info-box-icon"><i class="fas fa-file-invoice-dollar"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Unpaid Bills</span>
              <span class="info-box-number"><?= (int)$stats['unpaid'] ?></span>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
          <div class="info-box bg-info">
            <span class="info-box-icon"><i class="fas fa-utensils"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Meals Recorded Today</span>
              <span class="info-box-number"><?= (int)$stats['mess'] ?></span>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
          <div class="info-box bg-secondary">
            <span class="info-box-icon"><i class="fas fa-building"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Rooms</span>
              <span class="info-box-number"><?= (int)$stats['rooms'] ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <?php require __DIR__ . '/../includes/expense_summary.php'; ?>
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header bg-dark text-white">
              <h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3>
            </div>
            <div class="card-body">
              <a href="/shms/admin/resident_add.php" class="btn btn-primary mr-2 mb-2">
                <i class="fas fa-user-plus mr-1"></i>Add Resident
              </a>
              <a href="/shms/admin/room_add.php" class="btn btn-success mr-2 mb-2">
                <i class="fas fa-plus-square mr-1"></i>Add Room
              </a>
              <a href="/shms/admin/allocations.php" class="btn btn-warning mr-2 mb-2">
                <i class="fas fa-key mr-1"></i>Allocate Room
              </a>
              <a href="/shms/admin/generate_bill.php" class="btn btn-danger mr-2 mb-2">
                <i class="fas fa-file-invoice mr-1"></i>Generate Bills
              </a>
              <a href="/shms/admin/misc_charges.php" class="btn btn-info mr-2 mb-2">
                <i class="fas fa-receipt mr-1"></i>Add Misc Charge
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Complaints & Bills -->
      <div class="row">
        <!-- Recent Complaints -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-exclamation-circle mr-2 text-warning"></i>Recent Complaints</h3>
              <div class="card-tools">
                <a href="/shms/admin/complaints.php" class="btn btn-sm btn-default">View All</a>
              </div>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>Resident</th><th>Subject</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                  <?php foreach ($recentComplaints as $complaint): ?>
                  <tr><td><?= h($complaint['name']) ?></td><td><?= h($complaint['subject']) ?></td><td><?= h($complaint['status']) ?></td><td><?= h($complaint['created_at']) ?></td></tr>
                  <?php endforeach; if (!$recentComplaints): ?><tr>
                    <td colspan="4" class="text-center text-muted py-3">No complaints yet.</td>
                  </tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent Bills -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-2 text-danger"></i>Recent Bills</h3>
              <div class="card-tools">
                <a href="/shms/admin/bills.php" class="btn btn-sm btn-default">View All</a>
              </div>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>Resident</th><th>Month</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentBills as $bill): ?>
                  <tr><td><?= h($bill['name']) ?></td><td><?= h(sprintf('%04d-%02d',$bill['year'],$bill['month'])) ?></td><td>Rs. <?= number_format((float)$bill['total_amount'],2) ?></td><td><?= h($bill['payment_status']) ?></td></tr>
                  <?php endforeach; if (!$recentBills): ?><tr>
                    <td colspan="4" class="text-center text-muted py-3">No bills generated yet.</td>
                  </tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- Footer -->
<footer class="main-footer">
  <strong>&copy; 2026 <a href="#">Smart Hostel Management System</a>.</strong> All rights reserved.
</footer>
<div id="sidebar-overlay"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="<?= BASE_URL ?>/assets/vendor/select2/select2.full.min.js"></script>
<script src="/shms/assets/js/custom.js"></script>
</body>
</html>
