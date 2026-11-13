<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$u = require_login();
if ($u['role'] === 'admin' && !get('as')) { /* admins can still view their own dashboard */ }

if (is_post()) { // change password / profile
    csrf_verify();
    if (post('action') === 'profile') {
        $name = post('name'); $phone = post('phone'); $city = post('city');
        if (mb_strlen($name) < 3 || !valid_phone($phone)) flash('bad', 'Enter a valid name and 10-digit mobile number.');
        else { db()->prepare('UPDATE users SET name=?, phone=?, city=? WHERE id=?')->execute([$name, $phone, $city, $u['id']]); flash('ok', 'Profile updated.'); }
    } elseif (post('action') === 'password') {
        $old = (string)($_POST['old'] ?? ''); $n1 = (string)($_POST['new'] ?? '');
        if (!password_verify($old, $u['password'])) flash('bad', 'Current password is incorrect.');
        elseif (strlen($n1) < 8) flash('bad', 'New password must be at least 8 characters.');
        else { db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($n1, PASSWORD_DEFAULT), $u['id']]); flash('ok', 'Password changed.'); }
    }
    redirect('dashboard.php#profile');
}

$st = db()->prepare('SELECT i.*, p.name AS plan_name FROM investments i LEFT JOIN plans p ON p.id = i.plan_id WHERE i.user_id = ? ORDER BY i.id DESC'); $st->execute([$u['id']]);
$invs = $st->fetchAll();
$st = db()->prepare("SELECT po.*, i.ref_no FROM payouts po JOIN investments i ON i.id = po.investment_id WHERE po.user_id = ? ORDER BY po.due_date, po.id"); $st->execute([$u['id']]);
$payouts = $st->fetchAll();
$invested = $expected = $received = 0; $next = null;
foreach ($invs as $i) if ($i['status'] === 'confirmed') { $invested += $i['amount']; $expected += $i['expected_return']; }
foreach ($payouts as $p) {
    if ($p['status'] === 'paid') $received += $p['amount'];
    elseif (!$next) $next = $p;
}
$projects = db()->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order LIMIT 1')->fetch();
$vids = db()->query('SELECT * FROM videos WHERE is_active = 1 ORDER BY sort_order, id LIMIT 2')->fetchAll();
$docs = db()->query('SELECT * FROM documents WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$paidId = (int)get('paid');
public_header('My dashboard', ['body' => 'app', 'noindex' => 1]);
?>
<section class="page-hero"><div class="container row-between wrap">
  <div><h1>Hello, <?= e(explode(' ', $u['name'])[0]) ?> 👋</h1><p class="muted-l">Your investor dashboard</p></div>
  <div class="hero-actions"><a class="btn btn-gold" href="<?= e(url('invest.php')) ?>">+ New investment</a><a class="btn btn-ghost" href="<?= e(url('logout.php')) ?>">Log out</a></div>
</div></section>
<section class="section tight"><div class="container">
  <?= flashes() ?>
  <?php if ($paidId): ?><div class="alert alert-ok big">🎉 Investment confirmed! Your certificate and transaction ID have been emailed to <b><?= e($u['email']) ?></b>. <a href="<?= e(url('certificate.php?id=' . $paidId)) ?>" target="_blank">Download certificate</a></div><?php endif; ?>

  <?php if ($u['kyc_status'] !== 'approved'): ?>
  <div class="kyc-banner <?= $u['kyc_status'] ?>">
    <div><b><?= $u['kyc_status'] === 'submitted' ? '⏳ KYC under review' : ($u['kyc_status'] === 'rejected' ? '❌ KYC needs attention' : '🛡️ Secure your investment — complete KYC') ?></b>
      <p><?= $u['kyc_status'] === 'submitted' ? 'We are verifying your PAN & Aadhaar. You will be notified by email.' : ($u['kyc_status'] === 'rejected' ? e($u['kyc_note'] ?: 'Please resubmit your documents.') : 'Submit your PAN and Aadhaar to protect your funds and receive payouts without delay.') ?></p></div>
    <?php if ($u['kyc_status'] !== 'submitted'): ?><a class="btn btn-dark" href="<?= e(url('kyc.php')) ?>"><?= $u['kyc_status'] === 'rejected' ? 'Resubmit KYC' : 'Submit PAN & Aadhaar' ?></a><?php else: ?><a class="btn btn-ghost-dark" href="<?= e(url('kyc.php')) ?>">View</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="kpis">
    <div class="kpi"><span>Total invested</span><b><?= inr($invested) ?></b></div>
    <div class="kpi"><span>Projected returns</span><b><?= inr($expected) ?></b></div>
    <div class="kpi"><span>Returns received</span><b><?= inr($received) ?></b></div>
    <div class="kpi"><span>Next payout</span><b><?= $next ? inr($next['amount']) : '—' ?></b><small><?= $next ? e(dt($next['due_date'])) : 'None scheduled' ?></small></div>
  </div>

  <div class="card tbl-card">
    <h3>My investments</h3>
    <?php if (!$invs): ?><p class="muted">You have no investments yet. <a href="<?= e(url('invest.php')) ?>">Make your first investment →</a></p><?php else: ?>
    <div class="tbl-wrap"><table class="tbl stack">
      <thead><tr><th>Reference</th><th>Plan</th><th>Amount</th><th>Return</th><th>Status</th><th>Txn ID</th><th>Date</th><th></th></tr></thead>
      <tbody><?php foreach ($invs as $i): ?>
        <tr>
          <td data-label="Reference"><b><?= e($i['ref_no']) ?></b></td><td data-label="Plan"><?= e($i['plan_name']) ?></td><td data-label="Amount"><?= inr($i['amount']) ?></td>
          <td data-label="Return"><?= e(rtrim(rtrim(number_format((float)$i['roi'], 2), '0'), '.')) ?>% · <?= inr($i['expected_return']) ?></td>
          <td data-label="Status"><?= status_badge($i['status']) ?></td>
          <td data-label="Txn ID" class="mono"><?= e($i['txn_id'] ?: '—') ?></td>
          <td data-label="Date"><?= e(dt($i['created_at'])) ?></td>
          <td class="actions">
            <?php if ($i['status'] === 'confirmed'): ?><a class="btn btn-sm btn-dark" href="<?= e(url('certificate.php?id=' . $i['id'])) ?>" target="_blank">Certificate PDF</a>
            <?php elseif ($i['status'] === 'pending'): ?><a class="btn btn-sm btn-gold" href="<?= e(url('pay.php?id=' . $i['id'])) ?>">Pay now</a>
            <?php elseif ($i['status'] === 'submitted'): ?><span class="muted small">Verifying…</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <?php if ($payouts): ?>
  <div class="card tbl-card">
    <h3>Payout schedule</h3>
    <div class="tbl-wrap"><table class="tbl stack">
      <thead><tr><th>Due date</th><th>Investment</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
      <tbody><?php foreach ($payouts as $p): ?>
        <tr><td data-label="Due"><?= e(dt($p['due_date'])) ?></td><td data-label="Investment"><?= e($p['ref_no']) ?></td><td data-label="Type"><?= $p['type'] === 'principal' ? 'Principal' : 'Return' ?></td><td data-label="Amount"><b><?= inr($p['amount'], 2) ?></b></td><td data-label="Status"><?= status_badge($p['status']) ?></td><td data-label="Reference" class="mono"><?= e($p['reference'] ?: '—') ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <div class="dash-grid">
    <?php if ($projects): ?>
    <div class="card"><h3>Project progress</h3><p><b><?= e($projects['title']) ?></b><br><span class="muted"><?= e($projects['status_label']) ?> · completion <?= e(dt($projects['completion_date'], 'M Y')) ?></span></p>
      <div class="fillbar big"><div style="width:<?= (int)$projects['progress'] ?>%"></div></div><p class="small muted"><?= (int)$projects['progress'] ?>% complete</p></div>
    <?php endif; ?>
    <div class="card"><h3>Documents</h3>
      <?php foreach ($docs as $d): ?><a class="doc sm" href="<?= e(url('download.php?id=' . $d['id'])) ?>"><span class="doc-ico">PDF</span><span><b><?= e($d['title']) ?></b></span><span class="doc-dl">↓</span></a><?php endforeach; ?>
      <?php if (!$docs): ?><p class="muted">No documents yet.</p><?php endif; ?>
    </div>
  </div>

  <?php if ($vids): ?><div class="card"><h3>Project videos</h3><div class="videos"><?php foreach ($vids as $v): ?><div class="video-card"><div class="video-frame"><?= video_embed($v) ?></div><h3><?= e($v['title']) ?></h3></div><?php endforeach; ?></div></div><?php endif; ?>

  <div class="dash-grid" id="profile">
    <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <h3>Profile</h3>
      <label>Full name<input name="name" value="<?= e($u['name']) ?>" required></label>
      <label>Email<input value="<?= e($u['email']) ?>" disabled></label>
      <div class="grid2"><label>Mobile<input name="phone" value="<?= e($u['phone']) ?>" required maxlength="10"></label><label>City<input name="city" value="<?= e($u['city']) ?>"></label></div>
      <button class="btn btn-dark">Save profile</button>
    </form>
    <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h3>Change password</h3>
      <label>Current password<input type="password" name="old" required autocomplete="current-password"></label>
      <label>New password<input type="password" name="new" minlength="8" required autocomplete="new-password"></label>
      <button class="btn btn-dark">Update password</button>
    </form>
  </div>
</div></section>
<?php public_footer(); ?>

