const CACHE_NAME = 'sadigs-app-v11-KILLED';

// 1. Install Service Worker (Langsung ambil alih)
self.addEventListener('install', event => {
  self.skipWaiting();
});

// 2. Activate & Bersihkan SEMUA Cache Lama
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          return caches.delete(cacheName); // Hapus semuanya tanpa ampun
        })
      );
    }).then(() => {
      return self.clients.claim(); // Paksa browser pakai versi terbaru detik ini juga
    })
  );
});

// 3. Fetch Strategy: NETWORK ONLY (Abaikan cache)
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(err => console.log('Fetch error:', err))
  );
});