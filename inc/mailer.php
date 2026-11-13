<?php
/**
 * Mail: log / PHP mail() / SMTP (no external libraries). Supports PDF attachments.
 */
function mail_template(string $title, string $bodyHtml): string {
    $brand = e(setting('site_name')); $dark = e(setting('dark_color')); $gold = e(setting('primary_color'));
    return '<!doctype html><html><body style="margin:0;background:#f2f4f8;font-family:Arial,Helvetica,sans-serif;color:#1b2536">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f8;padding:24px 0"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden">'
        . '<tr><td style="background:' . $dark . ';padding:24px 32px;color:#fff;font-size:22px;font-weight:bold">' . $brand . '</td></tr>'
        . '<tr><td style="height:4px;background:' . $gold . '"></td></tr>'
        . '<tr><td style="padding:32px"><h2 style="margin:0 0 16px;color:' . $dark . '">' . e($title) . '</h2>' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:20px 32px;background:#f8f9fc;color:#6b7589;font-size:12px">This is an automated message from ' . $brand . '. '
        . 'Questions? Reply to this email or contact ' . e(setting('contact_email')) . '.</td></tr></table></td></tr></table></body></html>';
}

/** @param array $attachments [['name'=>'x.pdf','data'=>binary,'type'=>'application/pdf']] */
function send_mail(string $to, string $subject, string $bodyHtml, array $attachments = [], ?string $title = null): bool {
    $html = mail_template($title ?? $subject, $bodyHtml);
    $mode = setting('mail_mode', 'log');
    $fromEmail = trim(setting('mail_from')) ?: ('no-reply@' . preg_replace('/^www\./', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]));
    $fromName = trim(setting('mail_from_name')) ?: setting('site_name');
    try {
        if ($mode === 'log') return mail_log($to, $subject, $html, $attachments);
        $msg = build_mime($to, $fromEmail, $fromName, $subject, $html, $attachments);
        if ($mode === 'smtp') return smtp_deliver($fromEmail, $to, $msg);
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $msg['body'], $msg['headers']);
    } catch (Throwable $e) {
        error_log('[MAIL] ' . $e->getMessage());
        return false;
    }
}
function mail_log(string $to, string $subject, string $html, array $att): bool {
    $dir = ROOT . '/storage/logs'; if (!is_dir($dir)) mkdir($dir, 0755, true);
    $text = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<(style|script).*?</\1>#is', '', $html))));
    $names = implode(', ', array_map(fn($a) => $a['name'] . ' (' . strlen($a['data']) . ' bytes)', $att));
    file_put_contents($dir . '/mail.log', '[' . now() . "] TO: $to | SUBJECT: $subject | ATTACH: " . ($names ?: '-') . "\n$text\n" . str_repeat('-', 60) . "\n", FILE_APPEND);
    return true;
}
function build_mime(string $to, string $fromEmail, string $fromName, string $subject, string $html, array $att): array {
    $b = 'b' . bin2hex(random_bytes(8));
    $h = "MIME-Version: 1.0\r\nFrom: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\nReply-To: $fromEmail\r\n"
       . "To: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . ">\r\n"
       . "Content-Type: multipart/mixed; boundary=\"$b\"\r\n";
    $body = "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n";
    foreach ($att as $a) {
        $body .= "--$b\r\nContent-Type: " . ($a['type'] ?? 'application/octet-stream') . "; name=\"" . $a['name'] . "\"\r\n"
              . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $a['name'] . "\"\r\n\r\n" . chunk_split(base64_encode($a['data'])) . "\r\n";
    }
    $body .= "--$b--\r\n";
    return ['headers' => $h, 'body' => $body, 'raw' => $h . "\r\n" . $body];
}
function smtp_deliver(string $from, string $to, array $msg): bool {
    $host = trim(setting('smtp_host')); $port = (int)setting('smtp_port', 587); $sec = setting('smtp_secure', 'tls');
    $remote = ($sec === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $en, $es, 20, STREAM_CLIENT_CONNECT);
    if (!$fp) throw new RuntimeException("SMTP connect failed: $es");
    stream_set_timeout($fp, 20);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd = function (string $c, array $ok) use ($fp, $read) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP error after "' . substr($c, 0, 20) . '": ' . trim($r));
        return $r;
    };
    $cmd('', [220]);
    $ehlo = 'EHLO ' . (explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
    $cmd($ehlo, [250]);
    if ($sec === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS failed');
        $cmd($ehlo, [250]);
    }
    if (trim(setting('smtp_user')) !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(setting('smtp_user')), [334]);
        $cmd(base64_encode(setting('smtp_pass')), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], "\r\n", $msg['raw']));
    fwrite($fp, $data . "\r\n.\r\n");
    $cmd('', [250]);
    fwrite($fp, "QUIT\r\n"); fclose($fp);
    return true;
}
