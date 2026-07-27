const CACHE_NAME = 'smartagri-pwa-v1';

self.addEventListener('install', (event) => {
    // Force immediate activation
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Minimal pass-through fetch handler required for PWA installability
    event.respondWith(fetch(event.request));
});
