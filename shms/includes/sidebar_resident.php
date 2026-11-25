<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="main-sidebar sidebar-dark-success elevation-4">
  <a href="<?= BASE_URL ?>/resident/dashboard.php" class="brand-link">
    <i class="fas fa-building brand-image img-circle elevation-3 p-1 ml-3 mr-1" style="font-size:1.4rem;color:#fff;"></i>
    <span class="brand-text font-weight-bold"><?= APP_SHORT ?></span>
  </a>
  <div class="sidebar">
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <i class="fas fa-user fa-2x text-white ml-2 mt-1"></i>
      </div>
      <div class="info">
        <a href="<?= BASE_URL ?>/resident/profile.php" class="d-block">
          <?= htmlspecialchars($_SESSION['user_name'] ?? 'Resident') ?>
        </a>
        <small class="text-muted">Resident</small>
      </div>
    </div>
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/resident/dashboard.php"
             class="nav-link <?= $currentPage==='dashboard.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-home"></i><p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header">MY ROOM</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/resident/my_room.php"
             class="nav-link <?= $currentPage==='my_room.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-door-closed"></i><p>My Room</p>
          </a>
        </li>

        <li class="nav-header">BILLING</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/resident/my_bills.php"
             class="nav-link <?= $currentPage==='my_bills.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-file-invoice"></i><p>My Bills</p>
          </a>
        </li>

        <li class="nav-header">MESS</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/resident/mess_menu.php"
             class="nav-link <?= $currentPage==='mess_menu.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-utensils"></i><p>Mess Menu</p>
          </a>
        </li>

        <li class="nav-header">COMPLAINTS</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/resident/complaints.php"
             class="nav-link <?= $currentPage==='complaints.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-exclamation-circle"></i><p>My Complaints</p>
          </a>
        </li>

        <li class="nav-header">ANNOUNCEMENTS</li>
        <li class="nav-item"><a href="<?= BASE_URL ?>/resident/notices.php" class="nav-link <?= $currentPage==='notices.php'?'active':'' ?>"><i class="nav-icon fas fa-bullhorn"></i><p>Notice Board</p></a></li>
        <li class="nav-header">ACCOUNT</li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/resident/profile.php"
             class="nav-link <?= $currentPage==='profile.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-user"></i><p>Profile</p>
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
