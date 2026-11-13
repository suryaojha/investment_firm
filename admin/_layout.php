<?php
require_once dirname(__DIR__) . '/inc/bootstrap.php';
require_once dirname(__DIR__) . '/inc/layout.php';
ob_start();
$ADMIN = require_admin();
if (!empty($ADMIN['must_change_pw']) && basename($_SERVER['SCRIPT_NAME']) !== 'password.php') redirect('admin/password.php');

function admin_nav(): array {
    return [
        'Overview' => [['index.php', '📊', 'Dashboard']],
        'Money' => [['investments.php', '💼', 'Investments'], ['payouts.php', '💸', 'Payouts'], ['users.php', '👥', 'Investors & KYC']],
        'Website content' => [
            ['settings.php', '⚙️', 'Settings & Branding'], ['plans.php', '🏷️', 'Plans'], ['projects.php', '🏗️', 'Projects'],
            ['videos.php', '🎬', 'Videos'], ['documents.php', '📄', 'Documents / PDFs'],
            ['items.php?type=gallery', '🖼️', 'Photo gallery'], ['items.php?type=feature', '✨', 'Why-invest cards'], ['items.php?type=stat', '🔢', 'Stats strip'], ['items.php?type=how', '🪜', 'How it works'],
            ['items.php?type=timeline', '🗓️', 'Timeline'], ['items.php?type=testimonial', '💬', 'Testimonials'], ['items.php?type=faq', '❓', 'FAQ'], ['pages.php', '📝', 'Pages (Terms…)'],
        ],
        'Inbox' => [['messages.php', '✉️', 'Enquiries']],
    ];
}

function admin_header(string $title): void {
    global $ADMIN;
    $cur = basename($_SERVER['SCRIPT_NAME']) . (isset($_GET['type']) ? '?type=' . $_GET['type'] : '');
    $unread = (int)db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
    $pendInv = (int)db()->query("SELECT COUNT(*) FROM investments WHERE status = 'submitted'")->fetchColumn();
    $pendKyc = (int)db()->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'submitted'")->fetchColumn();
    $badge = ['messages.php' => $unread, 'investments.php' => $pendInv, 'users.php' => $pendKyc];
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>?v=1">
<style><?= theme_css_admin() ?></style></head>
<body class="admin">
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">◆ <?= e(setting('site_name')) ?><small>Admin</small></div>
  <?php foreach (admin_nav() as $group => $links): ?>
    <div class="sb-group"><?= e($group) ?></div>
    <?php foreach ($links as [$href, $ico, $label]): $key = basename(parse_url($href, PHP_URL_PATH)); $isCur = ($href === $cur) || (strpos($href, '?') === false && $key === basename($_SERVER['SCRIPT_NAME'])); ?>
      <a class="<?= $isCur ? 'on' : '' ?>" href="<?= e(url('admin/' . $href)) ?>"><span><?= $ico ?></span><?= e($label) ?><?php if (!empty($badge[$href])): ?><i><?= (int)$badge[$href] ?></i><?php endif; ?></a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="sb-group">Quick</div>
  <a href="<?= e(url('')) ?>" target="_blank"><span>🌐</span>View website ↗</a>
  <a href="<?= e(url('admin/password.php')) ?>"><span>🔑</span>Change password</a>
  <a href="<?= e(url('logout.php')) ?>"><span>🚪</span>Log out</a>
</aside>
<div class="content">
  <header class="topbar">
    <button class="burger" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menu">☰</button>
    <h1><?= e($title) ?></h1>
    <div class="who">👤 <?= e($ADMIN['name']) ?></div>
  </header>
  <div class="page">
  <?php if (setting('pay_demo') === '1'): ?><div class="alert alert-bad">⚠ DEMO payments are ON — fake payments can be made. Turn this off in Settings → Payments before going live.</div><?php endif; ?>
  <?= flashes() ?>
<?php
}
function theme_css_admin(): string {
    $g = preg_match('/^#[0-9a-fA-F]{6}$/', setting('primary_color')) ? setting('primary_color') : '#c8a24a';
    $d = preg_match('/^#[0-9a-fA-F]{6}$/', setting('dark_color')) ? setting('dark_color') : '#0a1930';
    return ":root{--gold:$g;--dark:$d}";
}
function admin_footer(): void { ?>
  </div>
</div>
<script src="<?= e(asset('js/admin.js')) ?>?v=1" defer></script>
</body></html>
<?php }
