<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Edit Room';
$role      = ROLE_ADMIN;
$id        = (int)($_GET['id'] ?? 0);
$errors    = [];

$res = $conn->query("SELECT * FROM rooms WHERE id=$id");
if (!$res->num_rows) { setFlash('error','Room not found.'); header('Location: rooms.php'); exit; }
$room = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $number = sanitize($_POST['room_number'] ?? '');
    $typeId = (int)($_POST['room_type_id'] ?? 0);
    $floor  = (int)($_POST['floor'] ?? 1);
    $status = sanitize($_POST['status'] ?? 'available');

    if (!$number) $errors[] = 'Room number required.';
    if (!$typeId) $errors[] = 'Room type required.';
    shmsCheckRoomState($id,$status,$typeId);

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE rooms SET room_number=?,room_type_id=?,floor=?,status=? WHERE id=?");
        $stmt->bind_param('siisi',$number,$typeId,$floor,$status,$id);
        $stmt->execute(); $stmt->close();
        setFlash('success','Room updated.');
        header('Location: rooms.php'); exit;
    }
    $room = array_merge($room, $_POST);
}

$roomTypes = $conn->query("SELECT * FROM room_types ORDER BY price DESC");
$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-edit mr-2"></i>Edit Room</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="rooms.php">Rooms</a></li>
          <li class="breadcrumb-item active">Edit</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <div class="card" style="max-width:600px;">
      <div class="card-header"><h3 class="card-title">Edit Room <?= htmlspecialchars($room['room_number']) ?></h3></div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="card-body">
          <div class="form-group">
            <label>Room Number <span class="text-danger">*</span></label>
            <input type="text" name="room_number" class="form-control" required value="<?= htmlspecialchars($room['room_number']) ?>">
          </div>
          <div class="form-group">
            <label>Room Type <span class="text-danger">*</span></label>
            <select name="room_type_id" class="form-control" required>
              <?php while ($rt=$roomTypes->fetch_assoc()): ?>
                <option value="<?= $rt['id'] ?>" <?= $room['room_type_id']==$rt['id']?'selected':'' ?>>
                  Type <?= htmlspecialchars($rt['type_name']) ?> — <?= $rt['beds'] ?> bed(s) — <?= formatCurrency($rt['price']) ?>/month
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Floor</label>
            <input type="number" name="floor" class="form-control" min="1" value="<?= htmlspecialchars($room['floor']) ?>">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
              <?php foreach (['available','occupied','maintenance'] as $s): ?>
                <option value="<?= $s ?>" <?= $room['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Update Room</button>
          <a href="rooms.php" class="btn btn-secondary ml-2">Cancel</a>
        </div>
      </form>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
