<?php
require_once __DIR__ . '/../includes/enhancements.php';
requireLogin(ROLE_RESIDENT);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit('Read-only notice board.'); }
shmsPageStart('Notice Board', ROLE_RESIDENT);
if (!shmsReady(['notices'])) { echo '<div class="alert alert-info">The notice board is being set up. Please check again later.</div>'; shmsPageEnd(); exit; }
$notices = shmsRows("SELECT title, content, is_important, published_at FROM notices WHERE status='published' AND published_at IS NOT NULL AND published_at <= NOW() ORDER BY is_important DESC, published_at DESC, id DESC");
?>
<p class="text-muted">Updates and announcements from hostel administration.</p>
<?php if (!$notices): ?><div class="card"><div class="card-body text-center text-muted py-5">No published notices yet.</div></div><?php endif; ?>
<div class="row">
<?php foreach ($notices as $notice): ?>
  <div class="col-lg-6"><article class="card h-100 <?= $notice['is_important'] ? 'card-outline card-warning' : 'card-outline card-primary' ?>">
    <div class="card-header"><h2 class="card-title"><?= h($notice['title']) ?></h2><?php if ($notice['is_important']): ?><span class="badge badge-warning float-right">Important</span><?php endif; ?></div>
    <div class="card-body notice-content"><?= nl2br(h($notice['content'])) ?></div>
    <div class="card-footer text-muted"><i class="far fa-clock mr-1"></i><?= h(date(DATETIME_FORMAT, strtotime($notice['published_at']))) ?></div>
  </article><div class="mb-3"></div></div>
<?php endforeach; ?>
</div>
<?php shmsPageEnd(); ?>
