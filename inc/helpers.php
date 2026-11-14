<?php
/**
 * Core helpers: escaping, URLs, session, CSRF, flash, auth, uploads, formatting, crypto.
 */

// ---- Base path detection (works in a sub-folder or at domain root) ----
(function () {
    $override = $GLOBALS['CFG']['base_path'] ?? null;
    if ($override !== null) { define('BASE', rtrim($override, '/')); return; }
    $doc = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $root = ROOT;
    define('BASE', ($doc !== '' && stripos($root, $doc) === 0) ? substr($root, strlen($doc)) : '');
})();

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('INVSESSID');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return BASE . '/' . ltrim($p, '/'); }
function asset(string $p): string { return url('assets/' . ltrim($p, '/')); }
function media(?string $p): string { return $p ? url($p) : ''; }
function site_url(string $p = ''): string {
    $base = function_exists('setting') ? trim((string)setting('site_url')) : '';
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $base = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE;
    }
    return rtrim($base, '/') . '/' . ltrim($p, '/');
}
function redirect(string $to): void {
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to)));
    exit;
}
function post(string $k, string $d = ''): string { return isset($_POST[$k]) && !is_array($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function get(string $k, string $d = ''): string { return isset($_GET[$k]) && !is_array($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function now(): string { return date('Y-m-d H:i:s'); }

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---- Flash messages ----
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): string {
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) $out .= '<div class="alert alert-' . e($t) . '">' . e($m) . '</div>';
    unset($_SESSION['flash']);
    return $out;
}

// ---- Auth ----
function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $row = $st->fetch();
    if ($row && $row['status'] === 'active') return $row;
    unset($_SESSION['uid']);
    return null;
}
function require_login(): array {
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Please log in to continue.');
        redirect('login.php');
    }
    return $u;
}
function require_admin(): array {
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') redirect('admin/login.php');
    return $u;
}
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([now(), $u['id']]);
}
function audit(string $action, string $detail = ''): void {
    try {
        $u = current_user();
        db()->prepare('INSERT INTO audit (user_id, action, detail, ip, created_at) VALUES (?,?,?,?,?)')
            ->execute([$u['id'] ?? null, $action, mb_substr($detail, 0, 500), client_ip(), now()]);
    } catch (Throwable $e) { /* never break the request for logging */ }
}

// ---- Rate limiting for logins ----
function login_blocked(string $email): bool {
    $since = date('Y-m-d H:i:s', time() - 900);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE created_at > ? AND (ip = ? OR email = ?)');
    $st->execute([$since, client_ip(), strtolower($email)]);
    return (int)$st->fetchColumn() >= 8;
}
function login_failed(string $email): void {
    db()->prepare('INSERT INTO login_attempts (ip, email, created_at) VALUES (?,?,?)')->execute([client_ip(), strtolower($email), now()]);
}
function login_clear(string $email): void {
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([strtolower($email)]);
}

// ---- Formatting ----
function inr($n, int $dec = 0): string {
    $n = (float)$n; $neg = $n < 0; $n = abs($n);
    $s = number_format($n, $dec, '.', '');
    $parts = explode('.', $s);
    $i = $parts[0]; $f = $parts[1] ?? '';
    $last = substr($i, -3); $rest = substr($i, 0, -3);
    if ($rest !== '') $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',';
    return ($neg ? '-' : '') . '₹' . $rest . $last . ($dec ? '.' . $f : '');
}
function inr_short($n): string {
    $n = (float)$n;
    if ($n >= 1e7) return '₹' . rtrim(rtrim(number_format($n / 1e7, 2), '0'), '.') . ' Cr';
    if ($n >= 1e5) return '₹' . rtrim(rtrim(number_format($n / 1e5, 2), '0'), '.') . ' L';
    return inr($n);
}
function dt(?string $d, string $fmt = 'd M Y'): string { return $d ? date($fmt, strtotime($d)) : '—'; }
function status_badge(string $s): string {
    $map = ['confirmed' => 'ok', 'paid' => 'ok', 'approved' => 'ok', 'active' => 'ok', 'pending' => 'warn', 'submitted' => 'info',
            'rejected' => 'bad', 'cancelled' => 'bad', 'none' => 'muted', 'disabled' => 'bad'];
    return '<span class="badge badge-' . ($map[$s] ?? 'muted') . '">' . e(ucfirst($s)) . '</span>';
}
function paginate_sql(int $page, int $per = 20): string { return ' LIMIT ' . $per . ' OFFSET ' . max(0, ($page - 1) * $per); }

// ---- Crypto for sensitive data (Aadhaar) ----
function app_key(): string { return hash('sha256', (string)($GLOBALS['CFG']['app_key'] ?? 'insecure'), true); }
function enc(string $plain): string {
    $iv = random_bytes(12);
    $c = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $c);
}
function dec(?string $blob): string {
    if (!$blob) return '';
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 29) return '';
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}

// ---- Validation ----
function valid_pan(string $p): bool { return (bool)preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $p); }
function valid_aadhaar(string $a): bool {
    if (!preg_match('/^[2-9][0-9]{11}$/', $a)) return false;
    // Verhoeff checksum
    static $d = [[0,1,2,3,4,5,6,7,8,9],[1,2,3,4,0,6,7,8,9,5],[2,3,4,0,1,7,8,9,5,6],[3,4,0,1,2,8,9,5,6,7],[4,0,1,2,3,9,5,6,7,8],
        [5,9,8,7,6,0,4,3,2,1],[6,5,9,8,7,1,0,4,3,2],[7,6,5,9,8,2,1,0,4,3],[8,7,6,5,9,3,2,1,0,4],[9,8,7,6,5,4,3,2,1,0]];
    static $p = [[0,1,2,3,4,5,6,7,8,9],[1,5,7,6,2,8,3,0,9,4],[5,8,0,3,7,9,6,1,4,2],[8,9,1,6,0,4,3,5,2,7],[9,4,5,3,1,2,6,8,7,0],
        [4,2,8,6,5,7,3,9,0,1],[2,7,9,3,8,0,6,4,1,5],[7,0,4,6,9,1,3,2,5,8]];
    $c = 0; $rev = strrev($a);
    for ($i = 0; $i < 12; $i++) $c = $d[$c][$p[$i % 8][(int)$rev[$i]]];
    return $c === 0;
}
function valid_ifsc(string $s): bool { return (bool)preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $s); }
function valid_phone(string $s): bool { return (bool)preg_match('/^[6-9][0-9]{9}$/', $s); }

// ---- File uploads ----
function save_upload(string $field, string $dir, array $exts, int $maxMb = 8): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (code ' . $f['error'] . '). The file may be too large.');
    if ($f['size'] > $maxMb * 1048576) throw new RuntimeException('File is larger than ' . $maxMb . ' MB.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) throw new RuntimeException('File type not allowed. Allowed: ' . implode(', ', $exts));
    $map = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif'],
            'pdf' => ['application/pdf'], 'mp4' => ['video/mp4'], 'webm' => ['video/webm'], 'ico' => ['image/x-icon', 'image/vnd.microsoft.icon']];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($map[$ext]) || !in_array($mime, $map[$ext], true)) throw new RuntimeException('File content does not match its extension.');
    $abs = ROOT . '/' . trim($dir, '/');
    if (!is_dir($abs) && !mkdir($abs, 0755, true)) throw new RuntimeException('Cannot create upload folder.');
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $abs . '/' . $name)) throw new RuntimeException('Could not save the uploaded file.');
    return trim($dir, '/') . '/' . $name;
}
function delete_file(?string $rel): void {
    // only ever delete user-uploaded / private files, never bundled assets
    if ($rel && strpos($rel, '..') === false && preg_match('#^(uploads|storage)/#', $rel) && is_file(ROOT . '/' . $rel)) @unlink(ROOT . '/' . $rel);
}

// ---- Content helpers ----
function items(string $type): array {
    $st = db()->prepare('SELECT * FROM items WHERE type = ? AND is_active = 1 ORDER BY sort_order, id');
    $st->execute([$type]);
    return $st->fetchAll();
}
function video_embed(array $v): string {
    $src = $v['source'] ?? 'youtube'; $u = (string)($v['url'] ?? '');
    if ($src === 'file' && !empty($v['file'])) {
        $poster = !empty($v['thumb']) ? ' poster="' . e(media($v['thumb'])) . '"' : '';
        return '<video controls preload="metadata" playsinline' . $poster . '><source src="' . e(media($v['file'])) . '"></video>';
    }
    if (preg_match('~(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{11})~', $u, $m))
        return '<iframe src="https://www.youtube-nocookie.com/embed/' . e($m[1]) . '" title="' . e($v['title'] ?? 'Video') . '" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>';
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $u, $m))
        return '<iframe src="https://player.vimeo.com/video/' . e($m[1]) . '" title="' . e($v['title'] ?? 'Video') . '" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>';
    return '<div class="video-missing">Video unavailable</div>';
}
/** Sanitise admin-authored HTML for CMS pages (admin is trusted, but strip scripts/handlers as defence in depth). */
function clean_html(string $html): string {
    $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#javascript\s*:#i', '', $html);
    return $html;
}
function slugify(string $s): string { $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-')); return $s ?: 'page'; }
