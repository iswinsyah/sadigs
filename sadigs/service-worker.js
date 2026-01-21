const CACHE_NAME = 'sadigs-app-v2';
const urlsToCache = [
  './',
  'index.html',
  'dashboard.html',
  'profile.html',
  'tahfizh_history.html',
  'tahfizh_recap.html',
  'manifest.json'
];

// 1. Install Service Worker & Cache Aset Statis
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('SADIGS: Caching app shell');
        return cache.addAll(urlsToCache);
      })
  );
});

// 2. Activate & Bersihkan Cache Lama
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// 3. Fetch Strategy: Cache First untuk Aset, Network First untuk API
self.addEventListener('fetch', event => {
  // Jangan cache request ke API (agar data selalu fresh)
  if (event.request.url.includes('sadigs_api_v3/') || event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Kembalikan cache jika ada, jika tidak ambil dari network
        return response || fetch(event.request);
      })
  );
});