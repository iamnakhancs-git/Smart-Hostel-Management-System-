<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'My Profile';
$role      = ROLE_ADMIN;
$id        = (int)$_SESSION['user_id'];
$errors    = [];

$admin = $conn->query("SELECT * FROM admins WHERE id=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $name  = sanitize($_POST['name']  ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $pass  = $_POST['password']         ?? '';
    $conf  = $_POST['password_confirm'] ?? '';

    if (!$name) $errors[] = 'Name required.';
    $errors = array_merge($errors, shmsPasswordChangeErrors($admin['password'], $_POST));

    if (empty($errors)) {
        if ($pass !== '') {
            $hash = hashPassword($pass);
            $stmt = $conn->prepare("UPDATE admins SET name=?,phone=?,password=? WHERE id=?");
            $stmt->bind_param('sssi',$name,$phone,$hash,$id);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET name=?,phone=? WHERE id=?");
            $stmt->bind_param('ssi',$name,$phone,$id);
        }
        $stmt->execute(); $stmt->close();
        if ($pass !== '') { session_regenerate_id(true); }
        $_SESSION['user_name'] = $name;
        setFlash('success','Profile updated.');
        header('Location: profile.php'); exit;
    }
}

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-user-cog mr-2"></i>My Profile</h1></div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <div class="card" style="max-width:500px;">
      <div class="card-header"><h3 class="card-title">Edit Profile</h3></div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="card-body">
          <div class="form-group"><label>Name</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($admin['name']) ?>">
          </div>
          <div class="form-group"><label>Email</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" disabled>
          </div>
          <div class="form-group"><label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($admin['phone']) ?>">
          </div>
          <div class="form-group"><label for="old_password">Current / Old Password <small class="text-muted">(required to change password)</small></label>
                <input id="old_password" type="password" name="old_password" class="form-control mb-3" autocomplete="current-password" maxlength="72">
                <label for="new_password">New Password</label>
                <input id="new_password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" maxlength="72">
                <small class="text-muted">At least 8 characters. Leave blank to keep your current password.</small>
          </div>
          <div class="form-group"><label>Confirm New Password</label>
            <input type="password" name="password_confirm" class="form-control" autocomplete="new-password" minlength="8" maxlength="72">
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
