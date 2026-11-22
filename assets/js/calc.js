/* Shop calculators. All math runs in the browser; nothing is sent anywhere. */
(function (root) {
  'use strict';

  // ---------- Length parsing & formatting (inches) ----------
  // Accepts: 23 5/8   23-5/8   5/8   23.625   5' 3 1/2"   5ft 3in   600mm   60cm   1.2m
  function parseLen(input) {
    let s = String(input || '').trim().toLowerCase().replace(/[”″]/g, '"').replace(/[’′]/g, "'");
    if (!s) return NaN;
    let m;
    if ((m = s.match(/^(-?[\d.]+)\s*(mm|cm|m)$/))) {
      return parseFloat(m[1]) / { mm: 25.4, cm: 2.54, m: 0.0254 }[m[2]];
    }
    s = s.replace(/(\d)-(\d+\/\d+)/g, '$1 $2'); // 23-5/8 -> 23 5/8
    let feet = 0;
    if ((m = s.match(/^(-?[\d.]+)\s*(?:'|ft|feet|foot)\s*(.*)$/))) {
      feet = parseFloat(m[1]);
      s = m[2].trim();
      if (!s) return feet * 12;
    }
    s = s.replace(/\s*(?:"|in|inch|inches)$/, '').trim();
    if ((m = s.match(/^(-?\d+)\s+(\d+)\/(\d+)$/))) {
      const whole = parseInt(m[1], 10);
      const frac = parseInt(m[2], 10) / parseInt(m[3], 10);
      return feet * 12 + (whole < 0 ? whole - frac : whole + frac);
    }
    if ((m = s.match(/^(-?\d+)\/(\d+)$/))) return feet * 12 + parseInt(m[1], 10) / parseInt(m[2], 10);
    if (/^-?(\d+\.?\d*|\.\d+)$/.test(s)) return feet * 12 + parseFloat(s);
    return NaN;
  }

  const gcd = (a, b) => (b ? gcd(b, a % b) : a);

  /** 23.6 -> 23 5/8" (nearest 1/denom). Adds "+" / "−" if rounding moved it. */
  function fmtIn(x, denom = 16, opts = {}) {
    if (!isFinite(x)) return '—';
    const neg = x < 0;
    const units = Math.round(Math.abs(x) * denom);
    const whole = Math.floor(units / denom);
    let num = units % denom;
    let den = denom;
    if (num) {
      const g = gcd(num, den);
      num /= g;
      den /= g;
    }
    let out = (whole || !num ? String(whole) : '') + (num ? (whole ? ' ' : '') + num + '/' + den : '');
    const err = Math.abs(x) - units / denom;
    const mark = opts.noMark || Math.abs(err) < 1e-6 ? '' : err > 0 ? '+' : '−';
    return (neg ? '-' : '') + out + '"' + (mark ? ' ' + mark : '');
  }

  function fmtFtIn(x, denom = 16) {
    if (!isFinite(x)) return '—';
    const neg = x < 0;
    let ax = Math.abs(x);
    let ft = Math.floor(ax / 12);
    let inch = ax - ft * 12;
    if (Math.round(inch * denom) === 12 * denom) {
      ft += 1;
      inch = 0;
    }
    return (neg ? '-' : '') + (ft ? ft + "' " : '') + fmtIn(inch, denom, { noMark: true });
  }

  /** "23 5/8 + 1 3/16 - 3/4", "96 ÷ 3", "2' 6 x 2". A hyphen right before a fraction means a mixed number. */
  function evalLen(expr) {
    const s = String(expr || '')
      .replace(/(\d)-(\d+\/\d+)/g, '$1 $2')
      .replace(/[×*]/g, ' x ')
      .replace(/([\d"'.])\s*([-+÷])\s*(?=[\d.])/g, '$1 $2 ')
      .replace(/\bx\b/g, ' x ')
      .trim();
    const tokens = s.split(/\s+([-+x÷])\s+/).filter((t) => t !== undefined && t.trim() !== '');
    let total = NaN;
    let op = '+';
    for (const raw of tokens) {
      const t = raw.trim();
      if (['+', '-', 'x', '÷'].includes(t)) {
        op = t;
        continue;
      }
      const n = op === 'x' || op === '÷' ? parseFloat(t) : parseLen(t);
      if (!isFinite(n)) return NaN;
      if (!isFinite(total)) total = op === '-' ? -n : n;
      else if (op === '+') total += n;
      else if (op === '-') total -= n;
      else if (op === 'x') total *= n;
      else total = n ? total / n : NaN;
    }
    return total;
  }

  const fmtDec = (x, d = 3) => (isFinite(x) ? String(+x.toFixed(d)) : '—');
  const fmtMm = (x) => (isFinite(x) ? String(+(x * 25.4).toFixed(1)) + ' mm' : '—');
  const D2R = Math.PI / 180;
  const R2D = 180 / Math.PI;
  const deg = (x) => (isFinite(x) ? (+x.toFixed(1)).toString() + '°' : '—');

  // ---------- Small DOM helpers ----------
  const $ = (sel, el = document) => el.querySelector(sel);
  const val = (name) => ($(`[name="${name}"]`) || {}).value || '';
  const setOut = (name, text) => {
    const el = $(`[data-out="${name}"]`);
    if (el) el.textContent = text;
  };
  const precision = () => parseInt(val('precision') || '16', 10);
  const live = (form, fn) => {
    form.addEventListener('input', fn);
    form.addEventListener('change', fn);
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      fn();
    });
    fn();
  };

  // ---------- Calculators ----------
  const calcs = {
    fractions(form) {
      live(form, () => {
        const x = evalLen(val('expr'));
        const d = precision();
        setOut('inches', fmtIn(x, d));
        setOut('ftin', fmtFtIn(x, d));
        setOut('decimal', fmtDec(x, 4) + (isFinite(x) ? '"' : ''));
        setOut('mm', fmtMm(x));
      });
    },

    square(form) {
      live(form, () => {
        const a = parseLen(val('a'));
        const b = parseLen(val('b'));
        const measured = parseLen(val('measured'));
        const c = Math.hypot(a, b);
        const d = precision();
        setOut('c', fmtIn(c, d));
        setOut('c-dec', fmtDec(c, 3) + '"');
        let note = '';
        if (isFinite(c) && isFinite(measured)) {
          const off = measured - c;
          note = Math.abs(off) < 1 / 64 ? 'Square ✓' : `Off by ${fmtIn(Math.abs(off), d, { noMark: true })} — diagonal is ${off > 0 ? 'long: corner is open (over 90°)' : 'short: corner is closed (under 90°)'}`;
        }
        setOut('check', note);
        drawTriangle(a, b, c, d);
      });
      function drawTriangle(a, b, c, d) {
        const svg = $('[data-out="diagram"]');
        if (!svg) return;
        if (!(a > 0 && b > 0)) {
          svg.innerHTML = '';
          return;
        }
        const s = 160 / Math.max(a, b);
        const A = a * s;
        const B = b * s;
        const x0 = 60;
        const y0 = 190;
        svg.innerHTML =
          `<path d="M${x0} ${y0 - A} L${x0} ${y0} L${x0 + B} ${y0}" class="leg"/>` +
          `<path d="M${x0} ${y0 - 14} h14 v14" class="leg thin"/>` +
          `<path d="M${x0} ${y0 - A} L${x0 + B} ${y0}" class="hyp"/>` +
          `<text x="${x0 - 8}" y="${y0 - A / 2}" text-anchor="end">A ${fmtIn(a, d)}</text>` +
          `<text x="${x0 + B / 2}" y="${y0 + 20}" text-anchor="middle">B ${fmtIn(b, d)}</text>` +
          `<text x="${x0 + B / 2 + 10}" y="${y0 - A / 2 - 6}" class="hyp-t">C ${fmtIn(c, d)}</text>`;
      }
    },

    crown(form) {
      live(form, () => {
        const wall = parseFloat(val('wall'));
        const springSel = val('spring');
        const spring = springSel === 'custom' ? parseFloat(val('spring_custom')) : parseFloat(springSel);
        const custom = $('[data-custom]');
        if (custom) custom.hidden = springSel !== 'custom';
        if (!(wall > 0 && wall < 180) || !(spring > 0 && spring < 90)) {
          ['nested', 'miter', 'bevel'].forEach((k) => setOut(k, '—'));
          return;
        }
        const half = (wall / 2) * D2R;
        const S = spring * D2R;
        setOut('nested', deg(90 - wall / 2));
        setOut('miter', deg(Math.atan(Math.sin(S) / Math.tan(half)) * R2D));
        setOut('bevel', deg(Math.asin(Math.cos(S) * Math.cos(half)) * R2D));
      });
    },

    spacing(form) {
      live(form, () => {
        const L = parseLen(val('length'));
        const n = parseInt(val('count'), 10);
        const t = parseLen(val('thickness') || '0');
        const ends = val('ends'); // "gap" = space at both ends, "flush" = first/last item at the ends
        const d = precision();
        const list = $('[data-out="marks"]');
        list.innerHTML = '';
        const gaps = ends === 'flush' ? n - 1 : n + 1;
        const g = (L - n * t) / gaps;
        if (!(L > 0) || !(n >= 1) || !(t >= 0) || gaps < 1 || !(g > 0)) {
          setOut('gap', '—');
          setOut('oc', '—');
          return;
        }
        setOut('gap', fmtIn(g, d));
        setOut('oc', fmtIn(g + t, d));
        for (let i = 0; i < n && i < 200; i++) {
          const left = (ends === 'flush' ? 0 : g) + i * (g + t);
          const li = document.createElement('li');
          li.innerHTML = `<b>#${i + 1}</b> <span>edge ${fmtIn(left, d)}</span>${t > 0 ? `<span>center ${fmtIn(left + t / 2, d)}</span>` : ''}`;
          list.appendChild(li);
        }
      });
    },

    'board-feet'(form) {
      live(form, () => {
        const t = parseLen(val('thick'));
        const w = parseLen(val('width'));
        const lenRaw = val('length');
        let len = parseLen(lenRaw);
        if (/^\s*[\d.]+\s*$/.test(lenRaw) && val('len_unit') === 'ft') len = parseFloat(lenRaw) * 12;
        const q = parseFloat(val('qty') || '1');
        const price = parseFloat((val('price') || '').replace('$', ''));
        const waste = parseFloat(val('waste') || '0');
        const bf = ((t * w * len) / 144) * q;
        const withWaste = bf * (1 + (isFinite(waste) ? waste : 0) / 100);
        setOut('bf', isFinite(bf) ? fmtDec(bf, 2) + ' bf' : '—');
        setOut('bf-waste', isFinite(withWaste) ? fmtDec(withWaste, 2) + ' bf' : '—');
        setOut('cost', isFinite(withWaste) && isFinite(price) ? '$' + (withWaste * price).toFixed(2) : '—');
      });
    },

    stairs(form) {
      live(form, () => {
        const rise = parseLen(val('rise'));
        const maxR = parseLen(val('max_riser'));
        const run = parseLen(val('run'));
        const d = precision();
        if (!(rise > 0 && maxR > 0 && run > 0)) {
          ['risers', 'riser', 'treads', 'total-run', 'stringer', 'angle', 'comfort'].forEach((k) => setOut(k, '—'));
          return;
        }
        const n = Math.ceil(rise / maxR - 1e-9);
        const r = rise / n;
        const treads = n - 1;
        const totalRun = treads * run;
        setOut('risers', String(n));
        setOut('riser', fmtIn(r, d));
        setOut('treads', String(treads));
        setOut('total-run', fmtFtIn(totalRun, d));
        setOut('stringer', fmtFtIn(Math.hypot(rise, totalRun + run), d) + ' min.');
        setOut('angle', deg(Math.atan(r / run) * R2D));
        const rule = 2 * r + run;
        setOut('comfort', `2 × riser + tread = ${fmtDec(rule, 2)}" ${rule >= 24 && rule <= 25.5 ? '✓ comfortable' : rule < 24 ? '(a bit shallow)' : '(a bit steep)'}`);
      });
    },
  };

  const api = { parseLen, evalLen, fmtIn, fmtFtIn };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
    return;
  }
  root.ShopCalc = api;
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[data-calc]');
    if (form && calcs[form.dataset.calc]) calcs[form.dataset.calc](form);
  });
})(this);
