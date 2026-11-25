// v6 on top of v5 (v5 drops the old snapshots of authenticated pages, which
// could make an ordinary F5 render yesterday's Camera Intelligence/POS state
// until the operator forced a hard reload). v6 changes:
//  - Only a failed network fetch can send a page to the offline fallback. An
//    error while writing the cache used to be treated as "offline" as well, so
//    a server that answered fine could still end on the offline page.
//  - Login/logout clear the authenticated snapshots but keep offline.html.
//    Deleting the whole cache removed the offline page for good, leaving only
//    the plain-text 503 fallback until the next service worker install.
//  - Cache writes never delay or fail a response that already succeeded.
const CACHE_NAME = 'qlbh-offline-shell-v6'
const OFFLINE_PAGE = new URL('offline.html', self.registration.scope).href
const APP_SHELL = new URL('__offline_app_shell__.html', self.registration.scope).href

// A cached authenticated dashboard is unsafe: it can say a camera is offline
// or a setting is disabled even though the server has already changed. Keep
// the offline shell solely for POS, where its IndexedDB queue is useful.
const isOfflinePosPage = value => {
    const url = value instanceof URL ? value : new URL(value, self.registration.scope)
    return /\/pos(?:\/|$)/.test(url.pathname)
}

const escapeAttribute = value => String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

const htmlForInertiaPage = (html, page) => {
    const appTag = html.match(/<[^>]*\bid=["']app["'][^>]*>/i)
    if (!appTag) return null
    const tag = appTag[0]
    const updatedTag = tag.replace(/\bdata-page=(["']).*?\1/i, `data-page="${escapeAttribute(JSON.stringify(page))}"`)
    if (updatedTag === tag) return null
    return html.replace(tag, updatedTag)
}

const cacheInertiaPage = async (request, response) => {
    if (!isOfflinePosPage(request.url)) return
    if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return
    let page
    try { page = await response.clone().json() } catch { return }
    if (!page?.component || !page?.url || !page?.props) return

    const cache = await caches.open(CACHE_NAME)
    const shell = await cache.match(APP_SHELL)
    if (!shell) return
    const html = htmlForInertiaPage(await shell.text(), page)
    if (!html) return

    const pageUrl = new URL(page.url, request.url)
    const headers = new Headers(shell.headers)
    ;['content-length', 'content-encoding', 'content-range', 'etag', 'last-modified', 'content-md5'].forEach(header => headers.delete(header))
    await cache.put(new Request(pageUrl.href), new Response(html, {
        status: 200,
        headers,
    }))
}

const cachePageSnapshot = async ({ html: sourceHtml, page }) => {
    if (typeof sourceHtml !== 'string' || !page?.component || !page?.url || !page?.props) return
    const pageUrl = new URL(page.url, self.registration.scope)
    if (pageUrl.origin !== self.location.origin || !isOfflinePosPage(pageUrl)) return
    const html = htmlForInertiaPage(sourceHtml, page)
    if (!html) return

    const headers = new Headers({ 'Content-Type': 'text/html; charset=utf-8' })
    const response = new Response(html, { status: 200, headers })
    const cache = await caches.open(CACHE_NAME)
    await cache.put(APP_SHELL, response.clone())
    await cache.put(new Request(pageUrl.href), response)
}

// Drop every snapshot of an authenticated page, but keep the offline fallback.
const clearAuthenticatedSnapshots = async () => {
    const cache = await caches.open(CACHE_NAME)
    const requests = await cache.keys()
    await Promise.all(requests
        .filter(cachedRequest => cachedRequest.url !== OFFLINE_PAGE)
        .map(cachedRequest => cache.delete(cachedRequest, { ignoreVary: true })))
}

// Used only after the network fetch itself failed.
const offlineFallback = async (request, url) => {
    try {
        if (isOfflinePosPage(url)) {
            const cachedPage = await caches.match(request)
            if (cachedPage) return cachedPage
        }
        const cachedShell = await caches.match(OFFLINE_PAGE)
        if (cachedShell) return cachedShell
    } catch (error) {
        console.warn('[sw] offline fallback lookup failed:', error)
    }
    return new Response('Mất kết nối mạng.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
}

self.addEventListener('message', event => {
    if (event.data?.type === 'CACHE_OFFLINE_PAGE') {
        event.waitUntil(cachePageSnapshot(event.data).catch(error => console.warn('[sw] could not store page snapshot:', error)))
    }
})

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.add(OFFLINE_PAGE)))
    self.skipWaiting()
})

self.addEventListener('activate', event => {
    event.waitUntil(Promise.all([
        self.clients.claim(),
        caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('qlbh-offline-shell-') && key !== CACHE_NAME).map(key => caches.delete(key)))),
    ]))
})

self.addEventListener('fetch', event => {
    const request = event.request
    if (request.method !== 'GET') {
        const requestUrl = new URL(request.url)
        if (requestUrl.origin === self.location.origin && /\/(login|logout)$/.test(requestUrl.pathname)) {
            event.respondWith(fetch(request).then(async response => {
                if (response.ok || response.redirected) {
                    try { await clearAuthenticatedSnapshots() } catch (error) { console.warn('[sw] could not clear snapshots:', error) }
                }
                return response
            }))
        }
        return
    }

    const url = new URL(request.url)
    if (url.origin !== self.location.origin) return

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            let response
            try {
                // Never let a normal F5 reuse a browser HTTP-cache entry for
                // dynamic, authenticated data. The app therefore behaves the
                // same for F5 and Shift+F5 while the server is reachable.
                response = await fetch(request, { cache: 'no-store' })
            } catch (error) {
                console.warn('[sw] navigation fetch failed:', error)
                return offlineFallback(request, url)
            }

            const finalUrl = new URL(response.url || request.url)
            const isSignInPage = /\/(login|register|forgot-password|reset-password)(\/|$)/.test(finalUrl.pathname)
            if (isOfflinePosPage(url) && response.ok && !isSignInPage && response.headers.get('content-type')?.includes('text/html')) {
                const shellCopy = response.clone()
                const pageCopy = response.clone()
                event.waitUntil((async () => {
                    try {
                        const cache = await caches.open(CACHE_NAME)
                        await cache.put(APP_SHELL, shellCopy)
                        await cache.put(request, pageCopy)
                    } catch (error) {
                        console.warn('[sw] could not store POS snapshot:', error)
                    }
                })())
            }
            return response
        })())
        return
    }

    if (request.headers.get('X-Inertia') === 'true') {
        event.respondWith(fetch(request, { cache: 'no-store' }).then(response => {
            const copy = response.clone()
            event.waitUntil(cacheInertiaPage(request, copy).catch(error => console.warn('[sw] could not store Inertia page:', error)))
            return response
        }))
        return
    }

    if (['script', 'style', 'font', 'image'].includes(request.destination)) {
        event.respondWith((async () => {
            try {
                const cache = await caches.open(CACHE_NAME)
                const cached = await cache.match(request)
                if (cached) return cached
            } catch (error) {
                console.warn('[sw] asset cache lookup failed:', error)
            }
            const response = await fetch(request)
            if (response.status === 200) {
                const copy = response.clone()
                event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.put(request, copy)).catch(error => console.warn('[sw] could not store asset:', error)))
            }
            return response
        })())
    }
})