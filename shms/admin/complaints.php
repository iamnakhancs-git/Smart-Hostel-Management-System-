<?php
// ============================================================
// Admin — View & Manage Complaints
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_ADMIN);

$conn      = getDBConnection();
$pageTitle = 'Complaints';
$role      = ROLE_ADMIN;

if (!shmsColumnExists('complaints', 'attachment_path') || !shmsColumnExists('complaints', 'resolution_attachment_path')) {
    shmsBackendError('Database setup is incomplete. Recreate the database using sql/shms_database.sql.', 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $id     = (int)($_POST['id']     ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $remark = sanitize($_POST['remark'] ?? '');
    if ($id && $status) {
        $stmt = $conn->prepare('SELECT resolution_attachment_path FROM complaints WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $id); $stmt->execute();
        $existingComplaint = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existingComplaint) { throw new InvalidArgumentException('Complaint no longer exists.'); }

        $resolutionPath = null;
        $resolutionName = null;
        $resolutionMime = null;
        $newSavedFile = null;
        $proofFile = $_FILES['resolution_attachment'] ?? null;

        if ($proofFile && ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($proofFile['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Resolution proof upload failed. Please choose the file again.');
            }
            if ((int)$proofFile['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('Resolution proof must not exceed 5 MB.');
            }
            if (!is_uploaded_file($proofFile['tmp_name'])) {
                throw new InvalidArgumentException('Invalid resolution proof upload.');
            }
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? finfo_file($finfo, $proofFile['tmp_name']) : false;
            if ($finfo) { finfo_close($finfo); }
            if (!$detectedMime || !isset($allowedTypes[$detectedMime])) {
                throw new InvalidArgumentException('Resolution proof must be a JPG, PNG, WEBP or PDF file.');
            }
            $uploadDir = __DIR__ . '/../assets/uploads/complaints';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                throw new RuntimeException('Resolution proof storage directory could not be created.');
            }
            $storedName = 'resolution_' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$detectedMime];
            $newSavedFile = $uploadDir . '/' . $storedName;
            if (!move_uploaded_file($proofFile['tmp_name'], $newSavedFile)) {
                throw new RuntimeException('Resolution proof could not be saved.');
            }
            $resolutionPath = 'assets/uploads/complaints/' . $storedName;
            $resolutionName = substr(basename((string)$proofFile['name']), 0, 255);
            $resolutionMime = $detectedMime;
        }

        try {
            if ($resolutionPath) {
                $stmt = $conn->prepare('UPDATE complaints SET status=?,admin_remark=?,resolution_attachment_path=?,resolution_attachment_name=?,resolution_attachment_mime=? WHERE id=?');
                $stmt->bind_param('sssssi', $status, $remark, $resolutionPath, $resolutionName, $resolutionMime, $id);
            } else {
                $stmt = $conn->prepare('UPDATE complaints SET status=?,admin_remark=? WHERE id=?');
                $stmt->bind_param('ssi', $status, $remark, $id);
            }
            $stmt->execute(); $stmt->close();
        } catch (Throwable $e) {
            if ($newSavedFile && is_file($newSavedFile)) { unlink($newSavedFile); }
            throw $e;
        }
        if ($resolutionPath && !empty($existingComplaint['resolution_attachment_path'])) {
            $oldFile = __DIR__ . '/../' . $existingComplaint['resolution_attachment_path'];
            if (is_file($oldFile)) { unlink($oldFile); }
        }
        setFlash('success','Complaint status updated.');
    }
    header('Location: complaints.php'); exit;
}

$filter = sanitize($_GET['status'] ?? '');
$filterSql = $conn->real_escape_string($filter);
$where  = $filter ? "WHERE c.status='$filterSql'" : '';
$complaints = $conn->query(
    "SELECT c.*, r.name AS resident_name, rm.room_number
     FROM complaints c
     JOIN residents r ON c.resident_id=r.id
     LEFT JOIN room_allocations ra ON ra.resident_id=r.id AND ra.is_active=1
     LEFT JOIN rooms rm ON ra.room_id=rm.id
     $where
     ORDER BY c.created_at DESC"
);

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-exclamation-circle mr-2"></i>Complaints</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Complaints</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); ?>
    <div class="card"><div class="card-body">
      <form method="get" class="form-inline">
        <label class="mr-2">Filter by Status:</label>
        <select name="status" class="form-control mr-2">
          <option value="">All</option>
          <option value="pending"     <?= $filter==='pending'     ?'selected':'' ?>>Pending</option>
          <option value="in_progress" <?= $filter==='in_progress' ?'selected':'' ?>>In Progress</option>
          <option value="resolved"    <?= $filter==='resolved'    ?'selected':'' ?>>Resolved</option>
        </select>
        <button class="btn btn-primary"><i class="fas fa-filter mr-1"></i>Filter</button>
        <a href="complaints.php" class="btn btn-secondary ml-2">Reset</a>
      </form>
    </div></div>

    <div class="card"><div class="card-body p-0">
      <table class="table table-hover data-table">
        <thead><tr><th>#</th><th>Resident</th><th>Room</th><th>Subject</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php $i=1; while ($c=$complaints->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($c['resident_name']) ?></td>
            <td><?= htmlspecialchars($c['room_number'] ?? '—') ?></td>
            <td><?= htmlspecialchars($c['subject']) ?></td>
            <td><?= statusBadge($c['status']) ?></td>
            <td><?= date('d M Y',strtotime($c['created_at'])) ?></td>
            <td>
              <button class="btn btn-xs btn-primary" data-toggle="modal" data-target="#updateModal"
                data-id="<?= $c['id'] ?>"
                data-subject="<?= htmlspecialchars($c['subject']) ?>"
                data-desc="<?= htmlspecialchars($c['description']) ?>"
                data-status="<?= $c['status'] ?>"
                data-remark="<?= htmlspecialchars($c['admin_remark'] ?? '') ?>"
                data-attachment="<?= htmlspecialchars($c['attachment_path'] ? '../' . $c['attachment_path'] : '', ENT_QUOTES) ?>"
                data-attachment-name="<?= htmlspecialchars($c['attachment_name'] ?? '', ENT_QUOTES) ?>"
                data-attachment-mime="<?= htmlspecialchars($c['attachment_mime'] ?? '', ENT_QUOTES) ?>"
                data-resolution="<?= htmlspecialchars($c['resolution_attachment_path'] ? '../' . $c['resolution_attachment_path'] : '', ENT_QUOTES) ?>"
                data-resolution-name="<?= htmlspecialchars($c['resolution_attachment_name'] ?? '', ENT_QUOTES) ?>"
                data-resolution-mime="<?= htmlspecialchars($c['resolution_attachment_mime'] ?? '', ENT_QUOTES) ?>">
                <i class="fas fa-edit"></i> Update
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div></div>
  </div></section>
</div>

<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id" id="cmpId">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Update Complaint</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p><strong>Subject:</strong> <span id="cmpSubject"></span></p>
          <p><strong>Description:</strong></p>
          <div id="cmpDesc" class="bg-light p-2 rounded mb-3"></div>
          <div id="cmpAttachmentWrap" class="form-group d-none">
            <label>Resident Attachment</label>
            <div id="cmpImagePreview" class="mb-2 d-none">
              <img id="cmpAttachmentImage" src="" alt="Complaint attachment" class="img-fluid img-thumbnail" style="max-height:300px">
            </div>
            <a id="cmpAttachmentLink" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-paperclip mr-1"></i><span>View attachment</span>
            </a>
          </div>
          <div class="form-group">
            <label>Update Status</label>
            <select name="status" id="cmpStatus" class="form-control">
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
            </select>
          </div>
          <div class="form-group">
            <label>Admin Remark</label>
            <textarea name="remark" id="cmpRemark" class="form-control" rows="3" placeholder="Optional remark"></textarea>
          </div>
          <div class="form-group">
            <label>Resolution Proof / Fixed Work Photo <small class="text-muted">(optional)</small></label>
            <div id="existingResolutionWrap" class="mb-2 d-none">
              <a id="existingResolutionLink" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success">
                <i class="fas fa-check-circle mr-1"></i><span>View current resolution proof</span>
              </a>
            </div>
            <div class="custom-file">
              <input type="file" name="resolution_attachment" id="resolutionAttachment" class="custom-file-input"
                     accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
              <label class="custom-file-label" for="resolutionAttachment">Choose fixed-work photo or PDF</label>
            </div>
            <small class="form-text text-muted">Maximum 5 MB. Uploading a new file replaces the previous resolution proof.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
$('#updateModal').on('show.bs.modal', function(e) {
  var btn = $(e.relatedTarget);
  $(this).find('#cmpId').val(btn.data('id'));
  $(this).find('#cmpSubject').text(btn.data('subject'));
  $(this).find('#cmpDesc').text(btn.data('desc'));
  $(this).find('#cmpStatus').val(btn.data('status'));
  $(this).find('#cmpRemark').val(btn.data('remark'));
  var attachment = btn.attr('data-attachment') || '';
  var attachmentName = btn.attr('data-attachment-name') || 'View attachment';
  var attachmentMime = btn.attr('data-attachment-mime') || '';
  var wrap = $(this).find('#cmpAttachmentWrap');
  var preview = $(this).find('#cmpImagePreview');
  var image = $(this).find('#cmpAttachmentImage');
  if (attachment) {
    wrap.removeClass('d-none');
    $(this).find('#cmpAttachmentLink').attr('href', attachment).find('span').text(attachmentName);
    if (attachmentMime.indexOf('image/') === 0) {
      image.attr('src', attachment);
      preview.removeClass('d-none');
    } else {
      image.attr('src', '');
      preview.addClass('d-none');
    }
  } else {
    wrap.addClass('d-none');
    preview.addClass('d-none');
    image.attr('src', '');
  }

  var resolution = btn.attr('data-resolution') || '';
  var resolutionName = btn.attr('data-resolution-name') || 'View current resolution proof';
  var resolutionWrap = $(this).find('#existingResolutionWrap');
  if (resolution) {
    resolutionWrap.removeClass('d-none');
    $(this).find('#existingResolutionLink').attr('href', resolution).find('span').text(resolutionName);
  } else {
    resolutionWrap.addClass('d-none');
    $(this).find('#existingResolutionLink').attr('href', '#');
  }
  $(this).find('#resolutionAttachment').val('');
  $(this).find('label[for="resolutionAttachment"]').text('Choose fixed-work photo or PDF');
});

$('#resolutionAttachment').on('change', function() {
  var name = this.files && this.files[0] ? this.files[0].name : 'Choose fixed-work photo or PDF';
  $(this).next('.custom-file-label').text(name);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
