<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Add Room';
$role      = ROLE_ADMIN;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $number  = sanitize($_POST['room_number'] ?? '');
        $typeId  = (int)($_POST['room_type_id'] ?? 0);
        $floor   = (int)($_POST['floor'] ?? 1);
        $status  = sanitize($_POST['status'] ?? 'available');

        if (!$number) $errors[] = 'Room number is required.';
        if (!$typeId) $errors[] = 'Room type is required.';
        shmsCheckRoomState(0,$status,$typeId);

        $chk = $conn->prepare("SELECT id FROM rooms WHERE room_number=?");
        $chk->bind_param('s',$number); $chk->execute();
        if ($chk->get_result()->num_rows) $errors[] = 'Room number already exists.';
        $chk->close();

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO rooms (room_number,room_type_id,floor,status) VALUES (?,?,?,?)");
            $stmt->bind_param('siis',$number,$typeId,$floor,$status);
            $stmt->execute(); $stmt->close();
            setFlash('success',"Room $number added.");
            header('Location: rooms.php'); exit;
        }
    }
}

$roomTypes = $conn->query("SELECT * FROM room_types ORDER BY price DESC");
$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-plus-square mr-2"></i>Add Room</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="rooms.php">Rooms</a></li>
          <li class="breadcrumb-item active">Add</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <div class="card" style="max-width:600px;">
      <div class="card-header"><h3 class="card-title">Room Details</h3></div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="card-body">
          <div class="form-group">
            <label>Room Number <span class="text-danger">*</span></label>
            <input type="text" name="room_number" class="form-control" required value="<?= htmlspecialchars($_POST['room_number']??'') ?>">
          </div>
          <div class="form-group">
            <label>Room Type <span class="text-danger">*</span></label>
            <select name="room_type_id" class="form-control" required>
              <option value="">-- Select Type --</option>
              <?php while ($rt=$roomTypes->fetch_assoc()): ?>
                <option value="<?= $rt['id'] ?>" <?= ($_POST['room_type_id']??'')==$rt['id']?'selected':'' ?>>
                  Type <?= htmlspecialchars($rt['type_name']) ?> — <?= $rt['beds'] ?> bed(s) — <?= formatCurrency($rt['price']) ?>/month
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Floor</label>
            <input type="number" name="floor" class="form-control" min="1" value="<?= htmlspecialchars($_POST['floor']??'1') ?>">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
              <option value="available">Available</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Room</button>
          <a href="rooms.php" class="btn btn-secondary ml-2">Cancel</a>
        </div>
      </form>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
