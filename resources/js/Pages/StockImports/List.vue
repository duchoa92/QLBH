<script setup>

import AdminLayout from '@/Layouts/AdminLayout.vue'
import StockImportFormModal from './StockImportFormModal.vue'

import {
    ref,
} from 'vue'

import {
    router,
} from '@inertiajs/vue3'

import {
    Plus,
    Search,
    Truck,
    Eye,
} from 'lucide-vue-next'


defineOptions({
    layout: AdminLayout,
})


const props = defineProps({

    imports: {
        type: Object,
        required: true,
    },

    categories: {
        type: Array,
        default: () => [],
    },

    suppliers: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: '',
        }),
    },

})


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

const showCreateModal = ref(false)


const openCreateModal = () => {
    showCreateModal.value = true
}


const closeCreateModal = () => {
    showCreateModal.value = false
}


const handleCreated = () => {

    showCreateModal.value = false

    router.reload({
        only: ['imports'],
    })

}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

const search = ref(
    props.filters.search || ''
)


const submitSearch = () => {

    router.get(
        '/stock-import',
        {
            search: search.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )

}


/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/

const money = (value) => {

    return Number(value || 0)
        .toLocaleString('vi-VN')

}


const formatDate = (value) => {

    if (!value) {
        return ''
    }

    return new Date(value)
        .toLocaleDateString('vi-VN')

}


/*
|--------------------------------------------------------------------------
| DETAIL
|--------------------------------------------------------------------------
*/

const showImport = (id) => {

    router.get(
        `/stock-import/${id}`
    )

}

</script>


<template>

<div class="min-h-screen bg-slate-100">

    <!-- HEADER -->

    <div class="border-b border-slate-200 bg-white">

        <div
            class="
                flex
                items-center
                justify-between
                px-6
                py-4
            "
        >

            <div class="flex items-center gap-3">

                <div
                    class="
                        flex
                        h-10
                        w-10
                        items-center
                        justify-center
                        rounded-lg
                        bg-emerald-50
                        text-emerald-600
                    "
                >

                    <Truck :size="21" />

                </div>


                <div>

                    <h1
                        class="
                            text-lg
                            font-bold
                            text-slate-900
                        "
                    >
                        Đơn nhập hàng
                    </h1>

                    <div
                        class="
                            mt-0.5
                            text-xs
                            text-slate-500
                        "
                    >
                        Quản lý các phiếu nhập kho
                    </div>

                </div>

            </div>


            <button
                type="button"
                @click="openCreateModal"
                class="
                    flex
                    items-center
                    gap-2
                    rounded-lg
                    bg-emerald-600
                    px-4
                    py-2.5
                    text-sm
                    font-semibold
                    text-white
                    shadow-sm
                    transition
                    hover:bg-emerald-700
                "
            >

                <Plus :size="18" />

                Tạo đơn nhập

            </button>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="p-6">

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

            <!-- SEARCH -->

            <div
                class="
                    flex
                    items-center
                    justify-between
                    gap-4
                    border-b
                    border-slate-200
                    p-4
                "
            >

                <div class="relative w-full max-w-md">

                    <Search
                        :size="18"
                        class="
                            absolute
                            left-3
                            top-1/2
                            -translate-y-1/2
                            text-slate-400
                        "
                    />

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm mã đơn, nhà cung cấp, số điện thoại..."
                        @keyup.enter="submitSearch"
                        class="
                            w-full
                            rounded-lg
                            border
                            border-slate-200
                            py-2.5
                            pl-10
                            pr-3
                            text-sm
                            outline-none
                            focus:border-emerald-500
                            focus:ring-2
                            focus:ring-emerald-100
                        "
                    />

                </div>

            </div>


            <!-- TABLE -->

            <div class="overflow-x-auto">

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

                            <th class="px-5 py-3 text-left">
                                Mã đơn
                            </th>

                            <th class="px-3 py-3 text-left">
                                Nhà cung cấp
                            </th>

                            <th class="px-3 py-3 text-center">
                                Ngày nhập
                            </th>

                            <th class="px-3 py-3 text-center">
                                Số dòng
                            </th>

                            <th class="px-3 py-3 text-right">
                                Tổng tiền
                            </th>

                            <th class="px-3 py-3 text-center">
                                Trạng thái
                            </th>

                            <th class="w-20 px-3 py-3">
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <tr
                            v-for="item in props.imports.data"
                            :key="item.id"
                            class="
                                border-t
                                border-slate-100
                                hover:bg-slate-50
                            "
                        >

                            <!-- CODE -->

                            <td class="px-5 py-4">

                                <div
                                    class="
                                        font-semibold
                                        text-slate-800
                                    "
                                >
                                    {{ item.code }}
                                </div>

                            </td>


                            <!-- SUPPLIER -->

                            <td class="px-3 py-4">

                                <div
                                    class="
                                        font-medium
                                        text-slate-800
                                    "
                                >
                                    {{ item.supplier?.name || '—' }}
                                </div>

                                <div
                                    v-if="item.supplier?.phone"
                                    class="
                                        mt-0.5
                                        text-xs
                                        text-slate-500
                                    "
                                >
                                    {{ item.supplier.phone }}
                                </div>

                            </td>


                            <!-- DATE -->

                            <td
                                class="
                                    px-3
                                    py-4
                                    text-center
                                    text-slate-600
                                "
                            >
                                {{ formatDate(item.import_date) }}
                            </td>


                            <!-- ITEMS -->

                            <td
                                class="
                                    px-3
                                    py-4
                                    text-center
                                "
                            >
                                {{ item.items_count }}
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
                                {{ money(item.grand_total) }}
                                đ
                            </td>


                            <!-- STATUS -->

                            <td class="px-3 py-4 text-center">

                                <span
                                    class="
                                        inline-flex
                                        rounded-full
                                        bg-emerald-50
                                        px-2.5
                                        py-1
                                        text-xs
                                        font-semibold
                                        text-emerald-600
                                    "
                                >
                                    {{ item.status || 'Hoàn thành' }}
                                </span>

                            </td>


                            <!-- ACTION -->

                            <td class="px-3 py-4 text-center">

                                <button
                                    type="button"
                                    title="Xem chi tiết"
                                    @click="showImport(item.id)"
                                    class="
                                        inline-flex
                                        h-8
                                        w-8
                                        items-center
                                        justify-center
                                        rounded-lg
                                        text-slate-400
                                        hover:bg-slate-100
                                        hover:text-slate-700
                                    "
                                >

                                    <Eye :size="17" />

                                </button>

                            </td>

                        </tr>


                        <!-- EMPTY -->

                        <tr
                            v-if="!props.imports.data?.length"
                        >

                            <td
                                colspan="7"
                                class="
                                    px-5
                                    py-16
                                    text-center
                                    text-slate-400
                                "
                            >
                                Chưa có đơn nhập hàng
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- ========================================================= -->
    <!-- CREATE MODAL -->
    <!-- ========================================================= -->

    <StockImportFormModal
        v-if="showCreateModal"
        :categories="props.categories"
        :suppliers="props.suppliers"
        @close="closeCreateModal"
        @created="handleCreated"
    />

</div>

</template>