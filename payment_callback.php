<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();
if (!is_post()) redirect('dashboard.php');
csrf_verify();

$inv = get_investment((int)post('investment_id'));
$pid = post('razorpay_payment_id'); $oid = post('razorpay_order_id'); $sig = post('razorpay_signature');
if (!$inv || (int)$inv['user_id'] !== (int)$user['id'] || $inv['method'] !== 'razorpay') { http_response_code(404); exit('Not found.'); }
if ($inv['status'] === 'confirmed') redirect('dashboard.php?paid=' . $inv['id']);

try {
    if (!$pid || !$oid || !$sig || $oid !== $inv['gateway_order_id'] || !rzp_verify_signature($oid, $pid, $sig))
        throw new RuntimeException('Signature mismatch');
    // Double-check with the gateway: amount, order and status.
    $pay = rzp_request('GET', '/payments/' . rawurlencode($pid));
    if (($pay['order_id'] ?? '') !== $oid || (int)$pay['amount'] !== (int)round($inv['amount'] * 100)) throw new RuntimeException('Amount/order mismatch');
    if (($pay['status'] ?? '') === 'authorized')
        $pay = rzp_request('POST', '/payments/' . rawurlencode($pid) . '/capture', ['amount' => (int)$pay['amount'], 'currency' => 'INR']);
    if (($pay['status'] ?? '') !== 'captured') throw new RuntimeException('Payment not captured: ' . ($pay['status'] ?? '?'));
    confirm_investment((int)$inv['id'], $pid, $pid);
    flash('ok', 'Payment successful! Your certificate and transaction ID have been emailed to you.');
    redirect('dashboard.php?paid=' . $inv['id']);
} catch (Throwable $e) {
    error_log('[RZP CALLBACK] ' . $e->getMessage());
    audit('payment.failed', $inv['ref_no'] . ' ' . $e->getMessage());
    flash('bad', 'We could not verify your payment. If money was debited it will be reconciled automatically — contact support with reference ' . $inv['ref_no'] . '.');
    redirect('dashboard.php');
}
