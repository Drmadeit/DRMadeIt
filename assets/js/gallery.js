// Tap a photo to see it full size. Swipe or use the arrows to move between photos.
(() => {
  const links = [...document.querySelectorAll('a[data-lightbox]')];
  if (!links.length) return;
  let i = 0;
  const box = document.createElement('div');
  box.className = 'lightbox';
  box.hidden = true;
  box.setAttribute('role', 'dialog');
  box.setAttribute('aria-modal', 'true');
  box.setAttribute('aria-label', 'Photo viewer');
  box.innerHTML = '<img alt=""><button class="lb-close" aria-label="Close">✕</button>'
    + '<button class="lb-prev" aria-label="Previous photo">‹</button><button class="lb-next" aria-label="Next photo">›</button>';
  document.body.appendChild(box);
  const img = box.querySelector('img');
  const multi = links.length > 1;
  box.querySelector('.lb-prev').hidden = box.querySelector('.lb-next').hidden = !multi;

  const show = (n) => {
    i = (n + links.length) % links.length;
    img.src = links[i].href;
    img.alt = links[i].querySelector('img')?.alt || '';
  };
  const open = (n) => {
    show(n);
    box.hidden = false;
    document.body.style.overflow = 'hidden';
    box.querySelector('.lb-close').focus();
  };
  const close = () => {
    box.hidden = true;
    document.body.style.overflow = '';
    links[i].focus();
  };

  links.forEach((a, n) => a.addEventListener('click', (e) => { e.preventDefault(); open(n); }));
  box.querySelector('.lb-close').addEventListener('click', close);
  box.querySelector('.lb-prev').addEventListener('click', () => show(i - 1));
  box.querySelector('.lb-next').addEventListener('click', () => show(i + 1));
  box.addEventListener('click', (e) => { if (e.target === box) close(); });
  document.addEventListener('keydown', (e) => {
    if (box.hidden) return;
    if (e.key === 'Escape') close();
    if (multi && e.key === 'ArrowLeft') show(i - 1);
    if (multi && e.key === 'ArrowRight') show(i + 1);
  });
  let x0 = null;
  box.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', (e) => {
    if (x0 === null || !multi) return;
    const dx = e.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 50) show(dx < 0 ? i + 1 : i - 1);
    x0 = null;
  });
})();
