<?php
// ============================================================
// Shared Header — SHMS
// ============================================================
// Variables expected before include:
//   $pageTitle  (string)
//   $role       (string: 'admin'|'resident'|'mess')
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> | <?= APP_SHORT ?></title>

  <!-- Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

  <!-- AdminLTE -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/select2/select2.min.css">
  <!-- Custom styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">

  <!-- jQuery -->
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
        <span class="ml-1"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></span>
        <i class="fas fa-caret-down ml-1"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <a href="<?= BASE_URL ?>/<?= $role ?>/profile.php" class="dropdown-item">
          <i class="fas fa-id-card mr-2"></i>Profile
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt mr-2"></i>Logout
        </a>
      </div>
    </li>
  </ul>
</nav>
<!-- /Navbar -->
