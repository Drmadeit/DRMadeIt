// Admin helpers: touch-friendly drag-to-reorder, confirm dialogs, photo previews.
(() => {
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';

  // ---- Drag to reorder (pointer events: works with finger, mouse and pen) ----
  document.querySelectorAll('[data-sortable]').forEach((list) => {
    let row = null;
    let startOrder = '';

    const order = () => [...list.children].map((li) => li.dataset.id);

    list.addEventListener('pointerdown', (ev) => {
      const grip = ev.target.closest('.a-grip');
      if (!grip) return;
      row = grip.closest('li');
      startOrder = order().join(',');
      row.classList.add('dragging');
      ev.preventDefault();
    });

    // Listen on document: moving the row in the DOM drops pointer capture.
    document.addEventListener('pointermove', (ev) => {
      if (!row) return;
      const y = ev.clientY;
      const others = [...list.children].filter((li) => li !== row);
      const next = others.find((li) => {
        const r = li.getBoundingClientRect();
        return y < r.top + r.height / 2;
      });
      if (next) {
        if (row.nextElementSibling !== next) list.insertBefore(row, next);
      } else if (list.lastElementChild !== row) {
        list.appendChild(row);
      }
    });

    const finish = async () => {
      if (!row) return;
      row.classList.remove('dragging');
      row = null;
      const ids = order();
      if (ids.join(',') === startOrder) return;
      const body = new URLSearchParams();
      body.append('csrf', csrf);
      ids.forEach((id) => body.append('ids[]', id));
      try {
        const res = await fetch(list.dataset.sortable, { method: 'POST', body });
        if (!res.ok) throw new Error();
      } catch {
        alert('Could not save the new order. Refresh and try again.');
      }
    };
    document.addEventListener('pointerup', finish);
    document.addEventListener('pointercancel', finish);
  });

  // ---- Confirm before destructive actions ----
  document.querySelectorAll('form[data-confirm]').forEach((f) => {
    f.addEventListener('submit', (ev) => {
      if (!confirm(f.dataset.confirm)) ev.preventDefault();
    });
  });
  // ---- One-tap "Remove photo": saves the form with the remove flag (a plain button, so Enter never triggers it) ----
  document.querySelectorAll('[data-remove-photo]').forEach((b) => {
    b.addEventListener('click', () => {
      if (!confirm(b.dataset.confirmText || 'Remove this photo?')) return;
      const flag = document.createElement('input');
      flag.type = 'hidden';
      flag.name = b.dataset.removePhoto;
      flag.value = '1';
      b.form.appendChild(flag);
      b.form.querySelectorAll('input[type=file]').forEach((f) => { f.value = ''; }); // don't upload a photo at the same moment
      b.form.requestSubmit ? b.form.requestSubmit() : b.form.submit();
    });
  });

  // ---- Crop: picking a single photo opens the cropper first (runs before the other photo handlers) ----
  const putFile = (input, file) => {
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    input.dataset.cropped = '1';
    input.dispatchEvent(new Event('change')); // now the preview / size handlers run on the cropped photo
    input.dataset.cropped = '';
  };
  document.querySelectorAll('input[type=file][data-crop]').forEach((input) => {
    input.addEventListener('change', async (ev) => {
      // Skip when the change comes from us (cropped) or from the shrink step re-sending the photo.
      if (input.dataset.cropped === '1' || input.dataset.shrunk === '1' || !window.cropPhoto || typeof DataTransfer === 'undefined') return;
      const file = input.files?.[0];
      if (!file) return;
      ev.stopImmediatePropagation();
      const cropped = await window.cropPhoto(file, { aspect: input.dataset.crop });
      if (!cropped) { input.value = ''; return; } // Cancel: nothing picked
      putFile(input, cropped);
    }, { capture: true });
  });

  // Re-crop a photo that's already saved, then save right away.
  document.querySelectorAll('[data-crop-existing]').forEach((b) => {
    b.addEventListener('click', async () => {
      const input = b.form.querySelector(`input[type=file][name="${b.dataset.cropExisting}"]`);
      const cropped = await window.cropPhoto(b.dataset.src, { aspect: b.dataset.aspect });
      if (!cropped || !input) return;
      putFile(input, cropped);
      b.form.requestSubmit ? b.form.requestSubmit() : b.form.submit();
    });
  });

  // Re-crop one photo in a page's or file's photo list.
  document.querySelectorAll('[data-crop-row]').forEach((b) => {
    b.addEventListener('click', async () => {
      const cropped = await window.cropPhoto(b.dataset.src, { aspect: 'original' });
      if (!cropped) return;
      const body = new FormData();
      body.append('csrf', csrf);
      body.append('photo', cropped);
      b.disabled = true;
      try {
        const res = await fetch(b.dataset.cropRow, { method: 'POST', body });
        if (!res.ok) throw new Error((await res.json().catch(() => ({}))).error || 'Upload failed');
        location.reload();
      } catch (e) {
        alert('Could not save the crop: ' + e.message);
        b.disabled = false;
      }
    });
  });

  // ---- Shrink photos on the phone before upload (fast on weak signal, under upload limits) ----
  const shrink = (file, max = 2000) => new Promise((resolve) => {
    if (!/^image\/(jpeg|png|webp|heic|heif)$/i.test(file.type) || file.size < 400 * 1024) return resolve(file);
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      const scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
      const c = document.createElement('canvas');
      c.width = Math.round(img.naturalWidth * scale);
      c.height = Math.round(img.naturalHeight * scale);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(url);
      c.toBlob((b) => resolve(b && b.size < file.size ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.85);
    };
    img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
    img.src = url;
  });
  document.querySelectorAll('input[type=file][accept^="image"]').forEach((input) => {
    input.addEventListener('change', async () => {
      if (!input.files?.length || input.dataset.shrunk === '1' || typeof DataTransfer === 'undefined') return;
      const form = input.form;
      const saves = form ? [...form.querySelectorAll('button:not([type=button])')] : [];
      saves.forEach((b) => { b.disabled = true; }); // wait until the photos are ready
      try {
        const dt = new DataTransfer();
        for (const f of input.files) dt.items.add(await shrink(f));
        input.dataset.shrunk = '1';
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
        input.dataset.shrunk = '';
      } catch { /* keep the originals */ }
      saves.forEach((b) => { b.disabled = false; });
    });
  });

  // ---- Repeating rows (page buttons) ----
  document.querySelectorAll('[data-repeat]').forEach((box) => {
    const addBtn = box.parentElement.querySelector('[data-repeat-add]');
    addBtn?.addEventListener('click', () => {
      const row = box.querySelector('[data-repeat-row]').cloneNode(true);
      row.querySelectorAll('input').forEach((i) => { i.value = ''; });
      box.appendChild(row);
      row.querySelector('input').focus();
    });
    box.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-repeat-remove]');
      if (!rm) return;
      const rows = box.querySelectorAll('[data-repeat-row]');
      const row = rm.closest('[data-repeat-row]');
      if (rows.length > 1) row.remove();
      else row.querySelectorAll('input').forEach((i) => { i.value = ''; });
    });
  });

  // ---- Say how many files were picked ----
  document.querySelectorAll('input[type=file][data-count]').forEach((input) => {
    input.addEventListener('change', () => {
      const n = input.files?.length || 0;
      const btn = input.previousElementSibling;
      if (btn && n) btn.textContent = n === 1 ? `${input.files[0].name} — tap Save` : `${n} photos picked — tap Save`;
    });
  });

  // ---- Show the chosen photo right away ----
  document.querySelectorAll('input[type=file][data-preview]').forEach((input) => {
    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (!file) return;
      const box = input.closest('.a-photo');
      let img = box.querySelector('img');
      if (!img) {
        img = document.createElement('img');
        img.width = img.height = 96;
        img.alt = 'New photo';
        box.insertBefore(img, input.closest('.a-file'));
      }
      img.src = URL.createObjectURL(file);
      const btn = input.previousElementSibling;
      if (btn) btn.textContent = 'Photo picked — tap Save';
    });
  });

  // ---- Show parts of a form only for one choice (e.g. Free vs Paid) ----
  document.querySelectorAll('form').forEach((form) => {
    const parts = form.querySelectorAll('[data-show-if]');
    if (!parts.length) return;
    const sync = () => parts.forEach((el) => {
      const [name, value] = el.dataset.showIf.split('=');
      const picked = form.querySelector(`input[name="${name}"]:checked`);
      el.hidden = !picked || !value.split('|').includes(picked.value);
    });
    form.addEventListener('change', sync);
    sync();
  });

  // ---- Taking an address something else used to have: ask first (old links would land on the new thing) ----
  document.querySelectorAll('form[data-reuse-check]').forEach((f) => {
    let old = {};
    try { old = JSON.parse(f.dataset.reuseCheck || '{}'); } catch (e) {}
    const input = f.querySelector('[data-reuse-input]');
    const ok = f.querySelector('input[name="reuse_ok"]');
    if (!input || !ok) return;
    f.addEventListener('submit', (ev) => {
      const word = input.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      if (!word || word === f.dataset.reuseCurrent || !(word in old)) return;
      const where = f.dataset.reusePrefix + word;
      if (confirm(`Hey fuckface, you're about to piss people off. ${where} used to go to "${old[word]}". Anyone with that old link will land here instead. Use it anyway?`)) {
        ok.value = '1';
      } else {
        ev.preventDefault();
      }
    });
  });

  // ---- Swipe a row: left = delete, right = its quick action (e.g. Publish / Unpublish) ----
  const postTo = (url, extra = {}) => {
    const f = document.createElement('form');
    f.method = 'post';
    f.action = url;
    const add = (name, value) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = value; f.append(i); };
    add('csrf', document.querySelector('meta[name="csrf"]').content);
    Object.entries(extra).forEach(([k, v]) => [].concat(v).forEach((one) => add(k, one)));
    document.body.append(f);
    f.submit();
  };
  (() => {
    const rows = document.querySelectorAll('li[data-delete], li[data-right]');
    if (!rows.length) return;
    const OPEN = 92;
    let open = null;
    const close = (row) => { if (row) { row.style.transform = ''; row.dataset.open = ''; } if (open === row) open = null; };
    const isOpen = (row) => row.dataset.open === 'left' || row.dataset.open === 'right';
    rows.forEach((row) => {
      row.classList.add('a-swipe');
      if (row.dataset.delete) {
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'a-swipe-act a-swipe-del';
        del.textContent = 'Delete';
        del.addEventListener('click', (ev) => {
          ev.stopPropagation();
          if (confirm(row.dataset.confirmDelete || 'Delete this?')) postTo(row.dataset.delete);
          else close(row);
        });
        row.append(del);
      }
      if (row.dataset.right) {
        const act = document.createElement('button');
        act.type = 'button';
        act.className = 'a-swipe-act a-swipe-right' + (row.dataset.rightTone ? ' is-' + row.dataset.rightTone : '');
        act.textContent = row.dataset.rightLabel || 'Go';
        act.addEventListener('click', (ev) => { ev.stopPropagation(); postTo(row.dataset.right); });
        row.append(act);
      }
      let x0 = 0, y0 = 0, dx = 0, base = 0, dir = null;
      row.addEventListener('touchstart', (e) => {
        if (e.target.closest('.a-grip') || document.body.classList.contains('is-selecting')) { dir = 'skip'; return; }
        if (open && open !== row) close(open);
        x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; dx = 0; dir = null;
        base = row.dataset.open === 'left' ? -OPEN : row.dataset.open === 'right' ? OPEN : 0;
        row.style.transition = 'none';
      }, { passive: true });
      row.addEventListener('touchmove', (e) => {
        if (dir === 'skip') return;
        const mx = e.touches[0].clientX - x0, my = e.touches[0].clientY - y0;
        if (!dir) { if (Math.abs(mx) < 8 && Math.abs(my) < 8) return; dir = Math.abs(mx) > Math.abs(my) ? 'x' : 'y'; }
        if (dir !== 'x') return;
        dx = mx;
        const min = row.dataset.delete ? -OPEN - 30 : 0, max = row.dataset.right ? OPEN + 30 : 0;
        row.style.transform = `translateX(${Math.min(max, Math.max(min, base + dx))}px)`;
      }, { passive: true });
      row.addEventListener('touchend', () => {
        row.style.transition = '';
        if (dir !== 'x') { dir = null; return; }
        const at = base + dx;
        if (at < -OPEN / 2 && row.dataset.delete) { row.style.transform = `translateX(${-OPEN}px)`; row.dataset.open = 'left'; open = row; }
        else if (at > OPEN / 2 && row.dataset.right) { row.style.transform = `translateX(${OPEN}px)`; row.dataset.open = 'right'; open = row; }
        else close(row);
        dir = null;
      });
      // A swipe shouldn't also count as a tap on the row's link
      row.addEventListener('click', (e) => {
        if (isOpen(row) && !e.target.closest('.a-swipe-act')) { e.preventDefault(); close(row); }
      }, true);
    });
    document.addEventListener('touchstart', (e) => { if (open && !open.contains(e.target)) close(open); }, { passive: true });
  })();

  // ---- Select mode: tick rows, then act on all of them at once ----
  document.querySelectorAll('[data-select-list]').forEach((wrap) => {
    const list = wrap.querySelector('.a-list');
    const bar = wrap.querySelector('.a-select-bar');
    const toggle = wrap.querySelector('[data-select-toggle]');
    const all = wrap.querySelector('[data-select-all]');
    const countEls = wrap.querySelectorAll('[data-select-count]');
    const rows = [...list.querySelectorAll('li[data-id]')];
    const picked = new Set();
    const sync = () => {
      rows.forEach((r) => r.classList.toggle('is-picked', picked.has(r.dataset.id)));
      countEls.forEach((el) => { el.textContent = picked.size; });
      bar.querySelectorAll('button[data-bulk]').forEach((b) => { b.disabled = !picked.size; });
      all.textContent = picked.size === rows.length ? 'Select none' : 'Select all';
    };
    toggle.addEventListener('click', () => {
      const on = !document.body.classList.contains('is-selecting');
      document.body.classList.toggle('is-selecting', on);
      bar.hidden = !on;
      toggle.textContent = on ? 'Done' : 'Select';
      if (!on) picked.clear();
      sync();
    });
    all.addEventListener('click', () => {
      if (picked.size === rows.length) picked.clear(); else rows.forEach((r) => picked.add(r.dataset.id));
      sync();
    });
    rows.forEach((r) => r.addEventListener('click', (e) => {
      if (!document.body.classList.contains('is-selecting')) return;
      e.preventDefault();
      e.stopPropagation();
      picked.has(r.dataset.id) ? picked.delete(r.dataset.id) : picked.add(r.dataset.id);
      sync();
    }, true));
    bar.querySelectorAll('button[data-bulk]').forEach((b) => b.addEventListener('click', () => {
      const action = b.dataset.bulk;
      const n = picked.size;
      if (!n) return;
      if (action === 'delete') {
        const uses = rows.filter((r) => picked.has(r.dataset.id)).reduce((sum, r) => sum + Number(r.dataset.uses || 0), 0);
        const msg = uses > 0
          ? `Hey fuckface, you're about to piss people off. ${uses} ${uses === 1 ? 'person' : 'people'} used these ${n} in the last month. Delete anyway?`
          : `Delete ${n} file${n === 1 ? '' : 's'}?`;
        if (!confirm(msg)) return;
      }
      postTo(wrap.dataset.selectList, { action, 'ids[]': [...picked] });
    }));
  });

  // ---- Slow forms: show that it's working ----
  document.querySelectorAll('form[data-busy]').forEach((f) => {
    f.addEventListener('submit', () => {
      const b = f.querySelector('button:not([type=button])');
      if (b) { b.disabled = true; b.textContent = f.dataset.busy; }
    });
  });

  // ---- Copy buttons ----
  document.querySelectorAll('[data-copy]').forEach((b) => {
    b.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(b.dataset.copy);
        const was = b.textContent;
        b.textContent = 'Copied ✓';
        setTimeout(() => { b.textContent = was; }, 1500);
      } catch {
        prompt('Copy this link:', b.dataset.copy);
      }
    });
  });
})();
