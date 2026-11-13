<?php
require __DIR__ . '/_layout.php';
$pdo = db();
if (is_post()) {
    csrf_verify();
    $id = (int)post('id');
    if (post('action') === 'read') $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$id]);
    if (post('action') === 'readall') $pdo->exec('UPDATE messages SET is_read = 1');
    if (post('action') === 'delete') $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
    redirect('admin/messages.php');
}
$rows = $pdo->query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll();
admin_header('Enquiries');
?>
<div class="card">
  <div class="card-head"><h2>Contact-form enquiries <span class="count"><?= count($rows) ?></span></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="readall"><button class="btn btn-sm btn-ghost">Mark all read</button></form></div>
  <?php foreach ($rows as $m): ?>
  <div class="msg <?= $m['is_read'] ? '' : 'unread' ?>">
    <div class="msg-head"><b><?= e($m['name']) ?></b> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?><span><?= e(dt($m['created_at'], 'd M Y H:i')) ?></span></div>
    <p><?= nl2br(e($m['message'])) ?></p>
    <div class="btn-row">
      <?php if (!$m['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="read"><button class="btn btn-sm btn-ghost">Mark read</button></form><?php endif; ?>
      <a class="btn btn-sm btn-ghost" href="mailto:<?= e($m['email']) ?>?subject=Re: your enquiry">Reply</a>
      <form method="post" onsubmit="return confirm('Delete message?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger">Delete</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="muted">No enquiries yet.</p><?php endif; ?>
</div>
<?php admin_footer(); ?>
