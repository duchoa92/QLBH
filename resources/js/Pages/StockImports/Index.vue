<script setup>

import AdminLayout from '@/Layouts/AdminLayout.vue'
import Form from './Form.vue'
import {ref, computed,} from 'vue'
import {useForm,} from '@inertiajs/vue3'

import {
    Save,
    ShoppingCart,
    Plus,
    Trash2,
    Truck,
    ReceiptText,
    Calculator,
    PackageOpen,
    UserRound,
    ChevronRight,
} from 'lucide-vue-next'


defineOptions({
    layout: AdminLayout,
})


const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },

    suppliers: {
        type: Array,
        default: () => [],
    },
})
/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/





const showForm = ref(false)


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

const form = useForm({
    supplier_id: null,
    items: [],
    discount: 0,
    extra_fee: 0,
    note: '',

})


/*
|--------------------------------------------------------------------------
| NHẬN SẢN PHẨM TỪ FORM
|--------------------------------------------------------------------------
*/

const addItems = (selectedItems) => {

    selectedItems.forEach(newItem => {

        const exist = form.items.find(item =>
            item.product_id === newItem.product_id &&
            item.variant_id === newItem.variant_id
        )


        if (exist) {

            /*
            |--------------------------------------------------------------------------
            | IMEI
            |--------------------------------------------------------------------------
            */

            if (newItem.imeis?.length) {

                const oldImeis =
                    exist.imeis || []

                const newImeis =
                    newItem.imeis.filter(
                        imei =>
                            !oldImeis.includes(imei)
                    )

                exist.imeis = [
                    ...oldImeis,
                    ...newImeis,
                ]

                exist.quantity =
                    exist.imeis.length

            } else {

                exist.quantity +=
                    Number(
                        newItem.quantity || 0
                    )

            }

        } else {

            form.items.push({

                ...newItem,

                imeis:
                    newItem.imeis || [],

                quantity:
                    Number(
                        newItem.quantity || 1
                    ),

                cost_price:
                    Number(
                        newItem.cost_price || 0
                    ),

            })

        }

    })


    showForm.value = false

}


/*
|--------------------------------------------------------------------------
| XÓA SẢN PHẨM
|--------------------------------------------------------------------------
*/

const removeItem = (index) => {
    form.items.splice(index, 1)

}


/*
|--------------------------------------------------------------------------
| TỔNG TIỀN HÀNG
|--------------------------------------------------------------------------
*/

const total = computed(() => {

    return form.items.reduce(
        (sum, item) => {

            return sum +
                Number(
                    item.quantity || 0
                ) *
                Number(
                    item.cost_price || 0
                )

        },
        0
    )

})


/*
|--------------------------------------------------------------------------
| TỔNG THANH TOÁN
|--------------------------------------------------------------------------
*/

const grandTotal = computed(() => {

    return Math.max(
        0,
        total.value -
        Number(form.discount || 0) +
        Number(form.extra_fee || 0)
    )

})


/*
|--------------------------------------------------------------------------
| FORMAT TIỀN
|--------------------------------------------------------------------------
*/

const money = (value) => {
    return Number(value || 0)
        .toLocaleString('vi-VN')
}


/*
|--------------------------------------------------------------------------
| LƯU
|--------------------------------------------------------------------------
*/

const submit = () => {
    form.post('/stock-imports', {
        preserveScroll: true,
    })
}

</script>


<template>

<div class="min-h-screen bg-slate-100">

    <!-- ========================================================= -->
    <!-- HEADER -->
    <!-- ========================================================= -->

    <div class="border-b border-slate-200 bg-white">
        <div class="flex items-center justify-between px-6 py-4">
            <!-- LEFT -->

            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <Truck
                            :size="21"
                        />
                    </div>

                    <div>
                        <h1 class=" text-lg font-bold text-slate-900">
                            Thêm đơn nhập hàng
                        </h1>

                        <div class="mt-0.5 text-xs text-slate-500" >
                            Tạo phiếu nhập kho mới
                        </div>
                    </div>
                </div>
            </div>


            <!-- RIGHT -->

            <div class="flex items-center gap-4">

                <div class="text-right">

                    <div
                        class="
                            text-xs
                            text-slate-500
                        "
                    >
                        Tổng thanh toán
                    </div>

                    <div
                        class="
                            text-xl
                            font-bold
                            text-emerald-600
                        "
                    >
                        {{ money(grandTotal) }}
                    </div>

                </div>


                <button
                    type="button"
                    :disabled="
                        form.processing ||
                        !form.items.length
                    "
                    @click="submit"
                    class="
                        flex
                        items-center
                        gap-2
                        rounded-lg
                        bg-emerald-600
                        px-5
                        py-2.5
                        text-sm
                        font-semibold
                        text-white
                        shadow-sm
                        transition
                        hover:bg-emerald-700
                        disabled:cursor-not-allowed
                        disabled:opacity-50
                    "
                >

                    <Save :size="18" />

                    {{
                        form.processing
                            ? 'Đang lưu...'
                            : 'Lưu đơn nhập'
                    }}

                </button>

            </div>

        </div>

    </div>


    <!-- ========================================================= -->
    <!-- CONTENT -->
    <!-- ========================================================= -->

    <div class="p-6">

        <div
            class="
                grid
                grid-cols-1
                gap-5
                xl:grid-cols-3
            "
        >


            <!-- ================================================= -->
            <!-- LEFT - PRODUCTS -->
            <!-- ================================================= -->

            <div class="space-y-5 xl:col-span-2">


                <!-- PRODUCT CARD -->

                <div
                    class="
                        overflow-hidden
                        rounded-xl
                        border
                        border-slate-200
                        bg-white
                        shadow-sm
                    "
                >

                    <!-- CARD HEADER -->

                    <div
                        class="
                            flex
                            items-center
                            justify-between
                            border-b
                            border-slate-200
                            px-5
                            py-4
                        "
                    >

                        <div class="flex items-center gap-3">

                            <div
                                class="
                                    flex
                                    h-9
                                    w-9
                                    items-center
                                    justify-center
                                    rounded-lg
                                    bg-slate-100
                                    text-slate-700
                                "
                            >

                                <ShoppingCart
                                    :size="19"
                                />

                            </div>


                            <div>

                                <div
                                    class="
                                        font-semibold
                                        text-slate-900
                                    "
                                >
                                    Hàng hóa
                                </div>

                                <div
                                    class="
                                        text-xs
                                        text-slate-500
                                    "
                                >
                                    {{ form.items.length }}
                                    sản phẩm
                                </div>

                            </div>

                        </div>


                        <button
                            type="button"
                            @click="showForm = true"
                            class="
                                flex
                                items-center
                                gap-2
                                rounded-lg
                                border
                                border-emerald-600
                                px-3
                                py-2
                                text-sm
                                font-semibold
                                text-emerald-600
                                transition
                                hover:bg-emerald-50
                            "
                        >

                            <Plus :size="17" />

                            Chọn hàng hóa

                        </button>

                    </div>


                    <!-- EMPTY -->

                    <div
                        v-if="!form.items.length"
                        class="
                            flex
                            min-h-[300px]
                            flex-col
                            items-center
                            justify-center
                            px-5
                            text-center
                        "
                    >

                        <div
                            class="
                                mb-4
                                flex
                                h-16
                                w-16
                                items-center
                                justify-center
                                rounded-full
                                bg-slate-100
                                text-slate-400
                            "
                        >

                            <PackageOpen
                                :size="30"
                            />

                        </div>


                        <div
                            class="
                                font-medium
                                text-slate-700
                            "
                        >
                            Chưa có hàng hóa
                        </div>


                        <div
                            class="
                                mt-1
                                text-sm
                                text-slate-400
                            "
                        >
                            Nhấn "Chọn hàng hóa" để thêm sản phẩm
                        </div>


                        <button
                            type="button"
                            @click="showForm = true"
                            class="
                                mt-4
                                flex
                                items-center
                                gap-2
                                rounded-lg
                                bg-emerald-600
                                px-4
                                py-2
                                text-sm
                                font-semibold
                                text-white
                                hover:bg-emerald-700
                            "
                        >

                            <Plus :size="17" />

                            Chọn hàng hóa

                        </button>

                    </div>


                    <!-- TABLE -->

                    <div
                        v-else
                        class="overflow-x-auto"
                    >

                        <table class="w-full text-sm">

                            <thead
                                class="
                                    bg-slate-50
                                    text-xs
                                    font-semibold
                                    uppercase
                                    text-slate-500
                                "
                            >

                                <tr>

                                    <th
                                        class="
                                            px-5
                                            py-3
                                            text-left
                                        "
                                    >
                                        Hàng hóa
                                    </th>

                                    <th
                                        class="
                                            px-3
                                            py-3
                                            text-center
                                        "
                                    >
                                        SL
                                    </th>

                                    <th
                                        class="
                                            px-3
                                            py-3
                                            text-right
                                        "
                                    >
                                        Đơn giá
                                    </th>

                                    <th
                                        class="
                                            px-3
                                            py-3
                                            text-right
                                        "
                                    >
                                        Thành tiền
                                    </th>

                                    <th
                                        class="
                                            w-12
                                            px-3
                                            py-3
                                        "
                                    >
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <tr
                                    v-for="(
                                        item,
                                        index
                                    ) in form.items"
                                    :key="
                                        item.variant_id ||
                                        index
                                    "
                                    class="
                                        border-t
                                        border-slate-100
                                        transition
                                        hover:bg-slate-50
                                    "
                                >

                                    <!-- PRODUCT -->

                                    <td class="px-5 py-4">

                                        <div
                                            class="
                                                flex
                                                items-center
                                                gap-3
                                            "
                                        >

                                            <img
                                                v-if="
                                                    item.image
                                                "
                                                :src="
                                                    item.image
                                                "
                                                class="
                                                    h-12
                                                    w-12
                                                    shrink-0
                                                    rounded-lg
                                                    border
                                                    border-slate-200
                                                    object-cover
                                                "
                                            />

                                            <div
                                                v-else
                                                class="
                                                    flex
                                                    h-12
                                                    w-12
                                                    shrink-0
                                                    items-center
                                                    justify-center
                                                    rounded-lg
                                                    bg-slate-100
                                                    text-slate-400
                                                "
                                            >

                                                <PackageOpen
                                                    :size="20"
                                                />

                                            </div>


                                            <div class="min-w-0">

                                                <div
                                                    class="
                                                        truncate
                                                        font-semibold
                                                        text-slate-800
                                                    "
                                                >
                                                    {{ item.name }}
                                                </div>


                                                <div
                                                    v-if="
                                                        item.variant
                                                    "
                                                    class="
                                                        mt-0.5
                                                        text-xs
                                                        text-slate-500
                                                    "
                                                >
                                                    {{ item.variant }}
                                                </div>


                                                <div
                                                    v-if="
                                                        item.manage_imei &&
                                                        item.imeis?.length
                                                    "
                                                    class="
                                                        mt-1
                                                        text-xs
                                                        text-blue-600
                                                    "
                                                >

                                                    {{
                                                        item.imeis.length
                                                    }}
                                                    IMEI

                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- QTY -->

                                    <td
                                        class="
                                            px-3
                                            py-4
                                            text-center
                                        "
                                    >

                                        <span
                                            class="
                                                inline-flex
                                                min-w-10
                                                justify-center
                                                rounded-md
                                                bg-slate-100
                                                px-2
                                                py-1
                                                font-medium
                                                text-slate-700
                                            "
                                        >
                                            {{ item.quantity }}
                                        </span>

                                    </td>


                                    <!-- PRICE -->

                                    <td
                                        class="
                                            px-3
                                            py-4
                                            text-right
                                            font-medium
                                            text-slate-700
                                        "
                                    >

                                        {{ money(item.price) }}

                                    </td>


                                    <!-- TOTAL -->

                                    <td
                                        class="
                                            px-3
                                            py-4
                                            text-right
                                            font-semibold
                                            text-slate-900
                                        "
                                    >

                                        {{
                                            money(
                                                Number(item.qty || 0) *
                                                Number(item.price || 0)
                                            )
                                        }}

                                    </td>


                                    <!-- REMOVE -->

                                    <td
                                        class="
                                            px-3
                                            py-4
                                            text-center
                                        "
                                    >

                                        <button
                                            type="button"
                                            title="Xóa"
                                            @click="
                                                removeItem(index)
                                            "
                                            class="
                                                inline-flex
                                                h-8
                                                w-8
                                                items-center
                                                justify-center
                                                rounded-lg
                                                text-slate-400
                                                transition
                                                hover:bg-red-50
                                                hover:text-red-600
                                            "
                                        >

                                            <Trash2
                                                :size="17"
                                            />

                                        </button>

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>


                <!-- NOTE -->

                <div
                    class="
                        rounded-xl
                        border
                        border-slate-200
                        bg-white
                        p-5
                        shadow-sm
                    "
                >

                    <div
                        class="
                            mb-3
                            flex
                            items-center
                            gap-2
                            font-semibold
                            text-slate-800
                        "
                    >

                        <ReceiptText
                            :size="18"
                        />

                        Ghi chú

                    </div>


                    <textarea
                        v-model="form.note"
                        rows="3"
                        placeholder="Nhập ghi chú cho đơn nhập..."
                        class="
                            w-full
                            resize-none
                            rounded-lg
                            border
                            border-slate-200
                            px-3
                            py-2
                            text-sm
                            outline-none
                            transition
                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-100
                        "
                    ></textarea>

                </div>

            </div>


            <!-- ================================================= -->
            <!-- RIGHT -->
            <!-- ================================================= -->

            <div class="space-y-5">


                <!-- SUPPLIER -->

                <div
                    class="
                        rounded-xl
                        border
                        border-slate-200
                        bg-white
                        p-5
                        shadow-sm
                    "
                >

                    <div
                        class="
                            mb-4
                            flex
                            items-center
                            justify-between
                        "
                    >

                        <div class="flex items-center gap-2">

                            <UserRound
                                :size="18"
                                class="text-slate-600"
                            />

                            <span
                                class="
                                    font-semibold
                                    text-slate-800
                                "
                            >
                                Nhà cung cấp
                            </span>

                        </div>


                        <button
                            type="button"
                            class="
                                flex
                                items-center
                                gap-1
                                text-sm
                                font-medium
                                text-emerald-600
                                hover:text-emerald-700
                            "
                        >

                            Thay đổi

                            <ChevronRight
                                :size="15"
                            />

                        </button>

                    </div>


                    <div
                        v-if="form.supplier_id"
                        class="
                            rounded-lg
                            bg-slate-50
                            p-3
                        "
                    >
                        Nhà cung cấp đã chọn
                    </div>


                    <div
                        v-else
                        class="
                            rounded-lg
                            border
                            border-dashed
                            border-slate-300
                            bg-slate-50
                            p-5
                            text-center
                        "
                    >

                        <UserRound
                            :size="25"
                            class="
                                mx-auto
                                text-slate-300
                            "
                        />

                        <div
                            class="
                                mt-2
                                text-sm
                                text-slate-500
                            "
                        >
                            Chưa chọn nhà cung cấp
                        </div>

                    </div>

                </div>


                <!-- PAYMENT -->

                <div
                    class="
                        rounded-xl
                        border
                        border-slate-200
                        bg-white
                        p-5
                        shadow-sm
                    "
                >

                    <div
                        class="
                            mb-5
                            flex
                            items-center
                            gap-2
                            font-semibold
                            text-slate-800
                        "
                    >

                        <Calculator
                            :size="18"
                        />

                        Chi tiết thanh toán

                    </div>


                    <!-- COUNT -->

                    <div
                        class="
                            flex
                            justify-between
                            text-sm
                        "
                    >

                        <span class="text-slate-500">
                            Số dòng hàng
                        </span>

                        <span class="font-medium">
                            {{ form.items.length }}
                        </span>

                    </div>


                    <!-- SUBTOTAL -->

                    <div
                        class="
                            mt-4
                            flex
                            justify-between
                            text-sm
                        "
                    >

                        <span class="text-slate-500">
                            Tiền hàng
                        </span>

                        <span class="font-medium">
                            {{ money(total) }}
                        </span>

                    </div>


                    <!-- DISCOUNT -->

                    <div class="mt-4">

                        <div
                            class="
                                mb-1.5
                                flex
                                justify-between
                                text-sm
                            "
                        >

                            <span class="text-slate-500">
                                Chiết khấu
                            </span>

                        </div>


                        <div class="relative">

                            <input
                                v-model.number="
                                    form.discount
                                "
                                type="number"
                                min="0"
                                class="
                                    w-full
                                    rounded-lg
                                    border
                                    border-slate-200
                                    py-2
                                    pl-3
                                    pr-10
                                    text-right
                                    text-sm
                                    outline-none
                                    focus:border-emerald-500
                                    focus:ring-2
                                    focus:ring-emerald-100
                                "
                            />

                            <span
                                class="
                                    absolute
                                    right-3
                                    top-1/2
                                    -translate-y-1/2
                                    text-xs
                                    text-slate-400
                                "
                            >
                                đ
                            </span>

                        </div>

                    </div>


                    <!-- EXTRA -->

                    <div class="mt-4">

                        <div
                            class="
                                mb-1.5
                                text-sm
                                text-slate-500
                            "
                        >
                            Phụ phí
                        </div>


                        <div class="relative">

                            <input
                                v-model.number="
                                    form.extra_fee
                                "
                                type="number"
                                min="0"
                                class="
                                    w-full
                                    rounded-lg
                                    border
                                    border-slate-200
                                    py-2
                                    pl-3
                                    pr-10
                                    text-right
                                    text-sm
                                    outline-none
                                    focus:border-emerald-500
                                    focus:ring-2
                                    focus:ring-emerald-100
                                "
                            />

                            <span
                                class="
                                    absolute
                                    right-3
                                    top-1/2
                                    -translate-y-1/2
                                    text-xs
                                    text-slate-400
                                "
                            >
                                đ
                            </span>

                        </div>

                    </div>


                    <div
                        class="
                            my-5
                            border-t
                            border-slate-200
                        "
                    ></div>


                    <!-- GRAND TOTAL -->

                    <div
                        class="
                            flex
                            items-end
                            justify-between
                        "
                    >

                        <div>

                            <div
                                class="
                                    text-sm
                                    text-slate-500
                                "
                            >
                                Tổng thanh toán
                            </div>

                            <div
                                class="
                                    mt-1
                                    text-2xl
                                    font-bold
                                    text-emerald-600
                                "
                            >
                                {{ money(grandTotal) }}
                                <span
                                    class="
                                        text-sm
                                        font-medium
                                    "
                                >
                                    đ
                                </span>
                            </div>

                        </div>

                    </div>


                    <!-- SAVE -->

                    <button
                        type="button"
                        :disabled="
                            form.processing ||
                            !form.items.length
                        "
                        @click="submit"
                        class="
                            mt-5
                            flex
                            w-full
                            items-center
                            justify-center
                            gap-2
                            rounded-lg
                            bg-emerald-600
                            px-4
                            py-3
                            text-sm
                            font-bold
                            text-white
                            shadow-sm
                            transition
                            hover:bg-emerald-700
                            disabled:cursor-not-allowed
                            disabled:opacity-50
                        "
                    >

                        <Save :size="18" />

                        {{
                            form.processing
                                ? 'Đang lưu...'
                                : 'Lưu đơn nhập'
                        }}

                    </button>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================================================= -->
    <!-- FORM CHỌN HÀNG -->
    <!-- ========================================================= -->

    <Form
        v-if="showForm"
        :categories="props.categories"
        @close="showForm = false"
        @select="addItems"
    />

</div>

</template>