<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
if (current_user()) redirect(current_user()['role'] === 'admin' ? 'admin/' : 'dashboard.php');

$email = ''; $error = '';
if (is_post()) {
    csrf_verify();
    $email = strtolower(post('email')); $pass = (string)($_POST['password'] ?? '');
    if (login_blocked($email)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE email = ?'); $st->execute([$email]); $u = $st->fetch();
        // constant-ish time: always verify against a hash
        $hash = $u['password'] ?? '$2y$10$usesomesillystringforsaltandotherwiseinvalidhashxxxxxxxxxxxxxxxx';
        if ($u && password_verify($pass, $hash) && $u['status'] === 'active') {
            if (password_needs_rehash($u['password'], PASSWORD_DEFAULT))
                db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            login_clear($email); login_user($u); audit('user.login', $email);
            $to = $_SESSION['after_login'] ?? ''; unset($_SESSION['after_login']);
            if ($u['role'] === 'admin') redirect('admin/');
            redirect($to && !preg_match('#(login|register)#', $to) ? $to : 'dashboard.php');
        }
        password_verify($pass, $hash);
        login_failed($email);
        $error = ($u && $u['status'] !== 'active') ? 'This account has been disabled. Please contact support.' : 'Incorrect email or password.';
    }
}
public_header('Log in', ['body' => 'auth', 'noindex' => 1]);
?>
<section class="auth-wrap"><div class="container auth-grid">
  <div class="auth-side">
    <h2>Welcome back</h2>
    <p>Track your investments, download certificates and see upcoming payouts.</p>
    <ul class="ticks light"><li>Investments &amp; returns at a glance</li><li>Download certificates anytime</li><li>Submit or update KYC securely</li></ul>
  </div>
  <form class="card form auth-card" method="post">
    <h1>Investor login</h1>
    <?= flashes() ?>
    <?php if ($error): ?><div class="alert alert-bad"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <div class="row-between"><span></span><a href="<?= e(url('forgot.php')) ?>" class="small">Forgot password?</a></div>
    <button class="btn btn-dark btn-block btn-lg">Log in</button>
    <p class="center muted">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
  </form>
</div></section>
<?php public_footer(); ?>
