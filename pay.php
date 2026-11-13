<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$user = require_login();
$inv = get_investment((int)get('id'));
if (!$inv || (int)$inv['user_id'] !== (int)$user['id']) { http_response_code(404); exit('Investment not found.'); }
if ($inv['status'] === 'confirmed') { flash('ok', 'This investment is already confirmed.'); redirect('dashboard.php'); }
if (in_array($inv['status'], ['rejected', 'cancelled'], true)) { flash('bad', 'This investment is ' . $inv['status'] . '.'); redirect('dashboard.php'); }
$plan = db()->prepare('SELECT * FROM plans WHERE id = ?'); $plan->execute([$inv['plan_id']]); $plan = $plan->fetch() ?: ['name' => 'Plan'];
$errors = [];

if (is_post()) {
    csrf_verify();
    $act = post('action');
    if ($act === 'cancel' && $inv['status'] === 'pending') {
        db()->prepare("UPDATE investments SET status='cancelled' WHERE id=?")->execute([$inv['id']]);
        flash('info', 'Investment cancelled.'); redirect('dashboard.php');
    }
    if ($act === 'demo' && $inv['method'] === 'demo' && setting('pay_demo') === '1') {
        confirm_investment((int)$inv['id'], 'DEMO' . strtoupper(bin2hex(random_bytes(4))));
        flash('ok', 'Demo payment successful — certificate emailed.'); redirect('dashboard.php?paid=' . $inv['id']);
    }
    if ($act === 'utr' && $inv['method'] === 'manual') {
        $utr = strtoupper(preg_replace('/\s+/', '', post('utr')));
        if (!preg_match('/^[A-Z0-9]{8,30}$/', $utr)) $errors[] = 'Enter a valid UTR / transaction reference (8–30 letters or digits).';
        $dup = db()->prepare("SELECT COUNT(*) FROM investments WHERE txn_id = ? AND id <> ?"); $dup->execute([$utr, $inv['id']]);
        if ((int)$dup->fetchColumn()) $errors[] = 'This UTR has already been submitted.';
        $proof = null;
        if (!$errors) { try { $proof = save_upload('proof', 'storage/proofs', ['jpg', 'jpeg', 'png', 'pdf'], 5); } catch (Throwable $e) { $errors[] = $e->getMessage(); } }
        if (!$errors) {
            db()->prepare("UPDATE investments SET status='submitted', txn_id=?, proof_file=COALESCE(?, proof_file), note=? WHERE id=?")->execute([$utr, $proof, mb_substr(post('note'), 0, 250), $inv['id']]);
            audit('investment.utr_submitted', $inv['ref_no'] . ' ' . $utr);
            if ($n = trim(setting('notify_email'))) send_mail($n, 'Payment to verify — ' . $inv['ref_no'], '<p>' . e($user['name']) . ' submitted UTR <strong>' . e($utr) . '</strong> for ' . inr($inv['amount']) . ' (' . e($inv['ref_no']) . '). Verify in Admin → Investments.</p>');
            send_mail($user['email'], 'Payment details received — ' . $inv['ref_no'], '<p>Hi ' . e($user['name']) . ',</p><p>We received your UTR <strong>' . e($utr) . '</strong> for <strong>' . inr($inv['amount']) . '</strong>. Our team will verify it shortly and email your certificate and transaction ID.</p>');
            flash('ok', 'Thank you! We have received your payment details and will verify them shortly.');
            redirect('dashboard.php');
        }
    }
}

$rzp = null;
if ($inv['method'] === 'razorpay') {
    try {
        $orderId = $inv['gateway_order_id'] ?: rzp_create_order($inv)['id']; // reuse the order on page reloads
        $rzp = ['key' => setting('razorpay_key_id'), 'order' => $orderId, 'amount' => (int)round($inv['amount'] * 100)];
    } catch (Throwable $e) { error_log('[RZP] ' . $e->getMessage()); $errors[] = 'We could not start the online payment. Please try again or contact support.'; }
}
public_header('Complete payment', ['body' => 'app', 'noindex' => 1]);
?>
<section class="page-hero"><div class="container"><h1>Complete your payment</h1><p class="muted-l">Reference <b><?= e($inv['ref_no']) ?></b> · <?= e($plan['name']) ?> plan</p></div></section>
<section class="section"><div class="container invest-grid">
  <div class="card form">
    <?= flashes() ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-bad"><?= e($er) ?></div><?php endforeach; ?>
    <div class="pay-amount"><span>Amount payable</span><b><?= inr($inv['amount'], 2) ?></b></div>

    <?php if ($inv['method'] === 'razorpay' && $rzp): ?>
      <p>Pay securely with UPI, cards or netbanking. Your investment is confirmed automatically as soon as the payment succeeds.</p>
      <button type="button" id="rzp-btn" class="btn btn-gold btn-block btn-lg">Pay <?= inr($inv['amount']) ?> securely</button>
      <form id="rzp-form" method="post" action="<?= e(url('payment_callback.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="investment_id" value="<?= (int)$inv['id'] ?>">
        <input type="hidden" name="razorpay_payment_id" id="rp-pid"><input type="hidden" name="razorpay_order_id" id="rp-oid"><input type="hidden" name="razorpay_signature" id="rp-sig">
      </form>
      <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
      <script>
      document.getElementById('rzp-btn').onclick = function () {
        var rz = new Razorpay({
          key: <?= json_encode($rzp['key']) ?>, amount: <?= (int)$rzp['amount'] ?>, currency: 'INR', order_id: <?= json_encode($rzp['order']) ?>,
          name: <?= json_encode(setting('site_name')) ?>, description: <?= json_encode('Investment ' . $inv['ref_no']) ?>,
          prefill: { name: <?= json_encode($user['name']) ?>, email: <?= json_encode($user['email']) ?>, contact: <?= json_encode($user['phone']) ?> },
          theme: { color: <?= json_encode(setting('dark_color')) ?> },
          handler: function (r) {
            document.getElementById('rp-pid').value = r.razorpay_payment_id;
            document.getElementById('rp-oid').value = r.razorpay_order_id;
            document.getElementById('rp-sig').value = r.razorpay_signature;
            document.getElementById('rzp-form').submit();
          }
        });
        rz.open();
      };
      </script>

    <?php elseif ($inv['method'] === 'manual'): ?>
      <?php if ($inv['status'] === 'submitted'): ?>
        <div class="alert alert-info">We have your UTR <b><?= e($inv['txn_id']) ?></b> and it is awaiting verification. You'll get an email once confirmed.</div>
      <?php else: ?>
      <h3>Step 1 — Transfer the amount</h3>
      <div class="bank-box">
        <?php if (setting('upi_qr')): ?><img class="qr" src="<?= e(media(setting('upi_qr'))) ?>" alt="UPI QR"><?php endif; ?>
        <dl>
          <?php foreach (['upi_id' => 'UPI ID', 'bank_holder' => 'Account name', 'bank_name' => 'Bank', 'bank_account' => 'Account number', 'bank_ifsc' => 'IFSC'] as $k => $label) if (trim(setting($k))): ?>
            <dt><?= e($label) ?></dt><dd><?= e(setting($k)) ?></dd>
          <?php endif; ?>
          <dt>Amount</dt><dd><b><?= inr($inv['amount'], 2) ?></b></dd><dt>Remark</dt><dd><?= e($inv['ref_no']) ?></dd>
        </dl>
      </div>
      <h3>Step 2 — Submit your UTR / transaction ID</h3>
      <form method="post" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?><input type="hidden" name="action" value="utr">
        <label>UTR / Transaction reference<input name="utr" required maxlength="30" placeholder="e.g. 412345678901" value="<?= e(post('utr')) ?>"></label>
        <label>Payment screenshot / receipt (optional, JPG/PNG/PDF)<input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf"></label>
        <label>Note (optional)<input name="note" maxlength="250"></label>
        <button class="btn btn-gold btn-block btn-lg">Submit payment details</button>
      </form>
      <?php endif; ?>

    <?php elseif ($inv['method'] === 'demo' && setting('pay_demo') === '1'): ?>
      <div class="alert alert-info"><b>DEMO MODE.</b> No real money moves. This simulates a successful payment so you can test the certificate &amp; email flow.</div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="demo"><button class="btn btn-gold btn-block btn-lg">Simulate successful payment</button></form>
    <?php endif; ?>

    <?php if ($inv['status'] === 'pending'): ?>
    <form method="post" class="center" onsubmit="return confirm('Cancel this investment?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="link-btn">Cancel this investment</button></form>
    <?php endif; ?>
  </div>
  <aside class="card summary">
    <h3>Investment summary</h3>
    <div class="co"><span>Plan</span><b><?= e($plan['name']) ?></b></div>
    <div class="co"><span>Annual return</span><b><?= e(rtrim(rtrim(number_format((float)$inv['roi'], 2), '0'), '.')) ?>%</b></div>
    <div class="co"><span>Tenure</span><b><?= (int)$inv['tenure_months'] ?> months</b></div>
    <div class="co"><span>Total projected return</span><b><?= inr($inv['expected_return']) ?></b></div>
    <div class="secure-note">📄 After confirmation you'll receive your PDF certificate and Transaction ID on <b><?= e($user['email']) ?></b>.</div>
  </aside>
</div></section>
<?php public_footer(); ?>
