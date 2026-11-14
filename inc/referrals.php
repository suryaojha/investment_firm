<?php
/** Referral wallet: credits are earned only after the referred user's first confirmed plan purchase. */
function new_referral_code(): string {
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE referral_code = ?'); $st->execute([$code]);
    } while ((int)$st->fetchColumn() > 0);
    return $code;
}
function credit_referral_commission(array $investment): void {
    if (setting('referral_enabled', '1') !== '1') return;
    $pdo = db();
    $st = $pdo->prepare('SELECT id, referred_by FROM users WHERE id = ?'); $st->execute([$investment['user_id']]); $buyer = $st->fetch();
    if (!$buyer || empty($buyer['referred_by']) || (int)$buyer['referred_by'] === (int)$buyer['id']) return;
    $st = $pdo->prepare('SELECT COUNT(*) FROM referral_commissions WHERE referred_user_id = ?'); $st->execute([$buyer['id']]);
    if ((int)$st->fetchColumn() > 0) return; // only the first confirmed plan purchase qualifies
    $rate = max(0, min(50, (float)setting('referral_rate', '5')));
    $commission = round((float)$investment['amount'] * $rate / 100, 2);
    if ($commission <= 0) return;
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO referral_commissions (referrer_id,referred_user_id,investment_id,amount,rate,status,created_at) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$buyer['referred_by'], $buyer['id'], $investment['id'], $commission, $rate, 'credited', now()]);
        $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([$commission, $buyer['referred_by']]);
        $pdo->prepare('INSERT INTO wallet_transactions (user_id,investment_id,type,amount,description,created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$buyer['referred_by'], $investment['id'], 'credit', $commission, 'Referral reward for first confirmed plan purchase', now()]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
function refund_investment_wallet(array $investment): void {
    $amount = round((float)($investment['wallet_used'] ?? 0), 2);
    if ($amount <= 0) return;
    $pdo = db();
    $st = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE investment_id = ? AND type = 'refund'");
    $st->execute([$investment['id']]);
    if ((int)$st->fetchColumn() > 0) return;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([$amount, $investment['user_id']]);
        $pdo->prepare('INSERT INTO wallet_transactions (user_id,investment_id,type,amount,description,created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$investment['user_id'], $investment['id'], 'refund', $amount, 'Wallet credit restored after cancelled/rejected plan purchase', now()]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
