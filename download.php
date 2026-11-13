<?php
require __DIR__ . '/inc/bootstrap.php';
$st = db()->prepare('SELECT * FROM documents WHERE id = ? AND is_active = 1'); $st->execute([(int)get('id')]); $d = $st->fetch();
if (!$d || !$d['file']) { http_response_code(404); exit('Document not found.'); }
if (!$d['is_public'] && !current_user()) { flash('info', 'Please log in to download this document.'); redirect('login.php'); }
$path = ROOT . '/' . $d['file'];
if (strpos($d['file'], '..') !== false || !is_file($path)) { http_response_code(404); exit('File missing.'); }
$name = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $d['title']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
