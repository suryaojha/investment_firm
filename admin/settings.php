<?php
require __DIR__ . '/_layout.php';
$schema = settings_schema();

if (is_post()) {
    csrf_verify();
    $errors = [];
    foreach ($schema as $g) foreach ($g['fields'] as $f) {
        [$k, $label, $type] = $f;
        try {
            if ($type === 'image') {
                $new = save_upload($k, 'uploads/images', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico'], 5);
                if ($new) { delete_file(setting($k) ?: null); set_setting($k, $new); }
                elseif (!empty($_POST['remove_' . $k])) { delete_file(setting($k) ?: null); set_setting($k, ''); }
                continue;
            }
            if ($type === 'toggle') { if (isset($_POST[$k])) set_setting($k, $_POST[$k] === '1' ? '1' : '0'); continue; }
            if (!isset($_POST[$k])) continue;
            $val = is_array($_POST[$k]) ? '' : (string)$_POST[$k];
            if ($type === 'password') { if ($val === '') continue; if ($val === '__clear__') $val = ''; }
            elseif ($type === 'textarea' || $k === 'custom_head' || $k === 'custom_css') $val = trim(str_replace("\r\n", "\n", $val));
            else $val = trim($val);
            if ($type === 'color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $val)) { $errors[] = "$label must be a hex colour like #c8a24a."; continue; }
            if ($type === 'number' && $val !== '' && !is_numeric($val)) { $errors[] = "$label must be a number."; continue; }
            if ($type === 'select' && !isset($f[5][$val])) continue;
            if ($k === 'site_url' && $val !== '' && !preg_match('#^https?://#i', $val)) { $errors[] = 'Public site URL must start with http:// or https://'; continue; }
            if ($type === 'date' && $val !== '' && !strtotime($val)) { $errors[] = "$label is not a valid date."; continue; }
            set_setting($k, $val);
        } catch (Throwable $e) { $errors[] = $label . ': ' . $e->getMessage(); }
    }
    audit('settings.saved');
    foreach ($errors as $er) flash('bad', $er);
    if (!$errors) flash('ok', 'Settings saved. Changes are live on the website.');
    $tab = post('tab', 'brand');
    redirect('admin/settings.php#' . $tab);
}

if (is_post() === false && get('test_mail')) { /* handled below */ }
if (get('test_mail') === '1') {
    $ok = send_mail($ADMIN['email'], 'Test email from ' . setting('site_name'), '<p>If you can read this, your email settings work. 🎉</p>');
    flash($ok ? 'ok' : 'bad', $ok ? 'Test email sent to ' . $ADMIN['email'] . ' (in "Log only" mode, check storage/logs/mail.log).' : 'Test email failed — check SMTP settings and storage/logs/php_error.log.');
    redirect('admin/settings.php#mail');
}
admin_header('Settings & Branding');
?>
<form method="post" enctype="multipart/form-data" class="settings" id="settings-form">
  <?= csrf_field() ?><input type="hidden" name="tab" id="tab-field" value="brand">
  <div class="tabs" id="tabs">
    <?php foreach ($schema as $key => $g): ?><button type="button" data-tab="<?= e($key) ?>"><?= $g['icon'] ?> <?= e($g['title']) ?></button><?php endforeach; ?>
  </div>
  <?php foreach ($schema as $key => $g): ?>
  <div class="card tabpane" data-pane="<?= e($key) ?>" hidden>
    <div class="card-head"><h2><?= $g['icon'] ?> <?= e($g['title']) ?></h2>
      <?php if ($key === 'mail'): ?><a class="btn btn-sm btn-ghost" href="?test_mail=1">Send test email</a><?php endif; ?></div>
    <?php if ($key === 'mail'): ?>
      <div class="alert alert-info"><b>Quick SMTP setup:</b> choose a provider to auto-fill host/port, set mode to “SMTP”, enter username &amp; password, <b>Save</b>, then press “Send test email”.
        <div class="btn-row" id="smtp-presets">
          <button type="button" class="btn btn-sm btn-ghost" data-h="smtp.gmail.com" data-p="587" data-s="tls">Gmail</button>
          <button type="button" class="btn btn-sm btn-ghost" data-h="smtp-relay.brevo.com" data-p="587" data-s="tls">Brevo</button>
          <button type="button" class="btn btn-sm btn-ghost" data-h="smtp.zoho.in" data-p="587" data-s="tls">Zoho (India)</button>
          <button type="button" class="btn btn-sm btn-ghost" data-h="smtp.hostinger.com" data-p="465" data-s="ssl">Hostinger</button>
          <button type="button" class="btn btn-sm btn-ghost" data-h="smtp.office365.com" data-p="587" data-s="tls">Outlook / 365</button>
        </div>
        <small>Gmail needs an <b>App Password</b> (Google Account → Security → 2-Step Verification → App passwords), not your normal password.</small></div>
    <?php endif; ?>
    <div class="aform">
    <?php foreach ($g['fields'] as $f): [$k, $label, $type, $def, $help] = $f; $v = setting($k); $opts = $f[5] ?? []; ?>
      <div class="field <?= $type === 'textarea' ? 'wide' : '' ?>">
        <?php if ($type === 'toggle'): ?>
          <input type="hidden" name="<?= e($k) ?>" value="0">
          <label class="check"><input type="checkbox" name="<?= e($k) ?>" value="1" <?= $v === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
        <?php else: ?>
          <label for="s_<?= e($k) ?>"><?= e($label) ?></label>
          <?php if ($type === 'textarea'): ?><textarea id="s_<?= e($k) ?>" name="<?= e($k) ?>" rows="<?= in_array($k, ['custom_css', 'custom_head'], true) ? 8 : 4 ?>"><?= e($v) ?></textarea>
          <?php elseif ($type === 'select'): ?><select id="s_<?= e($k) ?>" name="<?= e($k) ?>"><?php foreach ($opts as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= $v === (string)$ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
          <?php elseif ($type === 'color'): ?><div class="colorrow"><input type="color" id="s_<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($v) ?>"><code><?= e($v) ?></code></div>
          <?php elseif ($type === 'image'): ?>
            <?php if ($v): ?><div class="cur-file"><img src="<?= e(media($v)) ?>" alt=""><label class="check"><input type="checkbox" name="remove_<?= e($k) ?>" value="1"> remove</label></div><?php endif; ?>
            <input id="s_<?= e($k) ?>" type="file" name="<?= e($k) ?>" accept="image/*">
          <?php elseif ($type === 'password'): ?><input id="s_<?= e($k) ?>" type="password" name="<?= e($k) ?>" value="" autocomplete="new-password" placeholder="<?= $v !== '' ? '•••••••• (saved — leave blank to keep)' : 'not set' ?>">
          <?php else: ?><input id="s_<?= e($k) ?>" type="<?= $type === 'date' ? 'date' : ($type === 'number' ? 'number' : 'text') ?>" name="<?= e($k) ?>" value="<?= e($v) ?>" <?= $type === 'number' ? 'step="any"' : '' ?>>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($help): ?><small><?= e($help) ?></small><?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="savebar"><button class="btn btn-primary btn-lg">💾 Save all settings</button></div>
</form>
<?php admin_footer(); ?>
