(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var inr = function (n) {
    n = Math.round(n); var s = String(n), last = s.slice(-3), rest = s.slice(0, -3);
    if (rest) rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',';
    return '₹' + rest + last;
  };

  // Close mobile nav on link click
  $$('.main-nav a').forEach(function (a) { a.addEventListener('click', function () { document.body.classList.remove('nav-open'); }); });

  // Reveal on scroll
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: .12 });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else { $$('.reveal').forEach(function (el) { el.classList.add('in'); }); }

  // Animated stat counters ("₹15,000 Cr", "14% p.a." …) — animate the first number found
  function countUp(el) {
    var txt = el.getAttribute('data-text') || el.textContent; var m = txt.match(/([\d,]+(?:\.\d+)?)/);
    if (!m) return; var target = parseFloat(m[1].replace(/,/g, '')); if (!target || target > 1e9) return;
    var dec = (m[1].split('.')[1] || '').length, comma = m[1].indexOf(',') > -1, t0 = null, dur = 1400;
    function fmt(v) { var s = v.toFixed(dec); return comma ? Number(s).toLocaleString('en-IN', { minimumFractionDigits: dec, maximumFractionDigits: dec }) : s; }
    function step(ts) { t0 = t0 || ts; var p = Math.min(1, (ts - t0) / dur); p = 1 - Math.pow(1 - p, 3);
      el.textContent = txt.replace(m[1], fmt(target * p)); if (p < 1) requestAnimationFrame(step); }
    requestAnimationFrame(step);
  }
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var io2 = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { countUp(e.target); io2.unobserve(e.target); } }); }, { threshold: .5 });
    $$('.count').forEach(function (el) { io2.observe(el); });
  }

  // Countdown to completion
  var cd = $('.countdown');
  if (cd) {
    var end = new Date(cd.getAttribute('data-date')).getTime();
    var parts = { d: $('[data-u=d]', cd), h: $('[data-u=h]', cd), m: $('[data-u=m]', cd), s: $('[data-u=s]', cd) };
    (function tick() {
      var diff = Math.max(0, end - Date.now()), s = Math.floor(diff / 1000);
      parts.d.textContent = Math.floor(s / 86400); parts.h.textContent = String(Math.floor(s % 86400 / 3600)).padStart(2, '0');
      parts.m.textContent = String(Math.floor(s % 3600 / 60)).padStart(2, '0'); parts.s.textContent = String(s % 60).padStart(2, '0');
      setTimeout(tick, 1000);
    })();
  }
  var ct = $('.countdown-text');
  if (ct) {
    var end2 = new Date(ct.getAttribute('data-date')).getTime();
    (function tick2() {
      var s = Math.max(0, Math.floor((end2 - Date.now()) / 1000));
      ct.textContent = Math.floor(s / 86400) + 'd ' + String(Math.floor(s % 86400 / 3600)).padStart(2, '0') + 'h ' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + 'm ' + String(s % 60).padStart(2, '0') + 's';
      setTimeout(tick2, 1000);
    })();
  }

  // Home calculator
  var calc = $('.calc');
  if (calc) {
    var plans = JSON.parse(calc.getAttribute('data-plans') || '[]');
    var sel = $('#c-plan'), range = $('#c-range'), amt = $('#c-amt'), lab = $('#c-amt-label');
    var by = function (id) { return plans.filter(function (p) { return String(p.id) === String(id); })[0]; };
    function setup() {
      var p = by(sel.value); if (!p) return;
      var max = p.max > 0 ? p.max : Math.max(p.min * 10, 10000000);
      range.min = p.min; range.max = max; range.step = p.min >= 100000 ? 50000 : 5000;
      if (+amt.value < p.min || +amt.value > max) amt.value = p.min; range.value = amt.value; render();
    }
    function render() {
      var p = by(sel.value); if (!p) return; var a = Math.max(0, +amt.value || 0);
      var ret = a * p.roi / 100 * p.months / 12, step = { monthly: 1, quarterly: 3 }[p.mode] || p.months, n = Math.ceil(p.months / step);
      lab.textContent = inr(a); $('#c-ret').textContent = inr(ret);
      $('#c-pay-label').textContent = p.mode === 'maturity' ? 'Return paid at maturity' : (p.mode === 'monthly' ? 'Monthly payout' : 'Quarterly payout');
      $('#c-pay').textContent = p.mode === 'maturity' ? inr(ret) : inr(a * p.roi / 100 * step / 12);
      $('#c-total').textContent = inr(a + ret);
      $('#c-cta').href = $('#c-cta').getAttribute('href').split('?')[0] + '?plan=' + p.id;
    }
    sel.addEventListener('change', setup);
    range.addEventListener('input', function () { amt.value = range.value; render(); });
    amt.addEventListener('input', function () { range.value = amt.value; render(); });
    setup();
  }

  // Invest page live summary
  var form = $('#invest-form');
  if (form) {
    var amount = $('#amount'), chips = $('#chips');
    function cur() { var r = $('input[name=plan_id]:checked', form); return r ? r.dataset : null; }
    function upd() {
      var d = cur(); if (!d) return; var a = +amount.value || 0, roi = +d.roi, months = +d.months;
      var ret = a * roi / 100 * months / 12, step = { monthly: 1, quarterly: 3 }[d.mode] || months;
      $('#s-ret').textContent = inr(ret); $('#s-total').textContent = inr(a + ret);
      $('#s-pay-label').textContent = d.mode === 'maturity' ? 'Return at maturity' : (d.mode === 'monthly' ? 'Each monthly payout' : 'Each quarterly payout');
      $('#s-pay').textContent = d.mode === 'maturity' ? inr(ret) : inr(a * roi / 100 * step / 12);
      $('#s-note').textContent = 'Plan minimum ' + inr(+d.min) + (+d.max > 0 ? ', maximum ' + inr(+d.max) : '') + '. Principal returned at maturity.';
      amount.min = d.min; if (+d.max > 0) amount.max = d.max; else amount.removeAttribute('max');
    }
    function buildChips() {
      var d = cur(); if (!d) return; chips.innerHTML = '';
      [1, 2, 5, 10].forEach(function (k) { var v = +d.min * k; if (+d.max > 0 && v > +d.max) return;
        var b = document.createElement('button'); b.type = 'button'; b.textContent = inr(v); b.onclick = function () { amount.value = v; upd(); }; chips.appendChild(b); });
    }
    $$('input[name=plan_id]', form).forEach(function (r) { r.addEventListener('change', function () {
      $$('.pp', form).forEach(function (l) { l.classList.toggle('on', l.contains(r) && r.checked); });
      var d = cur(); if (+amount.value < +d.min) amount.value = d.min; buildChips(); upd(); }); });
    amount.addEventListener('input', upd); buildChips(); upd();
  }
  // Gallery lightbox
  var lb = null;
  $$('.g-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!lb) {
        lb = document.createElement('div'); lb.className = 'lightbox';
        lb.innerHTML = '<button type="button" aria-label="Close">?</button><img alt=""><p></p>';
        lb.addEventListener('click', function () { lb.classList.remove('on'); });
        document.body.appendChild(lb);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') lb.classList.remove('on'); });
      }
      lb.querySelector('img').src = b.dataset.full; lb.querySelector('img').alt = b.dataset.cap; lb.querySelector('p').textContent = b.dataset.cap;
      lb.classList.add('on');
    });
  });
})();
