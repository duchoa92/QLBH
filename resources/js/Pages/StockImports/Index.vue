<script setup>

import AdminLayout from '@/Layouts/AdminLayout.vue'
import Form from './StockImportProductModal.vue'
import {ref, computed,} from 'vue'
import {useForm,} from '@inertiajs/vue3'
import SupplierSection from './SupplierSection.vue'
import { toast } from 'vue-sonner'

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
    import_date: new Date()
        .toISOString()
        .slice(0, 10),
    items: [],
    discount: 0,
    extra_fee: 0,
    note: '',
})

const selectedSupplier = ref(null)
const selectSupplier = (supplier) => {

    selectedSupplier.value =
        supplier

    form.supplier_id =
        supplier?.id || null

    form.clearErrors(
        'supplier_id'
    )
}

/*
|--------------------------------------------------------------------------
| NHẬN SẢN PHẨM TỪ FORM
|--------------------------------------------------------------------------
*/

const addItems = (selectedItems) => {

    selectedItems.forEach(newItem => {

        const exist = form.items.find(item =>
            item.product_id === newItem.product_id &&
            (item.variant_id ?? null) === (newItem.variant_id ?? null)
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
                product_id: newItem.product_id,
                variant_id: newItem.variant_id ?? null,
                imeis: newItem.imeis || [],
                quantity: Number(newItem.quantity || 1),
                cost_price: Number(newItem.cost_price || 0),
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
| LƯU & VALIDATE
|--------------------------------------------------------------------------
*/
const submit = () => {
    let hasError = false

    if (!form.supplier_id) {
        form.setError('supplier_id', 'Vui lòng chọn nhà cung cấp.')
        toast.error('Vui lòng chọn nhà cung cấp')
        hasError = true
    }

    if (!form.items.length) {
        form.setError('items', 'Vui lòng chọn ít nhất một sản phẩm')
        toast.error('Chưa có sản phẩm nhập')
        hasError = true
    }

    if (hasError) return

    form.post('/stock-import', {
        preserveScroll: true,

        onSuccess: () => {
            toast.success('Tạo đơn nhập thành công')
        },

        onError: () => {
            // backend validate sẽ tự toast rồi
        }
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

                <!-- PRODUCT CARD HEADER -->
                <div 
                    data-error="items"
                    class="overflow-hidden rounded-xl border bg-white shadow-sm transition-colors"
                    :class="form.errors.items ? 'border-red-400' : 'border-slate-200'"
                >
                    <!-- THÔNG BÁO LỖI NẾU CHƯA CÓ MẶT HÀNG -->
                    <div v-if="form.errors.items" class="bg-red-50 px-5 py-2.5 border-b border-red-100 text-xs font-semibold text-red-600">
                        {{ form.errors.items }}
                    </div>

                    <!-- TABLE SẢN PHẨM HOẶC EMPTY STATE -->
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
                                    <td class="px-3 py-4 text-right font-medium text-slate-700">
                                        {{ money(item.cost_price) }}
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
                                                Number(item.quantity || 0) *
                                                Number(item.cost_price || 0)
                                            )
                                        }}
                                    </td>
                                    <!-- REMOVE -->
                                    <td class="px-3 py-4 text-center"
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
           <SupplierSection
                :supplier="selectedSupplier"
                :error="form.errors.supplier_id"
                @selected="selectSupplier"
            />


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

                    <!-- DISCOUNT INPUT -->
                    <div class="mt-4">
                        <div class="mb-1.5 flex justify-between text-sm">
                            <span class="text-slate-500">Chiết khấu</span>
                        </div>
                        <div class="relative">
                            <input
                                v-model.number="form.discount"
                                name="discount"
                                type="number"
                                min="0"
                                @input="form.clearErrors('discount')"
                                class="w-full rounded-lg border py-2 pl-3 pr-10 text-right text-sm outline-none transition"
                                :class="form.errors.discount ? 'border-red-500 bg-red-50/30' : 'border-slate-200 focus:border-emerald-500'"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">đ</span>
                        </div>
                        <!-- DÒNG BÁO LỖI ĐỎ -->
                        <p v-if="form.errors.discount" class="mt-1 text-xs text-red-600 font-medium">
                            {{ form.errors.discount }}
                        </p>
                    </div>

                    <!-- EXTRA FEE INPUT -->
                    <div class="mt-4">
                        <div class="mb-1.5 text-sm text-slate-500">Phụ phí</div>
                        <div class="relative">
                            <input
                                v-model.number="form.extra_fee"
                                name="extra_fee"
                                type="number"
                                min="0"
                                @input="form.clearErrors('extra_fee')"
                                class="w-full rounded-lg border py-2 pl-3 pr-10 text-right text-sm outline-none transition"
                                :class="form.errors.extra_fee ? 'border-red-500 bg-red-50/30' : 'border-slate-200 focus:border-emerald-500'"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">đ</span>
                        </div>
                        <!-- DÒNG BÁO LỖI ĐỎ -->
                        <p v-if="form.errors.extra_fee" class="mt-1 text-xs text-red-600 font-medium">
                            {{ form.errors.extra_fee }}
                        </p>
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
