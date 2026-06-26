import { toast } from 'vue-sonner'
import { useEventBus } from '@/Composables/useEventBus'
import { paymentService } from '@/Modules/POS/Payment/Services/paymentService'


export function useCheckout(

    cart,

    selectedCustomer,

    clearCart,
) {


    const {

        emitEvent,

    } = useEventBus()

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
// Debug
console.log(
    JSON.parse(
        JSON.stringify(
            cart.value
        )
    )
)

            const response =
           
                await paymentService.checkout({
                   items: cart.value.map(item => ({

                        id: item.id,

                        price: item.price,

                        quantity: item.quantity,

                        imei_id:
                            item.imei_id ?? null,

                        note:
                            item.note,

                        discount_type:
                            item.discount_type ?? null,

                        discount_value:
                            Number(
                                item.discount_value ?? 0
                            ),

                        gifts:
                            item.gifts ?? [],

                    })),

                    customer_id:
                        selectedCustomer.value?.id
                        || null,

                    paid_amount:
                        paymentData.paid_amount,

                    payment_method:
                        paymentData.payment_method,

                    pay_old_debt:
                        paymentData.pay_old_debt,
                })

            /*
            |--------------------------------------------------------------------------
            | Clear cart
            |--------------------------------------------------------------------------
            */

            clearCart()


            // Xóa dữ liệu POS
            emitEvent(
                'pos:reset'
            )


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

            return response

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