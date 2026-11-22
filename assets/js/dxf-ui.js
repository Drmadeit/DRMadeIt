// Page wiring for the DXF fixer. The work is done by dxf.js, all in the browser.
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('dxf-form');
  const out = (k) => document.querySelector(`[data-out="${k}"]`);
  const btn = form.querySelector('button');
  const UNITS = { 1: ['inches', 0.001, 0.03], 2: ['feet', 0.0001, 0.003], 4: ['mm', 0.02, 0.8], 5: ['cm', 0.002, 0.08], 6: ['meters', 0.00002, 0.0008] };
  let text = null;
  let name = 'drawing.dxf';
  let url = null;

  form.file.addEventListener('change', async () => {
    const f = form.file.files[0];
    if (!f) return;
    name = f.name.replace(/\.dxf$/i, '') + '-fixed.dxf';
    text = await f.text();
    out('filename').textContent = `${f.name} (${f.size < 1024 ? f.size + ' bytes' : Math.round(f.size / 1024) + ' KB'})`;
    btn.disabled = false;
    try {
      const u = UNITS[DxfFix.parse(text).units];
      if (u) {
        out('units').textContent = `Drawing units: ${u[0]}.`;
        form.tol.value = u[1];
        form.gap.value = u[2];
      } else {
        out('units').textContent = 'Drawing units not set in the file: using the numbers as they are.';
      }
    } catch (e) { /* shown on Fix */ }
    run();
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    run();
  });

  function run() {
    if (!text) return;
    const tol = parseFloat(form.tol.value);
    const gap = parseFloat(form.gap.value) || 0;
    if (!(tol > 0)) {
      alert('Join tolerance must be more than 0.');
      return;
    }
    let res;
    try {
      res = DxfFix.fix(DxfFix.parse(text), { tol, gap });
    } catch (e) {
      alert(e.message || 'Could not read that file.');
      return;
    }
    const r = res.report;
    out('closed').textContent = r.closed;
    out('open').textContent = r.open;
    out('dupes').textContent = r.duplicates;
    out('bridged').textContent = r.bridged;
    out('zero').textContent = r.zeroLength;
    out('circles').textContent = r.circles;
    const skipped = Object.entries(r.skipped).map(([t, n]) => `${n} ${t}`).join(', ');
    out('notes').textContent =
      (r.open ? `${r.open} shape(s) are still open: look for the red dots. ` : 'Every shape is closed ✓ ') +
      (skipped ? `Left out: ${skipped}.` : '');
    out('preview').innerHTML = DxfFix.svg(res);
    if (url) URL.revokeObjectURL(url);
    url = URL.createObjectURL(new Blob([DxfFix.write(res)], { type: 'application/dxf' }));
    const a = out('download');
    a.href = url;
    a.download = name;
    out('result').hidden = false;
  }
});
