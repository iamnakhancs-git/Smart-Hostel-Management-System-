<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="main-sidebar sidebar-dark-warning elevation-4">
  <a href="<?= BASE_URL ?>/mess/dashboard.php" class="brand-link">
    <i class="fas fa-building brand-image img-circle elevation-3 p-1 ml-3 mr-1" style="font-size:1.4rem;color:#fff;"></i>
    <span class="brand-text font-weight-bold"><?= APP_SHORT ?></span>
  </a>
  <div class="sidebar">
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <i class="fas fa-utensils fa-2x text-white ml-2 mt-1"></i>
      </div>
      <div class="info">
        <a href="<?= BASE_URL ?>/mess/profile.php" class="d-block">
          <?= htmlspecialchars($_SESSION['user_name'] ?? 'Mess Incharge') ?>
        </a>
        <small class="text-muted">Mess Incharge</small>
      </div>
    </div>
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/dashboard.php"
             class="nav-link <?= $currentPage==='dashboard.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header">MENU</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/menu.php"
             class="nav-link <?= $currentPage==='menu.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-clipboard-list"></i><p>Mess Menu</p>
          </a>
        </li>
        <li class="nav-header">ATTENDANCE</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/attendance.php"
             class="nav-link <?= $currentPage==='attendance.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-calendar-check"></i><p>Record Attendance</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/attendance_history.php"
             class="nav-link <?= $currentPage==='attendance_history.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-history"></i><p>Attendance History</p>
          </a>
        </li>

        <li class="nav-header">ACCOUNT</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/mess/profile.php"
             class="nav-link <?= $currentPage==='profile.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-user-cog"></i><p>Profile</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/logout.php" class="nav-link text-danger">
            <i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p>
          </a>
        </li>

      </ul>
    </nav>
  </div>
</aside>
