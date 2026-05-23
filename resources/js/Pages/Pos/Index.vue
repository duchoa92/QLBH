
<script setup>
import ProductSearch from '@/Components/POS/ProductSearch.vue'
import CartTable from '@/Components/POS/CartTable.vue'
import CustomerSection from '@/Components/POS/CustomerSection.vue'
import SummarySection from '@/Components/POS/SummarySection.vue'
import { ref } from 'vue'

// Giỏ hàng
const cart = ref([])

// Thêm sản phẩm vào giỏ hàng
const addToCart = (product) => {

    // kiểm tra sản phẩm đã tồn tại chưa
    const existing = cart.value.find(item => {

        // hàng IMEI → không gộp
        if (product.imei_id) {

            return item.imei_id === product.imei_id
        }

        // hàng thường → gộp SL
        return item.id === product.id
    })

    // nếu đã có → tăng SL
    if (existing) {

        // IMEI không được cộng số lượng
        if (product.imei_id) {
            return
        }

        existing.quantity++

        return
    }

    // nếu chưa có → thêm mới
    cart.value.push({

        id: product.id,

        name: product.name,

        sell_price: product.sell_price,

        quantity: 1,

        subtotal: product.sell_price,

        imei_id: product.imei_id || null,
        
        imei: product.imei || null,
    })
}

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

                <!--  -->
                <div class="mt-4">
                    <SummarySection
                        :items="cart"
                    />
                </div>

            </div>

        </div>

    </div>
</template>
