/*
 * PCM service worker. Pages hold private financial data, so HTML is never
 * cached: navigations always go to the network and fall back to offline.html
 * only when there is no connection. Static assets use stale-while-revalidate.
 */
const VERSION = 'pcm-v1';
const OFFLINE_URL = new URL('offline.html', self.registration.scope).href;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((c) => c.add(OFFLINE_URL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const STATIC_HOSTS = ['cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'ajax.googleapis.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];

function isStaticAsset(url) {
    if (url.origin === self.location.origin) {
        return /\/(ui|js|css|images)\//.test(url.pathname) || url.pathname.endsWith('.webmanifest');
    }
    return STATIC_HOSTS.includes(url.hostname);
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    const url = new URL(req.url);
    if (!isStaticAsset(url)) return;

    event.respondWith(
        caches.open(VERSION).then((cache) =>
            cache.match(req).then((cached) => {
                const network = fetch(req).then((res) => {
                    if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone());
                    return res;
                }).catch(() => cached);
                return cached || network;
            })
        )
    );
});
