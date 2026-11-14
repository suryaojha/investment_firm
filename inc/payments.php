<?php
/**
 * Payment gateways: Razorpay (REST via cURL, no SDK needed), manual UPI/bank, and a demo mode.
 */
function enabled_methods(): array {
    $m = [];
    if (setting('pay_razorpay') === '1' && setting('razorpay_key_id') && setting('razorpay_key_secret')) $m['razorpay'] = 'Pay online (UPI · Cards · Netbanking)';
    if (setting('pay_manual') === '1') $m['manual'] = 'UPI / Bank transfer (submit UTR)';
    if (setting('pay_demo') === '1') $m['demo'] = 'DEMO payment (testing only)';
    return $m;
}

function rzp_request(string $method, string $path, ?array $data = null): array {
    $ch = curl_init('https://api.razorpay.com/v1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => setting('razorpay_key_id') . ':' . setting('razorpay_key_secret'),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($res === false) throw new RuntimeException('Payment gateway unreachable: ' . $err);
    $j = json_decode($res, true) ?: [];
    if ($code >= 400) throw new RuntimeException('Gateway error: ' . ($j['error']['description'] ?? $res));
    return $j;
}

function rzp_create_order(array $inv): array {
    $order = rzp_request('POST', '/orders', [
        'amount' => (int)round(max(0, (float)$inv['amount'] - (float)($inv['wallet_used'] ?? 0)) * 100), 'currency' => 'INR', 'receipt' => $inv['ref_no'],
        'notes' => ['investment_id' => (string)$inv['id'], 'ref' => $inv['ref_no']],
    ]);
    db()->prepare('UPDATE investments SET gateway_order_id = ? WHERE id = ?')->execute([$order['id'], $inv['id']]);
    return $order;
}

function rzp_verify_signature(string $orderId, string $paymentId, string $signature): bool {
    return hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, setting('razorpay_key_secret')), $signature);
}
