<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$u = current_user();
$docs = db()->query('SELECT * FROM documents WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
public_header('Documents');
?>
<section class="page-hero"><div class="container"><h1>Documents &amp; disclosures</h1><p class="muted-l">Terms, approvals and project documents. Some documents are visible to registered investors only.</p></div></section>
<section class="section"><div class="container narrow">
  <?php if (!$docs): ?><p class="center muted">No documents have been published yet.</p><?php endif; ?>
  <div class="docs one">
  <?php foreach ($docs as $d): $locked = !$d['is_public'] && !$u; ?>
    <a class="doc" href="<?= e($locked ? url('login.php') : url('download.php?id=' . $d['id'])) ?>">
      <span class="doc-ico">PDF</span>
      <span><b><?= e($d['title']) ?></b><small><?= e($d['description']) ?><?= $d['category'] ? ' · ' . e($d['category']) : '' ?></small></span>
      <span class="doc-dl"><?= $locked ? '🔒 Log in' : '↓' ?></span>
    </a>
  <?php endforeach; ?>
  </div>
</div></section>
<?php public_footer(); ?>
