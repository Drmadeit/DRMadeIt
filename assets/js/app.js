// Shop calculator apps: ☰ menu, size buttons, tap-to-type boxes with the fraction keypad,
// remembered inputs, and each calculator's math. All offline; nothing is sent anywhere.
(() => {
  const C = window.ShopCalc;
  const app = document.body.dataset.app;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* private mode */ } },
  };

  // ---------- ☰ menu ----------
  const menu = $('#menu');
  const menuBtn = $('#menu-btn');
  const setMenu = (open) => {
    menu.hidden = !open;
    menuBtn.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('menu-open', open);
  };
  menuBtn.addEventListener('click', () => setMenu(menu.hidden));
  menu.addEventListener('click', (e) => { if (e.target === menu) setMenu(false); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

  // ---------- Light / Dark / Auto (Auto follows the phone) ----------
  const metas = $$('meta[name="theme-color"]');
  function applyTheme(t) {
    if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
    else delete document.documentElement.dataset.theme;
    const bg = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim();
    metas.forEach((m) => m.setAttribute('content', t === 'auto' ? (m.media.includes('dark') ? '#0a0a0b' : '#f6f5f2') : bg));
    $$('[data-theme-pick]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.themePick === t)));
  }
  applyTheme(store.get('app.theme', 'auto'));
  $$('[data-theme-pick]').forEach((b) => b.addEventListener('click', () => {
    store.set('app.theme', b.dataset.themePick);
    applyTheme(b.dataset.themePick);
  }));

  if (app === 'fractions') return; // Fractions has its own keypad (tape.js)

  // ---------- Size buttons (shared with Fractions: on = show in that size, off = simplified to 1/16) ----------
  let pick = store.get('tape.pick', 0);
  const size = () => pick || 16;
  const fmt = (x) => C.fmtIn(x, size(), { keepDenom: pick > 0 });
  const fmtFt = (x) => C.fmtFtIn(x, size(), { keepDenom: pick > 0 });
  $$('[data-prec]').forEach((b) => b.addEventListener('click', () => {
    pick = pick === +b.dataset.prec ? 0 : +b.dataset.prec;
    store.set('tape.pick', pick);
    update();
  }));

  // ---------- Inputs ----------
  const key = (name) => `app.${app}.${name}`;
  const vals = {};
  $$('[data-field]').forEach((el) => { vals[el.dataset.field] = store.get(key(el.dataset.field), el.dataset.default); });
  const fieldEls = $$('.field');

  function showValues() {
    fieldEls.forEach((el) => {
      const v = vals[el.dataset.field];
      const shown = el.dataset.type === 'len' && v !== '' && !/[a-z'"]/i.test(v) && /\d/.test(v) && !/[ /]$/.test(v) ? v + '"' : v;
      $('.f-val', el).textContent = v === '' ? '—' : shown;
    });
    $$('.choice').forEach((el) => $$('button', el).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.v === vals[el.dataset.field]))));
    $$('[data-show-when]').forEach((el) => {
      const [f, v] = el.dataset.showWhen.split('=');
      el.hidden = vals[f] !== v;
    });
  }

  $$('.choice').forEach((el) => $$('button', el).forEach((b) => b.addEventListener('click', () => {
    vals[el.dataset.field] = b.dataset.v;
    store.set(key(el.dataset.field), b.dataset.v);
    update();
  })));

  // Read a value: len = inches (fractions, feet, mm, and + / − math all work), num = plain number.
  const len = (n) => C.evalLen(vals[n] ?? '');
  const num = (n) => {
    const s = String(vals[n] ?? '').trim();
    return s === '' ? NaN : C.evalLen(s.replace(/\s+/g, ''));
  };

  // ---------- Keypad ----------
  const pad = $('#pad');
  let active = null;
  let replace = false; // first key after tapping a box replaces what was there

  function activate(el) {
    fieldEls.forEach((f) => f.classList.toggle('active', f === el));
    active = el;
    replace = true;
    if (!el) {
      pad.hidden = true;
      document.body.classList.remove('typing');
      return;
    }
    pad.hidden = false;
    document.body.classList.add('typing');
    $('#pad-label').textContent = $('.f-label', el).textContent;
    const isLen = el.dataset.type === 'len';
    $$('[data-pk="space"],[data-pk="/"],[data-pk="ft"]', pad).forEach((b) => { b.disabled = !isLen; });
    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  fieldEls.forEach((el) => el.addEventListener('click', () => activate(active === el ? null : el)));

  function typeKey(k) {
    if (!active) return;
    const name = active.dataset.field;
    let v = String(vals[name] ?? '');
    const isLen = active.dataset.type === 'len';
    if (replace && /^[\d.]$/.test(k)) v = '';
    replace = false;
    const tok = v.split(/ [+−-] /).pop();
    const last = v.slice(-1);
    const digit = /\d/.test(last);
    if (/^\d$/.test(k)) v += k;
    else if (k === '.') { if (!/[./]/.test(tok)) v += (digit ? '' : '0') + '.'; }
    else if (k === 'space' && isLen) { if (digit && !/[ ./]/.test(tok.replace(/^\d+' /, ''))) v += ' '; }
    else if (k === '/' && isLen) { if (digit && !/[/.]/.test(tok)) v += '/'; }
    else if (k === 'ft' && isLen) { if (digit && !/[' ./]/.test(tok)) v += "' "; }
    else if (k === 'back') v = v.endsWith("' ") ? v.slice(0, -2) : v.slice(0, -1);
    else if (k === 'clear') v = '';
    else if (k === 'next') {
      const i = fieldEls.indexOf(active);
      const next = fieldEls.slice(i + 1).find((f) => !f.closest('[hidden]'));
      activate(next || null);
      return;
    } else if (k === 'done') { activate(null); return; }
    vals[name] = v;
    store.set(key(name), v);
    update();
  }
  if (pad) {
    $$('[data-pk]', pad).forEach((b) => {
      b.addEventListener('pointerdown', (e) => { e.preventDefault(); if (!b.disabled) { b.classList.add('down'); typeKey(b.dataset.pk); } });
      ['pointerup', 'pointerleave', 'pointercancel'].forEach((t) => b.addEventListener(t, () => b.classList.remove('down')));
      b.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); typeKey(b.dataset.pk); } });
    });
    document.addEventListener('keydown', (e) => {
      if (!active || e.metaKey || e.ctrlKey) return;
      const map = { Backspace: 'back', Enter: 'next', Escape: 'done', ' ': 'space', '/': '/', "'": 'ft', '.': '.', Tab: 'next' };
      const k = /^\d$/.test(e.key) ? e.key : map[e.key];
      if (k) { e.preventDefault(); typeKey(k); }
    });
  }

  // ---------- Output helpers ----------
  const out = (name, text) => { $$(`[data-out="${name}"]`).forEach((el) => { el.textContent = text; }); };
  const ok = (...xs) => xs.every((x) => Number.isFinite(x));
  const deg = (x) => (Number.isFinite(x) ? (+x.toFixed(1)) + '°' : '—');
  const D2R = Math.PI / 180;

  // ---------- The calculators ----------
  const calcs = {
    square() {
      const a = len('a');
      const b = len('b');
      const m = len('measured');
      const c = Math.hypot(a, b);
      const good = ok(a, b) && a > 0 && b > 0;
      out('c', good ? fmt(c) : '—');
      out('c-dec', good ? (+c.toFixed(3)) + '"' : '—');
      out('c-mm', good ? (+(c * 25.4).toFixed(1)) + ' mm' : '—');
      let note = '';
      if (good && ok(m)) {
        const off = m - c;
        note = Math.abs(off) < 1 / 64 ? 'Square ✓'
          : `Off by ${C.fmtIn(Math.abs(off), 64)}: diagonal is ${off > 0 ? 'long, the corner is open (over 90°)' : 'short, the corner is closed (under 90°)'}`;
      }
      out('check', note);
      const svg = $('[data-out="diagram"]');
      if (!good) { svg.innerHTML = ''; return; }
      const s = 150 / Math.max(a, b);
      const A = a * s;
      const B = b * s;
      const x0 = 70;
      const y0 = 180;
      svg.innerHTML = `<path d="M${x0} ${y0 - A}V${y0}H${x0 + B}" class="leg"/><path d="M${x0} ${y0 - 14}h14v14" class="leg thin"/>`
        + `<path d="M${x0} ${y0 - A}L${x0 + B} ${y0}" class="hyp"/>`
        + `<text x="${x0 - 8}" y="${y0 - A / 2}" text-anchor="end">A ${fmt(a)}</text>`
        + `<text x="${x0 + B / 2}" y="${y0 + 20}" text-anchor="middle">B ${fmt(b)}</text>`
        + `<text x="${x0 + B / 2 + 12}" y="${y0 - A / 2 - 6}" class="hyp-t">C ${fmt(c)}</text>`;
    },

    crown() {
      const wall = num('wall');
      const spring = vals.spring === 'other' ? num('spring_custom') : +vals.spring;
      if (!(wall > 0 && wall < 180) || !(spring > 0 && spring < 90)) {
        ['nested', 'miter', 'bevel'].forEach((k) => out(k, '—'));
        return;
      }
      const half = (wall / 2) * D2R;
      const S = spring * D2R;
      out('nested', deg(90 - wall / 2));
      out('miter', deg(Math.atan(Math.sin(S) / Math.tan(half)) / D2R));
      out('bevel', deg(Math.asin(Math.cos(S) * Math.cos(half)) / D2R));
    },

    spacing() {
      const L = len('length');
      const n = Math.round(num('count'));
      const t = vals.thickness === '' ? 0 : len('thickness');
      const gaps = vals.ends === 'flush' ? n - 1 : n + 1;
      const g = (L - n * t) / gaps;
      const list = $('[data-out="marks"]');
      list.innerHTML = '';
      if (!(L > 0) || !(n >= 1) || !(t >= 0) || gaps < 1 || !(g > 0)) {
        ['gap', 'oc', 'total'].forEach((k) => out(k, '—'));
        return;
      }
      out('gap', fmt(g));
      out('oc', fmt(g + t));
      out('total', fmt(n * t));
      for (let i = 0; i < n && i < 200; i++) {
        const left = (vals.ends === 'flush' ? 0 : g) + i * (g + t);
        const li = document.createElement('li');
        li.innerHTML = '<b></b><span></span><span></span>';
        li.children[0].textContent = '#' + (i + 1);
        li.children[1].textContent = 'edge ' + fmt(left);
        li.children[2].textContent = t > 0 ? 'center ' + fmt(left + t / 2) : '';
        list.appendChild(li);
      }
    },

    'board-feet'() {
      const t = len('thick');
      const w = len('width');
      const l = num('length') * (vals.len_unit === 'in' ? 1 : 12);
      const q = vals.qty === '' ? 1 : num('qty');
      const waste = vals.waste === '' ? 0 : num('waste');
      const price = num('price');
      const bf = (t * w * l) / 144 * q;
      const withWaste = bf * (1 + (Number.isFinite(waste) ? waste : 0) / 100);
      out('bf', ok(bf) ? (+bf.toFixed(2)) + ' bf' : '—');
      out('bf-waste', ok(withWaste) ? (+withWaste.toFixed(2)) + ' bf' : '—');
      out('cost', ok(withWaste, price) ? '$' + (withWaste * price).toFixed(2) : '—');
    },

    stairs() {
      const rise = len('rise');
      const maxR = len('max_riser');
      const run = len('run');
      const keys = ['riser', 'risers', 'treads', 'total-run', 'stringer', 'angle', 'rule'];
      if (!(rise > 0 && maxR > 0 && run > 0)) { keys.forEach((k) => out(k, '—')); out('comfort', ''); return; }
      const n = Math.ceil(rise / maxR - 1e-9);
      const r = rise / n;
      const totalRun = (n - 1) * run;
      const rule = 2 * r + run;
      out('riser', fmt(r));
      out('risers', String(n));
      out('treads', String(n - 1));
      out('total-run', fmtFt(totalRun));
      out('stringer', fmtFt(Math.hypot(rise, totalRun + run)));
      out('angle', deg(Math.atan(r / run) / D2R));
      out('rule', (+rule.toFixed(2)) + '"');
      out('comfort', rule >= 24 && rule <= 25.5 ? 'Comfortable ✓' : rule < 24 ? 'A bit shallow (aim for 24–25")' : 'A bit steep (aim for 24–25")');
    },

    dxf() { dxfRun(); },
  };

  // ---------- DXF Fix ----------
  let dxfText = null;
  let dxfName = 'drawing-fixed.dxf';
  let dxfUrl = null;
  const UNITS = { 1: ['inches', '0.001', '0.03'], 2: ['feet', '0.0001', '0.003'], 4: ['mm', '0.02', '0.8'], 5: ['cm', '0.002', '0.08'], 6: ['meters', '0.00002', '0.0008'] };
  function dxfRun() {
    if (!dxfText || !window.DxfFix) return;
    const tol = num('tol');
    const gap = num('gap') || 0;
    if (!(tol > 0)) return;
    let res;
    try { res = DxfFix.fix(DxfFix.parse(dxfText), { tol, gap }); } catch (e) { $('#dxf-name').textContent = e.message || 'Could not read that file.'; return; }
    const r = res.report;
    out('closed', String(r.closed));
    out('open', String(r.open));
    out('dupes', String(r.duplicates));
    out('bridged', String(r.bridged));
    out('zero', String(r.zeroLength));
    out('circles', String(r.circles));
    const skipped = Object.entries(r.skipped).map(([t, n]) => `${n} ${t}`).join(', ');
    out('notes', (r.open ? `${r.open} shape(s) still open: look for the red dots. ` : 'Every shape is closed ✓ ') + (skipped ? `Left out: ${skipped}.` : ''));
    $('#dxf-preview').innerHTML = DxfFix.svg(res);
    if (dxfUrl) URL.revokeObjectURL(dxfUrl);
    dxfUrl = URL.createObjectURL(new Blob([DxfFix.write(res)], { type: 'application/dxf' }));
    const a = $('#dxf-download');
    a.href = dxfUrl;
    a.download = dxfName;
    $('#dxf-result').hidden = false;
  }
  const fileInput = $('#dxf-file');
  if (fileInput) {
    fileInput.addEventListener('change', async () => {
      const f = fileInput.files[0];
      if (!f) return;
      dxfName = f.name.replace(/\.dxf$/i, '') + '-fixed.dxf';
      dxfText = await f.text();
      $('#dxf-name').textContent = `${f.name} (${f.size < 1024 ? f.size + ' bytes' : Math.round(f.size / 1024) + ' KB'})`;
      try {
        const u = UNITS[DxfFix.parse(dxfText).units];
        $('#dxf-units').textContent = u ? `Drawing units: ${u[0]}.` : 'Drawing units not set in the file: using the numbers as they are.';
        if (u) {
          vals.tol = u[1]; vals.gap = u[2];
          store.set(key('tol'), u[1]); store.set(key('gap'), u[2]);
        }
      } catch { /* shown when fixing */ }
      update();
    });
  }

  function update() {
    showValues();
    $$('[data-prec]').forEach((b) => b.setAttribute('aria-pressed', String(+b.dataset.prec === pick)));
    calcs[app]?.();
  }
  update();
})();
