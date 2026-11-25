<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_RESIDENT);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_RESIDENT);

$conn      = getDBConnection();
$pageTitle = 'My Room';
$role      = ROLE_RESIDENT;
$rid       = (int)$_SESSION['user_id'];

$res = $conn->query(
    "SELECT ra.*, rm.room_number, rm.floor, rm.status AS room_status,
            rt.type_name, rt.beds, rt.description, rt.price
     FROM room_allocations ra
     JOIN rooms rm     ON ra.room_id=rm.id
     JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE ra.resident_id=$rid AND ra.is_active=1 LIMIT 1"
);
$room = $res->fetch_assoc();

// All allocations history for this resident
$historyRes = $conn->query(
    "SELECT ra.*, rm.room_number, rm.floor,
            rt.type_name, rt.beds, rt.description, rt.price
     FROM room_allocations ra
     JOIN rooms rm     ON ra.room_id=rm.id
     JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE ra.resident_id=$rid
     ORDER BY ra.is_active DESC, ra.alloc_date DESC, ra.id DESC"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_resident.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-door-closed mr-2"></i>My Room & History</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">My Room</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>

    <div class="row">
      <div class="col-lg-6">
        <?php if ($room): ?>
          <div class="card card-primary card-outline">
            <div class="card-header">
              <h3 class="card-title font-weight-bold"><i class="fas fa-door-open mr-2 text-primary"></i>Current Room: <?= htmlspecialchars($room['room_number']) ?></h3>
              <div class="card-tools">
                <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Active</span>
              </div>
            </div>
            <div class="card-body">
              <table class="table table-borderless">
                <tr><th style="width:40%">Room Number</th><td><strong>Room <?= htmlspecialchars($room['room_number']) ?></strong></td></tr>
                <tr><th>Room Type</th><td>Type <?= htmlspecialchars($room['type_name']) ?> — <?= htmlspecialchars($room['description']) ?></td></tr>
                <tr><th>Total Beds</th><td><?= $room['beds'] ?></td></tr>
                <tr><th>Floor</th><td>Floor <?= $room['floor'] ?></td></tr>
                <tr><th>Monthly Rent</th><td><strong class="text-success"><?= formatCurrency($room['price']) ?></strong></td></tr>
                <tr><th>Allocated On</th><td><?= date('d M Y',strtotime($room['alloc_date'])) ?></td></tr>
                <tr><th>Duration Here</th><td><?= formatStayDuration($room['alloc_date'], null) ?></td></tr>
                <tr><th>Room Status</th><td><?= statusBadge($room['room_status']) ?></td></tr>
              </table>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-warning">
            <i class="fas fa-info-circle mr-2"></i>You currently have no active room allocated. Please contact the hostel admin.
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-6">
        <div class="card card-info card-outline">
          <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2 text-info"></i>My Room History</h3>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Period</th>
                    <th>Duration</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php if ($historyRes && $historyRes->num_rows > 0): ?>
                  <?php while ($h = $historyRes->fetch_assoc()): ?>
                    <tr class="<?= $h['is_active'] ? 'table-success' : '' ?>">
                      <td>
                        <strong>Room <?= htmlspecialchars($h['room_number']) ?></strong>
                        <div class="text-muted small">Floor <?= htmlspecialchars($h['floor']) ?></div>
                      </td>
                      <td>Type <?= htmlspecialchars($h['type_name']) ?></td>
                      <td>
                        <div><small class="text-muted">From:</small> <?= date('d M Y', strtotime($h['alloc_date'])) ?></div>
                        <div>
                          <small class="text-muted">To:</small>
                          <?php if ($h['is_active']): ?>
                            <span class="text-success font-weight-bold">Present</span>
                          <?php else: ?>
                            <?= !empty($h['vacate_date']) ? date('d M Y', strtotime($h['vacate_date'])) : '—' ?>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td><?= formatStayDuration($h['alloc_date'], $h['vacate_date']) ?></td>
                      <td>
                        <?php if ($h['is_active']): ?>
                          <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Current</span>
                        <?php else: ?>
                          <span class="badge badge-secondary"><i class="fas fa-history mr-1"></i>Previous</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-3">
                      <i class="fas fa-info-circle mr-1"></i>No previous room history found.
                    </td>
                  </tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div></section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
