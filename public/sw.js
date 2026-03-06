const CACHE_VERSION = 'v1';
const STATIC_CACHE = `teamlead-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `teamlead-dynamic-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';

const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

const CACHE_FIRST_PATTERNS = [
    /\.(?:css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)$/i,
];

const NETWORK_FIRST_PATTERNS = [
    /\/api\//,
    /\/login/,
    /\/logout/,
];

/**
 * Determine whether a request should use cache-first strategy.
 */
function isCacheFirst(request) {
    const url = new URL(request.url);
    return CACHE_FIRST_PATTERNS.some((pattern) => pattern.test(url.pathname));
}

/**
 * Determine whether a request should use network-first strategy.
 */
function isNetworkFirst(request) {
    const url = new URL(request.url);
    return NETWORK_FIRST_PATTERNS.some((pattern) => pattern.test(url.pathname));
}

/**
 * Fetch from network and update the dynamic cache.
 */
async function fetchAndCache(request) {
    const response = await fetch(request);

    if (response.ok && request.method === 'GET') {
        const cache = await caches.open(DYNAMIC_CACHE);
        cache.put(request, response.clone());
    }

    return response;
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
                        .map((key) => caches.delete(key))
                )
            )
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    if (isCacheFirst(event.request)) {
        event.respondWith(
            caches
                .match(event.request)
                .then((cached) => cached ?? fetchAndCache(event.request))
        );
        return;
    }

    if (isNetworkFirst(event.request)) {
        event.respondWith(
            fetchAndCache(event.request).catch(() =>
                caches
                    .match(event.request)
                    .then((cached) => cached ?? caches.match(OFFLINE_URL))
            )
        );
        return;
    }

    event.respondWith(
        fetchAndCache(event.request).catch(() =>
            caches
                .match(event.request)
                .then((cached) => cached ?? caches.match(OFFLINE_URL))
        )
    );
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();
    const title = payload.title ?? 'Team Lead Dashboard';
    const options = {
        body: payload.body ?? '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: {
            url: payload.data?.url ?? '/',
        },
        tag: payload.tag ?? 'teamlead-notification',
        requireInteraction: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url ?? '/';

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                const existing = windowClients.find(
                    (client) => client.url === targetUrl && 'focus' in client
                );

                if (existing) {
                    return existing.focus();
                }

                return clients.openWindow(targetUrl);
            })
    );
});
