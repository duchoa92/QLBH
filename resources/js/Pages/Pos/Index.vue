
<script setup>
import {
    ref,
    onMounted,
} from 'vue'
import { useBarcodeScanner } from '@/Modules/POS/Product/Composables/useBarcodeScanner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { useToast } from '@/Composables/useToast'
import PaymentModal from '@/Components/POS/PaymentModal.vue'
import { useCart } from '@/Modules/POS/Cart/Composables/useCart'
import { useHoldSale } from '@/Modules/POS/HoldSale/Composables/useHoldSale'
import { useCheckout } from '@/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts'
import HoldSaleModal from '@/Modules/POS/HoldSale/Components/HoldSaleModal.vue'
import SaveHoldModal from '@/Modules/POS/HoldSale/Components/SaveHoldModal.vue'
import PosSidebar from '@/Components/POS/PosSidebar.vue'
import PosMainPanel from '@/Components/POS/PosMainPanel.vue'
import PosLayout from '@/Modules/POS/Core/Layouts/PosLayout.vue'
import CustomerSection from '@/Modules/POS/Customer/Components/CustomerSection.vue'



// Khai báo test Barcode nhập tay, sau này đổi thành máy quét
const testBarcode = ref('')
const scanTestBarcode =
    async () => {

    try {

        const product =
            await productService
                .findByBarcode(
                    testBarcode.value
                )

        addToCart(product)

        testBarcode.value = ''

    } catch (error) {

        errorToast(

            error?.response?.data?.message
            || 'Không tìm thấy sản phẩm'
        )
    }
}


// Sử dụng composable useCart để quản lý trạng thái giỏ hàng
const {

    cart,

    selectedCustomer,

    selectedCartIndex,

    grandTotal,

    addToCart,

    removeItem,

    clearCart,

} = useCart()


// Sử dụng composable useHoldSale để xử lý lưu tạm hóa đơn
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


// Sử dụng composable useCheckout để xử lý thanh toán
const {

    confirmCheckout,

} = useCheckout(

    cart,

    selectedCustomer,

    clearCart,
)



// Khi khách hàng được chọn từ component CustomerSection, cập nhật selectedCustomer
const onCustomerSelected = (customer) => {
    selectedCustomer.value = customer
}

// Hiển thị hướng dẫn phím tắt
const showShortcuts = ref(false)

// Hiển thị modal thanh toán
const showPaymentModal = ref(false)

// Xử lý xác nhận thanh toán từ PaymentModal
const handleCheckout = async (
    paymentData
) => {

    await confirmCheckout(
        paymentData,
        () => {

            showPaymentModal.value =
                false
        }
    )
}




// Thanh toán
const checkout = () => {

    if (!cart.value.length) {

        error('Giỏ hàng trống')

        return
    }

    showPaymentModal.value = true
}

// Sử dụng composable useKeyboardShortcuts để xử lý phím tắt
useKeyboardShortcuts({

    cart,

    selectedCartIndex,

    checkout,

    showPaymentModal,

    clearCart,
})

// 
useBarcodeScanner(

    async (barcode) => {

        try {

            const product =
                await productService
                    .findByBarcode(
                        barcode
                    )

            addToCart(product)

        } catch (error) {

            console.error(error)

            errorToast(
                'Không tìm thấy sản phẩm'
            )
        }
    }
)


onMounted(() => {

    fetchHoldSales()
})

const {

    success,

    error: errorToast,

    loading,

    dismiss,

} = useToast()

</script>

<template>

<!-- Ô nhập Imei -->
    <div class="p-2 bg-yellow-50 border-b">

        <input
            v-model="testBarcode"
            @keyup.enter="scanTestBarcode"
            class="border p-2 rounded w-full"
            placeholder="Nhập barcode hoặc IMEI để test"
        />

    </div>
   <PosLayout>

        <!-- PHẦN TRÁI -->
        <PosMainPanel
            :cart="cart"

            :selected-cart-index="
                selectedCartIndex
            "

            @add-product="addToCart"

            @remove-item="removeItem"
        />


        <!-- Phải -->
        <PosSidebar
            :cart="cart"

            :selected-customer="
                selectedCustomer
            "

            :show-shortcuts="
                showShortcuts
            "

            :hold-sales="holdSales"

            @customer-selected="
                onCustomerSelected
            "

            @toggle-shortcuts="
                showShortcuts =
                !showShortcuts
            "

            @open-hold="
                openHoldModal
            "

            @show-hold-list="
                showHoldModal = true
            "

            @checkout="checkout"
        />

    </PosLayout>


    <!-- Modal thanh toán -->
     <PaymentModal

        :show="showPaymentModal"

        :total="Number(grandTotal)"

        @close="showPaymentModal = false"

        @confirm="handleCheckout"
    />

    <!-- Modal hóa đơn giữ -->
    <HoldSaleModal

        :show="showHoldModal"

        :hold-sales="holdSales"

        @close="showHoldModal = false"

        @load="loadHoldSale"
    />

    <!-- Modal lưu tạm hóa đơn -->
     <SaveHoldModal

        :show="showSaveHoldModal"

        :hold-name="holdName"

        @close="showSaveHoldModal = false"

        @save="holdBill"

        @update:holdName="
            holdName = $event
        "
    />


</template>
