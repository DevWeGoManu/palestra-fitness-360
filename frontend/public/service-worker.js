const CACHE_NAME = 'fitness-360-v4';
const scopeUrl = new URL(self.registration.scope);
const scopePath = scopeUrl.pathname.endsWith('/') ? scopeUrl.pathname : `${scopeUrl.pathname}/`;
const fromScope = (path) => `${scopePath}${path}`.replace(/\/{2,}/g, '/');
const APP_SHELL = [
  scopePath,
  fromScope('index.html'),
  fromScope('manifest.json'),
  fromScope('icons/favicon.png'),
  fromScope('icons/icon-192.png'),
  fromScope('icons/icon-512.png'),
  fromScope('images/logo.png'),
  fromScope('images/gym-bg.svg')
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.pathname.startsWith(`${scopePath}api/`)) {
    return;
  }

  if (request.mode === 'navigate' || url.pathname === scopePath || url.pathname === fromScope('index.html')) {
    event.respondWith(
      fetch(request).then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(fromScope('index.html'), copy));
        return response;
      }).catch(() => caches.match(request).then((cached) => cached || caches.match(fromScope('index.html'))))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const networkFetch = fetch(request).then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        return response;
      });
      return cached || networkFetch;
    })
  );
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
