<?php

// Login Page — Smart Hostel Management System

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn(ROLE_ADMIN))    { header('Location: '.BASE_URL.'/admin/dashboard.php');    exit; }
if (isLoggedIn(ROLE_RESIDENT)) { header('Location: '.BASE_URL.'/resident/dashboard.php'); exit; }
if (isLoggedIn(ROLE_MESS))     { header('Location: '.BASE_URL.'/mess/dashboard.php');     exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $role  = sanitize($_POST['role']  ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $conn  = getDBConnection();

        $table = match($role) {
            ROLE_ADMIN    => 'admins',
            ROLE_RESIDENT => 'residents',
            ROLE_MESS     => 'mess_incharges',
            default       => ''
        };

        if ($table === '') {
            $error = 'Invalid role selected.';
        } else {
            $stmt = $conn->prepare("SELECT id, name, password FROM `$table` WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();
            $stmt->close();

            if ($user && verifyPassword($pass, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role']      = $role;

                $redirect = match($role) {
                    ROLE_ADMIN    => BASE_URL.'/admin/dashboard.php',
                    ROLE_RESIDENT => BASE_URL.'/resident/dashboard.php',
                    ROLE_MESS     => BASE_URL.'/mess/dashboard.php',
                };
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
  <style>
    body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); }
    .login-box { width: 420px; }
    .login-card-body { border-top: 4px solid #007bff; border-radius: 0 0 .25rem .25rem; }
    .login-logo a { color: #fff; font-size: 2rem; font-weight: 700; letter-spacing: 2px; }
    .login-logo small { display:block; color:#adb5bd; font-size:.9rem; font-weight:400; letter-spacing:1px; margin-top:-5px; }
    .role-btn { transition: all .2s; }
    .role-btn.active, .role-btn:hover { transform: scale(1.05); }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><i class="fas fa-building mr-2"></i><?= APP_SHORT ?><small><?= APP_NAME ?></small></a>
  </div>
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg font-weight-bold">Sign in to your account</p>

      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form action="" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <!-- Role Selection -->
        <div class="mb-3 text-center">
          <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
            <label class="btn btn-outline-primary role-btn <?= ($_POST['role']??'')===ROLE_ADMIN ? 'active' : '' ?>">
              <input type="radio" name="role" value="admin" autocomplete="off"
                     <?= ($_POST['role']??'')==='admin' ? 'checked' : '' ?>>
              <i class="fas fa-user-shield mr-1"></i> Super Admin
            </label>
            <label class="btn btn-outline-success role-btn <?= ($_POST['role']??'')==='resident' ? 'active' : '' ?>">
              <input type="radio" name="role" value="resident" autocomplete="off"
                     <?= ($_POST['role']??'')==='resident' ? 'checked' : '' ?>>
              <i class="fas fa-user mr-1"></i> Resident
            </label>
            <label class="btn btn-outline-warning role-btn <?= ($_POST['role']??'')==='mess' ? 'active' : '' ?>">
              <input type="radio" name="role" value="mess" autocomplete="off"
                     <?= ($_POST['role']??'')==='mess' ? 'checked' : '' ?>>
              <i class="fas fa-utensils mr-1"></i> Mess
            </label>
          </div>
        </div>

        <div class="input-group mb-3">
          <input type="email" name="email" class="form-control" placeholder="Email address"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-envelope"></i></div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-lock"></i></div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">
              <i class="fas fa-sign-in-alt mr-2"></i>Sign In
            </button>
          </div>
        </div>
      </form>

     
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
