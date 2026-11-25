<?php
// ============================================================
// Admin — Manage Rooms
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Manage Rooms';
$role      = ROLE_ADMIN;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        $conn->query("DELETE FROM rooms WHERE id=$id");
        setFlash('success','Room deleted.');
    } elseif ($action === 'status' && $id) {
        $status = sanitize($_POST['status'] ?? '');
        shmsCheckRoomState($id,$status);
        $conn->query("UPDATE rooms SET status='$status' WHERE id=$id");
        setFlash('success','Room status updated.');
    }
    header('Location: rooms.php'); exit;
}

$search = sanitize($_GET['search'] ?? '');
$type   = (int)($_GET['type'] ?? 0);
$status = sanitize($_GET['status'] ?? '');
$searchSql = $conn->real_escape_string($search);
$statusSql = $conn->real_escape_string($status);
$where  = '1=1';
if ($search) $where .= " AND r.room_number LIKE '%$searchSql%'";
if ($type)   $where .= " AND r.room_type_id=$type";
if ($status) $where .= " AND r.status='$statusSql'";

$rooms = $conn->query(
    "SELECT r.*, rt.type_name, rt.beds, rt.price
     FROM rooms r JOIN room_types rt ON r.room_type_id=rt.id
     WHERE $where ORDER BY r.room_number"
);
$roomTypes = $conn->query("SELECT * FROM room_types ORDER BY price DESC");
$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-door-open mr-2"></i>Manage Rooms</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Rooms</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card">
      <div class="card-body">
        <form method="get" class="form-inline flex-wrap">
          <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Room number" value="<?= htmlspecialchars($search) ?>">
          <select name="type" class="form-control mr-2 mb-2">
            <option value="">All Types</option>
            <?php $roomTypes->data_seek(0); while ($rt=$roomTypes->fetch_assoc()): ?>
              <option value="<?= $rt['id'] ?>" <?= $type==$rt['id']?'selected':'' ?>>Type <?= htmlspecialchars($rt['type_name']) ?></option>
            <?php endwhile; ?>
          </select>
          <select name="status" class="form-control mr-2 mb-2">
            <option value="">All Status</option>
            <option value="available"   <?= $status==='available'   ?'selected':'' ?>>Available</option>
            <option value="occupied"    <?= $status==='occupied'    ?'selected':'' ?>>Occupied</option>
            <option value="maintenance" <?= $status==='maintenance' ?'selected':'' ?>>Maintenance</option>
          </select>
          <button class="btn btn-primary mr-2 mb-2"><i class="fas fa-search mr-1"></i>Filter</button>
          <a href="rooms.php" class="btn btn-secondary mr-2 mb-2">Reset</a>
          <a href="room_add.php" class="btn btn-success ml-auto mb-2"><i class="fas fa-plus mr-1"></i>Add Room</a>
        </form>
      </div>
    </div>
    <div class="card"><div class="card-body p-0">
      <table class="table table-hover data-table">
        <thead><tr><th>#</th><th>Room No.</th><th>Type</th><th>Beds</th><th>Rent/Month</th><th>Floor</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $i=1; while ($r=$rooms->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><strong><?= htmlspecialchars($r['room_number']) ?></strong></td>
            <td>Type <?= htmlspecialchars($r['type_name']) ?></td>
            <td><?= $r['beds'] ?></td>
            <td><?= formatCurrency($r['price']) ?></td>
            <td><?= $r['floor'] ?></td>
            <td><?= statusBadge($r['status']) ?></td>
            <td>
              <a href="room_edit.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-warning"><i class="fas fa-edit"></i></a>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-xs btn-danger btn-delete"><i class="fas fa-trash"></i></button>
              </form>
              <!-- Quick status change -->
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                  Status
                </button>
                <div class="dropdown-menu">
                  <?php foreach (['available','occupied','maintenance'] as $s): ?>
                    <form method="post">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="action" value="status">
                      <input type="hidden" name="id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="status" value="<?= $s ?>">
                      <button class="dropdown-item <?= $r['status']===$s?'font-weight-bold':'' ?>">
                        <?= ucfirst($s) ?>
                      </button>
                    </form>
                  <?php endforeach; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div></div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
