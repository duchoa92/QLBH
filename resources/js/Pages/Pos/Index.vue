
<script setup>
import ProductSearch from '@/Components/POS/ProductSearch.vue'
import CartTable from '@/Components/POS/CartTable.vue'
import CustomerSection from '@/Components/POS/CustomerSection.vue'
import SummarySection from '@/Components/POS/SummarySection.vue'
import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
} from 'vue'
import axios from 'axios'
import PaymentModal from '@/Components/POS/PaymentModal.vue'

// Giỏ hàng
const cart = ref([])

// Khách hàng đã chọn
const selectedCustomer = ref(null)

// Chỉ số sản phẩm đang chọn trong giỏ hàng
const selectedCartIndex = ref(0)

// Hiển thị hướng dẫn phím tắt
const showShortcuts = ref(false)

// Hiển thị modal thanh toán
const showPaymentModal = ref(false)

// Tính tổng tiền
const grandTotal = computed(() => {

    return cart.value.reduce(
        (total, item) => {

            return total +
                (
                    Number(item.price) *
                    Number(item.quantity)
                )
        },
        0
    )
}) 

// Chuẩn hóa giá trị nhập vào (bỏ dấu phẩy)
const normalizePrice = (value) => {

    if (!value) {

        return 0
    }

    return Number(
        String(value)
            .replaceAll(',', '')
    )
}


// Thêm sản phẩm vào giỏ hàng
const addToCart = (product) => {


    const rawPrice =
        product.sell_price ??
        product.price ??
        0

    const price = Number(

        String(rawPrice)

            // bỏ dấu chấm
            .replaceAll('.', '')

            // bỏ dấu phẩy
            .replaceAll(',', '')

            // bỏ ký tự tiền
            .replaceAll('đ', '')

            .trim()
    )

    const existing = cart.value.find(
        (item) => {

            // IMEI
            if (product.imei_id) {

                return (
                    item.imei_id ===
                    product.imei_id
                )
            }

            // hàng thường
            return (
                item.id === product.id
            )
        }
    )

    // đã có
    if (existing) {

        // IMEI không tăng
        if (existing.imei_id) {

            return
        }

        existing.quantity++

        return
    }

    // thêm mới
    cart.value.push({

        id: product.id,

        name: product.name,

        price: price,

        quantity: 1,

        imei_id:
            product.imei_id || null,

        imei:
            product.imei || null,
    })

    selectedCartIndex.value =
        cart.value.length - 1

}

// Xóa sản phẩm
const removeItem = (index) => {

    cart.value.splice(index, 1)

    if (
        selectedCartIndex.value >
        cart.value.length - 1
    ) {

        selectedCartIndex.value =
            Math.max(
                0,
                cart.value.length - 1
            )
    }
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

        const response = await axios.post(
            '/pos/checkout',
            {

                items: cart.value,

                customer_id:
                    selectedCustomer.value?.id
                    || null,

                paid_amount:
                    paymentData.paid_amount,

                payment_method:
                    paymentData.payment_method,
            }
        )

        window.open(
            `/sales/${response.data.sale_id}/receipt`,
            '_blank'
        )

        cart.value = []

        showPaymentModal.value = false

    } catch (error) {

    console.error(error)

    console.log(
        error.response?.data
    )

    alert(
        error.response?.data?.message
        || 'Thanh toán thất bại'
    )
}
}

// Xử lý phím tắt
const handleKeydown = (event) => {

    /*
    |--------------------------------------------------------------------------
    | ESC → đóng popup
    |--------------------------------------------------------------------------
    */

    if (event.key === 'Escape') {

        showPaymentModal.value = false
    }

    /*
    |--------------------------------------------------------------------------
    | F2 → Thanh toán
    |--------------------------------------------------------------------------
    */

    if (event.key === 'F2') {

        event.preventDefault()

        checkout()
    }

    /*
    |--------------------------------------------------------------------------
    | Delete → xóa item
    |--------------------------------------------------------------------------
    */

    if (
        event.key === 'Delete' &&
        !event.ctrlKey
    ) {

        if (
            cart.value[
                selectedCartIndex.value
            ]
        ) {

            cart.value.splice(
                selectedCartIndex.value,
                1
            )

            if (
                selectedCartIndex.value >
                cart.value.length - 1
            ) {

                selectedCartIndex.value =
                    Math.max(
                        0,
                        cart.value.length - 1
                    )
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CTRL + DELETE → clear cart
    |--------------------------------------------------------------------------
    */

    if (
        event.key === 'Delete' &&
        event.ctrlKey
    ) {

        event.preventDefault()

        const confirmed = confirm(
            'Xóa toàn bộ giỏ hàng?'
        )

        if (confirmed) {

            cart.value = []
        }
    }

    /*
    |--------------------------------------------------------------------------
    | +
    |--------------------------------------------------------------------------
    */

    if (
        event.key === '+' ||
        event.key === '='
    ) {

        const item =
            cart.value[
                selectedCartIndex.value
            ]

        if (item) {

            item.quantity++
        }
    }

    /*
    |--------------------------------------------------------------------------
    | -
    |--------------------------------------------------------------------------
    */

    if (
        event.key === '-' ||
        event.key === '_'
    ) {

        const item =
            cart.value[
                selectedCartIndex.value
            ]

        if (
            item &&
            item.quantity > 1
        ) {

            item.quantity--
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Arrow Down
    |--------------------------------------------------------------------------
    */

    if (event.key === 'ArrowDown') {

        if (
            selectedCartIndex.value <
            cart.value.length - 1
        ) {

            selectedCartIndex.value++
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Arrow Up
    |--------------------------------------------------------------------------
    */

    if (event.key === 'ArrowUp') {

        if (
            selectedCartIndex.value > 0
        ) {

            selectedCartIndex.value--
        }
    }
}


// thêm sự kiện lắng nghe phím tắt khi component được mount
onMounted(() => {

    window.addEventListener(
        'keydown',
        handleKeydown
    )
})

// Xóa sự kiện lắng nghe khi component bị unmount
onBeforeUnmount(() => {

    window.removeEventListener(
        'keydown',
        handleKeydown
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
                        :selected-index="selectedCartIndex"
                        @remove="removeItem"
                    />
                </div>

            </div>

            <!-- Phải -->
            <div class="col-span-4 bg-white rounded shadow p-3 flex flex-col">

                <!-- KHÁCH HÀNG -->
                <CustomerSection
                    @selected="
                        selectedCustomer = $event
                    "
                />

                <!-- Thanh toán -->
                <div class="mt-4">
                    <!-- Keyboard Shortcuts -->
                    <div class="mb-4">

                        <!-- Toggle -->
                        <button
                            @click="showShortcuts = !showShortcuts"
                            class="text-sm text-blue-600 hover:underline"
                        >
                            {{ showShortcuts
                                ? 'Ẩn phím tắt'
                                : 'Xem phím tắt'
                            }}
                        </button>

                        <!-- Content -->
                        <div
                            v-if="showShortcuts"
                            class="mt-2 border rounded p-3 bg-gray-50"
                        >

                            <div class="font-bold mb-2">
                                Phím tắt POS
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-sm">

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        F2
                                    </kbd>

                                    → Thanh toán
                                </div>

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        ESC
                                    </kbd>

                                    → Đóng popup
                                </div>

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        DELETE
                                    </kbd>

                                    → Xóa sản phẩm
                                </div>

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        CTRL + DELETE
                                    </kbd>

                                    → Xóa giỏ hàng
                                </div>

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        +
                                    </kbd>

                                    → Tăng SL
                                </div>

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        -
                                    </kbd>

                                    → Giảm SL
                                </div>

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        ↑ ↓
                                    </kbd>

                                    → Chọn dòng
                                </div>

                                <div>
                                    <kbd
                                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                                    >
                                        ENTER
                                    </kbd>

                                    → Xác nhận
                                </div>

                            </div>

                        </div>

                    </div>

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

        :total="Number(grandTotal)"

        @close="showPaymentModal = false"

        @confirm="confirmCheckout"
    />
</template>
