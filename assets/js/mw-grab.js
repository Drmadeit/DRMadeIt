// Run on your MakerWorld profile (as a bookmark): scrolls to load every model, then shows the list to copy into DR Admin → Files → Import.
(async () => {
  if (!/makerworld\.com/.test(location.hostname)) { alert('Open your MakerWorld profile first, then tap this bookmark.'); return; }
  const count = () => document.querySelectorAll('a[href*="/models/"]').length;
  let last = -1;
  for (let i = 0; i < 80 && count() !== last; i++) {
    last = count();
    window.scrollTo(0, document.body.scrollHeight);
    await new Promise((r) => setTimeout(r, 1500));
  }
  const clean = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const looksLikeTitle = (s) => s.length > 2 && s.length < 150 && !/^[\d.,kKmM\s]+$/.test(s);
  const models = new Map();
  document.querySelectorAll('a[href*="/models/"]').forEach((a) => {
    const id = (a.href.match(/\/models\/(\d+)/) || [])[1];
    if (!id) return;
    const m = models.get(id) || { url: a.href.split(/[?#]/)[0], title: '', img: '' };
    let card = a;
    for (let k = 0; k < 4 && card.parentElement && card.parentElement.querySelectorAll('a[href*="/models/' + id + '"]').length === card.querySelectorAll('a[href*="/models/' + id + '"]').length; k++) card = card.parentElement;
    const img = a.querySelector('img') || card.querySelector('img');
    const tries = [a.getAttribute('title'), img && img.alt, ...[...card.querySelectorAll('h1,h2,h3,h4,[class*=title],[class*=Title]')].map((e) => e.textContent), a.textContent].map(clean).filter(looksLikeTitle);
    if (!m.title && tries[0]) m.title = tries[0];
    if (!m.img && img) m.img = img.currentSrc || img.src || '';
    models.set(id, m);
  });
  const text = [...models.values()].map((m) => [m.url, m.title.replace(/\|/g, '/'), m.img].join(' | ')).join('\n');
  document.body.innerHTML = '';
  document.body.style.cssText = 'margin:0;padding:16px;font:16px system-ui;background:#fff;color:#111';
  const h = document.createElement('p');
  h.textContent = 'Found ' + models.size + ' models. Tap Copy, then paste into DR Admin → Files → Import from MakerWorld.';
  const b = document.createElement('button');
  b.textContent = 'Copy';
  b.style.cssText = 'font:600 18px system-ui;padding:14px 28px;border:0;border-radius:10px;background:#d7141e;color:#fff;margin:0 0 12px';
  const t = document.createElement('textarea');
  t.value = text;
  t.style.cssText = 'width:100%;height:60vh;font:12px ui-monospace,monospace';
  b.onclick = async () => {
    try { await navigator.clipboard.writeText(text); b.textContent = 'Copied ✓'; } catch (e) { t.focus(); t.select(); document.execCommand('copy'); b.textContent = 'Copied ✓'; }
  };
  document.body.append(h, b, t);
})();
