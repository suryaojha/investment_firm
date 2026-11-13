<?php
require __DIR__ . '/inc/bootstrap.php';
// Logout via POST+CSRF only would be stricter; GET logout is low-risk here but we regenerate the session fully.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
session_start();
flash('ok', 'You have been logged out.');
redirect('login.php');
