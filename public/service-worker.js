const CACHE_NAME = 'qlbh-offline-shell-v3'
const OFFLINE_PAGE = new URL('offline.html', self.registration.scope).href
const APP_SHELL = new URL('__offline_app_shell__.html', self.registration.scope).href

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
    if (pageUrl.origin !== self.location.origin || /\/(login|register|forgot-password|reset-password)(\/|$)/.test(pageUrl.pathname)) return
    const html = htmlForInertiaPage(sourceHtml, page)
    if (!html) return

    const headers = new Headers({ 'Content-Type': 'text/html; charset=utf-8' })
    const response = new Response(html, { status: 200, headers })
    const cache = await caches.open(CACHE_NAME)
    await cache.put(APP_SHELL, response.clone())
    await cache.put(new Request(pageUrl.href), response)
}

self.addEventListener('message', event => {
    if (event.data?.type === 'CACHE_OFFLINE_PAGE') {
        event.waitUntil(cachePageSnapshot(event.data))
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
                if (response.ok || response.redirected) await caches.delete(CACHE_NAME)
                return response
            }))
        }
        return
    }

    const url = new URL(request.url)
    if (url.origin !== self.location.origin) return

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const response = await fetch(request)
                const finalUrl = new URL(response.url || request.url)
                const isSignInPage = /\/(login|register|forgot-password|reset-password)(\/|$)/.test(finalUrl.pathname)
                if (response.ok && !isSignInPage && response.headers.get('content-type')?.includes('text/html')) {
                    const cache = await caches.open(CACHE_NAME)
                    await cache.put(APP_SHELL, response.clone())
                    await cache.put(request, response.clone())
                }
                return response
            } catch {
                const cachedPage = await caches.match(request)
                if (cachedPage) return cachedPage
                const cachedShell = await caches.match(OFFLINE_PAGE)
                return cachedShell || new Response('Mất kết nối mạng.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
            }
        })())
        return
    }

    if (request.headers.get('X-Inertia') === 'true') {
        event.respondWith(fetch(request).then(async response => {
            await cacheInertiaPage(request, response.clone())
            return response
        }))
        return
    }

    if (['script', 'style', 'font', 'image'].includes(request.destination)) {
        event.respondWith((async () => {
            const cache = await caches.open(CACHE_NAME)
            const cached = await cache.match(request)
            if (cached) return cached
            const response = await fetch(request)
            if (response.ok) await cache.put(request, response.clone())
            return response
        })())
    }
})
