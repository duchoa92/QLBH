import {
    ref,
    watch,
} from 'vue'

import { useLocalStorage } from '@/Composables/useLocalStorage'
import { useCartTotals } from '@/Modules/POS/Cart/Composables/useCartTotals'



export function useCart() {

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

    // Tính toán tổng tiền
    const {

        totalQuantity,

        subTotal,

        discount,

        vat,

        grandTotal,

    } = useCartTotals(cart)

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
    | Thêm sản phẩm vào giỏ hàng
    |--------------------------------------------------------------------------
    */

    const addToCart = (product) => {
     
        /*
        |--------------------------------------------------
        | Sản phẩm IMEI
        |--------------------------------------------------
        */

        if (
            product.imei_id ||
            product.imei
        ) {

            const exists =
                cart.value.some((item) => {

                    return (
                        item.imei_id === product.imei_id
                        ||
                        item.imei === product.imei
                    )
                })

            if (exists) {

                return
            }

            cart.value.push({
                id: product.id,
                imei_id: product.imei_id ?? null,
                imei: product.imei ?? null,
                serial: product.serial ?? null,
                color: product.color ?? null,
                storage: product.storage ?? null,
                name: product.name,
                price: Number(product.sell_price ?? product.price ?? 0),
                quantity: 1,
                note: '',
                showNote: false,
                showPromotion: false,
                discount_type: null,
                discount_value: 0,
                gift_product_id: null,
                gift_product_name:'',
                gift_keyword: '',
                gift_results: [],
            })


            return
        }

        /*
        |--------------------------------------------------
        | Sản phẩm thường
        |--------------------------------------------------
        */

        const existing =
            cart.value.find((item) => {

                return (
                    item.id ===
                    product.id
                )
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
            note: '',
            showNote: false,
            showPromotion: false,
            discount_type: null,
            discount_value: 0,
            gift_product_id: null,
            gift_product_name:'',
            gift_keyword: '',
            gift_results: [],
        })
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE QUANTITY
    |--------------------------------------------------------------------------
    */

    const increaseQty = (item) => {

        if (item.imei_id) {

            return
        }

        item.quantity++
    }


    const decreaseQty = (item) => {

        if (item.imei_id) {

            return
        }

        if (item.quantity > 1) {

            item.quantity--
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */

    const removeItem = (item) => {

        const index = cart.value.findIndex(cartItem => {

            if (item.imei_id) {

                return cartItem.imei_id === item.imei_id
            }

            return cartItem.id === item.id
        })

        if (index !== -1) {

            cart.value.splice(index, 1)
        }
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

        totalQuantity,

        subTotal,

        discount,

        vat,

        grandTotal,

        addToCart,

        removeItem,

        clearCart,

        increaseQty,

        decreaseQty,
    }
}