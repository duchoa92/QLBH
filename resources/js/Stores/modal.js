import { reactive, markRaw, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

let uid = 0

const state = reactive({
    modals: []
})

export function useModal() {
    return state
}

export function openModal(component, options = {}) {
    const deepLink = options.deepLink || null
    if (deepLink && !options.fromUrl) {
        const url = new URL(window.location.href)
        url.searchParams.set('detail', deepLink.type)
        url.searchParams.set('detail_id', String(deepLink.id))
        router.push({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true })
    }

    state.modals.push({
        id: ++uid,
        component: markRaw(component),
        props: options.props || {},
        onUpdated: options.onUpdated || null,
        deepLink,
        fromUrl: Boolean(options.fromUrl),
    })
}

export function closeModal(id = null) {
    if (id !== null) {
        const index = state.modals.findIndex(m => m.id === id)
        if (index !== -1) {
            const [modal] = state.modals.splice(index, 1)
            clearDeepLink(modal)
        }
    } else if (state.modals.length) {
        const modal = state.modals.pop()
        clearDeepLink(modal)
    }
}

const clearDeepLink = (modal) => {
    if (!modal?.deepLink) return
    const url = new URL(window.location.href)
    if (url.searchParams.get('detail') !== modal.deepLink.type
        || url.searchParams.get('detail_id') !== String(modal.deepLink.id)) return
    url.searchParams.delete('detail')
    url.searchParams.delete('detail_id')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true })
}

export function registerUrlModal(type, component, propsForId) {
    const syncFromUrl = () => {
        const url = new URL(window.location.href)
        const active = state.modals.find(modal => modal.deepLink?.type === type)
        const id = url.searchParams.get('detail') === type ? url.searchParams.get('detail_id') : null

        if (id && (!active || String(active.deepLink.id) !== id)) {
            if (active) closeModal(active.id)
            openModal(component, { props: propsForId(id), deepLink: { type, id }, fromUrl: true })
        } else if (!id && active) {
            state.modals.splice(state.modals.indexOf(active), 1)
        }
    }

    syncFromUrl()
    window.addEventListener('popstate', syncFromUrl)
    const cleanup = () => window.removeEventListener('popstate', syncFromUrl)
    onBeforeUnmount(cleanup)
    return cleanup
}
