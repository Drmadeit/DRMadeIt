// Fractions app keypad. Uses parseLen / evalLen / fmtIn from calc.js. Works offline.
(() => {
  const C = window.ShopCalc;
  const $ = (id) => document.getElementById(id);
  const OPS = ['+', '−', '×', '÷'];
  const store = {
    get(k, d) { try { const v = localStorage.getItem('tape.' + k); return v === null ? d : JSON.parse(v); } catch { return d; } },
    set(k, v) { try { localStorage.setItem('tape.' + k, JSON.stringify(v)); } catch { /* private mode */ } },
  };

  let expr = '';
  let fresh = false;                 // just pressed = : a digit starts over, an operator keeps going
  let ans = null;                    // { text, value } so chaining uses the exact value, not the rounded one
  let prec = store.get('prec', 16);
  let history = store.get('history', []);

  const lastToken = () => expr.split(/ [+−×÷] /).pop();
  const endsWithOp = () => / [+−×÷] $/.test(expr);
  const lastChar = () => expr.slice(-1);
  const isDigit = (ch) => /\d/.test(ch);
  const afterMultiply = () => / [×÷] [^+−×÷]*$/.test(expr); // × and ÷ take a plain number

  function value(text = expr) {
    let t = text.trim();
    if (!t) return NaN;
    if (ans && t.startsWith(ans.text)) t = String(ans.value) + t.slice(ans.text.length);
    t = t.replace(/−/g, '-').replace(/ [+\-×÷]$/, '');
    return C.evalLen(t);
  }

  // Answers show in the size picked at the top (11/16 at 1/64 = 44/64) unless Simplify is on (= 11/16).
  let simplify = store.get('simplify', false);
  const opts = () => ({ keepDenom: !simplify });
  const fmt = (x) => C.fmtIn(x, prec, opts());
  const plain = (x) => C.fmtIn(x, prec, { ...opts(), noMark: true }).replace('"', '');

  function render() {
    $('expr').textContent = expr || ' ';
    const x = value();
    const shown = isFinite(x) ? x : (fresh && ans ? ans.value : 0);
    $('result').textContent = fmt(shown);
    $('ftin').textContent = Math.abs(shown) >= 12 ? C.fmtFtIn(shown, prec, opts()) : '';
    $('dec').textContent = (+shown.toFixed(4)) + '"';
    $('mm').textContent = (+(shown * 25.4).toFixed(1)) + ' mm';
    document.querySelectorAll('[data-prec]').forEach((b) => b.setAttribute('aria-checked', String(+b.dataset.prec === prec)));
    $('simplify').setAttribute('aria-pressed', String(simplify));
    const list = $('history');
    list.innerHTML = '';
    history.slice(-30).forEach((h) => {
      const li = document.createElement('li');
      const b = document.createElement('button');
      b.type = 'button';
      b.innerHTML = `<span></span><b></b>`;
      b.firstChild.textContent = h.expr + ' =';
      b.lastChild.textContent = fmt(h.value);
      b.addEventListener('click', () => useValue(h.value));
      li.appendChild(b);
      list.appendChild(li);
    });
    list.scrollTop = list.scrollHeight;
  }

  function useValue(v) {
    ans = { text: plain(v), value: v };
    if (!expr || fresh) expr = ans.text;
    else if (endsWithOp()) expr += ans.text;
    fresh = false;
    render();
  }

  function press(k) {
    if (fresh && (isDigit(k) || k === '.')) { expr = ''; ans = null; fresh = false; }
    if (fresh && k !== '=' && k !== 'clear') fresh = false;
    const tok = lastToken();

    if (isDigit(k)) {
      expr += k;
    } else if (k === '.') {
      if (!/[./]/.test(tok)) expr += (isDigit(lastChar()) ? '' : '0') + '.';
    } else if (k === 'space') {           // 23 ␣ 5/8
      if (isDigit(lastChar()) && !/[ ./]/.test(tok.replace(/^\d+' /, '')) && !afterMultiply()) expr += ' ';
    } else if (k === '/') {
      if (isDigit(lastChar()) && !tok.includes('/') && !tok.includes('.') && !afterMultiply()) expr += '/';
    } else if (k === 'ft') {
      if (isDigit(lastChar()) && !tok.includes("'") && !/[ ./]/.test(tok) && !afterMultiply()) expr += "' ";
    } else if (OPS.includes(k)) {
      if (!expr && ans) expr = ans.text;
      if (!expr) return;
      expr = expr.replace(/[ /.]+$/, '');
      if (endsWithOp()) expr = expr.slice(0, -3);
      expr += ` ${k} `;
    } else if (k === 'back') {
      if (endsWithOp()) expr = expr.slice(0, -3);
      else if (expr.endsWith("' ")) expr = expr.slice(0, -2);
      else expr = expr.slice(0, -1);
      if (ans && !expr.startsWith(ans.text)) ans = null;
    } else if (k === 'clear') {
      expr = '';
      ans = null;
      fresh = false;
    } else if (k === '=') {
      const x = value();
      if (!isFinite(x)) { flash('Check the numbers'); return; }
      history.push({ expr: expr.trim().replace(/ [+−×÷]$/, ''), value: x });
      history = history.slice(-50);
      store.set('history', history);
      ans = { text: plain(x), value: x };
      expr = ans.text;
      fresh = true;
    }
    render();
  }

  function flash(msg) {
    const h = $('hint');
    const old = h.innerHTML;
    h.textContent = msg;
    h.classList.add('warn');
    setTimeout(() => { h.innerHTML = old; h.classList.remove('warn'); }, 1600);
  }

  // Keys: react on pointerdown for speed, ignore the follow-up click.
  document.querySelectorAll('[data-k]').forEach((b) => {
    b.addEventListener('pointerdown', (e) => { e.preventDefault(); b.classList.add('down'); press(b.dataset.k); });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach((t) => b.addEventListener(t, () => b.classList.remove('down')));
    b.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); press(b.dataset.k); } });
  });
  document.querySelectorAll('[data-prec]').forEach((b) => b.addEventListener('click', () => {
    prec = +b.dataset.prec;
    store.set('prec', prec);
    if (ans) ans.text = plain(ans.value);
    if (fresh && ans) expr = ans.text;
    render();
  }));
  $('simplify').addEventListener('click', () => {
    simplify = !simplify;
    store.set('simplify', simplify);
    if (ans) ans.text = plain(ans.value);
    if (fresh && ans) expr = ans.text;
    render();
  });
  $('result').addEventListener('click', async () => {
    const text = $('result').textContent.replace(/ [+−]$/, '');
    try { await navigator.clipboard.writeText(text); flash('Copied ' + text); } catch { flash(text); }
  });

  // Hardware keyboard (iPad / Mac)
  document.addEventListener('keydown', (e) => {
    const map = { '+': '+', '-': '−', '*': '×', 'x': '×', '/': '/', 'Enter': '=', '=': '=', 'Backspace': 'back', 'Escape': 'clear', ' ': 'space', "'": 'ft', '.': '.' };
    const k = /^\d$/.test(e.key) ? e.key : map[e.key];
    if (k && !e.metaKey && !e.ctrlKey) { e.preventDefault(); press(k); }
  });

  render();
})();
