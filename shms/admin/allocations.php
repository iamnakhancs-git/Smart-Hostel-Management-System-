<?php
// ============================================================
// Admin — Room Allocations
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Room Allocations';
$role      = ROLE_ADMIN;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = sanitize($_POST['action'] ?? '');

    $conn->begin_transaction();
    if ($action === 'allocate') {
        $resId  = (int)($_POST['resident_id'] ?? 0);
        $roomId = (int)($_POST['room_id']     ?? 0);
        $date   = sanitize($_POST['alloc_date'] ?? date('Y-m-d'));

        shmsDate($date);
        $person = shmsRows('SELECT id,status FROM residents WHERE id=? FOR UPDATE',[$resId]);
        $roomRow = shmsRows('SELECT r.id,r.status,rt.beds FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.id=? FOR UPDATE',[$roomId]);
        if (!$person || $person[0]['status'] !== 'active') { throw new InvalidArgumentException('Select an active resident.'); }
        if (!$roomRow || $roomRow[0]['status'] === 'maintenance') { throw new InvalidArgumentException('Select an available room that is not under maintenance.'); }
        // The resident and room locks serialize concurrent allocations.
        $chk = $conn->query("SELECT id FROM room_allocations WHERE resident_id=$resId AND is_active=1");
        if ($chk->num_rows) {
            throw new InvalidArgumentException('Resident already has an active room allocation.');
        } else {
            // Check room capacity
            $capRes = $conn->query(
                "SELECT rt.beds,
                 (SELECT COUNT(*) FROM room_allocations ra2 WHERE ra2.room_id=$roomId AND ra2.is_active=1) AS occupied
                 FROM rooms r JOIN room_types rt ON r.room_type_id=rt.id WHERE r.id=$roomId"
            );
            $cap = $capRes->fetch_assoc();
            if ($cap['occupied'] >= $cap['beds']) {
                throw new InvalidArgumentException('Room is already at full capacity.');
            } else {
                $stmt = $conn->prepare("INSERT INTO room_allocations (resident_id,room_id,alloc_date) VALUES (?,?,?)");
                $stmt->bind_param('iis',$resId,$roomId,$date);
                $stmt->execute(); $stmt->close();
                // Update room status to occupied
                $conn->query("UPDATE rooms SET status='occupied' WHERE id=$roomId");
                setFlash('success','Room allocated successfully.');
            }
        }
    } elseif ($action === 'transfer') {
        $allocId   = (int)($_POST['alloc_id'] ?? 0);
        $newRoomId = (int)($_POST['new_room_id'] ?? 0);
        $transDate = sanitize($_POST['transfer_date'] ?? date('Y-m-d'));
        shmsDate($transDate);

        // Fetch and lock current allocation
        $alloc = $conn->query(
            "SELECT ra.id, ra.resident_id, ra.room_id, ra.alloc_date, r.name AS res_name, rm.room_number AS old_room_no
             FROM room_allocations ra
             JOIN residents r ON ra.resident_id=r.id
             JOIN rooms rm ON ra.room_id=rm.id
             WHERE ra.id=$allocId AND ra.is_active=1 FOR UPDATE"
        )->fetch_assoc();

        if (!$alloc) {
            throw new InvalidArgumentException('Invalid or inactive room allocation.');
        }
        if ($transDate < $alloc['alloc_date']) {
            throw new InvalidArgumentException('Transfer date cannot be before original allocation date.');
        }
        if ($newRoomId === (int)$alloc['room_id']) {
            throw new InvalidArgumentException('Resident is already allocated to this room.');
        }

        // Lock target room & verify capacity
        $newRoom = shmsRows('SELECT r.id, r.room_number, r.status, rt.beds FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.id=? FOR UPDATE', [$newRoomId]);
        if (!$newRoom || $newRoom[0]['status'] === 'maintenance') {
            throw new InvalidArgumentException('Selected target room is under maintenance or unavailable.');
        }
        $cap = (int)shmsValue('SELECT COUNT(*) FROM room_allocations WHERE room_id=? AND is_active=1', [$newRoomId]);
        if ($cap >= (int)$newRoom[0]['beds']) {
            throw new InvalidArgumentException('Selected target room is already at full capacity.');
        }

        $oldRoomId = (int)$alloc['room_id'];
        $resId     = (int)$alloc['resident_id'];

        // 1. Vacate old room allocation with transfer date
        $stmtVac = $conn->prepare("UPDATE room_allocations SET is_active=0, vacate_date=? WHERE id=?");
        $stmtVac->bind_param('si', $transDate, $allocId);
        $stmtVac->execute();
        $stmtVac->close();

        // 2. Insert new room allocation
        $stmtIns = $conn->prepare("INSERT INTO room_allocations (resident_id, room_id, alloc_date) VALUES (?,?,?)");
        $stmtIns->bind_param('iis', $resId, $newRoomId, $transDate);
        $stmtIns->execute();
        $stmtIns->close();

        // 3. Update status of old room if no residents remain
        $remOld = (int)shmsValue('SELECT COUNT(*) FROM room_allocations WHERE room_id=? AND is_active=1', [$oldRoomId]);
        if ($remOld === 0) {
            $conn->query("UPDATE rooms SET status='available' WHERE id=$oldRoomId AND status<>'maintenance'");
        }
        // 4. Update target room status to occupied
        $conn->query("UPDATE rooms SET status='occupied' WHERE id=$newRoomId");

        setFlash('success', htmlspecialchars($alloc['res_name']) . ' successfully transferred from Room ' . htmlspecialchars($alloc['old_room_no']) . ' to Room ' . htmlspecialchars($newRoom[0]['room_number']) . '.');
    } elseif ($action === 'vacate') {
        $allocId = (int)($_POST['alloc_id'] ?? 0);
        $vacDate = sanitize($_POST['vacate_date'] ?? date('Y-m-d'));
        shmsDate($vacDate);
        
        // Get room_id first to verify if allocation exists and is active
        $alloc = $conn->query("SELECT room_id,alloc_date FROM room_allocations WHERE id=$allocId AND is_active=1 FOR UPDATE")->fetch_assoc();
        if ($alloc) {
            if ($vacDate < $alloc['alloc_date']) { throw new InvalidArgumentException('Vacate date cannot be before allocation date.'); }
            $rId = (int)$alloc['room_id'];
            $stmt = $conn->prepare("UPDATE room_allocations SET is_active=0,vacate_date=? WHERE id=?");
            $stmt->bind_param('si',$vacDate,$allocId);
            $stmt->execute(); $stmt->close();
            
            // Check if any active allocations remain for this room
            $remaining = (int)$conn->query("SELECT COUNT(*) FROM room_allocations WHERE room_id=$rId AND is_active=1")->fetch_row()[0];
            if ($remaining === 0) {
                $conn->query("UPDATE rooms SET status='available' WHERE id=$rId AND status<>'maintenance'");
            }
            setFlash('success','Room vacated successfully.');
        } else {
            throw new InvalidArgumentException('Invalid or inactive room allocation.');
        }
    } else { throw new InvalidArgumentException('Invalid allocation action.'); }
    $conn->commit();
    header('Location: allocations.php'); exit;
}

// Active allocations
$allocations = $conn->query(
    "SELECT ra.*, r.name AS resident_name, rm.room_number,
            rt.type_name, rt.price
     FROM room_allocations ra
     JOIN residents r  ON ra.resident_id=r.id
     JOIN rooms rm     ON ra.room_id=rm.id
     JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE ra.is_active=1
     ORDER BY ra.alloc_date DESC"
);

// Past allocations / history
$historyAllocations = $conn->query(
    "SELECT ra.*, r.name AS resident_name, rm.room_number,
            rt.type_name, rt.price
     FROM room_allocations ra
     JOIN residents r  ON ra.resident_id=r.id
     JOIN rooms rm     ON ra.room_id=rm.id
     JOIN room_types rt ON rm.room_type_id=rt.id
     WHERE ra.is_active=0
     ORDER BY ra.vacate_date DESC, ra.id DESC"
);

// Show every room so a searched room never appears to be missing. Full and
// maintenance rooms remain visible with a reason, but cannot be selected.
$availRoomsRes = $conn->query(
    "SELECT r.id, r.room_number, r.status, rt.type_name, rt.beds, rt.price,
            (SELECT COUNT(*) FROM room_allocations ra2 WHERE ra2.room_id=r.id AND ra2.is_active=1) AS occupied
     FROM rooms r JOIN room_types rt ON r.room_type_id=rt.id
     ORDER BY r.room_number"
);
$roomsList = [];
while ($rm = $availRoomsRes->fetch_assoc()) {
    $roomsList[] = $rm;
}

// Unallocated residents
$unallocRes = $conn->query(
    "SELECT id,name FROM residents r
     WHERE r.status='active'
       AND NOT EXISTS (SELECT 1 FROM room_allocations ra WHERE ra.resident_id=r.id AND ra.is_active=1)
     ORDER BY name"
);

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-key mr-2"></i>Room Allocations & History</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Allocations</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>

    <!-- Allocate Room Form -->
    <div class="card card-success">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle mr-2"></i>Allocate Room</h3></div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="allocate">
        <div class="card-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Resident <span class="text-danger">*</span></label>
                <select name="resident_id" class="form-control" required>
                  <option value="">-- Select Resident --</option>
                  <?php while ($r=$unallocRes->fetch_assoc()): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Room <span class="text-danger">*</span></label>
                <select name="room_id" class="form-control" required>
                  <option value="">-- Select Room --</option>
                  <?php foreach ($roomsList as $rm): ?>
                    <?php $roomUnavailable = $rm['status']==='maintenance' || (int)$rm['occupied'] >= (int)$rm['beds']; ?>
                    <option value="<?= $rm['id'] ?>" <?= $roomUnavailable ? 'disabled' : '' ?>>
                      <?= htmlspecialchars($rm['room_number']) ?> (Type <?= htmlspecialchars($rm['type_name']) ?>) —
                      <?= $rm['status']==='maintenance' ? 'Maintenance' : ((int)$rm['beds']-(int)$rm['occupied']).'/'.$rm['beds'].' beds free' ?> —
                      <?= formatCurrency($rm['price']) ?>/mo
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Allocation Date</label>
                <input type="date" name="alloc_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i>Allocate Room</button>
        </div>
      </form>
    </div>

    <!-- Allocations Tabs (Active vs History) -->
    <div class="card card-primary card-outline card-outline-tabs">
      <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="allocTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active font-weight-bold" id="tab-active-btn" data-toggle="pill" href="#tab-active" role="tab" aria-controls="tab-active" aria-selected="true">
              <i class="fas fa-check-circle text-success mr-1"></i>Active Allocations
              <span class="badge badge-success ml-1"><?= $allocations->num_rows ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link font-weight-bold" id="tab-history-btn" data-toggle="pill" href="#tab-history" role="tab" aria-controls="tab-history" aria-selected="false">
              <i class="fas fa-history text-info mr-1"></i>Allocation History / Past Records
              <span class="badge badge-secondary ml-1"><?= $historyAllocations->num_rows ?></span>
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body p-0">
        <div class="tab-content" id="allocTabsContent">
          <!-- Active Allocations Tab -->
          <div class="tab-pane fade show active" id="tab-active" role="tabpanel" aria-labelledby="tab-active-btn">
            <div class="p-3">
              <table class="table table-hover data-table" style="width:100%">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Resident</th>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Rent</th>
                    <th>Allocated Since</th>
                    <th>Duration</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php $i=1; while ($a=$allocations->fetch_assoc()): ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td>
                      <a href="resident_view.php?id=<?= $a['resident_id'] ?>" class="font-weight-bold">
                        <?= htmlspecialchars($a['resident_name']) ?>
                      </a>
                    </td>
                    <td><strong>Room <?= htmlspecialchars($a['room_number']) ?></strong></td>
                    <td>Type <?= htmlspecialchars($a['type_name']) ?></td>
                    <td><?= formatCurrency($a['price']) ?>/mo</td>
                    <td><?= date('d M Y',strtotime($a['alloc_date'])) ?></td>
                    <td><?= formatStayDuration($a['alloc_date'], null) ?></td>
                    <td>
                      <div class="btn-group btn-group-xs">
                        <button type="button" class="btn btn-xs btn-primary mr-1"
                          data-toggle="modal" data-target="#transferModal"
                          data-allocid="<?= $a['id'] ?>"
                          data-name="<?= htmlspecialchars($a['resident_name']) ?>"
                          data-room="<?= htmlspecialchars($a['room_number']) ?>"
                          title="Transfer / Change Room">
                          <i class="fas fa-exchange-alt mr-1"></i>Transfer
                        </button>
                        <button type="button" class="btn btn-xs btn-danger mr-1"
                          data-toggle="modal" data-target="#vacateModal"
                          data-allocid="<?= $a['id'] ?>"
                          data-name="<?= htmlspecialchars($a['resident_name']) ?>"
                          title="Vacate Room">
                          <i class="fas fa-sign-out-alt mr-1"></i>Vacate
                        </button>
                        <a href="resident_view.php?id=<?= $a['resident_id'] ?>#room-history" class="btn btn-xs btn-info" title="View Resident Room History">
                          <i class="fas fa-history"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Allocation History Tab -->
          <div class="tab-pane fade" id="tab-history" role="tabpanel" aria-labelledby="tab-history-btn">
            <div class="p-3">
              <table class="table table-hover data-table" style="width:100%">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Resident</th>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Rent</th>
                    <th>Allocated Date</th>
                    <th>Vacated Date</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Profile</th>
                  </tr>
                </thead>
                <tbody>
                <?php $j=1; while ($h=$historyAllocations->fetch_assoc()): ?>
                  <tr>
                    <td><?= $j++ ?></td>
                    <td>
                      <a href="resident_view.php?id=<?= $h['resident_id'] ?>" class="font-weight-bold">
                        <?= htmlspecialchars($h['resident_name']) ?>
                      </a>
                    </td>
                    <td><strong>Room <?= htmlspecialchars($h['room_number']) ?></strong></td>
                    <td>Type <?= htmlspecialchars($h['type_name']) ?></td>
                    <td><?= formatCurrency($h['price']) ?>/mo</td>
                    <td><?= date('d M Y',strtotime($h['alloc_date'])) ?></td>
                    <td><?= !empty($h['vacate_date']) ? date('d M Y',strtotime($h['vacate_date'])) : '—' ?></td>
                    <td><?= formatStayDuration($h['alloc_date'], $h['vacate_date']) ?></td>
                    <td><span class="badge badge-secondary"><i class="fas fa-history mr-1"></i>Vacated</span></td>
                    <td>
                      <a href="resident_view.php?id=<?= $h['resident_id'] ?>#room-history" class="btn btn-xs btn-outline-info" title="View Full History">
                        <i class="fas fa-eye mr-1"></i>View
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div></section>
</div>

<!-- Vacate Modal -->
<div class="modal fade" id="vacateModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="vacate">
        <input type="hidden" name="alloc_id" id="vacateAllocId">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-sign-out-alt mr-2"></i>Vacate Room</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to vacate room for <strong id="vacateName"></strong>?</p>
          <div class="form-group">
            <label>Vacate Date <span class="text-danger">*</span></label>
            <input type="date" name="vacate_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            <small class="text-muted">This date will be permanently saved in the resident's room history.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fas fa-check mr-1"></i>Confirm Vacate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Transfer / Change Room Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="transfer">
        <input type="hidden" name="alloc_id" id="transferAllocId">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fas fa-exchange-alt mr-2"></i>Transfer / Change Room</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2">
            Transferring resident <strong id="transferResidentName"></strong> currently in <strong id="transferCurrentRoom"></strong>.
          </div>
          <div class="form-group">
            <label>Select New Room <span class="text-danger">*</span></label>
            <select name="new_room_id" class="form-control" required>
              <option value="">-- Select New Target Room --</option>
              <?php foreach ($roomsList as $rm): ?>
                <?php $roomUnavailable = $rm['status']==='maintenance' || (int)$rm['occupied'] >= (int)$rm['beds']; ?>
                <option value="<?= $rm['id'] ?>" <?= $roomUnavailable ? 'disabled' : '' ?>>
                  Room <?= htmlspecialchars($rm['room_number']) ?> (Type <?= htmlspecialchars($rm['type_name']) ?>) —
                  <?= $rm['status']==='maintenance' ? 'Maintenance' : ((int)$rm['beds']-(int)$rm['occupied']).'/'.$rm['beds'].' beds free' ?> —
                  <?= formatCurrency($rm['price']) ?>/mo
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Transfer Date <span class="text-danger">*</span></label>
            <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            <small class="text-muted">The current room will be recorded as vacated on this date, and new room history begins immediately.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i>Confirm Transfer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$('#vacateModal').on('show.bs.modal', function(e) {
  var btn = $(e.relatedTarget);
  $(this).find('#vacateAllocId').val(btn.data('allocid'));
  $(this).find('#vacateName').text(btn.data('name'));
});
$('#transferModal').on('show.bs.modal', function(e) {
  var btn = $(e.relatedTarget);
  $(this).find('#transferAllocId').val(btn.data('allocid'));
  $(this).find('#transferResidentName').text(btn.data('name'));
  $(this).find('#transferCurrentRoom').text('Room ' + btn.data('room'));
});
$('a[data-toggle="pill"]').on('shown.bs.tab', function () {
  if ($.fn.dataTable) {
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
  }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
