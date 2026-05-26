
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
    watch,
} from 'vue'
import axios from 'axios'
import PaymentModal from '@/Components/POS/PaymentModal.vue'

// Giỏ hàng
const cart = ref([])

// Danh sách hóa đơn giữ
const holdSales = ref([]);

// Hiển thị modal hóa đơn giữ
const showHoldModal = ref(false);

const showSaveHoldModal = ref(false);

const holdName = ref('');

const openHoldModal = () => {

    if (!cart.value.length) {
        alert('Giỏ hàng trống')
        return
    }

    holdName.value = selectedCustomer.value?.full_name?.trim()
        || `Hóa đơn ${Date.now()}`

    showSaveHoldModal.value = true
}

// Tải danh sách hóa đơn giữ
const fetchHoldSales = async () => {

    const response = await axios.get(
        '/api/hold-sales'
    );

    holdSales.value =
        response.data;
};

// Khách hàng đã chọn
const selectedCustomer = ref(null)

const onCustomerSelected = (customer) => {
    selectedCustomer.value = customer
}

// Chỉ số sản phẩm đang chọn trong giỏ hàng
const selectedCartIndex = ref(0)

// Hiển thị hướng dẫn phím tắt
const showShortcuts = ref(false)

// Hiển thị modal thanh toán
const showPaymentModal = ref(false)


/*
|--------------------------------------------------------------------------
| Tải giỏ hàng từ localStorage
|--------------------------------------------------------------------------
*/

const savedCart =
    localStorage.getItem(
        'pos_cart'
    )

if (savedCart) {

    cart.value =
        JSON.parse(savedCart)
}

/*
|--------------------------------------------------------------------------
| Tải chỉ số đã chọn từ localStorage
|--------------------------------------------------------------------------
*/

const savedIndex =
    localStorage.getItem(
        'pos_selected_index'
    )

if (savedIndex) {

    selectedCartIndex.value =
        Number(savedIndex)
}

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

/*
|--------------------------------------------------------------------------
| Tự động lưu giỏ hàng
|--------------------------------------------------------------------------
*/

watch(cart,(value) => {

        localStorage.setItem(
            'pos_cart',
            JSON.stringify(value)
        )
    },
    {
        deep: true,
    }
)

/*
|--------------------------------------------------------------------------
| Lưu chỉ số đã chọn
|--------------------------------------------------------------------------
*/

watch(
    selectedCartIndex,
    (value) => {

        localStorage.setItem(
            'pos_selected_index',
            value
        )
    }
)

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

        // Xóa dữ liệu đã lưu trong localStorage
        localStorage.removeItem(
            'pos_cart'
        )

        localStorage.removeItem(
            'pos_selected_index'
        )

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


/*
|--------------------------------------------------------------------------
| Hold Bill
|--------------------------------------------------------------------------
*/

const holdBill = async () => {
    

    if (!cart.value.length) {

        alert(
            'Giỏ hàng trống'
        )

        return
    }


    try {

        // Lấy CSRF token trước khi gọi API
        await axios.get('/sanctum/csrf-cookie');

        // Gọi API để lưu hóa đơn giữ
        const response = await axios.post('/api/hold-sales',
            {
                items:
                    cart.value,

                customer_id:
                    selectedCustomer.value?.id
                    || null,

                grand_total:
                    grandTotal.value,

                name: holdName.value,
            }
        )

        alert(
            'Đã giữ hóa đơn'
        )

        // Tải lại danh sách hóa đơn giữ
        await fetchHoldSales();

        // Xóa dữ liệu đã lưu trong localStorage
        cart.value = [];

        // Xóa khách hàng đã chọn
        selectedCustomer.value = null;

        // Đóng modal lưu hóa đơn giữ
        showSaveHoldModal.value = false;

        // Reset tên hóa đơn giữ
        holdName.value = '';

    } catch (error) {

        console.error(error)

        alert(
            'Không thể giữ hóa đơn'
        )
    }
}

// Tải hóa đơn giữ vào giỏ hàng
const loadHoldSale = async (holdSaleId) => {
    

    // Lấy dữ liệu hóa đơn giữ từ API
    const response = await axios.get(
        `/api/hold-sales/${holdSaleId}`
    );

    // Dữ liệu hóa đơn giữ
    const holdSale =
        response.data.data;

    // Gán sản phẩm vào giỏ hàng
    cart.value = holdSale.cart_items;

    // Nếu hóa đơn giữ có khách hàng, gán vào selectedCustomer
    selectedCustomer.value = holdSale.customer;

    selectedCartIndex.value = 0

    // Xóa hóa đơn giữ sau khi tải vào giỏ hàng
    await axios.delete(
        `/api/hold-sales/${holdSaleId}`
    );

    // Tải lại danh sách hóa đơn giữ
    await fetchHoldSales();

    // Đóng modal hóa đơn giữ
    showHoldModal.value =
        false;
};

// Xử lý phím tắt
const handleKeydown = (event) => {

    // Nếu đang nhập liệu trong input, textarea hoặc element có contenteditable thì không xử lý phím tắt
    const tagName =
        event.target.tagName;

    const isTyping =

        tagName === 'INPUT' ||

        tagName === 'TEXTAREA' ||

        event.target.isContentEditable;

    /*
    |--------------------------------------------------------------------------
    | Nếu đang nhập liệu
    |--------------------------------------------------------------------------
    */

    if (isTyping) {

        /*
        |--------------------------------------------------------------------------
        | Chỉ chặn shortcut POS
        |--------------------------------------------------------------------------
        */

        return;
    }

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

/*
|--------------------------------------------------------------------------
| FIX: sync selectedCartIndex với cart
|--------------------------------------------------------------------------
*/
watch(cart, () => {

    if (selectedCartIndex.value > cart.value.length - 1) {

        selectedCartIndex.value = Math.max(
            0,
            cart.value.length - 1
        )
    }

}, { deep: true })


// thêm sự kiện lắng nghe phím tắt khi component được mount
onMounted(() => {

    watch(cart, () => {
        fetchHoldSales()
    }, { deep: true })

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
                    @selected="onCustomerSelected"
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

                    <div class="space-y-2">

                        <button
                            type="button"
                            @click="openHoldModal"
                            class="px-4 py-2 bg-yellow-500 text-white py-3 rounded"
                        >
                            Lưu tạm hóa đơn
                        </button>

                        <!-- Chỉ hiển thị nút load hold khi có hóa đơn giữ -->
                        <button
                            v-if="holdSales.length > 0"
                            @click="showHoldModal = true"
                            class="px-4 py-2 bg-blue-500 text-white py-3 rounded"
                        >
                            Có: ({{ holdSales.length }}) hóa đơn
                        </button>

                        <SummarySection
                            :items="cart"
                            @checkout="checkout"
                        />

                    </div>
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

    <div
        v-if="showHoldModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
        <div class="bg-white w-[500px] rounded p-4">

            <div class="flex justify-between mb-4">

                <h2 class="text-lg font-bold">
                    Hóa đơn giữ
                </h2>

                <button
                    @click="showHoldModal = false"
                >
                    X
                </button>
            </div>

            <div
                v-for="hold in holdSales"
                :key="hold.id"
                class="border rounded p-3 mb-2"
            >
                <div class="font-bold">
                    {{ hold.name }}
                </div>

                <div>
                    {{ hold.grand_total }}
                </div>

                <button
                    @click="loadHoldSale(hold.id)"
                    class="mt-2 px-3 py-1 bg-blue-500 text-white rounded"
                >
                    Mở lại
                </button>
            </div>
        </div>
    </div>


    <!-- Modal lưu tạm hóa đơn -->
<div
    v-if="showSaveHoldModal"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
>
    <div class="bg-white w-[400px] rounded-xl p-5 shadow-xl">

        <div class="text-lg font-bold mb-4">
            Lưu tạm hóa đơn
        </div>

        <div>

            <label class="block mb-2 text-sm font-medium">
                Tên hóa đơn
            </label>

            <input
                v-model="holdName"
                type="text"
                class="w-full border rounded-lg px-3 py-2"
                placeholder="Nhập tên hóa đơn"
            />
        </div>

        <div class="flex justify-end gap-2 mt-5">

            <button
                @click="showSaveHoldModal = false"
                class="px-4 py-2 border rounded-lg"
            >
                Hủy
            </button>

            <button
                @click="holdBill"
                class="px-4 py-2 bg-yellow-500 text-white rounded-lg"
            >
                Lưu tạm
            </button>

        </div>

    </div>
</div>

</template>
