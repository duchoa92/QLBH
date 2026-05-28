import axios from 'axios'

import { toast } from 'vue-sonner'

export function useCheckout(
    cart,
    selectedCustomer,
    clearCart,
) {

    const confirmCheckout = async (
        paymentData,
        callback = null
    ) => {

        const loadingToast =
            loading(
                'Đang thanh toán...'
            )

        try {

            const response =
                await axios.post(
                    '/pos/checkout',
                    {

                        items:
                            cart.value,

                        customer_id:
                            selectedCustomer.value?.id
                            || null,

                        paid_amount:
                            paymentData.paid_amount,

                        payment_method:
                            paymentData.payment_method,
                    }
                )

            clearCart()

            dismiss(
                loadingToast
            )

            success(
                'Thanh toán thành công'
            )

            /*
            |--------------------------------------------------------------------------
            | Refresh products
            |--------------------------------------------------------------------------
            */

            window.dispatchEvent(
                new Event(
                    'pos-refresh-products'
                )
            )

            /*
            |--------------------------------------------------------------------------
            | Callback
            |--------------------------------------------------------------------------
            */

            if (callback) {

                callback(response.data)
            }

            return response.data

        } catch (error) {

            dismiss(
                loadingToast
            )

            console.error(error)

            error(

                error.response?.data?.message

                || 'Thanh toán thất bại'
            )

            throw error
        }
    }

    return {

        confirmCheckout,
    }
}