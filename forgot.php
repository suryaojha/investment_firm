<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$sent = false;
if (is_post()) {
    csrf_verify();
    $email = strtolower(post('email'));
    $st = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'"); $st->execute([$email]); $u = $st->fetch();
    if ($u && !login_blocked($email)) {
        $token = bin2hex(random_bytes(24));
        db()->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')->execute([hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600), $u['id']]);
        $link = site_url('reset.php?token=' . $token);
        send_mail($u['email'], 'Reset your password', '<p>Hi ' . e($u['name']) . ',</p><p>Click the button to choose a new password. The link is valid for 1 hour.</p><p><a href="' . e($link) . '" style="display:inline-block;background:' . e(setting('dark_color')) . ';color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none">Reset password</a></p><p>If you did not request this, you can ignore this email.</p>', [], 'Password reset');
    }
    login_failed($email); // throttles reset spam too
    $sent = true; // same answer whether or not the email exists
}
public_header('Forgot password', ['body' => 'auth', 'noindex' => 1]);
?>
<section class="auth-wrap"><div class="container"><form class="card form auth-card single" method="post">
  <h1>Forgot password</h1>
  <?php if ($sent): ?><div class="alert alert-ok">If that email is registered, a reset link has been sent. Check your inbox (and spam folder).</div>
  <?php else: ?>
  <p class="muted">Enter your registered email and we'll send you a reset link.</p>
  <?= csrf_field() ?>
  <label>Email<input type="email" name="email" required autofocus></label>
  <button class="btn btn-dark btn-block">Send reset link</button>
  <?php endif; ?>
  <p class="center"><a href="<?= e(url('login.php')) ?>">← Back to login</a></p>
</form></div></section>
<?php public_footer(); ?>
