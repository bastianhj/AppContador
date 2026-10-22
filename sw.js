const CACHE_NAME = 'contador-pasajeros-v4';
const ASSETS = ['./index.html', './manifest.json', './icon-192.png', './icon-512.png', './logo-uchile.png', './logo-ufrontera.png'];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  if(event.request.method !== 'GET'){
    return; // deja pasar directo a la red: no interceptar POST a los endpoints PHP
  }
  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request))
  );
});
