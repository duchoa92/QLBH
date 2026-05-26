import { ref } from 'vue'

export function useAutocomplete(fetcher, delay = 250) {
    const keyword = ref('')
    const results = ref([])
    let timeout = null

    const search = () => {
        clearTimeout(timeout)

        timeout = setTimeout(async () => {
            if (!keyword.value) {
                results.value = []
                return
            }

            results.value = await fetcher(keyword.value)
        }, delay)
    }

    return {
        keyword,
        results,
        search,
    }
}