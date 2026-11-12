const CACHE_PREFIX = `bloodlink-${new URL(self.registration.scope).pathname.replace(/[^a-z0-9]/gi, '-')}-`;
const CACHE_VERSION = 'v14';
const STATIC_CACHE = `${CACHE_PREFIX}static-${CACHE_VERSION}`;
const OFFLINE_CACHE = `${CACHE_PREFIX}offline-${CACHE_VERSION}`;
const LEGACY_CACHES = ['bloodlink-v4', 'bloodlink-v5'];
const SCOPE_URL = new URL(self.registration.scope);
const OFFLINE_URL = new URL('offline.html', SCOPE_URL).href;
const STATIC_ASSETS = [
    'assets/css/base.css?v=55',
    'assets/css/fonts.css?v=2',
    'assets/js/ui.js?v=12',
    'assets/js/pwa.js?v=9',
    'assets/js/search.js?v=14',
    'assets/js/address.js?v=8',
    'assets/js/address.js?v=9',
    'assets/js/donations.js?v=3',
    'assets/js/dashboard.js?v=3',
    'assets/js/dashboard.js?v=9',
    'assets/icons/icon-192.png',
    'assets/icons/icon-192.png?v=4',
    'assets/icons/icon-512.png?v=4',
    'assets/img/og-default.png',
    'assets/fonts/hind-siliguri-bengali-400-normal.woff2',
    'assets/fonts/hind-siliguri-bengali-500-normal.woff2',
    'assets/fonts/hind-siliguri-bengali-600-normal.woff2',
    'assets/fonts/hind-siliguri-bengali-700-normal.woff2',
    'assets/fonts/hind-siliguri-latin-400-normal.woff2',
    'assets/fonts/hind-siliguri-latin-500-normal.woff2',
    'assets/fonts/hind-siliguri-latin-600-normal.woff2',
    'assets/fonts/hind-siliguri-latin-700-normal.woff2',
    'assets/fonts/inter-latin-400-normal.woff2',
    'assets/fonts/inter-latin-500-normal.woff2',
    'assets/fonts/inter-latin-600-normal.woff2',
    'assets/fonts/inter-latin-700-normal.woff2'
].map(path => new URL(path, SCOPE_URL).href);
const STATIC_ASSET_URLS = new Set(STATIC_ASSETS);

const isInScope = url => url.origin === SCOPE_URL.origin && url.pathname.startsWith(SCOPE_URL.pathname);
const isCacheableStaticAsset = url => STATIC_ASSET_URLS.has(url.href);
const isSafeStaticResponse = response => {
    if (!response.ok || response.type !== 'basic' || response.redirected) {
        return false;
    }

    const contentType = response.headers.get('Content-Type') || '';
    if (/^(text\/html|application\/json)\b/i.test(contentType.trim())) {
        return false;
    }

    const cacheControl = response.headers.get('Cache-Control') || '';
    return !cacheControl.split(',').some(value => /^(no-store|private)\b/i.test(value.trim()));
};
const isSafeOfflineResponse = response => (
    response.ok
    && response.type === 'basic'
    && !response.redirected
    && /^text\/html\b/i.test(response.headers.get('Content-Type') || '')
    && !((response.headers.get('Cache-Control') || '').split(',').some(value => /^(no-store|private)\b/i.test(value.trim())))
);

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const staticCache = await caches.open(STATIC_CACHE);
        const offlineCache = await caches.open(OFFLINE_CACHE);
        const staticResults = await Promise.allSettled(
            STATIC_ASSETS.map(async url => {
                const response = await fetch(url, { cache: 'no-store' });
                if (!isSafeStaticResponse(response)) {
                    throw new Error(`Static asset returned an unsafe response: ${url}`);
                }
                await staticCache.put(url, response);
            })
        );

        staticResults.forEach((result, index) => {
            if (result.status === 'rejected') {
                console.warn('BloodLink could not precache a static asset:', STATIC_ASSETS[index], result.reason);
            }
        });
        if (staticResults.some(result => result.status === 'rejected')) {
            await caches.delete(STATIC_CACHE);
            await caches.delete(OFFLINE_CACHE);
            throw new Error('BloodLink could not precache all required static assets.');
        }

        try {
            const response = await fetch(OFFLINE_URL, { cache: 'no-store' });
            if (!isSafeOfflineResponse(response)) {
                throw new Error(`Offline page returned an unsafe response: ${response.status}`);
            }
            await offlineCache.put(OFFLINE_URL, response);
        } catch (error) {
            console.error('BloodLink could not precache the offline page:', error);
            await caches.delete(STATIC_CACHE);
            await caches.delete(OFFLINE_CACHE);
            throw error;
        }

        if (!self.registration.active) {
            await self.skipWaiting();
        }
    })());
});

self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') {
        event.waitUntil(self.skipWaiting());
    }
});

self.addEventListener('activate', event => {
    const activeCaches = new Set([STATIC_CACHE, OFFLINE_CACHE]);
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames
            .filter(name => (
                (name.startsWith(CACHE_PREFIX) && !activeCaches.has(name))
                || LEGACY_CACHES.includes(name)
            ))
            .map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(request.url);
    if (!isInScope(requestUrl)) {
        return;
    }

    if (isCacheableStaticAsset(requestUrl)) {
        event.respondWith((async () => {
            let cache = null;
            try {
                cache = await caches.open(STATIC_CACHE);
                const cached = await cache.match(request);
                if (cached) {
                    return cached;
                }
            } catch (error) {
                console.warn('BloodLink could not read its static cache:', request.url, error);
            }

            const response = await fetch(request, { cache: 'no-store' });
            if (cache && isSafeStaticResponse(response)) {
                await cache.put(request, response.clone()).catch(error => {
                    console.warn('BloodLink could not cache a static asset:', request.url, error);
                });
            }
            return response;
        })());
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                return await fetch(request, { cache: 'no-store' });
            } catch (error) {
                console.warn('BloodLink navigation failed while offline:', request.url, error);
                try {
                    const offlineCache = await caches.open(OFFLINE_CACHE);
                    return await offlineCache.match(OFFLINE_URL) || Response.error();
                } catch (cacheError) {
                    console.error('BloodLink could not read its offline cache:', cacheError);
                    return Response.error();
                }
            }
        })());
        return;
    }

    event.respondWith(fetch(request, { cache: 'no-store' }));
});
