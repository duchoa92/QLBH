<script setup>

import AdminLayout from '@/Layouts/AdminLayout.vue'
import StockImportFormModal from './StockImportFormModal.vue'
import api from '@/Services/api'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'

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
const detailImport = ref(null)
const loadingDetail = ref(false)


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
    loadingDetail.value = true

    api.get(`/stock-import/${id}`)
        .then((response) => {
            detailImport.value = response.data
        })
        .finally(() => {
            loadingDetail.value = false
        })

}

</script>


<template>

<div class="min-h-screen bg-slate-100">

    <!-- HEADER -->

    <!-- CONTENT -->

    <div class="space-y-4 p-6">
        <PageHeader
            title="Đơn nhập hàng"
            description="Quản lý các phiếu nhập kho"
        >
            <template #actions>
                <ActionButton @click="openCreateModal">
                    <Plus :size="18" />
                    Tạo đơn nhập
                </ActionButton>
            </template>
        </PageHeader>

        <DataPanel>

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

        </DataPanel>

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

    <BaseModal
        v-if="detailImport"
        title="Chi tiết đơn nhập"
        size="xl"
        @close="detailImport = null"
    >
        <div class="space-y-4">
            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Mã đơn</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ detailImport.code }}</div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Nhà cung cấp</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ detailImport.supplier?.name || '-' }}</div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Ngày nhập</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ formatDate(detailImport.import_date) }}</div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Tổng tiền</div>
                    <div class="mt-1 font-semibold text-emerald-600">{{ money(detailImport.grand_total) }} đ</div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <th class="px-3 py-2 text-left">Sản phẩm</th>
                            <th class="px-3 py-2 text-left">Biến thể</th>
                            <th class="px-3 py-2 text-center">Đơn vị</th>
                            <th class="px-3 py-2 text-center">Số lượng</th>
                            <th class="px-3 py-2 text-right">Giá nhập</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in detailImport.items"
                            :key="item.id"
                        >
                            <td class="px-3 py-2 font-medium text-slate-900">{{ item.product?.name || '-' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ item.variant?.sku || '-' }}</td>
                            <td class="px-3 py-2 text-center">{{ item.unit_name || item.unit?.short_name || '-' }}</td>
                            <td class="px-3 py-2 text-center font-semibold">{{ item.quantity }}</td>
                            <td class="px-3 py-2 text-right">{{ money(item.cost_price) }} đ</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="detailImport.note"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
            >
                {{ detailImport.note }}
            </div>
        </div>
    </BaseModal>

    <div
        v-if="loadingDetail"
        class="fixed bottom-4 right-4 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-lg"
    >
        Đang tải chi tiết...
    </div>

</div>

</template>
