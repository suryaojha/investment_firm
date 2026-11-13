<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = current_user();
if ($u && $u['role'] === 'admin') redirect('admin/');
$error = ''; $email = '';
if (is_post()) {
    csrf_verify();
    $email = strtolower(post('email')); $pass = (string)($_POST['password'] ?? '');
    if (login_blocked($email)) $error = 'Too many failed attempts. Try again in 15 minutes.';
    else {
        $st = db()->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'active'"); $st->execute([$email]); $a = $st->fetch();
        $hash = $a['password'] ?? '$2y$10$usesomesillystringforsaltandotherwiseinvalidhashxxxxxxxxxxxxxxxx';
        if ($a && password_verify($pass, $hash)) { login_clear($email); login_user($a); audit('admin.login', $email); redirect('admin/'); }
        login_failed($email); $error = 'Invalid admin credentials.';
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Admin login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"></head>
<body class="install-body">
<form class="install-card" method="post" style="max-width:420px">
  <h1>🔐 Admin login</h1><p class="muted"><?= e(setting('site_name')) ?></p>
  <?= flashes() ?>
  <?php if ($error): ?><div class="alert alert-bad"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Admin ID / email<input type="text" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
  <label>Password<input type="password" name="password" required></label>
  <button class="btn btn-primary btn-block">Log in</button>
  <p class="center small"><a href="<?= e(url('')) ?>/">← Back to website</a></p>
</form></body></html>
