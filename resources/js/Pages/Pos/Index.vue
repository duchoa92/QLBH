
<script setup>
import {
    ref,
    onMounted,
} from 'vue'
import { useToast } from '@/Composables/useToast'
import PaymentModal from '@/Components/POS/PaymentModal.vue'
import { usePos } from '@/Composables/usePos'
import { useHoldSale } from '@/Composables/useHoldSale'
import { useCheckout } from '@/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts'
import HoldSaleModal from '@/Components/POS/HoldSaleModal.vue'
import SaveHoldModal from '@/Components/POS/SaveHoldModal.vue'
import PosSidebar from '@/Components/POS/PosSidebar.vue'
import PosMainPanel from '@/Components/POS/PosMainPanel.vue'



// Sử dụng composable usePos để quản lý trạng thái giỏ hàng
const {

    cart,

    selectedCustomer,

    selectedCartIndex,

    grandTotal,

    addToCart,

    removeItem,

    clearCart,

} = usePos()

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


onMounted(() => {

    fetchHoldSales()
})

const {

    success,

    error,

    loading,

    dismiss,

} = useToast()

</script>

<template>
    <div class="h-screen bg-gray-100 p-3">

        <div class="grid grid-cols-12 gap-3 h-full">

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

        </div>

    </div>


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
