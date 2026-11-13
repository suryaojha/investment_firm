<?php
require __DIR__ . '/_layout.php';
$pdo = db();
$load = function (int $id) use ($pdo) { $s = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; };

if (is_post()) {
    csrf_verify();
    $u = $load((int)post('id')); $act = post('action');
    if (!$u) redirect('admin/users.php');
    if ($act === 'kyc_approve') {
        $pdo->prepare("UPDATE users SET kyc_status='approved', kyc_note=NULL WHERE id=?")->execute([$u['id']]);
        send_mail($u['email'], 'KYC approved', '<p>Hi ' . e($u['name']) . ',</p><p>Your KYC has been <b>verified and approved</b>. You are all set to receive payouts.</p>', [], 'KYC approved');
        audit('kyc.approved', $u['email']); flash('ok', 'KYC approved.');
    } elseif ($act === 'kyc_reject') {
        $note = mb_substr(post('note'), 0, 250);
        $pdo->prepare("UPDATE users SET kyc_status='rejected', kyc_note=? WHERE id=?")->execute([$note ?: 'Documents unclear or mismatched.', $u['id']]);
        send_mail($u['email'], 'KYC needs attention', '<p>Hi ' . e($u['name']) . ',</p><p>We could not verify your KYC. Reason: <b>' . e($note ?: 'Documents unclear or mismatched.') . '</b></p><p>Please log in and resubmit.</p>', [], 'KYC needs attention');
        audit('kyc.rejected', $u['email']); flash('ok', 'KYC rejected and investor notified.');
    } elseif ($act === 'toggle_status' && (int)$u['id'] !== (int)$ADMIN['id']) {
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$u['status'] === 'active' ? 'disabled' : 'active', $u['id']]); flash('ok', 'Account status changed.');
    } elseif ($act === 'toggle_admin' && (int)$u['id'] !== (int)$ADMIN['id']) {
        $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$u['role'] === 'admin' ? 'user' : 'admin', $u['id']]); audit('user.role_changed', $u['email']); flash('ok', 'Role changed.');
    } elseif ($act === 'reveal') {
        $_SESSION['reveal_' . $u['id']] = time(); audit('aadhaar.revealed', $u['email']);
    } elseif ($act === 'reset_pw' && strlen((string)($_POST['pw'] ?? '')) >= 8) {
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash((string)$_POST['pw'], PASSWORD_DEFAULT), $u['id']]); audit('user.password_set_by_admin', $u['email']); flash('ok', 'Password updated.');
    }
    redirect('admin/users.php?id=' . $u['id']);
}

if ($id = (int)get('id')) {
    $u = $load($id); if (!$u) redirect('admin/users.php');
    $st = $pdo->prepare('SELECT i.*, p.name AS plan_name FROM investments i LEFT JOIN plans p ON p.id = i.plan_id WHERE i.user_id = ? ORDER BY i.id DESC'); $st->execute([$id]); $invs = $st->fetchAll();
    $reveal = !empty($_SESSION['reveal_' . $id]) && time() - $_SESSION['reveal_' . $id] < 120;
    admin_header($u['name']);
    ?>
    <p><a href="users.php">← All investors</a></p>
    <div class="two-col">
      <div class="card">
        <div class="card-head"><h2><?= e($u['name']) ?> <?= status_badge($u['status']) ?> <?= $u['role'] === 'admin' ? '<span class="badge badge-info">ADMIN</span>' : '' ?></h2></div>
        <dl class="dl">
          <dt>Email</dt><dd><?= e($u['email']) ?></dd><dt>Mobile</dt><dd><?= e($u['phone']) ?></dd><dt>City</dt><dd><?= e($u['city'] ?: '—') ?></dd>
          <dt>Joined</dt><dd><?= e(dt($u['created_at'])) ?> · last login <?= e(dt($u['last_login'], 'd M Y H:i')) ?></dd>
          <dt>Date of birth</dt><dd><?= e($u['dob'] ?: '—') ?></dd><dt>Address</dt><dd><?= nl2br(e($u['address'] ?: '—')) ?></dd>
          <dt>Bank</dt><dd><?= $u['bank_account'] ? e($u['bank_holder']) . ' · ' . e($u['bank_account']) . ' · ' . e($u['bank_ifsc']) : '—' ?></dd>
          <dt>Nominee</dt><dd><?= e($u['nominee_name'] ? $u['nominee_name'] . ' (' . $u['nominee_relation'] . ')' : '—') ?></dd>
        </dl>
        <?php if ((int)$u['id'] !== (int)$ADMIN['id']): ?>
        <div class="btn-row">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="toggle_status"><button class="btn btn-sm btn-ghost"><?= $u['status'] === 'active' ? '🚫 Disable account' : '✔ Enable account' ?></button></form>
          <form method="post" onsubmit="return confirm('Change admin access for this user?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="toggle_admin"><button class="btn btn-sm btn-ghost"><?= $u['role'] === 'admin' ? 'Remove admin access' : 'Make admin' ?></button></form>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="reset_pw"><input type="text" name="pw" placeholder="New password (min 8)" minlength="8"><button class="btn btn-sm btn-ghost">Set password</button></form>
        </div><?php endif; ?>
      </div>
      <div class="card">
        <div class="card-head"><h2>KYC <?= status_badge($u['kyc_status']) ?></h2></div>
        <dl class="dl">
          <dt>PAN</dt><dd class="mono"><?= e($u['pan'] ?: '—') ?></dd>
          <dt>Aadhaar</dt><dd class="mono"><?php if (!$u['aadhaar_enc']): ?>—<?php elseif ($reveal): ?><?= e(trim(chunk_split(dec($u['aadhaar_enc']), 4, ' '))) ?> <small class="muted">(visible for 2 min, logged)</small><?php else: ?><?= e(kyc_masked_aadhaar($u)) ?> <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="reveal"><button class="link-btn">reveal</button></form><?php endif; ?></dd>
          <dt>PAN copy</dt><dd><?= $u['pan_file'] ? '<a target="_blank" href="' . e(url('file.php?t=pan&u=' . $id)) . '">View ↗</a>' : '—' ?></dd>
          <dt>Aadhaar copy</dt><dd><?= $u['aadhaar_file'] ? '<a target="_blank" href="' . e(url('file.php?t=aadhaar&u=' . $id)) . '">View ↗</a>' : '—' ?></dd>
          <dt>Submitted</dt><dd><?= e(dt($u['kyc_submitted_at'], 'd M Y H:i')) ?></dd><dt>Note</dt><dd><?= e($u['kyc_note'] ?: '—') ?></dd>
        </dl>
        <?php if (in_array($u['kyc_status'], ['submitted', 'rejected', 'approved'], true)): ?>
        <div class="btn-row">
          <?php if ($u['kyc_status'] !== 'approved'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="kyc_approve"><button class="btn btn-primary">✔ Approve KYC</button></form><?php endif; ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="kyc_reject"><input name="note" placeholder="Reason (sent to investor)" maxlength="200"><button class="btn btn-danger">Reject</button></form>
        </div><?php endif; ?>
      </div>
    </div>
    <div class="card"><div class="card-head"><h2>Investments</h2></div><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ref</th><th>Plan</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($invs as $i): ?><tr><td><a href="investments.php?id=<?= (int)$i['id'] ?>"><?= e($i['ref_no']) ?></a></td><td><?= e($i['plan_name']) ?></td><td><?= inr($i['amount']) ?></td><td><?= status_badge($i['status']) ?></td><td><?= e(dt($i['created_at'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$invs): ?><tr><td colspan="5" class="muted">None.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php admin_footer(); return;
}

$qs = get('q'); $kyc = get('kyc'); $page = max(1, (int)get('page', '1')); $per = 25;
$w = ' WHERE 1=1'; $a = [];
if ($qs !== '') { $w .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR pan LIKE ?)'; $a = array_fill(0, 4, "%$qs%"); }
if (in_array($kyc, ['none', 'submitted', 'approved', 'rejected'], true)) { $w .= ' AND kyc_status = ?'; $a[] = $kyc; }
$c = $pdo->prepare("SELECT COUNT(*) FROM users$w"); $c->execute($a); $total = (int)$c->fetchColumn();
$st = $pdo->prepare("SELECT u.*, (SELECT COALESCE(SUM(amount),0) FROM investments WHERE user_id = u.id AND status='confirmed') AS invested FROM users u$w ORDER BY u.id DESC" . paginate_sql($page, $per)); $st->execute($a); $rows = $st->fetchAll();
admin_header('Investors & KYC');
?>
<div class="card">
  <form class="filters" method="get">
    <select name="kyc" onchange="this.form.submit()"><option value="">All KYC statuses</option><?php foreach (['submitted' => 'KYC to review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'none' => 'Not submitted'] as $k => $l): ?><option value="<?= $k ?>" <?= $kyc === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <input name="q" value="<?= e($qs) ?>" placeholder="Search name, email, phone, PAN"><button class="btn btn-sm btn-primary">Search</button>
    <a class="btn btn-sm btn-ghost" href="export.php?type=users">⬇ Export CSV</a>
  </form>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Email / phone</th><th>Invested</th><th>KYC</th><th>Account</th><th>Joined</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><b><?= e($r['name']) ?></b> <?= $r['role'] === 'admin' ? '<span class="badge badge-info">ADMIN</span>' : '' ?></td><td><?= e($r['email']) ?><br><small class="muted"><?= e($r['phone']) ?></small></td><td><?= inr($r['invested']) ?></td><td><?= status_badge($r['kyc_status']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e(dt($r['created_at'])) ?></td><td class="actions"><a class="btn btn-sm btn-ghost" href="?id=<?= (int)$r['id'] ?>">Open</a></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">No users found.</td></tr><?php endif; ?></tbody></table></div>
  <?php $pages = (int)ceil($total / $per); if ($pages > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="?kyc=<?= e($kyc) ?>&q=<?= e(urlencode($qs)) ?>&page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php admin_footer(); ?>
