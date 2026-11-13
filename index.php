<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

function hl(string $s): string { return preg_replace('/\[(.+?)\]/', '<em>$1</em>', e($s)); }
$on = fn(string $k) => setting($k) === '1';

$plans = db()->query('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$projects = db()->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$maxRoi = 0; foreach ($plans as $p) $maxRoi = max($maxRoi, plan_total_roi($p));
$raised = total_raised();
$heroImg = setting('hero_image') ?: 'assets/img/hero-skyline.svg';
$bg = ' style="background-image:linear-gradient(105deg,rgba(var(--dark-rgb),.92) 30%,rgba(var(--dark-rgb),.5)),url(\'' . e(media($heroImg)) . '\')"';
$GLOBALS['mobile_cta'] = true;

public_header('', ['body' => 'home']);
?>
<section class="hero" <?= $bg ?>>
  <div class="container hero-grid">
    <div class="hero-copy reveal">
      <?php if (trim(setting('hero_badge'))): ?><span class="pill"><?= e(setting('hero_badge')) ?></span><?php endif; ?>
      <h1><?= hl(setting('hero_title')) ?></h1>
      <p class="lead"><?= e(setting('hero_subtitle')) ?></p>
      <div class="hero-actions">
        <a class="btn btn-gold btn-lg" href="<?= e(url(current_user() ? 'invest.php' : 'register.php')) ?>"><?= e(setting('hero_cta1')) ?> →</a>
        <a class="btn btn-ghost btn-lg" href="#plans"><?= e(setting('hero_cta2')) ?></a>
      </div>
      <?php $badges = array_filter(array_map('trim', explode("\n", setting('trust_badges')))); if ($badges): ?>
      <ul class="trust"><?php foreach ($badges as $b): ?><li>✔ <?= e($b) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <aside class="hero-card reveal">
      <div class="hc-label">Total project size</div>
      <div class="hc-big"><?= e(setting('project_value')) ?></div>
      <div class="hc-row"><div><span>Completion</span><strong><?= e(dt(setting('completion_date'), 'M Y')) ?></strong></div><div><span>Returns up to</span><strong><?= $maxRoi ? rtrim(rtrim(number_format($maxRoi, 2), '0'), '.') . '% p.a.' : '—' ?></strong></div></div>
      <div class="countdown" data-date="<?= e(setting('completion_date')) ?>T00:00:00">
        <div><b data-u="d">0</b><small>Days</small></div><div><b data-u="h">0</b><small>Hours</small></div><div><b data-u="m">0</b><small>Min</small></div><div><b data-u="s">0</b><small>Sec</small></div>
      </div>
      <div class="hc-note">until project completion</div>
      <?php if ($eb = trim(setting('early_bird_until'))): if (strtotime($eb . ' 23:59:59') > time()): ?>
      <div class="early"><span><?= e(setting('early_bird_label')) ?></span><b class="countdown-text" data-date="<?= e($eb) ?>T23:59:59">—</b></div>
      <?php endif; endif; ?>
      <a class="btn btn-gold btn-block" href="<?= e(url(current_user() ? 'invest.php' : 'register.php')) ?>">Reserve my allocation</a>
    </aside>
  </div>
</section>

<?php if ($on('show_stats') && ($stats = items('stat'))): ?>
<section class="stats"><div class="container stats-grid">
  <?php foreach ($stats as $s): ?><div class="stat reveal"><b class="count" data-text="<?= e($s['extra']) ?>"><?= e($s['extra']) ?></b><span><?= e($s['title']) ?></span></div><?php endforeach; ?>
</div></section>
<?php endif; ?>

<?php if ($on('show_why') && ($feat = items('feature'))): ?>
<section class="section" id="why"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Why us</span><h2><?= e(setting('why_title')) ?></h2><p><?= e(setting('why_sub')) ?></p></div>
  <div class="cards3">
    <?php foreach ($feat as $f): ?>
    <div class="card feature reveal"><div class="ficon"><?= e($f['extra']) ?></div><h3><?= e($f['title']) ?></h3><p><?= e($f['body']) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($on('show_plans') && $plans): ?>
<section class="section alt" id="plans"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Investment plans</span><h2><?= e(setting('plans_title')) ?></h2><p><?= e(setting('plans_sub')) ?></p></div>
  <div class="plans">
    <?php foreach ($plans as $p):
        $total = plan_total_roi($p); $feats = array_filter(array_map('trim', explode("\n", (string)$p['features'])));
        $fill = $p['target_amount'] > 0 ? min(100, round(plan_raised((int)$p['id']) / $p['target_amount'] * 100)) : null; ?>
    <div class="plan reveal <?= $p['badge'] ? 'plan-featured' : '' ?>">
      <?php if ($p['badge']): ?><div class="plan-badge"><?= e($p['badge']) ?></div><?php endif; ?>
      <h3><?= e($p['name']) ?></h3><p class="plan-tag"><?= e($p['tagline']) ?></p>
      <div class="plan-roi"><b><?= rtrim(rtrim(number_format($total, 2), '0'), '.') ?>%</b><span>per year<?= $p['bonus'] > 0 ? ' <small>(incl. ' . rtrim(rtrim(number_format((float)$p['bonus'], 2), '0'), '.') . '% early bonus)</small>' : '' ?></span></div>
      <ul class="plan-meta">
        <li><span>Minimum</span><b><?= inr($p['min_amount']) ?></b></li>
        <li><span>Tenure</span><b><?= (int)$p['tenure_months'] ?> months</b></li>
        <li><span>Payouts</span><b><?= e(ucfirst($p['payout_mode'])) ?></b></li>
      </ul>
      <ul class="plan-feats"><?php foreach ($feats as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
      <?php if ($fill !== null && $fill > 0): ?><div class="fillbar"><div style="width:<?= $fill ?>%"></div></div><div class="fill-note"><?= $fill ?>% of this plan's allocation subscribed</div><?php endif; ?>
      <a class="btn <?= $p['badge'] ? 'btn-gold' : 'btn-dark' ?> btn-block" href="<?= e(url('invest.php?plan=' . $p['id'])) ?>">Invest in <?= e($p['name']) ?></a>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="disclaimer"><?= e(setting('returns_disclaimer')) ?></p>
</div></section>
<?php endif; ?>

<?php if ($on('show_calc') && $plans): ?>
<section class="section" id="calculator"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Returns calculator</span><h2>See what your money can earn</h2></div>
  <div class="calc reveal"
       data-plans='<?= e(json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'min' => (float)$p['min_amount'], 'max' => (float)$p['max_amount'], 'roi' => plan_total_roi($p), 'months' => (int)$p['tenure_months'], 'mode' => $p['payout_mode']], $plans))) ?>'>
    <div class="calc-in">
      <label>Choose plan<select id="c-plan"><?php foreach ($plans as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> — <?= rtrim(rtrim(number_format(plan_total_roi($p), 2), '0'), '.') ?>% p.a.</option><?php endforeach; ?></select></label>
      <label>Investment amount: <b id="c-amt-label"></b>
        <input type="range" id="c-range"><input type="number" id="c-amt" inputmode="numeric"></label>
      <p class="muted small">Projected figures are illustrative and follow the plan terms. Not a guarantee.</p>
    </div>
    <div class="calc-out">
      <div class="co"><span>Total projected return</span><b id="c-ret">—</b></div>
      <div class="co"><span id="c-pay-label">Payout</span><b id="c-pay">—</b></div>
      <div class="co big"><span>Value at maturity (principal + return)</span><b id="c-total">—</b></div>
      <a id="c-cta" class="btn btn-gold btn-block" href="<?= e(url('invest.php')) ?>">Invest this amount →</a>
    </div>
  </div>
</div></section>
<?php endif; ?>

<?php if ($on('show_how') && ($how = items('how'))): ?>
<section class="section alt" id="how"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Simple process</span><h2>How it works</h2></div>
  <div class="steps">
    <?php foreach ($how as $s): ?><div class="step reveal"><div class="step-n"><?= e($s['extra']) ?></div><h3><?= e($s['title']) ?></h3><p><?= e($s['body']) ?></p></div><?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($on('show_projects') && $projects): ?>
<section class="section" id="project"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">The project</span><h2>What you are investing in</h2></div>
  <?php foreach ($projects as $pr): $hl = array_filter(array_map('trim', explode("\n", (string)$pr['highlights']))); ?>
  <div class="project reveal">
    <div class="project-img" <?= $pr['image'] ? 'style="background-image:url(\'' . e(media($pr['image'])) . '\')"' : '' ?>><?php if (!$pr['image']): ?><span>🏙️</span><?php endif; ?></div>
    <div class="project-body">
      <span class="pill pill-dark"><?= e($pr['status_label']) ?></span>
      <h3><?= e($pr['title']) ?></h3>
      <p class="loc">📍 <?= e($pr['location']) ?> &nbsp;·&nbsp; Completion <?= e(dt($pr['completion_date'], 'M Y')) ?></p>
      <p><?= nl2br(e($pr['summary'])) ?></p>
      <ul class="ticks"><?php foreach ($hl as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul>
      <div class="progress-label"><span>Construction progress</span><b><?= (int)$pr['progress'] ?>%</b></div>
      <div class="fillbar big"><div style="width:<?= (int)$pr['progress'] ?>%"></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div></section>
<?php endif; ?>

<?php if ($on('show_timeline') && ($tl = items('timeline'))): ?>
<section class="section alt" id="timeline"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Roadmap</span><h2>From groundbreaking to handover</h2></div>
  <div class="timeline">
    <?php foreach ($tl as $t): ?><div class="tl-item reveal"><div class="tl-year"><?= e($t['extra']) ?></div><div class="tl-body"><h3><?= e($t['title']) ?></h3><p><?= e($t['body']) ?></p></div></div><?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($on('show_gallery') && ($gal = items('gallery'))): ?>
<section class="section alt" id="gallery"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Gallery</span><h2>A look at the development</h2><p>Artist impressions of the project, amenities and living spaces.</p></div>
  <div class="gallery">
    <?php foreach ($gal as $g): if (!$g['image']) continue; ?>
    <figure class="g-item reveal"><button type="button" class="g-btn" data-full="<?= e(media($g['image'])) ?>" data-cap="<?= e($g['title']) ?>" aria-label="Open <?= e($g['title']) ?>">
      <img src="<?= e(media($g['image'])) ?>" alt="<?= e($g['title']) ?>" loading="lazy"></button>
      <figcaption><b><?= e($g['title']) ?></b><span><?= e($g['extra']) ?></span></figcaption></figure>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($on('show_videos') &&($vids = db()->query('SELECT * FROM videos WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll())): ?>
<section class="section" id="videos"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Watch</span><h2>See the project &amp; hear from us</h2></div>
  <div class="videos">
    <?php foreach ($vids as $v): ?>
    <div class="video-card reveal"><div class="video-frame"><?= video_embed($v) ?></div><h3><?= e($v['title']) ?></h3><?php if ($v['description']): ?><p><?= e($v['description']) ?></p><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($on('show_docs') && ($docs = db()->query('SELECT * FROM documents WHERE is_active = 1 AND is_public = 1 ORDER BY sort_order, id LIMIT 6')->fetchAll())): ?>
<section class="section alt" id="docs"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Transparency</span><h2>Documents &amp; disclosures</h2><p>Read everything before you invest — terms, project documents and approvals.</p></div>
  <div class="docs">
    <?php foreach ($docs as $d): ?>
    <a class="doc reveal" href="<?= e(url('download.php?id=' . $d['id'])) ?>"><span class="doc-ico">PDF</span><span><b><?= e($d['title']) ?></b><small><?= e($d['description'] ?: $d['category']) ?></small></span><span class="doc-dl">↓</span></a>
    <?php endforeach; ?>
  </div>
  <p class="center"><a href="<?= e(url('documents.php')) ?>" class="link">View all documents →</a></p>
</div></section>
<?php endif; ?>

<?php if ($on('show_testimonials') && ($tm = items('testimonial'))): ?>
<section class="section" id="testimonials"><div class="container">
  <div class="section-head reveal"><span class="eyebrow">Investors say</span><h2>Trusted by investors</h2></div>
  <div class="cards3"><?php foreach ($tm as $t): ?>
    <figure class="card quote reveal"><blockquote>“<?= e($t['body']) ?>”</blockquote><figcaption><b><?= e($t['title']) ?></b><span><?= e($t['extra']) ?></span></figcaption></figure>
  <?php endforeach; ?></div>
</div></section>
<?php endif; ?>

<?php if ($on('show_faq') && ($faq = items('faq'))): ?>
<section class="section alt" id="faq"><div class="container narrow">
  <div class="section-head reveal"><span class="eyebrow">FAQ</span><h2>Questions, answered</h2></div>
  <?php foreach ($faq as $q): ?><details class="faq reveal"><summary><?= e($q['title']) ?></summary><p><?= nl2br(e($q['body'])) ?></p></details><?php endforeach; ?>
</div></section>
<?php endif; ?>

<section class="cta-band"><div class="container reveal">
  <h2><?= e(setting('cta_title')) ?></h2><p><?= e(setting('cta_sub')) ?></p>
  <a class="btn btn-gold btn-lg" href="<?= e(url(current_user() ? 'invest.php' : 'register.php')) ?>"><?= current_user() ? 'Invest now' : 'Create free account' ?> →</a>
</div></section>

<?php if ($on('show_contact')): ?>
<section class="section" id="contact"><div class="container contact-grid">
  <div class="reveal">
    <span class="eyebrow">Contact</span><h2>Talk to our investor desk</h2>
    <p>Have questions before you invest? Send us a message and our team will get back to you.</p>
    <ul class="contact-list">
      <li>📞 <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li>
      <li>✉️ <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li>
      <li>📍 <?= nl2br(e(setting('contact_address'))) ?></li>
      <li>🕘 <?= e(setting('office_hours')) ?></li>
    </ul>
  </div>
  <form class="card form reveal" method="post" action="<?= e(url('contact.php')) ?>">
    <?= csrf_field() ?>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <?= flashes() ?>
    <label>Your name<input name="name" required maxlength="100"></label>
    <div class="grid2"><label>Email<input type="email" name="email" required></label><label>Phone<input name="phone" inputmode="tel" maxlength="15"></label></div>
    <label>Message<textarea name="message" rows="4" required maxlength="2000"></textarea></label>
    <button class="btn btn-dark btn-block">Send message</button>
  </form>
</div></section>
<?php endif; ?>
<?php public_footer(); ?>
