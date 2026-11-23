// Run on your MakerWorld profile (as a bookmark): loads every model, reads each one's description, then shows it all to copy into DR Admin → Files → Import.
(async () => {
  if (!/makerworld\.com/.test(location.hostname)) { alert('Open your MakerWorld profile first, then tap this bookmark.'); return; }
  const note = document.createElement('div');
  note.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:99999;padding:14px;border-radius:12px;background:#111;color:#fff;font:600 16px system-ui;text-align:center';
  const say = (t) => { note.textContent = t; };
  document.body.append(note);
  say('Loading your models…');
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
    const m = models.get(id) || { url: a.href.split(/[?#]/)[0], title: '', img: '', description: '' };
    let card = a;
    for (let k = 0; k < 4 && card.parentElement && card.parentElement.querySelectorAll('a[href*="/models/' + id + '"]').length === card.querySelectorAll('a[href*="/models/' + id + '"]').length; k++) card = card.parentElement;
    const img = a.querySelector('img') || card.querySelector('img');
    const tries = [a.getAttribute('title'), img && img.alt, ...[...card.querySelectorAll('h1,h2,h3,h4,[class*=title],[class*=Title]')].map((e) => e.textContent), a.textContent].map(clean).filter(looksLikeTitle);
    if (!m.title && tries[0]) m.title = tries[0];
    if (!m.img && img) m.img = img.currentSrc || img.src || '';
    models.set(id, m);
  });
  // Each model's description, read from its own page (your browser is allowed in)
  const toText = (html) => {
    const d = document.createElement('div');
    d.innerHTML = String(html).replace(/<\/(p|div|h\d|li)>/gi, '\n\n').replace(/<br\s*\/?>/gi, '\n').replace(/<li[^>]*>/gi, '• ');
    return d.textContent.replace(/[ \t]+/g, ' ').replace(/\n\s*\n\s*(\n\s*)+/g, '\n\n').trim();
  };
  const findSummary = (o, depth) => {
    if (!o || typeof o !== 'object' || depth > 8) return '';
    for (const k of ['summary', 'description', 'designSummary']) {
      if (typeof o[k] === 'string' && o[k].length > 20) return o[k];
    }
    for (const v of Object.values(o)) { const r = findSummary(v, depth + 1); if (r) return r; }
    return '';
  };
  let n = 0;
  for (const m of models.values()) {
    say('Reading descriptions… ' + (++n) + ' of ' + models.size);
    try {
      const html = await (await fetch(m.url, { credentials: 'include' })).text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const next = doc.getElementById('__NEXT_DATA__');
      let text = '';
      if (next) { try { text = toText(findSummary(JSON.parse(next.textContent).props, 0)); } catch (e) {} }
      if (!text) { const meta = doc.querySelector('meta[property="og:description"], meta[name="description"]'); text = meta ? meta.content.trim() : ''; }
      m.description = text.slice(0, 5000);
      if (!m.img) { const og = doc.querySelector('meta[property="og:image"]'); if (og) m.img = og.content; }
    } catch (e) {}
  }
  const text = 'MAKERWORLD:' + JSON.stringify([...models.values()]);
  note.remove();
  document.body.innerHTML = '';
  document.body.style.cssText = 'margin:0;padding:16px;font:16px system-ui;background:#fff;color:#111';
  const h = document.createElement('p');
  const withText = [...models.values()].filter((m) => m.description).length;
  h.textContent = 'Found ' + models.size + ' models (' + withText + ' with descriptions). Tap Copy, then paste into DR Admin → Files → Import from MakerWorld.';
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
