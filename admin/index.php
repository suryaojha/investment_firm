<?php
require __DIR__ . '/_layout.php';
$pdo = db();
$q = fn(string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $s = $pdo->prepare($sql); $s->execute($a); return $s->fetchColumn(); })();
$raised = (float)$q("SELECT COALESCE(SUM(amount),0) FROM investments WHERE status='confirmed'");
$pendAmt = (float)$q("SELECT COALESCE(SUM(amount),0) FROM investments WHERE status IN ('submitted')");
$pendCnt = (int)$q("SELECT COUNT(*) FROM investments WHERE status='submitted'");
$investors = (int)$q("SELECT COUNT(DISTINCT user_id) FROM investments WHERE status='confirmed'");
$users = (int)$q("SELECT COUNT(*) FROM users WHERE role='user'");
$kycPend = (int)$q("SELECT COUNT(*) FROM users WHERE kyc_status='submitted'");
$unread = (int)$q('SELECT COUNT(*) FROM messages WHERE is_read=0');
$due30 = (float)$q("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status='pending' AND due_date <= ?", [date('Y-m-d', strtotime('+30 days'))]);
$overdue = (int)$q("SELECT COUNT(*) FROM payouts WHERE status='pending' AND due_date < ?", [date('Y-m-d')]);
$recent = $pdo->query("SELECT i.*, u.name, p.name AS plan_name FROM investments i JOIN users u ON u.id = i.user_id LEFT JOIN plans p ON p.id = i.plan_id ORDER BY i.id DESC LIMIT 8")->fetchAll();
// last 6 months raised
$months = [];
for ($k = 5; $k >= 0; $k--) { $m = date('Y-m', strtotime("-$k months")); $months[$m] = 0.0; }
foreach ($pdo->query("SELECT paid_at, amount FROM investments WHERE status='confirmed' AND paid_at IS NOT NULL")->fetchAll() as $r) { $m = substr($r['paid_at'], 0, 7); if (isset($months[$m])) $months[$m] += $r['amount']; }
$maxM = max($months) ?: 1;
admin_header('Dashboard');
?>
<div class="kpis">
  <div class="kpi"><span>Total raised (confirmed)</span><b><?= inr_short($raised) ?></b><small><?= inr($raised) ?></small></div>
  <div class="kpi warn"><span>Awaiting verification</span><b><?= $pendCnt ?></b><small><?= inr($pendAmt) ?> · <a href="investments.php?status=submitted">review</a></small></div>
  <div class="kpi"><span>Investors / Registered users</span><b><?= $investors ?> / <?= $users ?></b></div>
  <div class="kpi warn"><span>KYC to review</span><b><?= $kycPend ?></b><small><a href="users.php?kyc=submitted">review</a></small></div>
  <div class="kpi"><span>Payouts due in 30 days</span><b><?= inr_short($due30) ?></b><small><?= $overdue ? '<span class="neg">' . $overdue . ' overdue</span> · ' : '' ?><a href="payouts.php">manage</a></small></div>
  <div class="kpi"><span>Unread enquiries</span><b><?= $unread ?></b><small><a href="messages.php">open inbox</a></small></div>
</div>
<div class="two-col">
  <div class="card">
    <div class="card-head"><h2>Raised — last 6 months</h2></div>
    <div class="bars"><?php foreach ($months as $m => $val): ?>
      <div class="bar"><i style="height:<?= max(3, round($val / $maxM * 100)) ?>%" title="<?= e(inr($val)) ?>"></i><b><?= e(inr_short($val)) ?></b><span><?= e(date('M', strtotime($m . '-01'))) ?></span></div>
    <?php endforeach; ?></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Quick actions</h2></div>
    <div class="quick">
      <a class="btn btn-ghost" href="settings.php">⚙️ Branding & settings</a><a class="btn btn-ghost" href="plans.php">🏷️ Edit plans</a>
      <a class="btn btn-ghost" href="videos.php?action=new">🎬 Add video</a><a class="btn btn-ghost" href="documents.php?action=new">📄 Upload PDF</a>
      <a class="btn btn-ghost" href="export.php?type=investments">⬇ Export investments</a><a class="btn btn-ghost" href="export.php?type=users">⬇ Export investors</a>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-head"><h2>Recent investments</h2><a class="btn btn-sm btn-ghost" href="investments.php">View all</a></div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ref</th><th>Investor</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead><tbody>
  <?php foreach ($recent as $r): ?><tr><td><a href="investments.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['ref_no']) ?></b></a></td><td><?= e($r['name']) ?></td><td><?= e($r['plan_name']) ?></td><td><?= inr($r['amount']) ?></td><td><?= e(strtoupper((string)$r['method'])) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e(dt($r['created_at'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="7" class="muted">No investments yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php admin_footer(); ?>
