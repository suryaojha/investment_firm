<?php
/**
 * Investment business logic: returns maths, payout schedule, confirmation + certificate email.
 */
function plan_total_roi(array $plan): float { return (float)$plan['roi'] + (float)$plan['bonus']; }

function calc_expected_return(float $amount, float $roi, int $months): float {
    return round($amount * $roi / 100 * $months / 12, 2);
}

function plan_raised(int $planId): float {
    $st = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM investments WHERE plan_id = ? AND status = 'confirmed'");
    $st->execute([$planId]);
    return (float)$st->fetchColumn();
}
function total_raised(): float {
    return (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM investments WHERE status = 'confirmed'")->fetchColumn();
}

function new_ref_no(): string {
    do {
        $ref = 'INV' . date('y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $st = db()->prepare('SELECT COUNT(*) FROM investments WHERE ref_no = ?'); $st->execute([$ref]);
    } while ((int)$st->fetchColumn() > 0);
    return $ref;
}

function create_investment(array $user, array $plan, float $amount, string $method, float $walletUsed = 0): int {
    $pdo = db(); $walletUsed = round(max(0, min($walletUsed, $amount)), 2); $roi = plan_total_roi($plan); $pdo->beginTransaction();
    try {
        if ($walletUsed > 0) { $st = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?'); $st->execute([$walletUsed, $user['id'], $walletUsed]); if ($st->rowCount() !== 1) throw new RuntimeException('Referral wallet balance changed. Refresh and retry.'); }
        $pdo->prepare('INSERT INTO investments (ref_no,user_id,plan_id,amount,roi,tenure_months,payout_mode,expected_return,method,status,created_at,wallet_used) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([new_ref_no(), $user['id'], $plan['id'], $amount, $roi, $plan['tenure_months'], $plan['payout_mode'], calc_expected_return($amount, $roi, (int)$plan['tenure_months']), $method, 'pending', now(), $walletUsed]);
        $id = (int)$pdo->lastInsertId();
        if ($walletUsed > 0) $pdo->prepare('INSERT INTO wallet_transactions (user_id,investment_id,type,amount,description,created_at) VALUES (?,?,?,?,?,?)')->execute([$user['id'], $id, 'debit', $walletUsed, 'Wallet applied to plan purchase', now()]);
        $pdo->commit(); return $id;
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

function get_investment(int $id): ?array {
    $st = db()->prepare('SELECT * FROM investments WHERE id = ?'); $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Build payout rows (interest instalments + a principal row at maturity). */
function build_payouts(array $inv, string $startDate): array {
    $amount = (float)$inv['amount']; $rate = (float)$inv['roi'] / 100; $months = (int)$inv['tenure_months'];
    $step = ['monthly' => 1, 'quarterly' => 3][$inv['payout_mode']] ?? $months;
    $step = max(1, min($step, $months));
    $rows = []; $done = 0; $start = new DateTime($startDate);
    while ($done < $months) {
        $span = min($step, $months - $done); $done += $span;
        $due = (clone $start)->modify('+' . $done . ' months')->format('Y-m-d');
        $rows[] = ['return', round($amount * $rate * $span / 12, 2), $due];
    }
    $rows[] = ['principal', round($amount, 2), (clone $start)->modify('+' . $months . ' months')->format('Y-m-d')];
    return $rows;
}

/** Idempotent: marks an investment confirmed, creates payouts, certificate and sends the email. */
function confirm_investment(int $id, string $txnId = '', string $gatewayPaymentId = ''): bool {
    $pdo = db();
    $inv = get_investment($id);
    if (!$inv || $inv['status'] === 'confirmed') return false;

    $paidAt = now();
    $maturity = (new DateTime($paidAt))->modify('+' . (int)$inv['tenure_months'] . ' months')->format('Y-m-d');
    $txn = $txnId !== '' ? $txnId : ($gatewayPaymentId !== '' ? $gatewayPaymentId : 'TXN' . strtoupper(bin2hex(random_bytes(5))));
    $certNo = 'CERT-' . date('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE investments SET status='confirmed', paid_at=?, maturity_date=?, txn_id=?, gateway_payment_id=COALESCE(NULLIF(?, ''), gateway_payment_id), certificate_no=? WHERE id=? AND status <> 'confirmed'")
            ->execute([$paidAt, $maturity, $txn, $gatewayPaymentId, $certNo, $id]);
        $pdo->prepare('DELETE FROM payouts WHERE investment_id = ?')->execute([$id]);
        $inv = get_investment($id);
        foreach (build_payouts($inv, $paidAt) as [$type, $amt, $due]) {
            $pdo->prepare('INSERT INTO payouts (investment_id,user_id,type,amount,due_date,status) VALUES (?,?,?,?,?,?)')
                ->execute([$id, $inv['user_id'], $type, $amt, $due, 'pending']);
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

    credit_referral_commission($inv);
    audit('investment.confirmed', $inv['ref_no'] . ' ' . $inv['amount']);
    notify_investment_confirmed($inv);
    return true;
}

function investment_context(array $inv): array {
    $u = db()->prepare('SELECT * FROM users WHERE id = ?'); $u->execute([$inv['user_id']]); $user = $u->fetch();
    $p = db()->prepare('SELECT * FROM plans WHERE id = ?'); $p->execute([$inv['plan_id']]); $plan = $p->fetch() ?: ['name' => 'Plan'];
    $pr = db()->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1')->fetch() ?: ['title' => setting('site_name') . ' Rental Project', 'completion_date' => setting('completion_date')];
    return [$user, $plan, $pr];
}

function notify_investment_confirmed(array $inv): void {
    [$user, $plan, $project] = investment_context($inv);
    $pdf = certificate_pdf($inv, $user, $plan, $project);
    $kycNote = in_array($user['kyc_status'], ['approved', 'submitted'], true) ? '' :
        '<p style="background:#fff7e0;border-left:4px solid ' . e(setting('primary_color')) . ';padding:12px 16px;border-radius:6px"><strong>Next step:</strong> please submit your PAN &amp; Aadhaar for KYC from your dashboard so we can release your returns without delay.</p>';
    $body = '<p>Dear ' . e($user['name']) . ',</p><p>Thank you for investing. Your payment has been <strong>received and confirmed</strong>.</p>'
        . '<table width="100%" cellpadding="8" style="border-collapse:collapse;font-size:14px;margin:16px 0">'
        . '<tr style="background:#f4f6fb"><td>Transaction ID</td><td align="right"><strong>' . e($inv['txn_id'] ?: $inv['gateway_payment_id']) . '</strong></td></tr>'
        . '<tr><td>Investment reference</td><td align="right"><strong>' . e($inv['ref_no']) . '</strong></td></tr>'
        . '<tr style="background:#f4f6fb"><td>Amount invested</td><td align="right"><strong>' . inr($inv['amount'], 2) . '</strong></td></tr>'
        . '<tr><td>Plan</td><td align="right">' . e($plan['name']) . ' · ' . e($inv['roi']) . '% p.a.</td></tr>'
        . '<tr style="background:#f4f6fb"><td>Total projected return</td><td align="right">' . inr($inv['expected_return'], 2) . '</td></tr>'
        . '<tr><td>Maturity date</td><td align="right">' . dt($inv['maturity_date']) . '</td></tr></table>'
        . '<p>Your <strong>investment certificate (PDF)</strong> is attached to this email for the safety of your funds. You can also download it any time from your dashboard.</p>'
        . $kycNote
        . '<p><a href="' . e(site_url('dashboard.php')) . '" style="display:inline-block;background:' . e(setting('dark_color')) . ';color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none">Open my dashboard</a></p>';
    send_mail($user['email'], 'Investment confirmed — Txn ' . ($inv['txn_id'] ?: $inv['gateway_payment_id']), $body,
        [['name' => 'Certificate-' . $inv['ref_no'] . '.pdf', 'data' => $pdf, 'type' => 'application/pdf']], 'Investment confirmed');
    if ($n = trim(setting('notify_email')))
        send_mail($n, 'New confirmed investment ' . $inv['ref_no'], '<p>' . e($user['name']) . ' invested <strong>' . inr($inv['amount']) . '</strong> (' . e($plan['name']) . '). Ref ' . e($inv['ref_no']) . '.</p>');
}

function kyc_masked_aadhaar(array $u): string {
    return $u['aadhaar_last4'] ? 'XXXX XXXX ' . $u['aadhaar_last4'] : '—';
}
