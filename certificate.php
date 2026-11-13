<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$inv = get_investment((int)get('id'));
if (!$inv || ((int)$inv['user_id'] !== (int)$u['id'] && $u['role'] !== 'admin') || $inv['status'] !== 'confirmed') { http_response_code(404); exit('Certificate not available.'); }
[$user, $plan, $project] = investment_context($inv);
$pdf = certificate_pdf($inv, $user, $plan, $project);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Certificate-' . $inv['ref_no'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $pdf;
