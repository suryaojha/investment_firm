<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
if (current_user()) redirect('dashboard.php');

$v = ['name' => '', 'email' => '', 'phone' => '', 'city' => ''];
$errors = [];
if (is_post()) {
    csrf_verify();
    foreach ($v as $k => $_) $v[$k] = post($k);
    $pass = (string)($_POST['password'] ?? ''); $pass2 = (string)($_POST['password2'] ?? '');
    $v['email'] = strtolower($v['email']);
    if (post('website') !== '') exit; // honeypot
    if (mb_strlen($v['name']) < 3) $errors[] = 'Please enter your full name.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!valid_phone($v['phone'])) $errors[] = 'Enter a valid 10-digit Indian mobile number.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $pass2) $errors[] = 'Passwords do not match.';
    if (empty($_POST['terms'])) $errors[] = 'You must accept the Terms & Conditions.';
    if (!$errors) {
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?'); $st->execute([$v['email']]);
        if ((int)$st->fetchColumn()) $errors[] = 'An account with this email already exists. Try logging in.';
    }
    if (!$errors) {
        db()->prepare('INSERT INTO users (name,email,phone,password,role,status,city,kyc_status,created_at) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$v['name'], $v['email'], $v['phone'], password_hash($pass, PASSWORD_DEFAULT), 'user', 'active', $v['city'], 'none', now()]);
        $id = (int)db()->lastInsertId();
        $st = db()->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$id]); $u = $st->fetch();
        login_user($u);
        audit('user.register', $v['email']);
        send_mail($u['email'], 'Welcome to ' . setting('site_name'),
            '<p>Hi ' . e($u['name']) . ',</p><p>Your investor account is ready. You can now choose an investment plan and invest securely.</p><p><a href="' . e(site_url('invest.php')) . '" style="display:inline-block;background:' . e(setting('dark_color')) . ';color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none">Start investing</a></p>', [], 'Welcome aboard');
        if ($n = trim(setting('notify_email'))) send_mail($n, 'New investor signup', '<p>' . e($u['name']) . ' (' . e($u['email']) . ', ' . e($u['phone']) . ') just registered.</p>');
        $to = $_SESSION['after_login'] ?? ''; unset($_SESSION['after_login']);
        flash('ok', 'Welcome! Your account has been created.');
        redirect($to && strpos($to, 'login') === false && strpos($to, 'register') === false ? $to : 'invest.php');
    }
}
public_header('Create account', ['body' => 'auth', 'noindex' => 1]);
?>
<section class="auth-wrap"><div class="container auth-grid">
  <div class="auth-side">
    <h2>Become an investor</h2>
    <p>Join the ₹15,000 Cr rental development. It takes under two minutes.</p>
    <ul class="ticks light"><li>Instant PDF investment certificate</li><li>Transaction ID emailed to you</li><li>Live dashboard &amp; payout schedule</li><li>Encrypted, private KYC</li></ul>
  </div>
  <form class="card form auth-card" method="post" autocomplete="on" novalidate>
    <h1>Create your account</h1>
    <?php foreach ($errors as $er): ?><div class="alert alert-bad"><?= e($er) ?></div><?php endforeach; ?>
    <?= flashes() ?>
    <?= csrf_field() ?>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label>Full name (as per PAN)<input name="name" value="<?= e($v['name']) ?>" required autocomplete="name"></label>
    <div class="grid2">
      <label>Email<input type="email" name="email" value="<?= e($v['email']) ?>" required autocomplete="email"></label>
      <label>Mobile number<input name="phone" value="<?= e($v['phone']) ?>" inputmode="numeric" maxlength="10" required autocomplete="tel-national" placeholder="10-digit number"></label>
    </div>
    <label>City<input name="city" value="<?= e($v['city']) ?>" autocomplete="address-level2"></label>
    <div class="grid2">
      <label>Password<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
      <label>Confirm password<input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
    </div>
    <label class="check"><input type="checkbox" name="terms" value="1" required> I agree to the <a href="<?= e(url('page.php?slug=terms')) ?>" target="_blank">Terms &amp; Conditions</a> and <a href="<?= e(url('page.php?slug=privacy')) ?>" target="_blank">Privacy Policy</a>.</label>
    <button class="btn btn-gold btn-block btn-lg">Create account</button>
    <p class="center muted">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
  </form>
</div></section>
<?php public_footer(); ?>
