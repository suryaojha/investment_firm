<?php
require __DIR__ . '/_layout.php';
$forced = !empty($ADMIN['must_change_pw']);
if (is_post()) {
    csrf_verify();
    $old = (string)($_POST['old'] ?? ''); $n1 = (string)($_POST['new'] ?? ''); $n2 = (string)($_POST['new2'] ?? '');
    $newId = trim(post('login_id'));
    if (!password_verify($old, $ADMIN['password'])) flash('bad', 'Current password is incorrect.');
    elseif (strlen($n1) < 10) flash('bad', 'New password must be at least 10 characters.');
    elseif ($n1 === $old || strtolower($n1) === 'admin') flash('bad', 'Choose a different, non-guessable password.');
    elseif ($n1 !== $n2) flash('bad', 'New passwords do not match.');
    elseif ($newId !== '' && $newId !== $ADMIN['email'] && (strlen($newId) < 3 || (function () use ($newId) { $s = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?'); $s->execute([strtolower($newId)]); return (int)$s->fetchColumn(); })()))
        flash('bad', 'That admin ID is invalid or already used.');
    else {
        db()->prepare('UPDATE users SET password = ?, must_change_pw = 0, email = ? WHERE id = ?')
            ->execute([password_hash($n1, PASSWORD_DEFAULT), $newId !== '' ? strtolower($newId) : $ADMIN['email'], $ADMIN['id']]);
        audit('admin.password_changed');
        flash('ok', 'Credentials updated.');
        redirect('admin/');
    }
    redirect('admin/password.php');
}
admin_header('Change admin password');
?>
<div class="card" style="max-width:560px">
  <?php if ($forced): ?><div class="alert alert-bad"><b>Security:</b> you are using the default login. Set a new password to continue.</div><?php endif; ?>
  <form method="post" class="aform">
    <?= csrf_field() ?>
    <div class="field wide"><label>Current password</label><input type="password" name="old" required autocomplete="current-password"></div>
    <div class="field wide"><label>Admin ID / email (optional change)</label><input name="login_id" value="<?= e($ADMIN['email']) ?>" autocomplete="username"><small>You log in with this. Use a real email so you can reset the password later.</small></div>
    <div class="field wide"><label>New password (min 10 characters)</label><input type="password" name="new" minlength="10" required autocomplete="new-password"></div>
    <div class="field wide"><label>Repeat new password</label><input type="password" name="new2" minlength="10" required autocomplete="new-password"></div>
    <div class="field wide"><button class="btn btn-primary">Save</button></div>
  </form>
</div>
<?php admin_footer(); ?>
