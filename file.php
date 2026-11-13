<?php
/** Gated viewer for private KYC / payment-proof files. Owner or admin only. */
require __DIR__ . '/inc/bootstrap.php';
$me = require_login();
$type = get('t'); $uid = (int)get('u'); $iid = (int)get('i');
$rel = null; $ownerId = 0;
if (in_array($type, ['pan', 'aadhaar'], true)) {
    $st = db()->prepare('SELECT id, pan_file, aadhaar_file FROM users WHERE id = ?'); $st->execute([$uid]); $r = $st->fetch();
    if ($r) { $ownerId = (int)$r['id']; $rel = $type === 'pan' ? $r['pan_file'] : $r['aadhaar_file']; }
} elseif ($type === 'proof') {
    $inv = get_investment($iid);
    if ($inv) { $ownerId = (int)$inv['user_id']; $rel = $inv['proof_file']; }
}
if (!$rel || ($ownerId !== (int)$me['id'] && $me['role'] !== 'admin') || strpos($rel, '..') !== false || !is_file(ROOT . '/' . $rel)) { http_response_code(404); exit('Not found.'); }
if ($me['role'] === 'admin' && $ownerId !== (int)$me['id']) audit('file.viewed', "$type user/inv $ownerId");
$mime = (new finfo(FILEINFO_MIME_TYPE))->file(ROOT . '/' . $rel);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
header('Cache-Control: private, no-store');
readfile(ROOT . '/' . $rel);
