
<script setup>
import ProductSearch from '@/Components/POS/ProductSearch.vue'
import CartTable from '@/Components/POS/CartTable.vue'
import CustomerSection from '@/Components/POS/CustomerSection.vue'
import SummarySection from '@/Components/POS/SummarySection.vue'
import { ref } from 'vue'
import axios from 'axios'
import { computed } from 'vue'
import PaymentModal from '@/Components/POS/PaymentModal.vue'

// Giỏ hàng
const cart = ref([])

// Hiển thị modal thanh toán
const showPaymentModal = ref(false)

// Thêm sản phẩm vào giỏ hàng
const addToCart = (product) => {

    const existing = cart.value.find(item => {

        /*
        |--------------------------------------------------------------------------
        | SẢN PHẨM SERIAL/IMEI
        |--------------------------------------------------------------------------
        */

        if (product.product_imei_id) {

            return (
                item.product_imei_id ===
                product.product_imei_id
            )
        }

        /*
        |--------------------------------------------------------------------------
        | SẢN PHẨM THƯỜNG
        |--------------------------------------------------------------------------
        */

        return item.id === product.id
    })

    /*
    |--------------------------------------------------------------------------
    | SẢN PHẨM ĐÃ TỒN TẠI TRONG GIỎ HÀNG
    |--------------------------------------------------------------------------
    */

    if (existing) {

        // serial product không tăng SL
        if (product.product_imei_id) {

            return
        }

        existing.quantity++

        existing.subtotal =
            existing.quantity *
            existing.sell_price

        return
    }

    /*
    |--------------------------------------------------------------------------
    | SẢN PHẨM MỚI
    |--------------------------------------------------------------------------
    */

    cart.value.push({

        id: product.id,

        name: product.name,

        sell_price: product.sell_price,

        quantity: 1,

        subtotal: product.sell_price,

        product_type: product.product_type,

        product_imei_id:
            product.product_imei_id,

        imei: product.imei,

        serial: product.serial,
    })
}

// Thanh toán
const checkout = () => {

    if (!cart.value.length) {

        alert('Giỏ hàng trống')

        return
    }

    showPaymentModal.value = true
}


// Xác nhận thanh toán
const confirmCheckout = async (paymentData) => {

    try {

        await axios.post(
            '/pos/checkout',
            {

                items: cart.value,

                customer_id: null,

                paid_amount:
                    paymentData.paid_amount,

                payment_method:
                    paymentData.payment_method,
            }
        )

        alert('Thanh toán thành công')

        cart.value = []

        showPaymentModal.value = false

    } catch (error) {

        console.error(error)

        alert('Thanh toán thất bại')
    }
}

// Tính tổng tiền
const grandTotal = computed(() => {

    return cart.value.reduce(
        (total, item) => {

            return total + item.subtotal
        },
        0
    )
})

</script>

<template>
    <div class="h-screen bg-gray-100 p-3">

        <div class="grid grid-cols-12 gap-3 h-full">

            <!-- PHẦN TRÁI -->
            <div class="col-span-8 bg-white rounded shadow p-3 flex flex-col">

                <!-- TÌM KIẾM SẢN PHẨM -->
                <ProductSearch
                    @selected="addToCart"
                />

                <!-- GIỎ HÀNG -->
                <div class="mt-3 flex-1 overflow-auto">
                    <CartTable
                        :items="cart"
                    />
                </div>

            </div>

            <!-- Phải -->
            <div class="col-span-4 bg-white rounded shadow p-3 flex flex-col">

                <!-- KHÁCH HÀNG -->
                <CustomerSection />

                <!-- Thanh toán -->
                <div class="mt-4">
                    <SummarySection
                        :items="cart"
                        @checkout="checkout"
                    />
                </div>

            </div>

        </div>

    </div>



    <!-- Modal thanh toán -->
     <PaymentModal

        :show="showPaymentModal"

        :total="grandTotal"

        @close="showPaymentModal = false"

        @confirm="confirmCheckout"
    />
</template>
