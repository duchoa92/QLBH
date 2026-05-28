import axios from 'axios'

import {
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
} from 'vue'

import { useEventBus } from '@/Composables/useEventBus'

export function useProductSearch() {

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const keyword = ref('')

    const categoryId = ref('')

    const products = ref([])

    const categories = ref([])

    let timeout = null

    // Sự kiện
    const {

        onEvent,

        offEvent,

    } = useEventBus()

    /*
    |--------------------------------------------------------------------------
    | LOAD PRODUCTS
    |--------------------------------------------------------------------------
    */

    const loadProducts = async () => {

        try {

            const response =
                await axios.get(
                    '/api/products',
                    {
                        params: {

                            keyword:
                                keyword.value,

                            category_id:
                                categoryId.value,
                        },
                    }
                )

            products.value =
                response.data.data
                ?? response.data

        } catch (error) {

            console.error(error)
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

            const response =
                await axios.get(
                    '/api/categories'
                )

            categories.value =
                response.data

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

        loadProducts,
    }
}