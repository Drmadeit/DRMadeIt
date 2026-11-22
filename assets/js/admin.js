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
      const save = form?.querySelector('button:not([type=button])');
      if (save) save.disabled = true;
      try {
        const dt = new DataTransfer();
        for (const f of input.files) dt.items.add(await shrink(f));
        input.dataset.shrunk = '1';
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
        input.dataset.shrunk = '';
      } catch { /* keep the originals */ }
      if (save) save.disabled = false;
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
})();
