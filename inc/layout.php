<?php
/**
 * Public site layout: header + footer.
 */
function hex_darken(string $hex, float $f = 0.85): string {
    $hex = ltrim($hex, '#'); if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $c = array_map(fn($x) => max(0, min(255, (int)round(hexdec($x) * $f))), str_split($hex, 2));
    return sprintf('#%02x%02x%02x', ...$c);
}
function hex_rgb(string $hex): string {
    $hex = ltrim($hex, '#'); if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
}
function safe_color(string $c, string $fallback): string { return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback; }

function theme_css(): string {
    $gold = safe_color(setting('primary_color'), '#c8a24a'); $dark = safe_color(setting('dark_color'), '#0a1930');
    return ':root{--gold:' . $gold . ';--gold-d:' . hex_darken($gold, .82) . ';--gold-rgb:' . hex_rgb($gold) . ';--dark:' . $dark . ';--dark-2:' . hex_darken($dark, 1.35) . ';--dark-rgb:' . hex_rgb($dark) . ';}';
}

function lang_names(): array {
    return ['en' => 'English', 'hi' => 'हिन्दी (Hindi)', 'mr' => 'मराठी (Marathi)', 'gu' => 'ગુજરાતી (Gujarati)', 'bn' => 'বাংলা (Bengali)', 'ta' => 'தமிழ் (Tamil)',
        'te' => 'తెలుగు (Telugu)', 'kn' => 'ಕನ್ನಡ (Kannada)', 'ml' => 'മലയാളം (Malayalam)', 'pa' => 'ਪੰਜਾਬੀ (Punjabi)', 'ur' => 'اردو (Urdu)', 'or' => 'ଓଡ଼ିଆ (Odia)',
        'as' => 'অসমীয়া (Assamese)', 'ne' => 'नेपाली (Nepali)', 'ar' => 'العربية (Arabic)', 'fr' => 'Français', 'es' => 'Español', 'de' => 'Deutsch', 'zh-CN' => '中文 (Chinese)'];
}
function lang_switcher(): string {
    if (setting('translate_on') !== '1') return '';
    $names = lang_names(); $codes = array_filter(array_map('trim', explode(',', setting('translate_langs'))));
    $cur = 'en';
    if (!empty($_COOKIE['googtrans']) && preg_match('#^/en/([A-Za-z-]+)$#', $_COOKIE['googtrans'], $m)) $cur = $m[1];
    $o = '<select class="lang-select notranslate" aria-label="Language" onchange="setLang(this.value)"><option value="en"' . ($cur === 'en' ? ' selected' : '') . '>🌐 English</option>';
    foreach ($codes as $c) if (isset($names[$c])) $o .= '<option value="' . e($c) . '"' . ($cur === $c ? ' selected' : '') . '>' . e($names[$c]) . '</option>';
    return $o . '</select>';
}

function public_header(string $title = '', array $o = []): void {
    $u = current_user();
    if (setting('maintenance') === '1' && (!$u || $u['role'] !== 'admin')) {
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Back soon</title><body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;margin:0;background:' . e(setting('dark_color')) . ';color:#fff;text-align:center"><div><h1>' . e(setting('site_name')) . '</h1><p>We are performing scheduled maintenance. Please check back shortly.</p></div></body>';
        exit;
    }
    $site = setting('site_name');
    $full = $title ? $title . ' — ' . $site : (setting('meta_title') ?: $site . ' — ' . setting('tagline'));
    $desc = $o['desc'] ?? setting('meta_description');
    $img = setting('meta_image') ? site_url(setting('meta_image')) : '';
    $nav = ['index.php#plans' => 'Plans', 'index.php#project' => 'Project', 'index.php#videos' => 'Videos', 'documents.php' => 'Documents', 'index.php#faq' => 'FAQ', 'index.php#contact' => 'Contact'];
    $cur = basename($_SERVER['SCRIPT_NAME']);
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($full) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="<?= e(setting('dark_color')) ?>">
<meta property="og:title" content="<?= e($full) ?>"><meta property="og:description" content="<?= e($desc) ?>"><meta property="og:type" content="website">
<?php if ($img): ?><meta property="og:image" content="<?= e($img) ?>"><?php endif; ?>
<?php if (!empty($o['noindex'])): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(media(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>?v=2">
<style><?= theme_css() ?><?= str_replace('</', '<\/', (string)setting('custom_css')) ?></style>
<?php if ($ga = trim(setting('analytics_id'))): if (preg_match('/^[A-Za-z0-9-]+$/', $ga)): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
<?php endif; endif; ?>
<?= setting('custom_head') ?>
</head>
<body class="<?= e($o['body'] ?? '') ?>">
<?php if (setting('announce_on') === '1' && trim(setting('announce_text')) !== ''): ?>
<div class="announce"><?= e(setting('announce_text')) ?></div>
<?php endif; ?>
<header class="site-header" id="top">
  <div class="container nav-wrap">
    <a class="brand" href="<?= e(url('')) ?>">
      <?php if (setting('logo')): ?><img src="<?= e(media(setting('logo'))) ?>" alt="<?= e($site) ?>"><?php else: ?><span class="brand-mark">◆</span><span class="brand-text"><?= e($site) ?></span><?php endif; ?>
    </a>
    <button class="nav-toggle" aria-label="Menu" aria-expanded="false" onclick="document.body.classList.toggle('nav-open');this.setAttribute('aria-expanded',document.body.classList.contains('nav-open'))"><span></span><span></span><span></span></button>
    <nav class="main-nav">
      <?php foreach ($nav as $href => $label): ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a><?php endforeach; ?>
      <div class="nav-cta">
        <?= lang_switcher() ?>
        <?php if ($u): ?>
          <?php if ($u['role'] === 'admin'): ?><a class="btn btn-ghost-dark btn-sm" href="<?= e(url('admin/')) ?>">Admin</a><?php endif; ?>
          <a class="btn btn-ghost-dark btn-sm" href="<?= e(url('dashboard.php')) ?>">My dashboard</a>
          <a class="btn btn-gold btn-sm" href="<?= e(url('invest.php')) ?>">Invest now</a>
        <?php else: ?>
          <a class="btn btn-ghost-dark btn-sm" href="<?= e(url('login.php')) ?>">Log in</a>
          <a class="btn btn-gold btn-sm" href="<?= e(url('register.php')) ?>">Sign up &amp; invest</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>
<main>
<?php
}

function public_footer(): void {
    $site = setting('site_name');
    $pages = db()->query('SELECT slug,title FROM pages WHERE is_active = 1 AND show_in_footer = 1 ORDER BY id')->fetchAll();
    $soc = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'];
    ?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <div class="brand brand-light"><?php if (setting('logo')): ?><img src="<?= e(media(setting('logo'))) ?>" alt="<?= e($site) ?>"><?php else: ?><span class="brand-mark">◆</span><span class="brand-text"><?= e($site) ?></span><?php endif; ?></div>
      <p class="muted-l"><?= nl2br(e(setting('footer_about'))) ?></p>
      <?php if (trim(setting('partner_text'))): ?><p class="partner-line"><?= e(setting('partner_text')) ?></p><?php endif; ?>
      <div class="social">
        <?php foreach ($soc as $k => $label) if ($l = trim(setting('social_' . $k))): ?><a href="<?= e($l) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"><?= e($label) ?></a><?php endif; ?>
      </div>
    </div>
    <div>
      <h4>Quick links</h4>
      <a href="<?= e(url('index.php#plans')) ?>">Investment plans</a>
      <a href="<?= e(url('index.php#project')) ?>">The project</a>
      <a href="<?= e(url('documents.php')) ?>">Documents</a>
      <a href="<?= e(url('register.php')) ?>">Create account</a>
      <a href="<?= e(url('login.php')) ?>">Investor login</a>
    </div>
    <div>
      <h4>Legal</h4>
      <?php foreach ($pages as $p): ?><a href="<?= e(url('page.php?slug=' . $p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
    </div>
    <div>
      <h4>Contact</h4>
      <p class="muted-l"><?= nl2br(e(setting('contact_address'))) ?></p>
      <p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></p>
      <p><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p>
      <p class="muted-l small"><?= e(setting('office_hours')) ?></p>
    </div>
  </div>
  <div class="container footer-legal">
    <p><?= e(setting('footer_legal')) ?></p>
    <p>© <?= date('Y') ?> <?= e($site) ?>. <?php if (trim(setting('rera_no'))): ?>RERA: <?= e(setting('rera_no')) ?>. <?php endif; ?>All rights reserved.</p>
  </div>
</footer>
<?php if ($wa = preg_replace('/\D/', '', setting('whatsapp'))): ?>
<a class="wa-float" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 32 32" width="28" height="28" fill="#fff"><path d="M16 3C9 3 3.4 8.6 3.4 15.5c0 2.4.7 4.7 1.9 6.6L3 29l7.1-2.2c1.8 1 3.8 1.5 5.9 1.5 7 0 12.6-5.6 12.6-12.5S23 3 16 3zm0 22.7c-1.9 0-3.8-.5-5.4-1.5l-.4-.2-4.2 1.3 1.4-4-.3-.4a10 10 0 0 1-1.6-5.4C5.5 9.9 10.2 5.3 16 5.3s10.5 4.6 10.5 10.2S21.8 25.7 16 25.7zm5.7-7.6c-.3-.2-1.9-.9-2.2-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7 0a8.5 8.5 0 0 1-4.2-3.7c-.3-.6.3-.6.9-1.7.1-.2 0-.4 0-.5l-1-2.3c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4s-1.1 1.1-1.1 2.6 1.1 3 1.3 3.2 2.2 3.4 5.4 4.7c2 .8 2.8.9 3.8.7.6-.1 1.9-.8 2.1-1.5s.3-1.4.2-1.5-.3-.2-.7-.4z"/></svg>
</a>
<?php endif; ?>
<?php if (($GLOBALS['mobile_cta'] ?? false) && !current_user()): ?>
<div class="mobile-cta"><a class="btn btn-gold btn-block" href="<?= e(url('register.php')) ?>">Start investing →</a></div>
<?php endif; ?>
<?php if (setting('translate_on') === '1'): ?>
<div id="google_translate_element" style="display:none"></div>
<script>
function setLang(c){var h=location.hostname,v=c==='en'?'':'/en/'+c,x=c==='en'?';expires=Thu, 01 Jan 1970 00:00:00 GMT':'';
document.cookie='googtrans='+v+';path=/'+x;document.cookie='googtrans='+v+';path=/;domain='+h+x;location.reload();}
<?php if (!empty($_COOKIE['googtrans']) && preg_match('#^/en/[A-Za-z-]+$#', $_COOKIE['googtrans'])): ?>
function gtInit(){new google.translate.TranslateElement({pageLanguage:'en',autoDisplay:false},'google_translate_element');}
(function(){var s=document.createElement('script');s.src='https://translate.google.com/translate_a/element.js?cb=gtInit';document.head.appendChild(s);})();
<?php endif; ?>
</script>
<?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>?v=2" defer></script>
</body>
</html>
<?php
}
