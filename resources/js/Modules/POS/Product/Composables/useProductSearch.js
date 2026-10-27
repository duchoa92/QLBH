import {
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
} from 'vue'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { useEventBus } from '@/Composables/useEventBus'
import { useReferenceData } from '@/Stores/referenceData'
import { usePage } from '@inertiajs/vue3'
import { cacheOfflineProducts, readOfflineProducts } from '@/utils/offlinePos'

export function useProductSearch(emit) {

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const keyword = ref('')

    const categoryId = ref('')

    const products = ref([])

    const { categories } = useReferenceData()
    const page = usePage()

    const loading = ref(false)

    let timeout = null

    // Sự kiện
    const {

        onEvent,

        offEvent,

    } = useEventBus()

    /*
    |--------------------------------------------------------------------------
    | Tải Sản phẩm
    |--------------------------------------------------------------------------
    */

    const loadProducts = async () => {

        loading.value = true

        try {

            const response =
                await productService.search(
                    keyword.value,
                    categoryId.value
                )

            products.value = response
            if (Array.isArray(response) && response.length) {
                await cacheOfflineProducts(page.props.auth?.user?.id || 'current', `${keyword.value.trim().toLowerCase()}|${categoryId.value}`, response).catch(() => {})
            }

            /*
            |--------------------------------------------------------------------------
            | Không tìm thấy sản phẩm
            |--------------------------------------------------------------------------
            */

            if (
                keyword.value &&
                response.length === 0
            ) {

                try {

                    const scanResult =
                        await productService.scan(
                            keyword.value
                        )

                    emit(
                        'product-scanned',
                        scanResult
                    )

                    keyword.value = ''

                } catch {

                    // IMEI không tồn tại
                }
            }

        } catch (error) {
            const cached = await readOfflineProducts(page.props.auth?.user?.id || 'current', `${keyword.value.trim().toLowerCase()}|${categoryId.value}`).catch(() => null)
            products.value = cached?.products || []

        } finally {

            loading.value = false
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    const searchProducts = () => {

        clearTimeout(timeout)

        timeout = setTimeout(
            () => {

                loadProducts()
            },
            300
        )
    }

    // Reset ô tìm sản phẩm
    const resetProductSearch = async () => {

        keyword.value = ''

        categoryId.value = ''

        products.value = []

        await loadProducts()
    }

    /*
    |--------------------------------------------------------------------------
    | WATCH
    |--------------------------------------------------------------------------
    */

    watch(
        [keyword, categoryId],
        () => {

            searchProducts()
        }
    )

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    onMounted(() => {

        loadProducts()

        onEvent(
            'products:refresh',
            loadProducts
        )


        onEvent(
            'pos:reset',
            resetProductSearch
        )
    })

    /*
    |--------------------------------------------------------------------------
    | UNMOUNT
    |--------------------------------------------------------------------------
    */

    onBeforeUnmount(() => {
        // Hủy bỏ timeout khi component bị hủy để tránh gọi API không cần thiết
        clearTimeout(timeout)

       offEvent(
            'products:refresh',
            loadProducts
        )

        offEvent(
            'pos:reset',
            resetProductSearch
        )
    })

    return {

        keyword,

        categoryId,

        products,

        categories,

        loading,

        loadProducts,
    }
}
