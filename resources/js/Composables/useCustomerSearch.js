import axios from 'axios'
import { ref } from 'vue'

export function useCustomerSearch() {
    const query = ref('')
    const loading = ref(false)
    const results = ref([])
    const selectedCustomer = ref(null)

    let timeout = null

    const search = (keyword) => {
        query.value = keyword

        clearTimeout(timeout)

        if (!keyword || keyword.length < 2) {
            results.value = []
            return
        }

        timeout = setTimeout(async () => {
            loading.value = true

            try {
                const res = await axios.get('/api/customers/search', {
                    params: { q: keyword }
                })

                results.value = res.data
            } catch (e) {
                results.value = []
            } finally {
                loading.value = false
            }

        }, 300)
    }

    const select = (customer) => {
        selectedCustomer.value = customer
        query.value = customer.full_name
        results.value = []
    }

    const clear = () => {
        selectedCustomer.value = null
        query.value = ''
        results.value = []
    }

    return {
        query,
        loading,
        results,
        selectedCustomer,
        search,
        select,
        clear
    }
}