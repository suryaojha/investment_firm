<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$token = get('token') ?: post('token');
$u = null;
if (preg_match('/^[a-f0-9]{48}$/', $token)) {
    $st = db()->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expires > ?');
    $st->execute([hash('sha256', $token), now()]);
    $u = $st->fetch() ?: null;
}
$error = '';
if ($u && is_post()) {
    csrf_verify();
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if (strlen($p1) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($p1 !== $p2) $error = 'Passwords do not match.';
    else {
        db()->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')->execute([password_hash($p1, PASSWORD_DEFAULT), $u['id']]);
        audit('user.password_reset', $u['email']);
        flash('ok', 'Password updated. You can log in now.');
        redirect('login.php');
    }
}
public_header('Reset password', ['body' => 'auth', 'noindex' => 1]);
?>
<section class="auth-wrap"><div class="container"><form class="card form auth-card single" method="post">
  <h1>Choose a new password</h1>
  <?php if (!$u): ?><div class="alert alert-bad">This reset link is invalid or has expired.</div><p class="center"><a href="<?= e(url('forgot.php')) ?>">Request a new link</a></p>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-bad"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
    <label>Confirm password<input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
    <button class="btn btn-dark btn-block">Update password</button>
  <?php endif; ?>
</form></div></section>
<?php public_footer(); ?>
