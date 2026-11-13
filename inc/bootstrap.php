<?php
/**
 * Bootstrap: config, session, helpers, database.
 */
define('ROOT', str_replace('\\', '/', dirname(__DIR__)));
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

$cfgFile = ROOT . '/config.local.php';
define('INSTALLED', is_file($cfgFile));
$GLOBALS['CFG'] = INSTALLED ? (require $cfgFile) : [];

ini_set('display_errors', !empty($GLOBALS['CFG']['debug']) ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(ROOT . '/storage/logs')) ini_set('error_log', ROOT . '/storage/logs/php_error.log');

require ROOT . '/inc/helpers.php';

if (!INSTALLED) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: ' . BASE . '/install.php');
        exit;
    }
} else {
    require ROOT . '/inc/db.php';
    require ROOT . '/inc/settings_schema.php';
    require ROOT . '/inc/mailer.php';
    require ROOT . '/inc/pdf.php';
    require ROOT . '/inc/invest.php';
    require ROOT . '/inc/payments.php';
}

set_exception_handler(function (Throwable $e) {
    error_log('[UNCAUGHT] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty($GLOBALS['CFG']['debug'])) {
        echo '<pre style="padding:20px">' . htmlspecialchars((string)$e) . '</pre>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Something went wrong</title>'
           . '<body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f6fb;color:#0a1930">'
           . '<div style="text-align:center;padding:24px"><h1>Something went wrong</h1><p>Please try again in a moment.</p><p><a href="' . BASE . '/">Back to home</a></p></div></body>';
    }
    exit;
});
