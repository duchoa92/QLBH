import { ref } from 'vue'
import { saleService } from '../Services/saleService'

export function useSaleHistory() {

    const invoices = ref([])

    const selectedInvoice = ref(null)

    const showDetail = ref(false)

    const loadInvoices = async () => {

        try {

            const response =
                await saleService.getSales()

            invoices.value =
                response.data

        } catch (error) {

            console.error(error)
        }
    }

    const openInvoice = async (id) => {

        try {

            const response =
                await saleService.getSale(id)

            selectedInvoice.value =
                response.data

            showDetail.value = true

        } catch (error) {

            console.error(error)
        }
    }

    return {

        invoices,

        selectedInvoice,

        showDetail,

        loadInvoices,

        openInvoice,
    }
}