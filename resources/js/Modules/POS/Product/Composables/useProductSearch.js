import { productService } from '@/Modules/POS/Product/Services/productService'
import { categoryService } from '@/Modules/POS/Product/Services/categoryService'

import {
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
} from 'vue'

import { useEventBus } from '@/Composables/useEventBus'

export function useProductSearch(emit) {

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const keyword = ref('')

    const categoryId = ref('')

    const products = ref([])

    const categories = ref([])

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

            console.error(error)

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

    /*
    |--------------------------------------------------------------------------
    | LOAD CATEGORIES
    |--------------------------------------------------------------------------
    */

    const loadCategories = async () => {

        try {

            categories.value =
                await categoryService.getAll()

        } catch (error) {

            console.error(error)
        }
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

        loadCategories()

        onEvent(
            'products:refresh',
            loadProducts
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