// Offline worker for the shop calculator apps. VERSION and FILES are written above this line by the server.
// Every app saves every other app, so the ☰ menu works with no signal.
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(FILES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => /^(apps|tape)-/.test(k) && k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // App pages: try the network (gets updates), fall back to the saved copy when offline.
  if (req.mode === 'navigate') {
    const key = url.pathname;
    e.respondWith(
      fetch(req)
        .then((res) => {
          if (res.ok && !res.redirected) {
            const copy = res.clone();
            caches.open(VERSION).then((c) => c.put(key, copy));
          }
          return res;
        })
        .catch(() => caches.match(key).then((hit) => hit || caches.match('/calc/')))
    );
    return;
  }
  // Everything else: saved copy first (instant, works offline).
  e.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
});
