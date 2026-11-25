<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(ROLE_RESIDENT);
require_once __DIR__ . '/../includes/backend.php';
shmsBackendStart(ROLE_RESIDENT);

$conn      = getDBConnection();
$pageTitle = 'My Complaints';
$role      = ROLE_RESIDENT;
$rid       = (int)$_SESSION['user_id'];
$errors    = [];
$showForm  = isset($_GET['action']) && $_GET['action'] === 'new';

if (!shmsColumnExists('complaints', 'attachment_path') || !shmsColumnExists('complaints', 'resolution_attachment_path')) {
    shmsBackendError('Database setup is incomplete. Recreate the database using sql/shms_database.sql.', 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $subject = sanitize($_POST['subject']     ?? '');
    $desc    = sanitize($_POST['description'] ?? '');

    $attachmentPath = null;
    $attachmentName = null;
    $attachmentMime = null;
    $attachmentFile = $_FILES['attachment'] ?? null;

    if (!$subject || !$desc) {
        $errors[] = 'Subject and description are required.';
        $showForm = true;
    }

    if (!$errors && $attachmentFile && ($attachmentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($attachmentFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Attachment upload failed. Please choose the file again.';
        } elseif ((int)$attachmentFile['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Attachment must not exceed 5 MB.';
        } elseif (!is_uploaded_file($attachmentFile['tmp_name'])) {
            $errors[] = 'Invalid attachment upload.';
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? finfo_file($finfo, $attachmentFile['tmp_name']) : false;
            if ($finfo) { finfo_close($finfo); }
            if (!$detectedMime || !isset($allowedTypes[$detectedMime])) {
                $errors[] = 'Only JPG, PNG, WEBP or PDF attachments are allowed.';
            } else {
                $attachmentMime = $detectedMime;
                $attachmentName = substr(basename((string)$attachmentFile['name']), 0, 255);
                $storedName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$detectedMime];
                $uploadDir = __DIR__ . '/../assets/uploads/complaints';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $errors[] = 'Attachment storage directory could not be created.';
                } else {
                    $attachmentPath = 'assets/uploads/complaints/' . $storedName;
                    if (!move_uploaded_file($attachmentFile['tmp_name'], $uploadDir . '/' . $storedName)) {
                        $attachmentPath = $attachmentName = $attachmentMime = null;
                        $errors[] = 'Attachment could not be saved.';
                    }
                }
            }
        }
    }

    if ($errors) {
        $showForm = true;
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO complaints (resident_id,subject,description,attachment_path,attachment_name,attachment_mime) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('isssss',$rid,$subject,$desc,$attachmentPath,$attachmentName,$attachmentMime);
            $stmt->execute(); $stmt->close();
        } catch (Throwable $e) {
            if ($attachmentPath) {
                $savedFile = __DIR__ . '/../' . $attachmentPath;
                if (is_file($savedFile)) { unlink($savedFile); }
            }
            throw $e;
        }
        setFlash('success','Complaint submitted successfully.');
        header('Location: complaints.php'); exit;
    }
}

$complaints = $conn->query(
    "SELECT * FROM complaints WHERE resident_id=$rid ORDER BY created_at DESC"
);

$csrf = generateCSRF();
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_resident.php';
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1><i class="fas fa-exclamation-circle mr-2"></i>My Complaints</h1></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Complaints</li>
        </ol>
      </div>
    </div>
  </div></div>
  <section class="content"><div class="container-fluid">
    <?php showFlash(); foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

    <!-- Submit New Complaint -->
    <?php if ($showForm): ?>
    <div class="card card-warning">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-plus mr-2"></i>Submit New Complaint</h3></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="card-body">
          <div class="form-group">
            <label>Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" class="form-control" required
                   value="<?= htmlspecialchars($_POST['subject']??'') ?>" placeholder="Brief description of issue">
          </div>
          <div class="form-group">
            <label>Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-control" rows="4" required
                      placeholder="Describe your complaint in detail..."><?= htmlspecialchars($_POST['description']??'') ?></textarea>
          </div>
          <div class="form-group">
            <label>Picture or Document <small class="text-muted">(optional)</small></label>
            <div class="custom-file">
              <input type="file" name="attachment" id="complaintAttachment" class="custom-file-input"
                     accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
              <label class="custom-file-label" for="complaintAttachment">Choose JPG, PNG, WEBP or PDF</label>
            </div>
            <small class="form-text text-muted">Maximum file size: 5 MB.</small>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane mr-1"></i>Submit Complaint</button>
          <a href="complaints.php" class="btn btn-secondary ml-2">Cancel</a>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="mb-3">
      <a href="complaints.php?action=new" class="btn btn-warning">
        <i class="fas fa-plus mr-1"></i>Submit New Complaint
      </a>
    </div>
    <?php endif; ?>

    <!-- Complaints List -->
    <div class="card"><div class="card-body p-0">
      <table class="table table-hover data-table">
        <thead><tr><th>#</th><th>Subject</th><th>Status</th><th>Admin Remark</th><th>Files</th><th>Date</th></tr></thead>
        <tbody>
        <?php $i=1; while ($c=$complaints->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($c['subject']) ?></td>
            <td><?= statusBadge($c['status']) ?></td>
            <td><?= htmlspecialchars($c['admin_remark'] ?: '—') ?></td>
            <td>
              <?php if ($c['attachment_path']): ?>
                <a class="btn btn-xs btn-outline-primary" target="_blank" rel="noopener"
                   href="../<?= htmlspecialchars($c['attachment_path']) ?>">
                  <i class="fas fa-paperclip"></i> My File
                </a>
              <?php endif; ?>
              <?php if ($c['resolution_attachment_path']): ?>
                <a class="btn btn-xs btn-outline-success" target="_blank" rel="noopener"
                   href="../<?= htmlspecialchars($c['resolution_attachment_path']) ?>">
                  <i class="fas fa-check-circle"></i> Resolution Proof
                </a>
              <?php endif; ?>
              <?php if (!$c['attachment_path'] && !$c['resolution_attachment_path']): ?>—<?php endif; ?>
            </td>
            <td><?= date('d M Y',strtotime($c['created_at'])) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div></div>
</div></section>
</div>
<script>
$('#complaintAttachment').on('change', function() {
  var name = this.files && this.files[0] ? this.files[0].name : 'Choose JPG, PNG, WEBP or PDF';
  $(this).next('.custom-file-label').text(name);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
