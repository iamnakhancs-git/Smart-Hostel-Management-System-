<?php
require_once __DIR__ . '/../includes/enhancements.php';
requireLogin(ROLE_ADMIN);
require_once __DIR__ . '/../includes/notice_generator.php';
$ready = shmsReady(['notices']);
$error = '';
$draftMessage = '';
$draftKind = 'info';
$form = ['id' => 0, 'title' => '', 'content' => '', 'raw_information' => '', 'is_important' => 0, 'status' => 'draft'];
if ($ready) {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            shmsCheckPost();
            $action = shmsPost('action');
            if ($action === 'check_ollama') {
                try {
                    $config = shmsAIConfig();
                    if ($config['provider'] !== 'ollama') { throw new RuntimeException('Select provider ollama to check the local connection.'); }
                    setFlash('success', shmsCheckOllama($config));
                } catch (Throwable $e) {
                    setFlash('error', $e instanceof JsonException ? 'Ollama returned an unreadable model list.' : $e->getMessage());
                }
                header('Location: notices.php'); exit;
            }
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if ($id === false || $id < 0) { throw new InvalidArgumentException('Invalid notice.'); }
            if ($id && !shmsValue('SELECT COUNT(*) FROM notices WHERE id = ?', [$id])) {
                throw new InvalidArgumentException('Notice no longer exists.');
            }
            if ($action === 'generate' || $action === 'save') {
                $form = ['id' => $id, 'title' => shmsText('title', 200, false),
                    'content' => shmsText('content', 12000, false), 'raw_information' => shmsText('raw_information', 3000, false),
                    'is_important' => isset($_POST['is_important']) ? 1 : 0, 'status' => shmsPost('status', 'draft')];
                if ($action === 'generate') {
                    if ($form['raw_information'] === '') { throw new InvalidArgumentException('Enter short information first.'); }
                    if (time() - ($_SESSION['notice_generation_at'] ?? 0) < 10) {
                        throw new InvalidArgumentException('Please wait 10 seconds between draft requests.');
                    }
                    $_SESSION['notice_generation_at'] = time();
                    require_once __DIR__ . '/../includes/notice_generator.php';
                    $generated = shmsGenerateNotice($form['raw_information']);
                    $form['status'] = 'draft';
                    if (in_array($generated['source'], ['error', 'clarification'], true)) {
                        $draftKind = 'warning';
                        $draftMessage = ($generated['source'] === 'clarification' ? 'Clarification needed. ' : 'English translation not completed. ')
                            . $generated['reason'] . ' Your short information and any previous title/content are retained. No new draft was created or saved.';
                    } else {
                        $form['title'] = $generated['title'];
                        $form['content'] = $generated['content'];
                        $draftMessage = in_array($generated['source'], ['ai', 'ollama'], true)
                        ? ($generated['source'] === 'ollama' ? 'Offline AI draft is ready. ' : 'AI draft is ready. ') . 'Check every fact and edit before saving or publishing. Nothing has been saved yet.'
                        : 'Template draft (not AI-generated). ' . ($generated['reason'] ?? 'AI is not configured or unavailable.') . ' Review and edit the wording. Nothing has been saved yet.';
                    }
                } else {
                    if ($form['title'] === '' || $form['content'] === '') { throw new InvalidArgumentException('Title and notice content are required.'); }
                    if (!in_array($form['status'], ['draft', 'published'], true)) { throw new InvalidArgumentException('Invalid status.'); }
                    if ($id) {
                        shmsQuery("UPDATE notices SET title=?, content=?, raw_information=?, is_important=?,
                            published_at=CASE WHEN ?='published' THEN COALESCE(published_at,NOW()) ELSE NULL END, status=? WHERE id=?",
                            [$form['title'], $form['content'], $form['raw_information'], $form['is_important'], $form['status'], $form['status'], $id])->close();
                    } else {
                        shmsQuery("INSERT INTO notices (title,content,raw_information,is_important,status,created_by,published_at)
                            VALUES (?,?,?,?,?,?,CASE WHEN ?='published' THEN NOW() ELSE NULL END)",
                            [$form['title'], $form['content'], $form['raw_information'], $form['is_important'], $form['status'], (int)$_SESSION['user_id'], $form['status']])->close();
                    }
                    setFlash('success', $form['status'] === 'published' ? 'Notice published to residents.' : 'Draft saved. Residents cannot see it.');
                    header('Location: notices.php'); exit;
                }
            } elseif ($id && in_array($action, ['publish', 'unpublish', 'delete'], true)) {
                if ($action === 'delete') { shmsQuery('DELETE FROM notices WHERE id=?', [$id])->close(); }
                elseif ($action === 'publish') { shmsQuery("UPDATE notices SET status='published', published_at=COALESCE(published_at,NOW()) WHERE id=?", [$id])->close(); }
                else { shmsQuery("UPDATE notices SET status='draft', published_at=NULL WHERE id=?", [$id])->close(); }
                setFlash('success', 'Notice updated.'); header('Location: notices.php'); exit;
            } else { throw new InvalidArgumentException('Invalid action.'); }
        } elseif (isset($_GET['edit'])) {
            $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
            if (!$id || $id < 1) { throw new InvalidArgumentException('Invalid notice.'); }
            $rows = shmsRows('SELECT * FROM notices WHERE id=?', [$id]);
            if (!$rows) { throw new InvalidArgumentException('Notice not found.'); }
            $form = $rows[0];
        }
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (mysqli_sql_exception $e) { $error = 'The notice could not be saved. Check database setup and try again.'; }
}
shmsPageStart('Notice Board Management', ROLE_ADMIN);
if (!$ready) { shmsSetupMessage(); shmsPageEnd(); exit; }
?>
<div class="card"><div class="card-body">
  <form method="post" class="d-inline">
    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
    <button class="btn btn-outline-secondary btn-sm" name="action" value="check_ollama">Check Ollama Connection</button>
  </form>
  <span class="text-muted ml-2">For offline AI, keep Ollama running on the hostel computer. The first draft may take longer.</span>
</div></div>
<div class="row"><div class="col-lg-5"><div class="card card-primary">
  <div class="card-header"><h2 class="card-title"><?= $form['id'] ? 'Edit Notice' : 'Create Notice' ?></h2></div>
  <form method="post" id="notice-editor"><div class="card-body">
    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
    <div class="form-group"><label for="raw">Short information</label>
      <textarea id="raw" name="raw_information" class="form-control" rows="3" maxlength="3000" placeholder="Water supply raat 9 baje aaye gi"><?= h($form['raw_information']) ?></textarea>
      <small class="form-text text-muted">Roman Urdu is supported. Write complete dates and times, for example 16 April at 6 AM. Explain numbers such as “3 tak”.</small>
    </div>
    <button name="action" value="generate" class="btn btn-outline-primary mb-3" formnovalidate><i class="fas fa-magic mr-1"></i>Generate Professional Notice</button>
    <p id="generation-progress" class="text-muted" role="status" hidden>Generating your draft… please wait. On this computer it may take up to 3 minutes.</p>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
    <?php if ($draftMessage): ?><div class="alert alert-<?= h($draftKind) ?>" role="status"><?= h($draftMessage) ?></div><?php endif; ?>
    <div class="form-group"><label for="title">Notice title</label><input id="title" name="title" class="form-control" maxlength="200" value="<?= h($form['title']) ?>" required></div>
    <div class="form-group"><label for="content">Notice content (review and edit)</label><textarea id="content" name="content" class="form-control" rows="12" maxlength="12000" required><?= h($form['content']) ?></textarea></div>
    <div class="form-check mb-3"><input id="important" name="is_important" type="checkbox" class="form-check-input" <?= $form['is_important'] ? 'checked' : '' ?>><label class="form-check-label" for="important">Important announcement</label></div>
    <div class="form-group"><label for="status">Save as</label><select id="status" name="status" class="form-control"><option value="draft" <?= $form['status']==='draft' ? 'selected' : '' ?>>Draft — hidden from residents</option><option value="published" <?= $form['status']==='published' ? 'selected' : '' ?>>Published — visible to residents</option></select></div>
    <button name="action" value="save" class="btn btn-primary">Save Notice</button> <a href="notices.php" class="btn btn-default">Clear / New</a>
  </div></form>
</div></div><div class="col-lg-7">
<?php $notices = shmsRows('SELECT * FROM notices ORDER BY created_at DESC, id DESC'); ?>
<?php if (!$notices): ?><div class="card"><div class="card-body text-muted">No notices yet. Create your first draft.</div></div><?php endif; ?>
<?php foreach ($notices as $notice): ?>
<article class="card <?= $notice['is_important'] ? 'card-outline card-warning' : '' ?>">
  <div class="card-header"><h2 class="card-title"><?= h($notice['title']) ?></h2><div class="card-tools"><span class="badge badge-<?= $notice['status']==='published' ? 'success' : 'secondary' ?>"><?= h(ucfirst($notice['status'])) ?></span> <?= $notice['is_important'] ? '<span class="badge badge-warning">Important</span>' : '' ?></div></div>
  <div class="card-body"><div class="notice-content"><?= nl2br(h($notice['content'])) ?></div><small class="text-muted d-block mt-3">Created: <?= h($notice['created_at']) ?><?php if ($notice['published_at']): ?> · Published: <?= h($notice['published_at']) ?><?php endif; ?></small></div>
  <div class="card-footer"><a class="btn btn-sm btn-outline-primary" href="notices.php?edit=<?= (int)$notice['id'] ?>">Edit</a>
    <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>"><input type="hidden" name="id" value="<?= (int)$notice['id'] ?>">
      <button class="btn btn-sm btn-outline-secondary" name="action" value="<?= $notice['status']==='published' ? 'unpublish' : 'publish' ?>"><?= $notice['status']==='published' ? 'Unpublish' : 'Publish' ?></button>
      <button class="btn btn-sm btn-outline-danger" name="action" value="delete" onclick="return confirm('Delete this notice?')">Delete</button>
    </form>
  </div>
</article>
<?php endforeach; ?>
</div></div>
<script>
document.getElementById('notice-editor').addEventListener('submit', function (event) {
  if (this.dataset.busy === 'yes') { event.preventDefault(); return; }
  if (event.submitter && event.submitter.value === 'generate') {
    this.dataset.busy = 'yes';
    document.getElementById('generation-progress').hidden = false;
    // Keep submitter enabled so its action value is included in the POST.
    event.submitter.setAttribute('aria-disabled', 'true');
  }
});
window.addEventListener('pageshow', function () {
  document.getElementById('notice-editor').dataset.busy = '';
  document.getElementById('generation-progress').hidden = true;
  document.querySelector('[name="action"][value="generate"]').removeAttribute('aria-disabled');
});
</script>
<?php shmsPageEnd(); ?>
