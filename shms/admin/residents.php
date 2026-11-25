<?php
// ============================================================
// Admin — Manage Residents
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Manage Residents';
$role      = ROLE_ADMIN;

// Handle status toggle / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($id && in_array($action,['delete','toggle'],true) && shmsValue('SELECT COUNT(*) FROM room_allocations WHERE resident_id=? AND is_active=1',[$id])) {
        throw new InvalidArgumentException('Vacate this resident\'s active room allocation first.');
    }

    if ($action === 'delete' && $id) {
        $conn->query("DELETE FROM residents WHERE id=$id");
        setFlash('success', 'Resident deleted successfully.');
    } elseif ($action === 'toggle' && $id) {
        $conn->query("UPDATE residents SET status = IF(status='active','inactive','active') WHERE id=$id");
        setFlash('success', 'Resident status updated.');
    }
    header('Location: residents.php'); exit;
}

// Search & filter
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$searchSql = $conn->real_escape_string($search);
$statusSql = $conn->real_escape_string($status);
$where  = '1=1';
if ($search) $where .= " AND (r.name LIKE '%$searchSql%' OR r.email LIKE '%$searchSql%' OR r.phone LIKE '%$searchSql%')";
if ($status) $where .= " AND r.status='$statusSql'";

$residents = $conn->query(
    "SELECT r.*,
            ra.room_id,
            rm.room_number,
            rt.type_name AS room_type,
            (SELECT COUNT(*) FROM room_allocations WHERE resident_id=r.id) AS total_allocations,
            (SELECT rm2.room_number FROM room_allocations ra2 JOIN rooms rm2 ON ra2.room_id=rm2.id WHERE ra2.resident_id=r.id AND ra2.is_active=0 ORDER BY ra2.vacate_date DESC, ra2.id DESC LIMIT 1) AS last_past_room
     FROM residents r
     LEFT JOIN room_allocations ra ON ra.resident_id=r.id AND ra.is_active=1
     LEFT JOIN rooms rm ON ra.room_id=rm.id
     LEFT JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE $where
     ORDER BY r.created_at DESC"
);

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1><i class="fas fa-users mr-2"></i>Manage Residents</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Residents</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>

    <!-- Search bar -->
    <div class="card">
      <div class="card-body">
        <form method="get" class="form-inline">
          <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search name/email/phone"
                 value="<?= htmlspecialchars($search) ?>">
          <select name="status" class="form-control mr-2 mb-2">
            <option value="">All Status</option>
            <option value="active"   <?= $status==='active'   ? 'selected':'' ?>>Active</option>
            <option value="inactive" <?= $status==='inactive' ? 'selected':'' ?>>Inactive</option>
          </select>
          <button class="btn btn-primary mr-2 mb-2"><i class="fas fa-search mr-1"></i>Filter</button>
          <a href="residents.php" class="btn btn-secondary mb-2">Reset</a>
          <a href="resident_add.php" class="btn btn-success ml-auto mb-2">
            <i class="fas fa-user-plus mr-1"></i>Add Resident
          </a>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover data-table">
          <thead>
            <tr>
              <th>#</th><th>Name</th><th>Email</th><th>Phone</th>
              <th>Room</th><th>Join Date</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php $i=1; while ($r = $residents->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
              <td>
                <?php if (!empty($r['room_number'])): ?>
                  <span class="badge badge-success">Room <?= htmlspecialchars($r['room_number']) ?></span>
                  <small class="text-muted">(<?= htmlspecialchars($r['room_type']) ?>)</small>
                <?php elseif (!empty($r['last_past_room'])): ?>
                  <span class="badge badge-light border text-muted" title="Previously stayed in Room <?= htmlspecialchars($r['last_past_room']) ?>">
                    <i class="fas fa-history mr-1 text-info"></i>Prev: Room <?= htmlspecialchars($r['last_past_room']) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= $r['join_date'] ? date('d M Y', strtotime($r['join_date'])) : '—' ?></td>
              <td><?= statusBadge($r['status']) ?></td>
              <td>
                <a href="resident_edit.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-warning" title="Edit Resident">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="resident_view.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-info" title="View Profile">
                  <i class="fas fa-eye"></i>
                </a>
                <a href="resident_view.php?id=<?= $r['id'] ?>#room-history" class="btn btn-xs btn-outline-primary" title="View Room History">
                  <i class="fas fa-history"></i>
                </a>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button class="btn btn-xs btn-secondary" title="Toggle Status">
                    <i class="fas fa-toggle-on"></i>
                  </button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button class="btn btn-xs btn-danger btn-delete" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
