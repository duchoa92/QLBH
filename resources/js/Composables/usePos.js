import {
    ref,
    computed,
    watch,
} from 'vue'

import { useLocalStorage }
    from '@/Composables/useLocalStorage'

export function usePos() {

    /*
    |--------------------------------------------------------------------------
    | Local Storage
    |--------------------------------------------------------------------------
    */

    const {

        save,

        load,

        remove,

    } = useLocalStorage()

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const cart = ref(
        load('pos_cart', [])
    )

    const selectedCustomer = ref(
        load('pos_customer', null)
    )

    const selectedCartIndex = ref(
        load('pos_selected_index', 0)
    )

    /*
    |--------------------------------------------------------------------------
    | GRAND TOTAL
    |--------------------------------------------------------------------------
    */

    const grandTotal = computed(() => {

        return cart.value.reduce(
            (total, item) => {

                return total +
                    (
                        Number(item.price) *
                        Number(item.quantity)
                    )
            },
            0
        )
    })

    /*
    |--------------------------------------------------------------------------
    | AUTO SAVE
    |--------------------------------------------------------------------------
    */

    watch(
        cart,
        (value) => {

            save(
                'pos_cart',
                value
            )
        },
        {
            deep: true,
        }
    )

    watch(
        selectedCustomer,
        (value) => {

            if (!value) {

                remove('pos_customer')

                return
            }

            save(
                'pos_customer',
                value
            )
        },
        {
            deep: true,
        }
    )

    watch(
        selectedCartIndex,
        (value) => {

            save(
                'pos_selected_index',
                value
            )
        }
    )

    /*
    |--------------------------------------------------------------------------
    | ADD TO CART
    |--------------------------------------------------------------------------
    */

    const addToCart = (product) => {

        const existing =
            cart.value.find((item) => {

                return item.id === product.id
            })

        if (existing) {

            existing.quantity++

            return
        }

        cart.value.push({

            id: product.id,

            name: product.name,

            price: Number(product.price),

            quantity: 1,
        })
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */

    const removeItem = (index) => {

        cart.value.splice(index, 1)
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CART
    |--------------------------------------------------------------------------
    */

    const clearCart = () => {

        cart.value = []

        selectedCustomer.value = null

        selectedCartIndex.value = 0

        remove('pos_cart')

        remove('pos_customer')

        remove('pos_selected_index')
    }

    return {

        cart,

        selectedCustomer,

        selectedCartIndex,

        grandTotal,

        addToCart,

        removeItem,

        clearCart,
    }
}