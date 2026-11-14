<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$user = require_login();
$plans = db()->query('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$methods = enabled_methods();
$selId = (int)(is_post() ? post('plan_id') : get('plan'));
$sel = null; foreach ($plans as $p) if ((int)$p['id'] === $selId) $sel = $p;
$sel = $sel ?: ($plans[1] ?? ($plans[0] ?? null));
$errors = []; $walletBalance = (float)$user['wallet_balance'];

if (is_post() && $sel) {
    csrf_verify();
    $amount = round((float)preg_replace('/[^0-9.]/', '', post('amount')), 2);
    $method = post('method');
    $min = max((float)$sel['min_amount'], (float)setting('min_invest', 0)); $max = (float)$sel['max_amount'];
    if ($amount < $min) $errors[] = 'Minimum investment for ' . $sel['name'] . ' is ' . inr($min) . '.';
    if ($max > 0 && $amount > $max) $errors[] = 'Maximum investment for ' . $sel['name'] . ' is ' . inr($max) . '. Please choose a higher plan.';
    $walletUsed = isset($_POST['use_wallet']) ? min($walletBalance, $amount) : 0;
    if ($amount > 100000000) $errors[] = 'Please contact us directly for investments above ₹10 Cr.';
    if (!isset($methods[$method])) $errors[] = 'Please choose a payment method.';
    if (empty($_POST['terms'])) $errors[] = 'Please accept the Terms & Conditions to continue.';
    if (!$errors) {
        $id = create_investment($user, $sel, $amount, $method, $walletUsed);
        audit('investment.created', "#$id $amount $method");
        redirect('pay.php?id=' . $id);
    }
}
$amountVal = post('amount') ?: ($sel ? (string)max((float)$sel['min_amount'], (float)setting('min_invest')) : '');
public_header('Invest', ['body' => 'app', 'noindex' => 1]);
?>
<section class="page-hero"><div class="container"><h1>Make an investment</h1><p class="muted-l">Choose a plan, enter an amount and pay securely. Your certificate and transaction ID will be emailed instantly.</p></div></section>
<section class="section"><div class="container invest-grid">
<?php if (!$plans): ?><p>No plans are open right now. Please check back soon.</p><?php else: ?>
  <form method="post" class="card form" id="invest-form">
    <?= csrf_field() ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-bad"><?= e($er) ?></div><?php endforeach; ?>
    <?= flashes() ?>
    <h3>1. Select plan</h3>
    <div class="plan-pick">
      <?php foreach ($plans as $p): ?>
      <label class="pp <?= $sel && $p['id'] == $sel['id'] ? 'on' : '' ?>">
        <input type="radio" name="plan_id" value="<?= (int)$p['id'] ?>" <?= $sel && $p['id'] == $sel['id'] ? 'checked' : '' ?>
          data-min="<?= e(max((float)$p['min_amount'], (float)setting('min_invest'))) ?>" data-max="<?= e($p['max_amount']) ?>" data-roi="<?= e(plan_total_roi($p)) ?>" data-months="<?= (int)$p['tenure_months'] ?>" data-mode="<?= e($p['payout_mode']) ?>">
        <b><?= e($p['name']) ?></b><span><?= rtrim(rtrim(number_format(plan_total_roi($p), 2), '0'), '.') ?>% p.a. · <?= (int)$p['tenure_months'] ?> mo</span><small>from <?= inr_short(max((float)$p['min_amount'], (float)setting('min_invest'))) ?></small>
      </label>
      <?php endforeach; ?>
    </div>
    <h3>2. Amount</h3>
    <label>Investment amount (₹)<input type="number" name="amount" id="amount" value="<?= e($amountVal) ?>" min="1" step="1" inputmode="numeric" required></label>
    <div class="chips" id="chips"></div>
    <?php if ($walletBalance > 0): ?><label class="check"><input type="checkbox" name="use_wallet" value="1" <?= isset($_POST['use_wallet']) ? 'checked' : '' ?>> Apply referral wallet credit (available: <?= inr($walletBalance, 2) ?>). Wallet credit is only for plan purchases.</label><?php endif; ?>
    <h3>3. Payment method</h3>
    <?php if (!$methods): ?><div class="alert alert-bad">Payments are not configured yet. Please contact support.</div><?php endif; ?>
    <?php foreach ($methods as $k => $label): ?>
      <label class="radio card-radio"><input type="radio" name="method" value="<?= e($k) ?>" <?= post('method') === $k || (!post('method') && array_key_first($methods) === $k) ? 'checked' : '' ?>> <?= e($label) ?></label>
    <?php endforeach; ?>
    <label class="check"><input type="checkbox" name="terms" value="1" required> I have read and accept the <a href="<?= e(url('page.php?slug=terms')) ?>" target="_blank">Terms &amp; Conditions</a> and <a href="<?= e(url('page.php?slug=risk-disclosure')) ?>" target="_blank">Risk Disclosure</a>.</label>
    <button class="btn btn-gold btn-block btn-lg" <?= $methods ? '' : 'disabled' ?>>Continue to payment →</button>
  </form>
  <aside class="card summary" id="summary">
    <h3>Your projected returns</h3>
    <div class="co"><span>Total projected return</span><b id="s-ret">—</b></div>
    <div class="co"><span id="s-pay-label">Each payout</span><b id="s-pay">—</b></div>
    <div class="co big"><span>Value at maturity</span><b id="s-total">—</b></div>
    <p class="muted small" id="s-note"></p>
    <p class="muted small"><?= e(setting('returns_disclaimer')) ?></p>
    <div class="secure-note">🔒 Your payment is processed securely. After payment you'll receive a PDF certificate and transaction ID by email.</div>
  </aside>
<?php endif; ?>
</div></section>
<?php public_footer(); ?>
