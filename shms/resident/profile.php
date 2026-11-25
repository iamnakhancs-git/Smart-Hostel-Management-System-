<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_RESIDENT);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_RESIDENT);

$conn      = getDBConnection();
$pageTitle = 'My Profile';
$role      = ROLE_RESIDENT;
$id        = (int)$_SESSION['user_id'];
$errors    = [];

$resident = $conn->query("SELECT * FROM residents WHERE id=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $phone     = sanitize($_POST['phone'] ?? '');
    try { $phone = shmsNormalizePhone($phone); }
    catch (InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
    $emergency = sanitize($_POST['emergency_contact'] ?? '');
    $address   = sanitize($_POST['address']   ?? '');
    $pass      = $_POST['password']         ?? '';
    $conf      = $_POST['password_confirm'] ?? '';

    $errors = array_merge($errors, shmsPasswordChangeErrors($resident['password'], $_POST));
    $errors = array_merge($errors, shmsResidentDuplicateErrors($resident['email'], $phone, $resident['id_proof_number'], $id));

    if (empty($errors)) {
        $phoneDb = $phone === '' ? null : $phone;
        if ($pass !== '') {
            $hash = hashPassword($pass);
            $stmt = $conn->prepare("UPDATE residents SET phone=?,emergency_contact=?,address=?,password=? WHERE id=?");
            $stmt->bind_param('ssssi',$phoneDb,$emergency,$address,$hash,$id);
        } else {
            $stmt = $conn->prepare("UPDATE residents SET phone=?,emergency_contact=?,address=? WHERE id=?");
            $stmt->bind_param('sssi',$phoneDb,$emergency,$address,$id);
        }
        $stmt->execute(); $stmt->close();
        if ($pass !== '') { session_regenerate_id(true); }
        setFlash('success','Profile updated.');
        header('Location: profile.php'); exit;
    }
}

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_resident.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-user mr-2"></i>My Profile</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Profile</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <div class="row">
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-body text-center">
            <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
            <h3><?= htmlspecialchars($resident['name']) ?></h3>
            <p class="text-muted"><?= htmlspecialchars($resident['email']) ?></p>
            <?= statusBadge($resident['status']) ?>
          </div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Edit Profile</h3></div>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="card-body">
              <div class="form-group"><label>Full Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($resident['name']) ?>" disabled>
              </div>
              <div class="form-group"><label>Email</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($resident['email']) ?>" disabled>
              </div>
              <div class="form-group"><label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($resident['phone'] ?? '') ?>">
              </div>
              <div class="form-group"><label>Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($resident['emergency_contact'] ?? '') ?>">
              </div>
              <div class="form-group"><label>Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($resident['address'] ?? '') ?></textarea>
              </div>
              <div class="form-group"><label for="old_password">Current / Old Password <small class="text-muted">(required to change password)</small></label>
                <input id="old_password" type="password" name="old_password" class="form-control mb-3" autocomplete="current-password" maxlength="72">
                <label for="new_password">New Password</label>
                <input id="new_password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" maxlength="72">
                <small class="text-muted">At least 8 characters. Leave blank to keep your current password.</small>
              </div>
              <div class="form-group"><label>Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control" autocomplete="new-password" minlength="8" maxlength="72">
              </div>
            </div>
            <div class="card-footer">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
