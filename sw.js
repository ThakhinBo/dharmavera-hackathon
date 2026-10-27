// DharmaVera service worker
// Pages (and all personal data) always come from the network; only static
// artwork is cached. When the device is offline, page loads show offline.html.
const CACHE = 'dharmavera-static-v3';
const PRECACHE = [
  '/offline.html',
  '/dharmavera.css',
  '/app-icons/icon-192.png?v=2',
  '/app-icons/icon-512.png?v=2',
  '/assets/cards/back.svg',
  '/assets/brand/logo-160.png?v=1'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Page navigations: network only, offline page as fallback.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // Card artwork and app icons never change: cache first.
  if (url.pathname.startsWith('/assets/cards/') || url.pathname.startsWith('/app-icons/')) {
    event.respondWith(
      caches.match(req).then(hit => hit || fetch(req).then(res => {
        if (res.ok) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(req, copy)); }
        return res;
      }))
    );
  }
  // Everything else (PHP, API, CSS, JS) goes straight to the network.
});
