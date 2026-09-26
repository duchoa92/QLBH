import { computed, onBeforeUnmount, onMounted, reactive } from 'vue'
import api from '@/Services/api'

const REFRESH_INTERVAL = 3000
const CHANGE_EVENT = 'reference-data:changed'
const VERSION_KEY = 'reference-data:version'
const CHANNEL_NAME = 'reference-data'

const state = reactive({
    categories: [],
    brands: [],
    units: [],
    suppliers: [],
    roles: [],
    loaded: false,
})

let refreshPromise = null
let consumers = 0
let refreshTimer = null
let channel = null

const replaceItems = (key, items) => {
    state[key].splice(0, state[key].length, ...(items || []))
}

const hydrate = (initialData = {}) => {
    ;['categories', 'brands', 'units', 'suppliers', 'roles'].forEach((key) => {
        if (Array.isArray(initialData[key])) {
            replaceItems(key, initialData[key])
        }
    })
}

export const refreshReferenceData = async () => {
    if (refreshPromise) return refreshPromise

    refreshPromise = api.get('/api/reference-data')
        .then(({ data }) => {
            hydrate(data)
            state.loaded = true
            return data
        })
        .catch((error) => {
            console.warn('Không thể cập nhật dữ liệu ô chọn.', error)
            return null
        })
        .finally(() => {
            refreshPromise = null
        })

    return refreshPromise
}

export const notifyReferenceDataChanged = () => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event(CHANGE_EVENT))

        try {
            window.localStorage.setItem(VERSION_KEY, String(Date.now()))
        } catch {
            // Không ảnh hưởng việc cập nhật dữ liệu trong tab hiện tại.
        }

        channel?.postMessage({ type: CHANGE_EVENT })

        return
    }

    void refreshReferenceData()
}

const startPolling = () => {
    if (refreshTimer || typeof window === 'undefined') return

    refreshTimer = window.setInterval(() => {
        void refreshReferenceData()
    }, REFRESH_INTERVAL)
}

const stopPolling = () => {
    if (consumers > 0 || !refreshTimer) return

    window.clearInterval(refreshTimer)
    refreshTimer = null
}

const handleChange = () => {
    void refreshReferenceData()
}

const handleStorageChange = (event) => {
    if (event.key === VERSION_KEY) {
        void refreshReferenceData()
    }
}

const handleChannelMessage = (event) => {
    if (event.data?.type === CHANGE_EVENT) {
        void refreshReferenceData()
    }
}

export const useReferenceData = (initialData = {}) => {
    hydrate(initialData)

    onMounted(() => {
        consumers += 1

        if (consumers === 1) {
            window.addEventListener(CHANGE_EVENT, handleChange)
            window.addEventListener('storage', handleStorageChange)

            if ('BroadcastChannel' in window) {
                channel = new BroadcastChannel(CHANNEL_NAME)
                channel.addEventListener('message', handleChannelMessage)
            }

            startPolling()
        }

        void refreshReferenceData()
    })

    onBeforeUnmount(() => {
        consumers = Math.max(0, consumers - 1)

        if (consumers === 0 && typeof window !== 'undefined') {
            window.removeEventListener(CHANGE_EVENT, handleChange)
            window.removeEventListener('storage', handleStorageChange)
            channel?.close()
            channel = null
        }

        stopPolling()
    })

    return {
        referenceData: state,
        categories: computed(() => state.categories),
        brands: computed(() => state.brands),
        units: computed(() => state.units),
        suppliers: computed(() => state.suppliers),
        roles: computed(() => state.roles),
        refreshReferenceData,
    }
}
