const CACHE_NAME = 'pos-cache-v3';
const MASTER_API_PATTERNS = ['/products', '/customers', '/pricing', '/categories', '/warehouses'];

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Drop every cache from previous builds so stale bundles never come back.
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Only same-origin GET requests are eligible for caching.
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Cache-first for master data API
    if (MASTER_API_PATTERNS.some((p) => url.pathname.includes(p))) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                fetch(event.request)
                    .then((response) => {
                        cache.put(event.request, response.clone());
                        return response;
                    })
                    .catch(() => cache.match(event.request))
            )
        );
        return;
    }

    // Network-first for transaction API
    if (url.pathname.includes('/transactions/')) {
        event.respondWith(
            fetch(event.request).catch(() => new Response(JSON.stringify({ offline: true }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' },
            }))
        );
        return;
    }

    // Network-first for HTML documents (navigations) so users always get the
    // latest page referencing the current build — fall back to cache offline.
    if (event.request.mode === 'navigate' || (event.request.headers.get('accept') || '').includes('text/html')) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                    return response;
                })
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // Cache-first only for hashed static assets (/build/, images, fonts).
    const isStaticAsset = url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/images/') ||
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/screenshots/') ||
        /\.(js|css|png|jpg|jpeg|gif|svg|webp|woff2?|ttf|ico)$/i.test(url.pathname);

    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                }
                return response;
            }))
        );
        return;
    }

    // Everything else: network-first with cache fallback.
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                }
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
