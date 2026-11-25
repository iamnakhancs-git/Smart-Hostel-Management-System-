<?php
// ============================================================
// Admin — Add Resident
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Add Resident';
$role      = ROLE_ADMIN;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $name      = sanitize($_POST['name']      ?? '');
        $email     = strtolower(sanitize($_POST['email'] ?? ''));
        $phone     = sanitize($_POST['phone'] ?? '');
        try { $phone = shmsNormalizePhone($phone); }
        catch (InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
        $emergency = sanitize($_POST['emergency_contact'] ?? '');
        $address   = sanitize($_POST['address']   ?? '');
        $idType    = sanitize($_POST['id_proof_type']   ?? '');
        $idNum     = sanitize($_POST['id_proof_number'] ?? '');
        try { $idNum = shmsValidateIdentity($idType, $idNum); }
        catch (InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
        $joinDate  = sanitize($_POST['join_date'] ?? '');
        $joinDate = $joinDate === '' ? null : $joinDate;
        $pass      = $_POST['password'] ?? '';
        $passConf  = $_POST['password_confirm'] ?? '';

        if (!$name)  $errors[] = 'Name is required.';
        if (!$email) $errors[] = 'Email is required.';
        if (!$pass)  $errors[] = 'Password is required.';
        if ($pass !== $passConf) $errors[] = 'Passwords do not match.';

        $errors = array_merge($errors, shmsResidentDuplicateErrors($email, $phone, $idNum));

        if (empty($errors)) {
            $hash = hashPassword($pass);
            $stmt = $conn->prepare(
                "INSERT INTO residents (name,email,password,phone,emergency_contact,address,id_proof_type,id_proof_number,join_date)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $phoneDb = $phone === '' ? null : $phone;
            $idTypeDb = $idType === '' ? null : $idType;
            $idNumDb = $idNum === '' ? null : $idNum;
            $stmt->bind_param('sssssssss', $name,$email,$hash,$phoneDb,$emergency,$address,$idTypeDb,$idNumDb,$joinDate);
            $stmt->execute(); $stmt->close();
            setFlash('success', "Resident '$name' added successfully.");
            header('Location: residents.php'); exit;
        }
    }
}

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1><i class="fas fa-user-plus mr-2"></i>Add Resident</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="residents.php">Residents</a></li>
            <li class="breadcrumb-item active">Add</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Resident Information</h3></div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name']??'') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email']??'') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone']??'') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($_POST['emergency_contact']??'') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>ID Proof Type</label>
                <select id="id_proof_type" name="id_proof_type" class="form-control">
                  <option value="">Select</option>
                  <?php foreach (['CNIC','Passport'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($_POST['id_proof_type']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>ID Proof Number</label>
                <input type="text" id="id_proof_number" name="id_proof_number" class="form-control" maxlength="20" aria-describedby="identity-help" value="<?= htmlspecialchars($_POST['id_proof_number']??'') ?>">
                <small id="identity-help" class="form-text text-muted">Choose an ID type to see its number format.</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Join Date</label>
                <input type="date" name="join_date" class="form-control" value="<?= htmlspecialchars($_POST['join_date']??date('Y-m-d')) ?>">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address']??'') ?></textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="password_confirm" class="form-control" required>
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Resident</button>
          <a href="residents.php" class="btn btn-secondary ml-2">Cancel</a>
        </div>
      </form>
    </div>
  </div></section>
</div>
<script type="application/json" id="identity-rules"><?= json_encode(shmsIdentityRules(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
