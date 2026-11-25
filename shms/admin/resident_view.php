<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'View Resident';
$role      = ROLE_ADMIN;
$id        = (int)($_GET['id'] ?? 0);

$res = $conn->query(
    "SELECT r.*,
            ra.alloc_date, ra.id AS alloc_id,
            rm.room_number, rt.type_name, rt.price
     FROM residents r
     LEFT JOIN room_allocations ra ON ra.resident_id=r.id AND ra.is_active=1
     LEFT JOIN rooms rm ON ra.room_id=rm.id
     LEFT JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE r.id=$id LIMIT 1"
);
if (!$res->num_rows) { setFlash('error','Resident not found.'); header('Location: residents.php'); exit; }
$resident = $res->fetch_assoc();

// Room history
$roomHistory = $conn->query(
    "SELECT ra.*,
            rm.room_number, rm.floor,
            rt.type_name, rt.beds, rt.price, rt.description
     FROM room_allocations ra
     JOIN rooms rm ON ra.room_id=rm.id
     JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE ra.resident_id=$id
     ORDER BY ra.is_active DESC, ra.alloc_date DESC, ra.id DESC"
);

// Bills summary
$bills     = $conn->query("SELECT * FROM bills WHERE resident_id=$id ORDER BY year DESC, month DESC LIMIT 6");
// Complaints
$complaints = $conn->query("SELECT * FROM complaints WHERE resident_id=$id ORDER BY created_at DESC LIMIT 5");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-user mr-2"></i>Resident Profile</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="residents.php">Residents</a></li>
          <li class="breadcrumb-item active">View</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="row">
      <!-- Profile Card -->
      <div class="col-md-4">
        <div class="card card-primary card-outline">
          <div class="card-body text-center">
            <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
            <h3><?= htmlspecialchars($resident['name']) ?></h3>
            <p><?= htmlspecialchars($resident['email']) ?></p>
            <?= statusBadge($resident['status']) ?>
            <hr>
            <table class="table table-borderless table-sm text-left">
              <tr><th>Phone</th><td><?= htmlspecialchars($resident['phone']??'—') ?></td></tr>
              <tr><th>Emergency</th><td><?= htmlspecialchars($resident['emergency_contact']??'—') ?></td></tr>
              <tr><th>ID Proof</th><td><?= htmlspecialchars($resident['id_proof_type']??'—') ?> — <?= htmlspecialchars($resident['id_proof_number']??'') ?></td></tr>
              <tr><th>Join Date</th><td><?= $resident['join_date'] ? date('d M Y',strtotime($resident['join_date'])) : '—' ?></td></tr>
              <tr><th>Current Room</th><td><?= $resident['room_number'] ? '<span class="badge badge-success font-weight-bold">Room ' . htmlspecialchars($resident['room_number']) . '</span> (Type ' . htmlspecialchars($resident['type_name']) . ')' : '<span class="text-muted">Not Allocated</span>' ?></td></tr>
              <?php if ($resident['room_number']): ?>
              <tr><th>Rent</th><td><?= formatCurrency($resident['price']) ?>/mo</td></tr>
              <tr><th>Since</th><td><?= date('d M Y',strtotime($resident['alloc_date'])) ?></td></tr>
              <?php endif; ?>
            </table>
          </div>
          <div class="card-footer text-center">
            <a href="resident_edit.php?id=<?= $id ?>" class="btn btn-warning btn-sm">
              <i class="fas fa-edit mr-1"></i>Edit
            </a>
            <a href="residents.php" class="btn btn-secondary btn-sm ml-2">Back</a>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <!-- Room Allocation History -->
        <div class="card card-info card-outline" id="room-history">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2 text-info"></i>Room Allocation History</h3>
            <div class="card-tools ml-auto">
              <?php if (!empty($resident['room_number'])): ?>
                <a href="allocations.php" class="btn btn-xs btn-outline-primary"><i class="fas fa-key mr-1"></i>Manage Allocations</a>
              <?php else: ?>
                <a href="allocations.php" class="btn btn-xs btn-success"><i class="fas fa-plus mr-1"></i>Allocate Room</a>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Rent</th>
                    <th>Allocated Date</th>
                    <th>Vacate Date</th>
                    <th>Stay Duration</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php if ($roomHistory && $roomHistory->num_rows > 0): ?>
                  <?php while ($rh = $roomHistory->fetch_assoc()): ?>
                    <tr class="<?= $rh['is_active'] ? 'table-success' : '' ?>">
                      <td>
                        <strong>Room <?= htmlspecialchars($rh['room_number']) ?></strong>
                        <div class="text-muted small">Floor <?= htmlspecialchars($rh['floor']) ?></div>
                      </td>
                      <td>Type <?= htmlspecialchars($rh['type_name']) ?></td>
                      <td><?= formatCurrency($rh['price']) ?></td>
                      <td><i class="far fa-calendar-alt text-primary mr-1"></i><?= date('d M Y', strtotime($rh['alloc_date'])) ?></td>
                      <td>
                        <?php if ($rh['is_active']): ?>
                          <span class="text-success font-weight-bold"><i class="fas fa-circle text-xs mr-1"></i>Present</span>
                        <?php else: ?>
                          <i class="far fa-calendar-check text-muted mr-1"></i><?= !empty($rh['vacate_date']) ? date('d M Y', strtotime($rh['vacate_date'])) : '—' ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="text-nowrap"><?= formatStayDuration($rh['alloc_date'], $rh['vacate_date']) ?></span>
                      </td>
                      <td>
                        <?php if ($rh['is_active']): ?>
                          <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Current Room</span>
                        <?php else: ?>
                          <span class="badge badge-secondary"><i class="fas fa-history mr-1"></i>Previous Room</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-3">
                      <i class="fas fa-info-circle mr-1"></i>No room allocations or history recorded yet.
                    </td>
                  </tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Bills -->
        <div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i>Recent Bills</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>Month/Year</th><th>Total</th><th>Status</th><th></th></tr></thead>
              <tbody>
              <?php while ($b=$bills->fetch_assoc()): ?>
                <tr>
                  <td><?= monthName($b['month']).' '.$b['year'] ?></td>
                  <td><?= formatCurrency($b['total_amount']) ?></td>
                  <td><?= statusBadge($b['payment_status']) ?></td>
                  <td><a href="view_bill.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a></td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Complaints -->
        <div class="card">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-circle mr-2"></i>Recent Complaints</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>Subject</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
              <?php while ($c=$complaints->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars(substr($c['subject'],0,40)) ?></td>
                  <td><?= statusBadge($c['status']) ?></td>
                  <td><?= date('d M Y',strtotime($c['created_at'])) ?></td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
