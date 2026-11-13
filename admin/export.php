<?php
require_once dirname(__DIR__) . '/inc/bootstrap.php';
require_admin();
$type = get('type');
$pdo = db();
$q = [
    'investments' => ["SELECT i.ref_no, u.name, u.email, u.phone, p.name AS plan, i.amount, i.roi, i.tenure_months, i.expected_return, i.method, i.status, i.txn_id, i.certificate_no, i.created_at, i.paid_at, i.maturity_date FROM investments i JOIN users u ON u.id=i.user_id LEFT JOIN plans p ON p.id=i.plan_id ORDER BY i.id DESC"],
    'users' => ["SELECT id, name, email, phone, city, role, status, kyc_status, pan, aadhaar_last4, bank_holder, bank_account, bank_ifsc, created_at FROM users ORDER BY id DESC"],
    'payouts' => ["SELECT i.ref_no, u.name, u.email, po.type, po.amount, po.due_date, po.status, po.paid_at, po.reference, u.bank_holder, u.bank_account, u.bank_ifsc FROM payouts po JOIN users u ON u.id=po.user_id JOIN investments i ON i.id=po.investment_id ORDER BY po.due_date"],
];
if (!isset($q[$type])) { http_response_code(404); exit('Unknown export.'); }
audit('export.' . $type);
$rows = $pdo->query($q[$type][0])->fetchAll();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '-' . date('Ymd-His') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
if ($rows) fputcsv($out, array_keys($rows[0]));
foreach ($rows as $r) {
    // neutralise spreadsheet formula injection
    fputcsv($out, array_map(fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") ? "'" . $v : $v, $r));
}
fclose($out);
