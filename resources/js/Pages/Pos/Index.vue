<script setup>
import {
    ref,
    onMounted,
} from 'vue'
import { useBarcodeScanner } from '@/Modules/POS/Product/Composables/useBarcodeScanner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { useToast } from '@/Composables/useToast'
import { useCart } from '@/Modules/POS/Cart/Composables/useCart'
import { useHoldSale } from '@/Modules/POS/HoldSale/Composables/useHoldSale'
import { useCheckout } from '@/Modules/POS/Payment/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts'
import HoldSaleModal from '@/Modules/POS/HoldSale/Components/HoldSaleModal.vue'
import SaveHoldModal from '@/Modules/POS/HoldSale/Components/SaveHoldModal.vue'
import PosSidebar from '@/Modules/POS/Core/Components/PosSidebar.vue'
import PosMainPanel from '@/Modules/POS/Core/Components/PosMainPanel.vue'
import PosLayout from '@/Modules/POS/Core/Layouts/PosLayout.vue'
import InvoiceDetailModal from '@/Modules/POS/Sale/Components/InvoiceDetailModal.vue'


const {

    cart,

    selectedCustomer,

    selectedCartIndex,

    grandTotal,

    addToCart,

    removeItem,

    clearCart,

} = useCart()

const {

    holdSales,

    showHoldModal,

    showSaveHoldModal,

    holdName,

    fetchHoldSales,

    openHoldModal,

    holdBill,

    loadHoldSale,

} = useHoldSale(

    cart,

    selectedCustomer,

    grandTotal,

    clearCart,
)

const {

    confirmCheckout,

} = useCheckout(

    cart,

    selectedCustomer,
    
    clearCart,
)

const {

    error: errorToast,

} = useToast()

const onCustomerSelected = (customer) => {
    selectedCustomer.value = customer
}

const showShortcuts = ref(false)


// Thanh toán 

const showInvoice = ref(false)
const invoiceData = ref(null)

// loading khi nhấn checkout để tránh việc click nhiều lần vào nút checkout
const loading = ref(false)

const handleCheckout = async (data) => {

    if (!cart.value.length) {
        errorToast('Giỏ hàng trống')
        return
    }

    try {

        loading.value = true

        const res = await confirmCheckout({
            ...data,
            cart: cart.value
        })
        
        if(!res) return

        invoiceData.value = res
        showInvoice.value = true

    } catch (e) {
        errorToast('Lỗi thanh toán')
    } finally {
        loading.value = false
    }
}

const checkout = async (data) => {

    if (!cart.value.length) {
        errorToast('Giỏ hàng trống')
        return
    }

    await handleCheckout({
        note: data.note,
        payment_method: data.payment_method,
        paid_amount: Number(data.paid_amount || 0),
        pay_old_debt: data.pay_old_debt,
    })
}

useKeyboardShortcuts({

    cart,

    selectedCartIndex,

    checkout: handleCheckout,

    clearCart,
})

useBarcodeScanner(

    async (barcode) => {

        try {

            const result =
                await productService.scan(
                    barcode
                )

            addToCart(
                result.data
            )

        } catch (error) {

            console.error(error)

            errorToast(
                'Không tìm thấy sản phẩm với mã vạch này'
            )
        }
    }
)






onMounted(() => {

    fetchHoldSales()
})
</script>

<template>

    <PosLayout>

        <template #main>
            <PosMainPanel
                @add-product="addToCart"
            />
        </template>

        <template #sidebar>
            <PosSidebar
                :cart="cart"
                :selected-customer="selectedCustomer"
                :show-shortcuts="showShortcuts"
                :hold-sales="holdSales"
                :grand-total="grandTotal"
                :loading="loading"
                @customer-selected="onCustomerSelected"
                @toggle-shortcuts="showShortcuts = !showShortcuts"
                @open-hold="openHoldModal"
                @show-hold-list="showHoldModal = true"
                @remove-item="removeItem"
                @checkout="checkout"
            />
        </template>

    </PosLayout>

    <HoldSaleModal

        :show="showHoldModal"

        :hold-sales="holdSales"

        @close="showHoldModal = false"

        @load="loadHoldSale"
    />

    <SaveHoldModal

        :show="showSaveHoldModal"

        :hold-name="holdName"

        @close="showSaveHoldModal = false"

        @save="holdBill"

        @update:holdName="
            holdName = $event
        "
    />

<!--     <InvoiceModal
        v-if="invoiceData"
        :show="showInvoice"
        :data="invoiceData"
        @close="showInvoice = false"
    /> -->

</template>
