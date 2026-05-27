import { ref, computed, watch } from 'vue'

export function usePos() {

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const cart = ref([])

    const selectedCustomer = ref(null)

    const selectedCartIndex = ref(0)

    /*
    |--------------------------------------------------------------------------
    | LOAD LOCAL STORAGE
    |--------------------------------------------------------------------------
    */

    const savedCart =
        localStorage.getItem('pos_cart')

    if (savedCart) {

        cart.value = JSON.parse(savedCart)
    }

    const savedCustomer =
        localStorage.getItem(
            'pos_customer'
        )

    if (savedCustomer) {

        selectedCustomer.value =
            JSON.parse(savedCustomer)
    }

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
    | WATCH
    |--------------------------------------------------------------------------
    */

    watch(cart, (value) => {

        localStorage.setItem(
            'pos_cart',
            JSON.stringify(value)
        )

    }, {
        deep: true,
    })

    watch(selectedCustomer, (value) => {

        if (value) {

            localStorage.setItem(
                'pos_customer',
                JSON.stringify(value)
            )

            return
        }

        localStorage.removeItem(
            'pos_customer'
        )

    }, {
        deep: true,
    })

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

        localStorage.removeItem(
            'pos_cart'
        )

        localStorage.removeItem(
            'pos_customer'
        )
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