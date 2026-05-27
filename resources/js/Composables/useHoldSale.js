import { ref } from 'vue'
import axios from 'axios'
import { toast } from 'vue-sonner'

export function useHoldSale(
    cart,
    selectedCustomer,
    grandTotal,
    clearCart,
) {

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const holdSales = ref([])

    const showHoldModal = ref(false)

    const showSaveHoldModal = ref(false)

    const holdName = ref('')

    /*
    |--------------------------------------------------------------------------
    | LOAD
    |--------------------------------------------------------------------------
    */

    const fetchHoldSales = async () => {

        try {

            const response = await axios.get(
                '/api/hold-sales'
            )

            holdSales.value =
                response.data

        } catch (error) {

            console.error(error)
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN MODAL
    |--------------------------------------------------------------------------
    */

    const openHoldModal = () => {

        if (!cart.value.length) {

            toast.error(
                'Giỏ hàng trống'
            )

            return
        }

        holdName.value =
            selectedCustomer.value?.full_name?.trim()
            || `Hóa đơn ${Date.now()}`

        showSaveHoldModal.value = true
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE HOLD
    |--------------------------------------------------------------------------
    */

    const holdBill = async () => {

        if (!cart.value.length) {

            toast.error(
                'Giỏ hàng trống'
            )

            return
        }

        try {

            await axios.post(
                '/api/hold-sales',
                {

                    items: cart.value,

                    customer_id:
                        selectedCustomer.value?.id
                        || null,

                    grand_total:
                        grandTotal.value,

                    name:
                        holdName.value,
                }
            )

            toast.success(
                'Đã lưu hóa đơn'
            )

            await fetchHoldSales()

            clearCart()

            showSaveHoldModal.value =
                false

            holdName.value = ''

        } catch (error) {

            console.error(error)

            toast.error(
                'Không thể lưu hóa đơn'
            )
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD HOLD SALE
    |--------------------------------------------------------------------------
    */

    const loadHoldSale = async (
        holdSaleId
    ) => {

        try {

            const response =
                await axios.get(
                    `/api/hold-sales/${holdSaleId}`
                )

            const holdSale =
                response.data.data

            cart.value =
                holdSale.cart_items

            selectedCustomer.value =
                holdSale.customer

            await axios.delete(
                `/api/hold-sales/${holdSaleId}`
            )

            await fetchHoldSales()

            showHoldModal.value =
                false

            toast.success(
                'Đã mở hóa đơn'
            )

        } catch (error) {

            console.error(error)

            toast.error(
                'Không thể mở hóa đơn'
            )
        }
    }

    return {

        holdSales,

        showHoldModal,

        showSaveHoldModal,

        holdName,

        fetchHoldSales,

        openHoldModal,

        holdBill,

        loadHoldSale,
    }
}