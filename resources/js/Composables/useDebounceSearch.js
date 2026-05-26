import { ref } from 'vue'

export function useDebounceSearch(callback, delay = 300, externalKeyword = null) {

    const keyword = externalKeyword ?? ref('')
    const results = ref([])
    const loading = ref(false)

    let timeout = null
    let requestId = 0

    const search = () => {

        clearTimeout(timeout)

        timeout = setTimeout(async () => {

            const q = keyword.value

            if (!q) {
                results.value = []
                return
            }

            const currentRequest = ++requestId

            loading.value = true

            try {
                const data = await callback(q)

                if (currentRequest !== requestId) return

                results.value = data ?? []
            } finally {
                loading.value = false
            }

        }, delay)
    }

    return {
        keyword,
        results,
        loading,
        search,
    }
}