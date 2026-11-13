<?php
require __DIR__ . '/inc/bootstrap.php';
if (!is_post()) redirect('index.php#contact');
csrf_verify();
if (post('website') !== '') redirect('index.php'); // honeypot
if (!empty($_SESSION['last_contact']) && time() - $_SESSION['last_contact'] < 30) { flash('bad', 'Please wait a moment before sending another message.'); redirect('index.php#contact'); }
$name = mb_substr(post('name'), 0, 100); $email = post('email'); $phone = mb_substr(post('phone'), 0, 20); $msg = mb_substr(post('message'), 0, 2000);
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $msg === '') { flash('bad', 'Please fill in your name, a valid email and a message.'); redirect('index.php#contact'); }
db()->prepare('INSERT INTO messages (name,email,phone,message,is_read,created_at) VALUES (?,?,?,?,0,?)')->execute([$name, $email, $phone, $msg, now()]);
$_SESSION['last_contact'] = time();
if ($n = trim(setting('notify_email')))
    send_mail($n, 'New enquiry from ' . $name, '<p><strong>' . e($name) . '</strong> (' . e($email) . ', ' . e($phone) . ')</p><p>' . nl2br(e($msg)) . '</p>');
flash('ok', 'Thank you! Your message has been received — we will contact you soon.');
redirect('index.php#contact');
