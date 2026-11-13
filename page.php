<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$st = db()->prepare('SELECT * FROM pages WHERE slug = ? AND is_active = 1'); $st->execute([get('slug')]); $pg = $st->fetch();
if (!$pg) { http_response_code(404); public_header('Not found'); echo '<section class="section"><div class="container narrow center"><h1>Page not found</h1><p><a href="' . e(url('')) . '/">Back to home</a></p></div></section>'; public_footer(); exit; }
public_header($pg['title']);
?>
<section class="page-hero"><div class="container"><h1><?= e($pg['title']) ?></h1><p class="muted-l">Last updated <?= e(dt($pg['updated_at'])) ?></p></div></section>
<section class="section"><div class="container narrow prose"><?= clean_html((string)$pg['content']) ?></div></section>
<?php public_footer(); ?>
