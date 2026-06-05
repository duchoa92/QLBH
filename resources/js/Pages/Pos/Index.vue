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
import { useCheckout } from '@/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts'
import HoldSaleModal from '@/Modules/POS/HoldSale/Components/HoldSaleModal.vue'
import SaveHoldModal from '@/Modules/POS/HoldSale/Components/SaveHoldModal.vue'
import PosSidebar from '@/Components/POS/PosSidebar.vue'
import PosMainPanel from '@/Components/POS/PosMainPanel.vue'
import PosLayout from '@/Modules/POS/Core/Layouts/PosLayout.vue'
import InvoiceModal from '@/Components/POS/InvoiceModal.vue'

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

const handleCheckout = async (data) => {

    const res = await confirmCheckout({
        ...data,
        cart: cart.value
    })

    invoiceData.value = res
    showInvoice.value = true

}

const checkout = async (data) => {

    if (!cart.value.length) {
        errorToast('Gio hang trong')
        return
    }

    await handleCheckout(data)
}

useKeyboardShortcuts({

    cart,

    selectedCartIndex,

    checkout,

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
                'Khong tim thay san pham'
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

    <InvoiceModal
        v-if="invoiceData"
        :show="showInvoice"
        :data="invoiceData"
        @close="showInvoice = false"
    />

</template>
