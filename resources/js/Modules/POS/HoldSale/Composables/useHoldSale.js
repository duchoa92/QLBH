import { ref } from 'vue'

import { useToast }
from '@/Composables/useToast'
import { holdSaleService } from '@/Modules/POS/HoldSale/Services/holdSaleService'



export function useHoldSale(

    cart,

    selectedCustomer,

    grandTotal,

    clearCart,
) {

    const toast = useToast()

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const holdSales = ref([])

    const showHoldModal =
        ref(false)

    const showSaveHoldModal =
        ref(false)

    const holdName = ref('')

    /*
    |--------------------------------------------------------------------------
    | FETCH HOLD SALES
    |--------------------------------------------------------------------------
    */

    const fetchHoldSales =
        async () => {

        try {

            const response =
                await holdSaleService.getAll()

            holdSales.value =
                response.data

        } catch (error) {

            console.error(error)
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN HOLD MODAL
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

            selectedCustomer.value
                ?.full_name?.trim()

            ||

            `Hóa đơn ${Date.now()}`
        
        showSaveHoldModal.value =
            true
    }

    /*
    |--------------------------------------------------------------------------
    | HOLD BILL
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

            await holdSaleService.create({

                items:
                    cart.value,

                customer_id:
                    selectedCustomer
                        .value?.id
                    || null,

                grand_total:
                    grandTotal.value,

                name:
                    holdName.value,
            })

            toast.success(
                'Đã lưu hóa đơn'
            )

            await fetchHoldSales()

            clearCart()

            holdName.value = ''

            showSaveHoldModal.value =
                false

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

    const loadHoldSale =
        async (holdSaleId) => {

        try {

            const response =
                await holdSaleService.getById(
                    holdSaleId
                )

            const holdSale =
                response.data.data

            cart.value =
                holdSale.cart_items

            selectedCustomer.value =
                holdSale.customer

            await holdSaleService.remove(
                holdSaleId
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