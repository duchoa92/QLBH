<script setup>
import { ref } from 'vue'
import { useBarcodeScanner } from '@/Modules/POS/Product/Composables/useBarcodeScanner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { toast } from 'vue-sonner'
import { useCart } from '@/Modules/POS/Cart/Composables/useCart'
import { useCheckout } from '@/Modules/POS/Payment/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Modules/POS/Core/Composables/useKeyboardShortcuts'
import PosSidebar from '@/Modules/POS/Core/Components/PosSidebar.vue'
import PosMainPanel from '@/Modules/POS/Core/Components/PosMainPanel.vue'
import PosLayout from '@/Modules/POS/Core/Layouts/PosLayout.vue'
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue'
import SelectProductModal from '@/Modules/POS/Product/Components/SelectProductModal.vue'

const {
    tabs,
    activeTabId,
    createTab,
    removeTab,
    switchToNextTab,
    cleanupEmptyTabs,
    cart,
    selectedCustomer,
    selectedCartIndex,
    grandTotal,
    addToCart,
    removeItem,
    clearCart,
} = useCart()


const {
    confirmCheckout,
} = useCheckout(
    cart,
    selectedCustomer,
    clearCart,
)

// tab đang chọn
const onCustomerSelected = (customer) => {
    selectedCustomer.value = customer
}

// Hàm chuyển tab
const selectTab = (
    tabId
) => {

    activeTabId.value =
        tabId
}

const invoiceData = ref(null)

const showInvoice = ref(false)

// loading khi nhấn checkout để tránh việc click nhiều lần vào nút checkout
const loading = ref(false)

const showSelectModal = ref(false)
const selectedProduct = ref(null)

const handleSelectProduct = (product) => {

    const hasImei = product.has_imei || (product.imeis && product.imeis.length)
    // 👉 có IMEI
    if (hasImei) {
        selectedProduct.value = product
        showSelectModal.value = true
        return
    }

    // 👉 có variant
    if (product.variants && product.variants.length) {
        selectedProduct.value = product
        showSelectModal.value = true
        return
    }

    // 👉 sản phẩm thường
    addToCart(product)
}
const handleConfirmSelect = ({ variant, imei }) => {

    addToCart({
        ...selectedProduct.value,
        variant,
        imei
    })

    showSelectModal.value = false
}

const handleCheckout = async (data) => {

    if (!cart.value.length) {

        toast.error('Giỏ hàng trống')

        return
    }

    try {

        loading.value = true

        const res = await confirmCheckout({
            ...data,
            cart: cart.value
        })

        showCheckoutModal.value = false

        if (!res) {
            return
        }

        invoiceData.value = res
        showInvoice.value = true
        // sau khi TT thành công chuyển về tab có DL
        switchToNextTab()
        // Dọn tab rỗng
        cleanupEmptyTabs()

    } catch (error) {

        console.error(error)

    } finally {

        loading.value = false
    }
}


const checkout = async (data) => {

    if (!cart.value.length) {
        toast.error('Giỏ hàng trống')
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


const openCheckoutModal = () => {

    if (!cart.value.length) {

        toast.error('Giỏ hàng trống')

        return
    }

    showCheckoutModal.value = true
}

useKeyboardShortcuts({
    cart,
    selectedCartIndex,
    clearCart,
    showCheckoutModal,
    checkout: openCheckoutModal,
})

useBarcodeScanner(
    async (barcode) => {
        try {
            const result = await productService.scan(barcode)

            handleSelectProduct(result.data)
        } catch (error) {
            console.error(error)
            toast.error('Không tìm thấy sản phẩm với mã vạch này')
        }
    }
)





</script>

<template>

    <PosLayout>

        <template #main>
            <PosMainPanel
                @add-product="handleSelectProduct"
            />
        </template>

        <template #sidebar>
            <PosSidebar
                :tabs="tabs"
                :active-tab-id="activeTabId"
                :cart="cart"
                :selected-customer="selectedCustomer"
                :grand-total="grandTotal"
                :loading="loading"
                @select-tab="selectTab"
                @create-tab="createTab"
                @remove-tab="removeTab"
                @customer-selected="onCustomerSelected"
                @remove-item="removeItem"
                @checkout="openCheckoutModal"
            />
        </template>

    </PosLayout>

    <CheckoutModal
        :loading="loading"
        :show="showCheckoutModal"
        :cart="cart"
        :grand-total="grandTotal"
        :selected-customer="selectedCustomer"
        @close="showCheckoutModal = false"
        @confirm="checkout($event)"
    />

    <SelectProductModal
        v-if="selectedProduct"
        :show="showSelectModal"
        :product="selectedProduct"
        @close="showSelectModal = false"
        @confirm="handleConfirmSelect"
    />


</template>


