<?php
/**
 * Razorpay webhook (backup confirmation if the user closes the browser after paying).
 * Dashboard → Settings → Webhooks → URL: https://yourdomain/payment_webhook.php, event: payment.captured, set the secret in Admin → Settings → Payments.
 */
require __DIR__ . '/inc/bootstrap.php';
$secret = setting('razorpay_webhook_secret');
$body = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
if ($secret === '' || !hash_equals(hash_hmac('sha256', $body, $secret), $sig)) { http_response_code(400); exit('bad signature'); }
$ev = json_decode($body, true);
if (($ev['event'] ?? '') === 'payment.captured') {
    $p = $ev['payload']['payment']['entity'] ?? [];
    $st = db()->prepare('SELECT * FROM investments WHERE gateway_order_id = ?'); $st->execute([$p['order_id'] ?? '']); $inv = $st->fetch();
    if ($inv && $inv['status'] !== 'confirmed' && (int)$p['amount'] === (int)round($inv['amount'] * 100)) confirm_investment((int)$inv['id'], (string)$p['id'], (string)$p['id']);
}
http_response_code(200);
echo 'ok';
