<?php
// Admin Sidebar — SHMS
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="<?= BASE_URL ?>/admin/dashboard.php" class="brand-link">
    <i class="fas fa-building brand-image img-circle elevation-3 p-1 ml-3 mr-1" style="font-size:1.4rem;color:#fff;"></i>
    <span class="brand-text font-weight-bold"><?= APP_SHORT ?></span>
  </a>
  <div class="sidebar">
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <i class="fas fa-user-shield fa-2x text-white ml-2 mt-1"></i>
      </div>
      <div class="info">
        <a href="<?= BASE_URL ?>/admin/profile.php" class="d-block">
          <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
        </a>
        <small class="text-muted">Super Administrator</small>
      </div>
    </div>
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/dashboard.php"
             class="nav-link <?= $currentPage==='dashboard.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header">RESIDENTS</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/residents.php"
             class="nav-link <?= in_array($currentPage,['residents.php','resident_add.php','resident_edit.php','resident_view.php']) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Manage Residents</p>
          </a>
        </li>

        <li class="nav-header">ROOMS</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/rooms.php"
             class="nav-link <?= in_array($currentPage,['rooms.php','room_add.php','room_edit.php']) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-door-open"></i>
            <p>Manage Rooms</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/allocations.php"
             class="nav-link <?= $currentPage==='allocations.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-key"></i>
            <p>Room Allocations</p>
          </a>
        </li>

        <li class="nav-header">COMPLAINTS</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/complaints.php"
             class="nav-link <?= $currentPage==='complaints.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-exclamation-circle"></i>
            <p>View Complaints</p>
          </a>
        </li>

        <li class="nav-header">MESS</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/menu.php"
             class="nav-link <?= $currentPage==='menu.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-clipboard-list"></i>
            <p>Manage Mess Menu</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/attendance.php"
             class="nav-link <?= $currentPage==='attendance.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-calendar-check"></i>
            <p>Record Attendance</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/attendance_history.php"
             class="nav-link <?= $currentPage==='attendance_history.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-history"></i>
            <p>Attendance History</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/mess_attendance.php"
             class="nav-link <?= $currentPage==='mess_attendance.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-utensils"></i>
            <p>Monitor &amp; Corrections</p>
          </a>
        </li>

        <li class="nav-header">BILLING</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/misc_charges.php"
             class="nav-link <?= $currentPage==='misc_charges.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-receipt"></i>
            <p>Misc Charges</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/bills.php"
             class="nav-link <?= in_array($currentPage,['bills.php','generate_bill.php']) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-file-invoice-dollar"></i>
            <p>Bills &amp; Payments</p>
          </a>
        </li>

        <li class="nav-header">NOTICES &amp; REPORTS</li>
        <li class="nav-item"><a href="<?= BASE_URL ?>/admin/notices.php" class="nav-link <?= $currentPage==='notices.php'?'active':'' ?>"><i class="nav-icon fas fa-bullhorn"></i><p>Notice Board</p></a></li>
        <li class="nav-item"><a href="<?= BASE_URL ?>/admin/expenses.php" class="nav-link <?= $currentPage==='expenses.php'?'active':'' ?>"><i class="nav-icon fas fa-wallet"></i><p>Hostel Expenses</p></a></li>
        <li class="nav-item"><a href="<?= BASE_URL ?>/admin/reports.php" class="nav-link <?= $currentPage==='reports.php'?'active':'' ?>"><i class="nav-icon fas fa-chart-bar"></i><p>Reports</p></a></li>
        <li class="nav-header">ACCOUNT</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/profile.php"
             class="nav-link <?= $currentPage==='profile.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-user-cog"></i>
            <p>My Profile</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/logout.php" class="nav-link text-danger">
            <i class="nav-icon fas fa-sign-out-alt"></i>
            <p>Logout</p>
          </a>
        </li>

      </ul>
    </nav>
  </div>
</aside>
<!-- /Sidebar -->
