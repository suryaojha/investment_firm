<?php
/**
 * Every editable site setting. The admin Settings page is generated from this list,
 * so adding a field here makes it editable in the panel automatically.
 * type: text | textarea | color | image | toggle | password | select | date | number
 */
function settings_schema(): array {
    return [
        'brand' => ['title' => 'Brand & Identity', 'icon' => '🏷️', 'fields' => [
            ['site_name', 'Company / site name', 'text', 'Aurum Estates', 'Shown in header, emails and certificates.'],
            ['tagline', 'Tagline', 'text', 'Build wealth with real estate that earns rent', ''],
            ['logo', 'Logo (PNG/JPG/WebP)', 'image', '', 'Leave empty to show the site name as text.'],
            ['favicon', 'Favicon', 'image', '', ''],
            ['primary_color', 'Accent colour', 'color', '#c8a24a', 'Buttons, highlights.'],
            ['dark_color', 'Dark / brand colour', 'color', '#0a1930', 'Header, hero, footer.'],
            ['site_url', 'Public site URL', 'text', '', 'e.g. https://yourdomain.com — used in emails. Leave empty to auto-detect.'],
            ['partner_text', 'Partner / backed-by line', 'text', '', 'Optional. Only add a partner name that your client has written permission to display.'],
        ]],
        'hero' => ['title' => 'Home Page Hero', 'icon' => '🏙️', 'fields' => [
            ['announce_on', 'Show top announcement bar', 'toggle', '1', ''],
            ['announce_text', 'Announcement text', 'text', '🔥 Early-investor bonus returns are open for a limited time — secure your allocation today.', ''],
            ['hero_badge', 'Hero small badge', 'text', 'Phase 1 now open · Completion Dec 2028', ''],
            ['hero_title', 'Hero headline', 'text', 'Own a share of a ₹15,000 Cr rental township', 'Wrap a word in [square brackets] to highlight it in gold.'],
            ['hero_subtitle', 'Hero sub-headline', 'textarea', 'Invest in a large-scale MIDC-corridor residential & commercial rental development. Earn structured, regular returns — backed by a registered builder and fully documented.', ''],
            ['hero_image', 'Hero background image', 'image', '', 'Wide image, at least 1600px. A default gradient is used if empty.'],
            ['hero_cta1', 'Primary button text', 'text', 'Start Investing', ''],
            ['hero_cta2', 'Secondary button text', 'text', 'View Plans', ''],
            ['project_value', 'Project size label', 'text', '₹15,000 Cr', 'Big number in the hero card.'],
            ['completion_date', 'Project completion date', 'date', '2028-12-31', 'Drives the live countdown.'],
            ['early_bird_until', 'Early-bird offer ends on', 'date', '', 'Leave empty to hide the offer countdown.'],
            ['early_bird_label', 'Early-bird label', 'text', 'Early-investor bonus ends in', ''],
            ['trust_badges', 'Trust badges (one per line)', 'textarea', "RERA Registered\nBank-verified documents\nTransparent payout tracking\nSecure & encrypted KYC", 'Shown under the hero.'],
        ]],
        'sections' => ['title' => 'Section Visibility & Text', 'icon' => '🧩', 'fields' => [
            ['show_stats', 'Show statistics strip', 'toggle', '1', ''],
            ['show_why', 'Show "Why invest" section', 'toggle', '1', ''],
            ['show_plans', 'Show plans section', 'toggle', '1', ''],
            ['show_calc', 'Show returns calculator', 'toggle', '1', ''],
            ['show_projects', 'Show project showcase', 'toggle', '1', ''],
            ['show_timeline', 'Show timeline / roadmap', 'toggle', '1', ''],
            ['show_how', 'Show "How it works"', 'toggle', '1', ''],
            ['show_gallery', 'Show photo gallery', 'toggle', '1', ''],
            ['show_videos', 'Show videos', 'toggle', '1', ''],
            ['show_docs', 'Show documents', 'toggle', '1', ''],
            ['show_testimonials', 'Show testimonials', 'toggle', '1', ''],
            ['show_faq', 'Show FAQ', 'toggle', '1', ''],
            ['show_contact', 'Show contact form', 'toggle', '1', ''],
            ['why_title', '"Why invest" heading', 'text', 'Why investors choose us', ''],
            ['why_sub', '"Why invest" sub-text', 'textarea', 'Rental real estate in a fast-growing industrial corridor delivers steady demand, long-term appreciation and predictable income.', ''],
            ['plans_title', 'Plans heading', 'text', 'Choose your investment plan', ''],
            ['plans_sub', 'Plans sub-text', 'textarea', 'Returns are paid on a published schedule and tracked live in your investor dashboard.', ''],
            ['cta_title', 'Bottom call-to-action heading', 'text', 'Your allocation is waiting', ''],
            ['cta_sub', 'Bottom call-to-action text', 'text', 'Create your free investor account in under two minutes.', ''],
        ]],
        'invest' => ['title' => 'Investment Rules', 'icon' => '💼', 'fields' => [
            ['min_invest', 'Global minimum investment (₹)', 'number', '10000', 'A plan can set a higher minimum.'],
            ['kyc_required_before_payout', 'Require approved KYC before paying returns', 'toggle', '1', ''],
            ['returns_disclaimer', 'Returns disclaimer', 'textarea', 'Projected returns are based on the published plan terms and the project business plan. Real-estate investments carry risk; please read the Terms & Conditions and offer documents before investing.', 'Shown near plans, calculator and on certificates.'],
            ['certificate_clause', 'Safety clause printed on the certificate', 'textarea', 'This certificate acknowledges receipt of the investment amount stated above against the named project. The amount is recorded in the company\'s investor register and is repayable together with the returns stated, in accordance with the Terms & Conditions accepted by the investor at the time of investment.', ''],
            ['certificate_signatory', 'Signatory name', 'text', 'Authorised Signatory', ''],
            ['certificate_signatory_title', 'Signatory designation', 'text', 'Director', ''],
            ['company_legal', 'Company legal name & registration', 'text', '', 'Printed on certificates, e.g. "Aurum Estates Pvt Ltd · CIN U00000MH2020PTC000000".'],
            ['rera_no', 'RERA registration number', 'text', '', 'Printed on certificates and footer when filled.'],
        ]],
        'payments' => ['title' => 'Payments', 'icon' => '💳', 'fields' => [
            ['pay_razorpay', 'Enable Razorpay online payments (UPI / cards / netbanking)', 'toggle', '0', 'Open-source SDKs on GitHub; free to integrate, needs a Razorpay merchant account.'],
            ['razorpay_key_id', 'Razorpay Key ID', 'text', '', 'rzp_test_… for testing, rzp_live_… for production.'],
            ['razorpay_key_secret', 'Razorpay Key Secret', 'password', '', ''],
            ['razorpay_webhook_secret', 'Razorpay Webhook Secret (optional)', 'password', '', 'Webhook URL: your-site/payment_webhook.php  (event: payment.captured)'],
            ['pay_manual', 'Enable direct UPI / bank transfer (user submits UTR)', 'toggle', '1', 'No gateway fees. Admin verifies the UTR and confirms.'],
            ['upi_id', 'UPI ID', 'text', '', 'e.g. company@okhdfcbank'],
            ['upi_qr', 'UPI QR image', 'image', '', ''],
            ['bank_name', 'Bank name', 'text', '', ''],
            ['bank_holder', 'Account holder name', 'text', '', ''],
            ['bank_account', 'Account number', 'text', '', ''],
            ['bank_ifsc', 'IFSC', 'text', '', ''],
            ['pay_demo', '⚠ Enable DEMO payments (fake instant payment for testing only)', 'toggle', '0', 'NEVER leave this on for a live site.'],
        ]],
        'mail' => ['title' => 'Email', 'icon' => '✉️', 'fields' => [
            ['mail_mode', 'Mail mode', 'select', 'log', 'log = write to storage/logs/mail.log (testing) · php = PHP mail() · smtp = SMTP server', ['log' => 'Log only (testing)', 'php' => 'PHP mail()', 'smtp' => 'SMTP (recommended)']],
            ['mail_from', 'From email', 'text', '', 'e.g. no-reply@yourdomain.com'],
            ['mail_from_name', 'From name', 'text', '', 'Defaults to the site name.'],
            ['smtp_host', 'SMTP host', 'text', '', 'e.g. smtp.gmail.com / smtp-relay.brevo.com'],
            ['smtp_port', 'SMTP port', 'number', '587', '587 (TLS) or 465 (SSL)'],
            ['smtp_secure', 'SMTP security', 'select', 'tls', '', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None']],
            ['smtp_user', 'SMTP username', 'text', '', ''],
            ['smtp_pass', 'SMTP password', 'password', '', ''],
            ['notify_email', 'Admin notification email', 'text', '', 'Gets an email for new investments & enquiries.'],
        ]],
        'lang' => ['title' => 'Languages', 'icon' => '🌐', 'fields' => [
            ['translate_on', 'Show language switcher for visitors', 'toggle', '1', 'Uses the free Google Website Translator. The Google script loads only after a visitor picks a language.'],
            ['translate_langs', 'Languages offered (comma-separated codes)', 'text', 'hi,mr,gu,bn,ta,te,kn,ml,pa,ur,or,as', 'Codes: hi Hindi, mr Marathi, gu Gujarati, bn Bengali, ta Tamil, te Telugu, kn Kannada, ml Malayalam, pa Punjabi, ur Urdu, or Odia, as Assamese, ne Nepali, ar Arabic, fr French, es Spanish, de German, zh-CN Chinese.'],
        ]],
        'contact' => ['title' => 'Contact & Footer', 'icon' => '📞', 'fields' => [
            ['contact_email', 'Contact email', 'text', 'invest@example.com', ''],
            ['contact_phone', 'Phone', 'text', '+91 98765 43210', ''],
            ['whatsapp', 'WhatsApp number (digits only, with country code)', 'text', '919876543210', 'Shows a floating WhatsApp button.'],
            ['contact_address', 'Office address', 'textarea', "Corporate Office, MIDC Business District,\nMaharashtra, India", ''],
            ['office_hours', 'Office hours', 'text', 'Mon–Sat, 10:00 AM – 7:00 PM', ''],
            ['social_facebook', 'Facebook URL', 'text', '', ''],
            ['social_instagram', 'Instagram URL', 'text', '', ''],
            ['social_linkedin', 'LinkedIn URL', 'text', '', ''],
            ['social_youtube', 'YouTube URL', 'text', '', ''],
            ['footer_about', 'Footer about text', 'textarea', 'We develop premium rental real estate and share the income with our investors — transparently, on a published schedule.', ''],
            ['footer_legal', 'Footer legal / disclaimer', 'textarea', 'Real-estate investments are subject to market risk. Past performance is not indicative of future results. Please read all offer documents carefully.', ''],
        ]],
        'seo' => ['title' => 'SEO & Advanced', 'icon' => '🔎', 'fields' => [
            ['meta_title', 'Home page title', 'text', '', 'Defaults to "Site name — Tagline".'],
            ['meta_description', 'Meta description', 'textarea', 'Invest in a ₹15,000 Cr rental real-estate project in the MIDC corridor. Structured returns, transparent tracking, complete documentation.', ''],
            ['meta_image', 'Social share image', 'image', '', ''],
            ['analytics_id', 'Google Analytics ID (G-XXXXXXX)', 'text', '', ''],
            ['custom_css', 'Custom CSS', 'textarea', '', 'Injected into every page.'],
            ['custom_head', 'Custom <head> HTML (verification tags, pixels)', 'textarea', '', 'Only add code you trust.'],
            ['maintenance', 'Maintenance mode (public site offline)', 'toggle', '0', 'Admins can still log in.'],
        ]],
    ];
}

function settings_defaults(): array {
    $d = [];
    foreach (settings_schema() as $g) foreach ($g['fields'] as $f) $d[$f[0]] = $f[3];
    return $d;
}

/** Cached setting getter. */
function setting(string $k, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = settings_defaults();
        try {
            foreach (db()->query('SELECT k, v FROM settings')->fetchAll() as $r) $cache[$r['k']] = $r['v'];
        } catch (Throwable $e) { /* before install */ }
    }
    return array_key_exists($k, $cache) && $cache[$k] !== null ? $cache[$k] : $default;
}
function set_setting(string $k, string $v): void {
    $st = db()->prepare('SELECT COUNT(*) FROM settings WHERE k = ?'); $st->execute([$k]);
    if ((int)$st->fetchColumn()) db()->prepare('UPDATE settings SET v = ? WHERE k = ?')->execute([$v, $k]);
    else db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?)')->execute([$k, $v]);
}
