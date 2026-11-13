<?php
require __DIR__ . '/_layout.php';
$pdo = db();

// ---------- actions ----------
if (is_post()) {
    csrf_verify();
    $inv = get_investment((int)post('id'));
    if (!$inv) { flash('bad', 'Investment not found.'); redirect('admin/investments.php'); }
    $act = post('action');
    if ($act === 'confirm' && $inv['status'] !== 'confirmed') {
        $txn = strtoupper(preg_replace('/\s+/', '', post('txn_id')));
        confirm_investment((int)$inv['id'], $txn);
        flash('ok', 'Investment confirmed. Certificate & transaction ID emailed to the investor.');
    } elseif ($act === 'reject' && $inv['status'] !== 'confirmed') {
        $pdo->prepare("UPDATE investments SET status='rejected', note=? WHERE id=?")->execute([mb_substr(post('reason'), 0, 250), $inv['id']]);
        [$user] = investment_context($inv);
        send_mail($user['email'], 'Payment could not be verified — ' . $inv['ref_no'], '<p>Hi ' . e($user['name']) . ',</p><p>We could not verify the payment for investment <b>' . e($inv['ref_no']) . '</b>.' . (post('reason') ? ' Reason: ' . e(post('reason')) : '') . '</p><p>Please contact us with your payment details.</p>');
        audit('investment.rejected', $inv['ref_no']); flash('ok', 'Investment rejected and investor notified.');
    } elseif ($act === 'resend' && $inv['status'] === 'confirmed') {
        notify_investment_confirmed($inv); flash('ok', 'Confirmation email with certificate re-sent.');
    } elseif ($act === 'mark_pending' && $inv['status'] === 'rejected') {
        $pdo->prepare("UPDATE investments SET status='submitted' WHERE id=?")->execute([$inv['id']]); flash('ok', 'Moved back to “submitted”.');
    }
    redirect('admin/investments.php?id=' . $inv['id']);
}

// ---------- detail ----------
if ($id = (int)get('id')) {
    $inv = get_investment($id);
    if (!$inv) { redirect('admin/investments.php'); }
    [$user, $plan] = investment_context($inv);
    $po = $pdo->prepare('SELECT * FROM payouts WHERE investment_id = ? ORDER BY due_date, id'); $po->execute([$id]); $payouts = $po->fetchAll();
    admin_header('Investment ' . $inv['ref_no']);
    ?>
    <p><a href="investments.php">← All investments</a></p>
    <div class="two-col">
      <div class="card">
        <div class="card-head"><h2><?= e($inv['ref_no']) ?> <?= status_badge($inv['status']) ?></h2></div>
        <dl class="dl">
          <dt>Investor</dt><dd><a href="users.php?id=<?= (int)$user['id'] ?>"><?= e($user['name']) ?></a> · <?= e($user['email']) ?> · <?= e($user['phone']) ?></dd>
          <dt>Plan</dt><dd><?= e($plan['name']) ?> — <?= e(rtrim(rtrim(number_format((float)$inv['roi'], 2), '0'), '.')) ?>% for <?= (int)$inv['tenure_months'] ?> mo, <?= e($inv['payout_mode']) ?></dd>
          <dt>Amount</dt><dd><b><?= inr($inv['amount'], 2) ?></b> (<?= e(amount_in_words((float)$inv['amount'])) ?>)</dd>
          <dt>Projected return</dt><dd><?= inr($inv['expected_return'], 2) ?></dd>
          <dt>Method</dt><dd><?= e(strtoupper((string)$inv['method'])) ?></dd>
          <dt>Transaction / UTR</dt><dd class="mono"><?= e($inv['txn_id'] ?: '—') ?></dd>
          <dt>Gateway order / payment</dt><dd class="mono"><?= e($inv['gateway_order_id'] ?: '—') ?> / <?= e($inv['gateway_payment_id'] ?: '—') ?></dd>
          <dt>Created</dt><dd><?= e(dt($inv['created_at'], 'd M Y H:i')) ?></dd>
          <dt>Confirmed</dt><dd><?= e(dt($inv['paid_at'], 'd M Y H:i')) ?> · maturity <?= e(dt($inv['maturity_date'])) ?></dd>
          <dt>Certificate</dt><dd><?= $inv['certificate_no'] ? e($inv['certificate_no']) . ' · <a href="' . e(url('certificate.php?id=' . $inv['id'])) . '" target="_blank">View PDF</a>' : '—' ?></dd>
          <dt>Proof</dt><dd><?= $inv['proof_file'] ? '<a target="_blank" href="' . e(url('file.php?t=proof&i=' . $inv['id'])) . '">View uploaded proof ↗</a>' : '—' ?></dd>
          <dt>Note</dt><dd><?= e($inv['note'] ?: '—') ?></dd>
        </dl>
      </div>
      <div class="card">
        <div class="card-head"><h2>Actions</h2></div>
        <?php if ($inv['status'] !== 'confirmed'): ?>
          <form method="post" class="aform"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>"><input type="hidden" name="action" value="confirm">
            <div class="field wide"><label>Transaction ID to print on certificate / email</label><input name="txn_id" value="<?= e($inv['txn_id']) ?>" placeholder="UTR / bank reference (auto-generated if empty)"><small>Verify the money in your bank account first. Confirming sends the investor their PDF certificate and transaction ID.</small></div>
            <div class="field wide"><button class="btn btn-primary" onclick="return confirm('Confirm that you have received <?= e(inr($inv['amount'])) ?> in the bank account?')">✔ Confirm payment received</button></div>
          </form>
          <hr>
          <form method="post" class="aform"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>"><input type="hidden" name="action" value="reject">
            <div class="field wide"><label>Reject reason (sent to investor)</label><input name="reason" maxlength="200" placeholder="e.g. Amount not received"></div>
            <div class="field wide"><button class="btn btn-danger" onclick="return confirm('Reject this investment?')">✖ Reject</button></div>
          </form>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>"><input type="hidden" name="action" value="resend"><button class="btn btn-ghost">📧 Re-send certificate email</button></form>
        <?php endif; ?>
        <?php if ($inv['status'] === 'rejected'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>"><input type="hidden" name="action" value="mark_pending"><button class="btn btn-ghost">↺ Re-open for verification</button></form><?php endif; ?>
      </div>
    </div>
    <?php if ($payouts): ?><div class="card"><div class="card-head"><h2>Payout schedule</h2><a class="btn btn-sm btn-ghost" href="payouts.php">Manage payouts</a></div>
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Due</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead><tbody>
      <?php foreach ($payouts as $p): ?><tr><td><?= e(dt($p['due_date'])) ?></td><td><?= e(ucfirst($p['type'])) ?></td><td><?= inr($p['amount'], 2) ?></td><td><?= status_badge($p['status']) ?></td><td class="mono"><?= e($p['reference'] ?: '—') ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div><?php endif; ?>
    <?php admin_footer(); return;
}

// ---------- list ----------
$status = get('status'); $qs = get('q'); $page = max(1, (int)get('page', '1')); $per = 25;
$w = ' WHERE 1=1'; $a = [];
if (in_array($status, ['pending', 'submitted', 'confirmed', 'rejected', 'cancelled'], true)) { $w .= ' AND i.status = ?'; $a[] = $status; }
if ($qs !== '') { $w .= ' AND (i.ref_no LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR i.txn_id LIKE ?)'; $a = array_merge($a, array_fill(0, 4, "%$qs%")); }
$cnt = $pdo->prepare("SELECT COUNT(*) FROM investments i JOIN users u ON u.id = i.user_id$w"); $cnt->execute($a); $total = (int)$cnt->fetchColumn();
$st = $pdo->prepare("SELECT i.*, u.name, u.email, p.name AS plan_name FROM investments i JOIN users u ON u.id = i.user_id LEFT JOIN plans p ON p.id = i.plan_id$w ORDER BY i.id DESC" . paginate_sql($page, $per));
$st->execute($a); $rows = $st->fetchAll();
admin_header('Investments');
?>
<div class="card">
  <form class="filters" method="get">
    <select name="status" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach (['submitted' => 'Awaiting verification', 'pending' => 'Pending payment', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <input name="q" value="<?= e($qs) ?>" placeholder="Search ref, name, email, UTR"><button class="btn btn-sm btn-primary">Search</button>
    <a class="btn btn-sm btn-ghost" href="export.php?type=investments">⬇ Export CSV</a>
  </form>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ref</th><th>Investor</th><th>Plan</th><th>Amount</th><th>Method</th><th>Txn / UTR</th><th>Status</th><th>Date</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr>
    <td><b><?= e($r['ref_no']) ?></b></td><td><?= e($r['name']) ?><br><small class="muted"><?= e($r['email']) ?></small></td><td><?= e($r['plan_name']) ?></td><td><?= inr($r['amount']) ?></td>
    <td><?= e(strtoupper((string)$r['method'])) ?></td><td class="mono"><?= e($r['txn_id'] ?: '—') ?></td><td><?= status_badge($r['status']) ?></td><td><?= e(dt($r['created_at'])) ?></td>
    <td class="actions"><a class="btn btn-sm <?= $r['status'] === 'submitted' ? 'btn-primary' : 'btn-ghost' ?>" href="?id=<?= (int)$r['id'] ?>"><?= $r['status'] === 'submitted' ? 'Verify' : 'Open' ?></a></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="muted">No investments found.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php $pages = (int)ceil($total / $per); if ($pages > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="?status=<?= e($status) ?>&q=<?= e(urlencode($qs)) ?>&page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php admin_footer(); ?>
