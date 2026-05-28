import axios from 'axios'

import { useToast } from '@/Composables/useToast'
import { useEventBus } from '@/Composables/useEventBus'


export function useCheckout(

    cart,

    selectedCustomer,

    clearCart,
) {

    /*
    |--------------------------------------------------------------------------
    | CHECKOUT
    |--------------------------------------------------------------------------
    */

    const confirmCheckout = async (

        paymentData,

        onSuccess = null,
    ) => {

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if (!cart.value.length) {

            toast.error(
                'Giỏ hàng trống'
            )

            return
        }

        /*
        |--------------------------------------------------------------------------
        | Loading
        |--------------------------------------------------------------------------
        */

        const loadingToast =
            toast.loading(
                'Đang thanh toán...'
            )

        try {

            /*
            |--------------------------------------------------------------------------
            | API
            |--------------------------------------------------------------------------
            */

            const response =
                await axios.post(
                    '/pos/checkout',
                    {

                        items:
                            cart.value,

                        customer_id:
                            selectedCustomer
                                .value?.id
                            || null,

                        paid_amount:
                            paymentData
                                .paid_amount,

                        payment_method:
                            paymentData
                                .payment_method,
                    }
                )

            /*
            |--------------------------------------------------------------------------
            | Clear cart
            |--------------------------------------------------------------------------
            */

            clearCart()

            /*
            |--------------------------------------------------------------------------
            | Refresh products
            |--------------------------------------------------------------------------
            */
            emitEvent(
                'products:refresh'
            )

            /*
            |--------------------------------------------------------------------------
            | Success callback
            |--------------------------------------------------------------------------
            */

            if (onSuccess) {

                onSuccess(response.data)
            }

            /*
            |--------------------------------------------------------------------------
            | Toast success
            |--------------------------------------------------------------------------
            */

            toast.dismiss(
                loadingToast
            )

            toast.success(
                'Thanh toán thành công'
            )

            return response.data

        } catch (error) {

            /*
            |--------------------------------------------------------------------------
            | Error
            |--------------------------------------------------------------------------
            */

            console.error(error)

            toast.dismiss(
                loadingToast
            )

            toast.error(

                error.response?.data
                    ?.message

                || 'Thanh toán thất bại'
            )

            throw error
        }
    }

    return {

        confirmCheckout,
    }
}
// 
const toast = useToast()


// Sự kiện 
const { emitEvent } =
    useEventBus()