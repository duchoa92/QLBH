<script setup>
import { ref, onMounted, watch } from 'vue'
import { useBarcodeScanner } from '@/Modules/POS/Product/Composables/useBarcodeScanner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { useToast } from '@/Composables/useToast'
import { useCart } from '@/Modules/POS/Cart/Composables/useCart'
import { useHoldSale } from '@/Modules/POS/HoldSale/Composables/useHoldSale'
import { useCheckout } from '@/Modules/POS/Payment/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Modules/POS/Core/Composables/useKeyboardShortcuts'
import HoldSaleModal from '@/Modules/POS/HoldSale/Components/HoldSaleModal.vue'
import SaveHoldModal from '@/Modules/POS/HoldSale/Components/SaveHoldModal.vue'
import PosSidebar from '@/Modules/POS/Core/Components/PosSidebar.vue'
import PosMainPanel from '@/Modules/POS/Core/Components/PosMainPanel.vue'
import PosLayout from '@/Modules/POS/Core/Layouts/PosLayout.vue'
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue'


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

const invoiceData = ref(null)

const showInvoice = ref(false)

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
        showCheckoutModal.value = false

    } catch (e) {
        // Để trống hoặc chỉ console.log(e) nếu file cấu hình chung Axios của bạn đã tự bật Toast lỗi
        console.error(e);
    }
    finally {
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


// Hiện popup thanh toán
const showCheckoutModal = ref(false)

// debug
watch(
    showCheckoutModal,
    (value) => {

        console.log(
            'showCheckoutModal =',
            value
        )

        console.trace()
    }
)

const openCheckoutModal = () => {

    if (!cart.value.length) {

        errorToast('Giỏ hàng trống')

        return
    }

    showCheckoutModal.value = true
}

useKeyboardShortcuts({
    cart,
    selectedCartIndex,
    clearCart,
    showPaymentModal: showCheckoutModal,
    checkout: openCheckoutModal,
})

useBarcodeScanner(
    async (barcode) => {
        try {
            const result = await productService.scan(barcode)
            addToCart(result.data)
        } catch (error) {
            console.error(error)
            errorToast('Không tìm thấy sản phẩm với mã vạch này')
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
                :hold-sales="holdSales"
                :grand-total="grandTotal"
                :loading="loading"
                @customer-selected="onCustomerSelected"
                @open-hold="openHoldModal"
                @show-hold-list="showHoldModal = true"
                @remove-item="removeItem"
                @checkout="openCheckoutModal"
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

    <CheckoutModal
        :show="showCheckoutModal"
        :grand-total="grandTotal"
        :selected-customer="selectedCustomer"
        @close="showCheckoutModal = false"
        @confirm="checkout($event)"

    />

</template>
