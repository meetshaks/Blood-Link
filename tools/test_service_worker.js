'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '../public/sw.js'), 'utf8');
const scope = 'https://bloodlink.example/BloodLink_2/public/';
const listeners = new Map();
const cacheStore = new Map([
    ['bloodlink-v4', new Map()],
    ['bloodlink--BloodLink-2-public--static-v13', new Map()],
    ['bloodlink--BloodLink-2-public--offline-v13', new Map()],
]);
const requests = [];
let offline = false;
let skipWaitingCalls = 0;
let claimCalls = 0;
let mockResponse = null;

const response = (contentType = 'application/octet-stream', options = {}) => ({
    ok: options.ok ?? true,
    type: options.type ?? 'basic',
    redirected: options.redirected ?? false,
    headers: {
        get(name) {
            return name.toLowerCase() === 'content-type' ? contentType : (options.cacheControl ?? null);
        },
    },
    clone() {
        return this;
    },
});

const cacheFor = name => {
    if (!cacheStore.has(name)) {
        cacheStore.set(name, new Map());
    }
    const entries = cacheStore.get(name);
    return {
        async put(request, value) {
            const key = typeof request === 'string' ? request : request.url;
            entries.set(key, value);
        },
        async match(request) {
            const key = typeof request === 'string' ? request : request.url;
            return entries.get(key);
        },
        async delete(request) {
            const key = typeof request === 'string' ? request : request.url;
            return entries.delete(key);
        },
        async keys() {
            return [...entries.keys()].map(url => ({ url }));
        },
    };
};

const caches = {
    open: async name => cacheFor(name),
    keys: async () => [...cacheStore.keys()],
    delete: async name => cacheStore.delete(name),
};

const self = {
    registration: { scope, active: {} },
    clients: { claim: async () => { claimCalls++; } },
    addEventListener(name, callback) {
        listeners.set(name, callback);
    },
    skipWaiting: async () => { skipWaitingCalls++; },
};

const fetch = async (request, options) => {
    requests.push({ request, options });
    if (offline) {
        throw new Error('simulated network loss');
    }
    const url = typeof request === 'string' ? request : request.url;
    return mockResponse || (url.endsWith('/offline.html') ? response('text/html') : response());
};

vm.runInNewContext(source, {
    URL,
    caches,
    console: { warn() {}, error() {} },
    fetch,
    self,
    Response: { error: () => ({ error: true }) },
});

const run = async () => {
    let installPromise;
    listeners.get('install')({ waitUntil(promise) { installPromise = promise; } });
    await installPromise;
    assert.strictEqual(skipWaitingCalls, 0, 'an update must wait for user approval');

    const staticName = [...cacheStore.keys()].find(name => name.endsWith('static-v14'));
    const offlineName = [...cacheStore.keys()].find(name => name.endsWith('offline-v14'));
    assert(staticName && offlineName, 'versioned static and offline caches should be created separately');
    const staticCache = await caches.open(staticName);
    const offlineCache = await caches.open(offlineName);
    const staticKeys = await staticCache.keys();
    assert(staticKeys.length > 0, 'the required static asset allowlist should be precached');
    assert(
        staticKeys.every(({ url }) => !/\/(admin|user|api)(\/|$)/i.test(new URL(url).pathname)),
        'private routes must not be in the static cache'
    );
    assert.strictEqual((await offlineCache.keys()).length, 1, 'only the offline page belongs in its cache');
    console.log('PASS: static allowlist and separate offline cache');

    const staticUrl = `${scope}assets/css/base.css?v=55`;
    const cachedStatic = await staticCache.match(staticUrl);
    let staticResponse;
    listeners.get('fetch')({
        request: { method: 'GET', url: staticUrl, mode: 'cors' },
        respondWith(promise) { staticResponse = promise; },
    });
    assert.strictEqual(await staticResponse, cachedStatic, 'allowlisted assets should use the static cache');

    const privateUrl = `${scope}user/dashboard.php`;
    let privateResponse;
    const cacheSizeBeforePrivate = (await staticCache.keys()).length;
    listeners.get('fetch')({
        request: { method: 'GET', url: privateUrl, mode: 'cors' },
        respondWith(promise) { privateResponse = promise; },
    });
    await privateResponse;
    assert.strictEqual(requests[requests.length - 1].options.cache, 'no-store');
    assert.strictEqual((await staticCache.keys()).length, cacheSizeBeforePrivate);
    assert.strictEqual((await staticCache.match(privateUrl)), undefined);
    console.log('PASS: private routes are network-only');

    mockResponse = response('text/html');
    const uncachedStaticUrl = `${scope}assets/css/fonts.css?v=999`;
    let unsafeResponse;
    listeners.get('fetch')({
        request: { method: 'GET', url: uncachedStaticUrl, mode: 'cors' },
        respondWith(promise) { unsafeResponse = promise; },
    });
    await unsafeResponse;
    assert.strictEqual(await staticCache.match(uncachedStaticUrl), undefined);
    mockResponse = null;
    console.log('PASS: unallowlisted assets are not cached');

    const privateStaticUrl = `${scope}assets/icons/icon-512.png?v=4`;
    await staticCache.delete(privateStaticUrl);
    mockResponse = response('image/png', { cacheControl: 'private, max-age=3600' });
    let privateStaticResponse;
    listeners.get('fetch')({
        request: { method: 'GET', url: privateStaticUrl, mode: 'cors' },
        respondWith(promise) { privateStaticResponse = promise; },
    });
    await privateStaticResponse;
    assert.strictEqual(await staticCache.match(privateStaticUrl), undefined);
    mockResponse = null;
    console.log('PASS: private static responses are not cached');

    let postIntercepted = false;
    listeners.get('fetch')({
        request: { method: 'POST', url: `${scope}api/action.php`, mode: 'cors' },
        respondWith() { postIntercepted = true; },
    });
    assert.strictEqual(postIntercepted, false, 'POST requests must bypass the worker');

    offline = true;
    let navigationResponse;
    listeners.get('fetch')({
        request: { method: 'GET', url: `${scope}about.php`, mode: 'navigate' },
        respondWith(promise) { navigationResponse = promise; },
    });
    const fallback = await navigationResponse;
    assert.strictEqual(fallback, await offlineCache.match(`${scope}offline.html`));
    offline = false;
    console.log('PASS: offline navigation returns the cached fallback');

    let activationPromise;
    listeners.get('activate')({ waitUntil(promise) { activationPromise = promise; } });
    await activationPromise;
    assert.strictEqual(claimCalls, 1);
    assert.deepStrictEqual(
        (await caches.keys()).filter(name => /static-v13|offline-v13|bloodlink-v4/.test(name)),
        []
    );
    assert((await caches.keys()).includes(staticName) && (await caches.keys()).includes(offlineName));
    console.log('PASS: activation claims clients and removes previous caches');

    let messagePromise;
    listeners.get('message')({
        data: { type: 'SKIP_WAITING' },
        waitUntil(promise) { messagePromise = promise; },
    });
    await messagePromise;
    assert.strictEqual(skipWaitingCalls, 1);
    console.log('PASS: waiting worker activates only after the update message');
};

run().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
