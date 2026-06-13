import { ref } from 'vue'
import { saleService } from '../Services/saleService'

export function useSaleHistory() {

    const invoices = ref([])

    const search = ref('')

    const currentPage = ref(1)

    const lastPage = ref(1)

    const loading = ref(false)

    const selectedInvoice = ref(null)

    const showDetail = ref(false)

    // Phân trang
    const loadInvoices = async (
        page = 1
    ) => {

        try {

            loading.value = true

            const response =
                await saleService.getSales({

                    page,

                    search:
                        search.value,
                })

            invoices.value =
                response.data.data

            currentPage.value =
                response.data.current_page

            lastPage.value =
                response.data.last_page

        } catch (error) {

            console.error(error)

        } finally {

            loading.value = false
        }
    }

    // Chuyển trang
    const changePage = async (
        page
    ) => {

        if (
            page < 1 ||
            page > lastPage.value
        ) {
            return
        }

        await loadInvoices(page)
    }

    // hàm tìm kiếm
    const searchInvoices = async () => {

        await loadInvoices(1)
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

        search,

        loading,

        currentPage,

        lastPage,

        selectedInvoice,

        showDetail,

        loadInvoices,

        searchInvoices,

        changePage,

        openInvoice,
    }
}