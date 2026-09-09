const CACHE_NAME = 'hogar-static-v1';
const OFFLINE_URL = '/offline';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/css/app.css',
    '/js/pwa.js',
    '/js/sidebar-menu.js',
    '/js/password-toggle.js',
    '/js/public-portal.js',
    '/js/map-zoom.js',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

const isStaticAsset = (url) => url.origin === self.location.origin && (
    url.pathname.startsWith('/css/')
    || url.pathname.startsWith('/js/')
    || url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/pwa/')
    || url.pathname === '/manifest.webmanifest'
);

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (!isStaticAsset(url)) return;

    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            if (!response.ok || response.type !== 'basic') return response;

            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));

            return response;
        })),
    );
});
