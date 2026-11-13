<?php
require __DIR__ . '/_layout.php';
$pdo = db();

if (is_post()) {
    csrf_verify();
    $st = $pdo->prepare('SELECT po.*, u.name, u.email, u.kyc_status, i.ref_no FROM payouts po JOIN users u ON u.id = po.user_id JOIN investments i ON i.id = po.investment_id WHERE po.id = ?');
    $st->execute([(int)post('id')]); $p = $st->fetch();
    if ($p && post('action') === 'paid' && $p['status'] === 'pending') {
        if (setting('kyc_required_before_payout') === '1' && $p['kyc_status'] !== 'approved') {
            flash('bad', 'Cannot mark as paid: ' . $p['name'] . "'s KYC is not approved yet.");
        } else {
            $ref = mb_substr(post('reference'), 0, 100);
            $pdo->prepare("UPDATE payouts SET status='paid', paid_at=?, reference=? WHERE id=?")->execute([now(), $ref, $p['id']]);
            audit('payout.paid', "#{$p['id']} {$p['amount']}");
            send_mail($p['email'], 'Payout sent — ' . inr($p['amount'], 2), '<p>Hi ' . e($p['name']) . ',</p><p>We have paid <b>' . inr($p['amount'], 2) . '</b> (' . ($p['type'] === 'principal' ? 'principal' : 'return') . ') for investment <b>' . e($p['ref_no']) . '</b> to your registered bank account.' . ($ref ? '<br>Reference: <b>' . e($ref) . '</b>' : '') . '</p>', [], 'Payout sent');
            flash('ok', 'Marked as paid and investor notified.');
        }
    } elseif ($p && post('action') === 'undo' && $p['status'] === 'paid') {
        $pdo->prepare("UPDATE payouts SET status='pending', paid_at=NULL WHERE id=?")->execute([$p['id']]); flash('ok', 'Reverted to pending.');
    }
    redirect('admin/payouts.php?' . http_build_query(['f' => get('f') ?: post('f')]));
}
$f = get('f', 'pending');
$w = ' WHERE 1=1'; $a = [];
if ($f === 'pending') { $w .= " AND po.status='pending'"; }
elseif ($f === 'overdue') { $w .= " AND po.status='pending' AND po.due_date < ?"; $a[] = date('Y-m-d'); }
elseif ($f === 'month') { $w .= " AND po.status='pending' AND po.due_date <= ?"; $a[] = date('Y-m-d', strtotime('+30 days')); }
elseif ($f === 'paid') { $w .= " AND po.status='paid'"; }
$st = $pdo->prepare("SELECT po.*, u.name, u.kyc_status, u.bank_holder, u.bank_account, u.bank_ifsc, i.ref_no FROM payouts po JOIN users u ON u.id = po.user_id JOIN investments i ON i.id = po.investment_id$w ORDER BY po.due_date, po.id LIMIT 300");
$st->execute($a); $rows = $st->fetchAll();
admin_header('Payouts');
?>
<div class="card">
  <div class="filters"><?php foreach (['pending' => 'All pending', 'overdue' => 'Overdue', 'month' => 'Due in 30 days', 'paid' => 'Paid', 'all' => 'Everything'] as $k => $l): ?><a class="btn btn-sm <?= $f === $k ? 'btn-primary' : 'btn-ghost' ?>" href="?f=<?= $k ?>"><?= $l ?></a><?php endforeach; ?>
    <a class="btn btn-sm btn-ghost" href="export.php?type=payouts">⬇ Export CSV</a></div>
  <p class="muted small">Pay returns from your bank, then record the bank reference here — the investor is emailed automatically.</p>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Due</th><th>Investor</th><th>Investment</th><th>Type</th><th>Amount</th><th>Bank details</th><th>Status</th><th>Record payment</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $over = $r['status'] === 'pending' && $r['due_date'] < date('Y-m-d'); ?><tr>
    <td class="<?= $over ? 'neg' : '' ?>"><?= e(dt($r['due_date'])) ?><?= $over ? '<br><small>overdue</small>' : '' ?></td>
    <td><?= e($r['name']) ?><br><?= $r['kyc_status'] !== 'approved' ? '<small class="neg">KYC ' . e($r['kyc_status']) . '</small>' : '<small class="muted">KYC ✔</small>' ?></td>
    <td><?= e($r['ref_no']) ?></td><td><?= e(ucfirst($r['type'])) ?></td><td><b><?= inr($r['amount'], 2) ?></b></td>
    <td class="small"><?= $r['bank_account'] ? e($r['bank_holder']) . '<br>' . e($r['bank_account']) . '<br>' . e($r['bank_ifsc']) : '<span class="muted">not provided</span>' ?></td>
    <td><?= status_badge($r['status']) ?><?= $r['status'] === 'paid' ? '<br><small>' . e(dt($r['paid_at'])) . '<br>' . e($r['reference']) . '</small>' : '' ?></td>
    <td><?php if ($r['status'] === 'pending'): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="paid"><input type="hidden" name="f" value="<?= e($f) ?>"><input name="reference" placeholder="Bank ref / UTR" maxlength="100"><button class="btn btn-sm btn-primary">Mark paid</button></form>
      <?php else: ?><form method="post" onsubmit="return confirm('Revert to pending?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="undo"><input type="hidden" name="f" value="<?= e($f) ?>"><button class="btn btn-sm btn-ghost">Undo</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">Nothing to show.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php admin_footer(); ?>
