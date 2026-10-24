const configuredBasePath = () => {
    if (typeof window === 'undefined') return ''

    try {
        const configuredUrl = new URL(window.Ziggy?.url || window.location.origin)
        const path = configuredUrl.pathname.replace(/\/+$/, '')
        return path === '/' ? '' : path
    } catch {
        return ''
    }
}

export const appBasePath = configuredBasePath()
export const appBaseUrl = `${window.location.origin}${appBasePath}/`

/** Prefix same-origin root paths with Laravel's deployment path when needed. */
export const withAppBasePath = (value) => {
    if (typeof window === 'undefined' || !appBasePath) return value

    try {
        const url = value instanceof URL
            ? new URL(value.href)
            : new URL(String(value), window.location.href)

        if (url.origin !== window.location.origin) return value
        if (url.pathname === appBasePath || url.pathname.startsWith(`${appBasePath}/`)) return value

        url.pathname = `${appBasePath}${url.pathname.startsWith('/') ? '' : '/'}${url.pathname}`
        return value instanceof URL ? url : url.href
    } catch {
        return value
    }
}

export const installAppPathSupport = (router) => {
    if (typeof window === 'undefined' || window.__appPathSupportInstalled) return
    window.__appPathSupportInstalled = true

    // Normalize every Inertia visit, including links and forms using root paths.
    router.on('before', (event) => {
        event.detail.visit.url = withAppBasePath(event.detail.visit.url)
    })

    // Keep existing same-origin fetch('/...') calls within the app subdirectory.
    const nativeFetch = window.fetch.bind(window)
    window.fetch = (input, init) => {
        if (typeof input === 'string' || input instanceof URL) {
            return nativeFetch(withAppBasePath(input), init)
        }
        return nativeFetch(input, init)
    }

    // Also handle ordinary same-origin anchors that do not use Inertia Link.
    document.addEventListener('click', (event) => {
        const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null
        if (!anchor) return
        const href = anchor.getAttribute('href')
        if (href?.startsWith('/')) anchor.setAttribute('href', withAppBasePath(href))
    }, true)
}
