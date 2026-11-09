import { toast } from 'vue-sonner'
import { useEventBus } from '@/Composables/useEventBus'
import { paymentService } from '@/Modules/POS/Payment/Services/paymentService'
import { buildCheckoutItems } from '@/Modules/POS/Payment/Utils/checkoutPayload'


export function useCheckout(

    cart,

    selectedCustomer,

    clearCart,

    invoiceDiscount = { value: 0 },

    extraFee = { value: 0 },

    invoiceDiscountType = { value: 'amount' },
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

            const response =
           
                await paymentService.checkout({
                    items: buildCheckoutItems(cart.value),
                    customer_id:
                        selectedCustomer.value?.id
                        || null,

                    invoice_discount: Number(invoiceDiscount.value || 0),

                    invoice_discount_type: invoiceDiscountType.value === 'percent' ? 'percent' : 'amount',

                    extra_fee: Number(extraFee.value || 0),

                    paid_amount:
                        paymentData.paid_amount,

                    cash_received_amount:
                        paymentData.cash_received_amount ?? null,

                    bank_received_amount:
                        paymentData.bank_received_amount ?? null,

                    payment_method:
                        paymentData.payment_method,

                    bank_account:
                        paymentData.bank_account ?? null,

                    invoice_code:
                        paymentData.invoice_code ?? null,

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
