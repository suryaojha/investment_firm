<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$u = require_login();
$errors = [];
$locked = $u['kyc_status'] === 'approved' || $u['kyc_status'] === 'submitted';

if (is_post() && !$locked) {
    csrf_verify();
    $pan = strtoupper(preg_replace('/\s+/', '', post('pan')));
    $aad = preg_replace('/\D/', '', post('aadhaar'));
    $dob = post('dob'); $addr = mb_substr(post('address'), 0, 400);
    $bh = post('bank_holder'); $ba = preg_replace('/\D/', '', post('bank_account')); $bi = strtoupper(post('bank_ifsc'));
    if (!valid_pan($pan)) $errors[] = 'Enter a valid PAN (format ABCDE1234F).';
    if (!valid_aadhaar($aad)) $errors[] = 'Enter a valid 12-digit Aadhaar number.';
    if (!$dob || !strtotime($dob) || strtotime($dob) > strtotime('-18 years')) $errors[] = 'You must be at least 18 years old (enter your date of birth).';
    if (mb_strlen($addr) < 10) $errors[] = 'Enter your full residential address.';
    if ($bh === '' || strlen($ba) < 8 || !valid_ifsc($bi)) $errors[] = 'Enter valid bank details (holder name, account number, IFSC) for payouts.';
    if (empty($_POST['consent'])) $errors[] = 'Please give consent to store and verify your PAN & Aadhaar.';
    $panFile = $aadFile = null;
    if (!$errors) {
        try {
            $panFile = save_upload('pan_file', 'storage/kyc', ['jpg', 'jpeg', 'png', 'pdf'], 5);
            $aadFile = save_upload('aadhaar_file', 'storage/kyc', ['jpg', 'jpeg', 'png', 'pdf'], 5);
            if (!$panFile && !$u['pan_file']) $errors[] = 'Please upload a copy of your PAN card.';
            if (!$aadFile && !$u['aadhaar_file']) $errors[] = 'Please upload a copy of your Aadhaar card.';
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
    if (!$errors) {
        if ($panFile && $u['pan_file']) delete_file($u['pan_file']);
        if ($aadFile && $u['aadhaar_file']) delete_file($u['aadhaar_file']);
        db()->prepare("UPDATE users SET pan=?, aadhaar_enc=?, aadhaar_last4=?, dob=?, address=?, bank_holder=?, bank_account=?, bank_ifsc=?, nominee_name=?, nominee_relation=?,
            pan_file=COALESCE(?, pan_file), aadhaar_file=COALESCE(?, aadhaar_file), kyc_status='submitted', kyc_note=NULL, kyc_submitted_at=? WHERE id=?")
            ->execute([$pan, enc($aad), substr($aad, -4), $dob, $addr, $bh, $ba, $bi, mb_substr(post('nominee_name'), 0, 150), mb_substr(post('nominee_relation'), 0, 60), $panFile, $aadFile, now(), $u['id']]);
        audit('kyc.submitted', $u['email']);
        if ($n = trim(setting('notify_email'))) send_mail($n, 'KYC submitted — ' . $u['name'], '<p>' . e($u['name']) . ' submitted KYC documents. Review in Admin → Investors.</p>');
        send_mail($u['email'], 'KYC received', '<p>Hi ' . e($u['name']) . ',</p><p>We received your PAN &amp; Aadhaar details and will review them shortly. You will be notified once verified.</p>', [], 'KYC received');
        flash('ok', 'KYC submitted successfully. We will verify it shortly.');
        redirect('kyc.php');
    }
    $u = array_merge($u, ['pan' => $pan, 'dob' => $dob, 'address' => $addr, 'bank_holder' => $bh, 'bank_account' => $ba, 'bank_ifsc' => $bi, 'nominee_name' => post('nominee_name'), 'nominee_relation' => post('nominee_relation')]);
}
$u2 = current_user(); // fresh copy for status display
public_header('KYC verification', ['body' => 'app', 'noindex' => 1]);
?>
<section class="page-hero"><div class="container"><h1>KYC verification</h1><p class="muted-l">Submit your PAN &amp; Aadhaar to secure your investment and receive payouts without delay.</p></div></section>
<section class="section"><div class="container narrow">
  <?= flashes() ?>
  <div class="card kyc-status">
    <div>Status: <?= status_badge($u2['kyc_status']) ?></div>
    <?php if ($u2['kyc_status'] === 'rejected' && $u2['kyc_note']): ?><div class="alert alert-bad"><b>Reason:</b> <?= e($u2['kyc_note']) ?> — please correct and resubmit below.</div><?php endif; ?>
    <?php if ($u2['kyc_status'] === 'approved'): ?><p>✅ Your KYC is verified. PAN <b><?= e($u2['pan']) ?></b> · Aadhaar <b><?= e(kyc_masked_aadhaar($u2)) ?></b></p><?php endif; ?>
    <?php if ($u2['kyc_status'] === 'submitted'): ?><p>⏳ Your documents are under review. PAN <b><?= e($u2['pan']) ?></b> · Aadhaar <b><?= e(kyc_masked_aadhaar($u2)) ?></b></p><?php endif; ?>
  </div>
  <?php if (!$locked): ?>
  <form class="card form" method="post" enctype="multipart/form-data" autocomplete="off">
    <?= csrf_field() ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-bad"><?= e($er) ?></div><?php endforeach; ?>
    <h3>Identity</h3>
    <div class="grid2">
      <label>PAN number<input name="pan" value="<?= e($u['pan']) ?>" maxlength="10" style="text-transform:uppercase" required placeholder="ABCDE1234F"></label>
      <label>Aadhaar number<input name="aadhaar" inputmode="numeric" maxlength="14" required placeholder="1234 5678 9012"></label>
      <label>Date of birth<input type="date" name="dob" value="<?= e($u['dob']) ?>" required></label>
      <span></span>
      <label>PAN card copy (JPG/PNG/PDF, max 5 MB)<input type="file" name="pan_file" accept=".jpg,.jpeg,.png,.pdf" <?= $u['pan_file'] ? '' : 'required' ?>></label>
      <label>Aadhaar card copy (JPG/PNG/PDF, max 5 MB)<input type="file" name="aadhaar_file" accept=".jpg,.jpeg,.png,.pdf" <?= $u['aadhaar_file'] ? '' : 'required' ?>></label>
    </div>
    <label>Residential address<textarea name="address" rows="3" required><?= e($u['address']) ?></textarea></label>
    <h3>Bank account for payouts</h3>
    <div class="grid2">
      <label>Account holder name<input name="bank_holder" value="<?= e($u['bank_holder']) ?>" required></label>
      <label>Account number<input name="bank_account" value="<?= e($u['bank_account']) ?>" inputmode="numeric" required></label>
      <label>IFSC code<input name="bank_ifsc" value="<?= e($u['bank_ifsc']) ?>" maxlength="11" style="text-transform:uppercase" required></label>
    </div>
    <h3>Nominee (optional)</h3>
    <div class="grid2">
      <label>Nominee name<input name="nominee_name" value="<?= e($u['nominee_name']) ?>"></label>
      <label>Relationship<input name="nominee_relation" value="<?= e($u['nominee_relation']) ?>"></label>
    </div>
    <label class="check"><input type="checkbox" name="consent" value="1" required> I consent to <?= e(setting('site_name')) ?> collecting and verifying my PAN and Aadhaar for KYC and payout purposes. I understand my Aadhaar number is stored encrypted and only authorised staff can view my documents.</label>
    <button class="btn btn-gold btn-block btn-lg">Submit for verification</button>
  </form>
  <?php endif; ?>
  <p class="center"><a href="<?= e(url('dashboard.php')) ?>">← Back to dashboard</a></p>
</div></section>
<?php public_footer(); ?>
