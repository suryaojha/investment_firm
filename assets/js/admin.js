(function () {
  'use strict';
  // Settings tabs
  var tabs = document.getElementById('tabs');
  if (tabs) {
    var btns = [].slice.call(tabs.querySelectorAll('button')), panes = [].slice.call(document.querySelectorAll('.tabpane')), field = document.getElementById('tab-field');
    function show(key) {
      if (!panes.some(function (p) { return p.dataset.pane === key; })) key = btns[0].dataset.tab;
      btns.forEach(function (b) { b.classList.toggle('on', b.dataset.tab === key); });
      panes.forEach(function (p) { p.hidden = p.dataset.pane !== key; });
      field.value = key; history.replaceState(null, '', '#' + key);
    }
    btns.forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.tab); }); });
    show(location.hash.replace('#', '') || btns[0].dataset.tab);
  }
  // SMTP provider presets
  [].forEach.call(document.querySelectorAll('#smtp-presets button'), function (b) {
    b.addEventListener('click', function () {
      var set = function (id, v) { var el = document.getElementById(id); if (el) el.value = v; };
      set('s_smtp_host', b.dataset.h); set('s_smtp_port', b.dataset.p); set('s_smtp_secure', b.dataset.s); set('s_mail_mode', 'smtp');
    });
  });
  // colour inputs show their hex
  [].forEach.call(document.querySelectorAll('input[type=color]'), function (i) {
    i.addEventListener('input', function () { var c = i.parentNode.querySelector('code'); if (c) c.textContent = i.value; });
  });
  // warn before leaving a dirty settings form
  var sf = document.getElementById('settings-form');
  if (sf) { var dirty = false; sf.addEventListener('input', function () { dirty = true; }); sf.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } }); }
})();
