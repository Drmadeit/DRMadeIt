// Photo cropper for the admin. Full screen: drag to move, pinch (or the slider) to zoom, pick a shape.
// window.cropPhoto(source, { aspect }) → Promise<File|null>. source is a File or an image URL.
(() => {
  const SHAPES = { original: 'Original', '1': 'Square', '4/3': '4:3', '16/9': '16:9', '3/4': '3:4' };
  const MAX = 2000; // longest side of the result, px

  let ui = null;
  function build() {
    const el = document.createElement('div');
    el.className = 'crop';
    el.hidden = true;
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Crop photo');
    el.innerHTML = `
      <div class="crop-top">
        <button type="button" class="crop-btn" data-act="cancel">Cancel</button>
        <b>Crop</b>
        <button type="button" class="crop-btn crop-use" data-act="use">Use</button>
      </div>
      <div class="crop-stage"><div class="crop-frame"><img alt="Photo being cropped" draggable="false"></div></div>
      <div class="crop-bottom">
        <input type="range" class="crop-zoom" min="1" max="4" step="0.01" value="1" aria-label="Zoom">
        <div class="crop-shapes" role="group" aria-label="Shape">${['original', '1', '4/3', '16/9', '3/4'].map((v) => `<button type="button" data-shape="${v}">${SHAPES[v]}</button>`).join('')}</div>
        <p class="crop-hint">Drag to move · pinch or slide to zoom</p>
      </div>`;
    document.body.appendChild(el);
    return { el, stage: el.querySelector('.crop-stage'), frame: el.querySelector('.crop-frame'), img: el.querySelector('img'), zoom: el.querySelector('.crop-zoom') };
  }

  window.cropPhoto = (source, opts = {}) => new Promise((resolve) => {
    ui = ui || build();
    const { el, stage, frame, img, zoom } = ui;
    const url = source instanceof Blob ? URL.createObjectURL(source) : source;
    const name = (source.name || 'photo').replace(/\.\w+$/, '') + '.jpg';
    let shape = opts.aspect && SHAPES[opts.aspect] ? opts.aspect : 'original';
    let iw = 0, ih = 0, fw = 0, fh = 0, base = 1, z = 1, tx = 0, ty = 0;
    const pointers = new Map();
    let pinch = null;

    const RATIOS = { 1: 1, '4/3': 4 / 3, '16/9': 16 / 9, '3/4': 3 / 4 };
    const ratio = () => (shape === 'original' ? iw / ih : RATIOS[shape]);
    function layout(keepCenter) {
      const sw = stage.clientWidth - 32;
      const sh = stage.clientHeight - 32;
      const r = ratio();
      fw = Math.min(sw, sh * r);
      fh = fw / r;
      frame.style.width = fw + 'px';
      frame.style.height = fh + 'px';
      base = Math.max(fw / iw, fh / ih); // smallest zoom that still fills the frame
      if (!keepCenter) {
        z = 1;
        tx = (fw - iw * base) / 2;
        ty = (fh - ih * base) / 2;
      }
      clamp();
      draw();
    }
    const s = () => base * z;
    function clamp() {
      tx = Math.min(0, Math.max(fw - iw * s(), tx));
      ty = Math.min(0, Math.max(fh - ih * s(), ty));
    }
    function draw() {
      img.style.transform = `translate(${tx}px, ${ty}px) scale(${s()})`;
      zoom.value = String(z);
      el.querySelectorAll('[data-shape]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.shape === shape)));
    }
    function zoomTo(nz, cx = fw / 2, cy = fh / 2) {
      nz = Math.min(4, Math.max(1, nz));
      const ix = (cx - tx) / s();
      const iy = (cy - ty) / s();
      z = nz;
      tx = cx - ix * s();
      ty = cy - iy * s();
      clamp();
      draw();
    }

    const local = (e) => { const r = frame.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
    function down(e) {
      stage.setPointerCapture(e.pointerId);
      pointers.set(e.pointerId, local(e));
      if (pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        pinch = { d: Math.hypot(a[0] - b[0], a[1] - b[1]), z };
      }
    }
    function move(e) {
      if (!pointers.has(e.pointerId)) return;
      const prev = pointers.get(e.pointerId);
      const cur = local(e);
      pointers.set(e.pointerId, cur);
      if (pointers.size === 1) {
        tx += cur[0] - prev[0];
        ty += cur[1] - prev[1];
        clamp();
        draw();
      } else if (pointers.size === 2 && pinch) {
        const [a, b] = [...pointers.values()];
        zoomTo(pinch.z * (Math.hypot(a[0] - b[0], a[1] - b[1]) / pinch.d), (a[0] + b[0]) / 2, (a[1] + b[1]) / 2);
      }
    }
    function up(e) { pointers.delete(e.pointerId); if (pointers.size < 2) pinch = null; }
    function wheel(e) { e.preventDefault(); const [x, y] = local(e); zoomTo(z * (e.deltaY < 0 ? 1.08 : 1 / 1.08), x, y); }
    const onZoom = () => zoomTo(+zoom.value);
    const onResize = () => layout(true);

    function finish(result) {
      el.hidden = true;
      document.body.style.overflow = '';
      stage.removeEventListener('pointerdown', down);
      stage.removeEventListener('pointermove', move);
      stage.removeEventListener('pointerup', up);
      stage.removeEventListener('pointercancel', up);
      stage.removeEventListener('wheel', wheel);
      zoom.removeEventListener('input', onZoom);
      window.removeEventListener('resize', onResize);
      el.onclick = null;
      if (source instanceof Blob) URL.revokeObjectURL(url);
      resolve(result);
    }

    el.onclick = (e) => {
      const act = e.target.closest('[data-act]')?.dataset.act;
      const sh = e.target.closest('[data-shape]')?.dataset.shape;
      if (sh) { shape = sh; layout(false); }
      if (act === 'cancel') finish(null);
      if (act === 'use') {
        // The part of the photo inside the frame, at full resolution (up to 2000 px).
        const sx = -tx / s(), sy = -ty / s(), sw = fw / s(), sh2 = fh / s();
        const k = Math.min(1, MAX / Math.max(sw, sh2));
        const c = document.createElement('canvas');
        c.width = Math.round(sw * k);
        c.height = Math.round(sh2 * k);
        c.getContext('2d').drawImage(img, sx, sy, sw, sh2, 0, 0, c.width, c.height);
        c.toBlob((b) => finish(b ? new File([b], name, { type: 'image/jpeg' }) : null), 'image/jpeg', 0.9);
      }
    };

    img.onload = () => {
      iw = img.naturalWidth;
      ih = img.naturalHeight;
      el.hidden = false;
      document.body.style.overflow = 'hidden';
      requestAnimationFrame(() => layout(false));
    };
    img.onerror = () => { alert("Couldn't open that photo."); finish(null); };
    stage.addEventListener('pointerdown', down);
    stage.addEventListener('pointermove', move);
    stage.addEventListener('pointerup', up);
    stage.addEventListener('pointercancel', up);
    stage.addEventListener('wheel', wheel, { passive: false });
    zoom.addEventListener('input', onZoom);
    window.addEventListener('resize', onResize);
    img.src = url;
  });
})();
