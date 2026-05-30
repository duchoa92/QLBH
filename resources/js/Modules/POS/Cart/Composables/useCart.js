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

        
        // Nếu sản phẩm có IMEI, mỗi IMEI là một item riêng biệt
        if (product.imei) {

            const existsImei =
                cart.value.find((item) => {

                    return (
                        item.imei ===
                        product.imei
                    )
                })

            if (existsImei) {

                return
            }

            cart.value.push({

                id: product.id,

                name: product.name,

                price: Number(
                    product.price
                ),

                quantity: 1,

                imei:
                    product.imei,

                serial:
                    product.serial,

                color:
                    product.color,

                storage:
                    product.storage,
            })

            return
        }

        /*
        |--------------------------------------------------------------------------
        | Sản phẩm thường
        |--------------------------------------------------------------------------
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

            price: Number(
                product.price
            ),

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

        totalQuantity,

        subTotal,

        discount,

        vat,

        grandTotal,

        addToCart,

        removeItem,

        clearCart,
    }
}