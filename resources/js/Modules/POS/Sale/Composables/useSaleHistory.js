import { ref } from 'vue'
import { saleService } from '../Services/saleService'

export function useSaleHistory() {

    const invoices = ref([])

    const currentPage = ref(1)

    const lastPage = ref(1)

    const total = ref(0)

    const from = ref(0)

    const to = ref(0)

    const perPage = ref(10)

    const filters = ref({ search: '', status: '', date_from: '', date_to: '' })

    const loading = ref(false)

    const selectedInvoice = ref(null)

    const showDetail = ref(false)

    // Phân trang
    const loadInvoices = async (page = 1) => {

        try {

            loading.value = true

            const response =
                await saleService.getSales({ page, per_page: perPage.value, ...filters.value })

            invoices.value =
                response.data.data

            currentPage.value =
                response.data.current_page

            lastPage.value =
                response.data.last_page

            total.value = response.data.total || 0
            from.value = response.data.from || 0
            to.value = response.data.to || 0

        } catch (error) {

            console.error(error)

        } finally {

            loading.value = false
        }
    }

    const fetchInvoice = async (id) => {

        try {

            const response =
                await saleService.getSale(id)

            return response.data

        } catch (error) {

            console.error(error)
            return null
        }
    }

    const openInvoice = async (id) => {

        selectedInvoice.value = await fetchInvoice(id)

        if (selectedInvoice.value) {
            showDetail.value = true
        }
    }

    return {

        invoices,

        loading,

        currentPage,

        lastPage,

        total,

        from,

        to,

        perPage,

        filters,

        selectedInvoice,

        showDetail,

        loadInvoices,

        fetchInvoice,

        openInvoice,
    }
}
