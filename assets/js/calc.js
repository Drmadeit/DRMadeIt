/* Shop calculator math: lengths, fractions, tape math. Used by the apps in /calc. Runs in the browser. */
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
    if (num && !opts.keepDenom) { // keepDenom: 44/64 stays 44/64 instead of 11/16
      const g = gcd(num, den);
      num /= g;
      den /= g;
    }
    let out = (whole || !num ? String(whole) : '') + (num ? (whole ? ' ' : '') + num + '/' + den : '');
    const err = Math.abs(x) - units / denom;
    const mark = opts.noMark || Math.abs(err) < 1e-6 ? '' : err > 0 ? '+' : '−';
    return (neg ? '-' : '') + out + '"' + (mark ? ' ' + mark : '');
  }

  function fmtFtIn(x, denom = 16, opts = {}) {
    if (!isFinite(x)) return '—';
    const neg = x < 0;
    let ax = Math.abs(x);
    let ft = Math.floor(ax / 12);
    let inch = ax - ft * 12;
    if (Math.round(inch * denom) === 12 * denom) {
      ft += 1;
      inch = 0;
    }
    return (neg ? '-' : '') + (ft ? ft + "' " : '') + fmtIn(inch, denom, { ...opts, noMark: true });
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


  const api = { parseLen, evalLen, fmtIn, fmtFtIn };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
    return;
  }
  root.ShopCalc = api;
})(this);
