<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import {
    Search,
    Eye,
    Printer,
    FileText,
    DollarSign,
    CreditCard,
    ShoppingBag,
    Calendar,
    Gift,
    User,
    Clock,
    Tag
} from 'lucide-vue-next'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatDateTime, formatMoney } from '@/utils/format'

defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    sales: Object,
    filters: Object,
    stats: {
        type: Object,
        default: () => ({
            totalRevenue: 0,
            totalOrders: 0,
            totalDebt: 0
        })
    }
})

// Trạng thái tìm kiếm & Modal
const search = ref(props.filters?.search ?? '')
const detailSale = ref(null)
let searchTimeout = null

watch(search, (value) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(
            route('sales.index'),
            { search: value },
            { preserveState: true, replace: true }
        )
    }, 250)
})

// Tính tổng tiền hóa đơn
const saleTotal = (sale) =>
    sale.grand_total ?? sale.total_amount ?? sale.subtotal ?? 0

// Định dạng hiển thị số lượng
const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Cái'
    return `${Number.isInteger(qty) ? qty : qty.toLocaleString('vi-VN')} ${unit}`
}

// Xử lý lấy tên khách hàng chính xác
const getCustomerName = (sale) => {
    if (!sale?.customer) return 'Khách lẻ'
    return sale.customer.full_name || sale.customer.name || 'Khách lẻ'
}

// Mở cửa sổ in hóa đơn
const openPrintWindow = (saleId) => {
    window.open(route('sales.receipt', saleId), '_blank', 'width=400,height=600')
}
</script>

<template>
    <div class="space-y-5 p-6">
        <!-- HEADER TRANG -->
        <PageHeader
            title="Quản lý Hóa đơn & Bán hàng"
            description="Tra cứu lịch sử giao dịch, chi tiết sản phẩm, IMEI và in lại hóa đơn"
        />

        <!-- THỐNG KÊ TÀI CHÍNH NHANH (KPI CARDS) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 bg-white rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Doanh thu kỳ này</div>
                    <div class="text-lg font-black text-slate-900 mt-1">
                        {{ formatMoney(stats.totalRevenue || sales.data.reduce((acc, s) => acc + saleTotal(s), 0)) }} đ
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <DollarSign :size="18" />
                </div>
            </div>

            <div class="p-4 bg-white rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tổng số đơn hàng</div>
                    <div class="text-lg font-black text-slate-900 mt-1">
                        {{ stats.totalOrders || sales.total || sales.data.length }} hóa đơn
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <FileText :size="18" />
                </div>
            </div>

            <div class="p-4 bg-white rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tổng công nợ gối đầu</div>
                    <div class="text-lg font-black text-rose-600 mt-1">
                        {{ formatMoney(stats.totalDebt || 0) }} đ
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <CreditCard :size="18" />
                </div>
            </div>
        </div>

        <!-- BẢNG DỮ LIỆU CHÍNH -->
        <DataPanel>
            <template #header>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 w-full">
                    <div class="relative w-full sm:max-w-md">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Tìm mã HD, Tên khách hàng, SĐT hoặc IMEI..."
                            class="w-full rounded-lg border border-slate-200 py-1.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                        >
                    </div>

                    <div class="text-xs font-medium text-slate-500 self-end sm:self-center">
                        Hiển thị <span class="text-slate-900 font-bold">{{ sales.data.length }}</span> / <span class="text-slate-900 font-bold">{{ sales.total || sales.data.length }}</span> hóa đơn
                    </div>
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-3">Mã HD</th>
                            <th class="px-4 py-3">Khách Hàng</th>
                            <th class="px-4 py-3">Thu Ngân</th>
                            <th class="px-4 py-3 text-right">Tổng Tiền</th>
                            <th class="px-4 py-3">Ngày Tảo</th>
                            <th class="w-24 px-4 py-3 text-center">Thao Tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="sale in sales.data"
                            :key="sale.id"
                            class="hover:bg-slate-50 transition"
                        >
                            <!-- Mã HD -->
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ sale.code }}
                            </td>

                            <!-- Tên Khách Hàng -->
                            <td class="px-4 py-3">
                                <span v-if="sale.customer" class="font-medium text-slate-900">
                                    {{ getCustomerName(sale) }}
                                </span>
                                <span v-else class="text-slate-400 italic">
                                    Khách lẻ
                                </span>
                            </td>

                            <!-- Thu ngân -->
                            <td class="px-4 py-3 text-slate-600">
                                {{ sale.user?.name || '-' }}
                            </td>

                            <!-- Tổng tiền -->
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                {{ formatMoney(saleTotal(sale)) }} đ
                            </td>

                            <!-- Ngày tạo -->
                            <td class="px-4 py-3 text-slate-600 text-xs">
                                {{ formatDateTime(sale.created_at) }}
                            </td>

                            <!-- Thao tác -->
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <ActionButton
                                        variant="ghost"
                                        title="Xem chi tiết"
                                        @click="detailSale = sale"
                                    >
                                        <Eye class="h-4 w-4 text-slate-600" />
                                    </ActionButton>

                                    <ActionButton
                                        variant="ghost"
                                        title="In hóa đơn"
                                        @click="openPrintWindow(sale.id)"
                                    >
                                        <Printer class="h-4 w-4 text-blue-600" />
                                    </ActionButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Dữ liệu trống -->
                        <tr v-if="!sales.data || !sales.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                Không có dữ liệu hóa đơn nào.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DataPanel>

        <!-- MODAL CHI TIẾT HÓA ĐƠN -->
        <BaseModal
            v-if="detailSale"
            title="Chi tiết hóa đơn"
            size="xl"
            @close="detailSale = null"
        >
            <div class="space-y-4">
                <!-- Thông tin tóm tắt -->
                <div class="grid gap-3 grid-cols-2 md:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-slate-400 flex items-center gap-1">
                            <Tag :size="12" /> Mã HD
                        </div>
                        <div class="mt-1 font-semibold text-slate-900 text-sm">{{ detailSale.code }}</div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-slate-400 flex items-center gap-1">
                            <User :size="12" /> Khách Hàng
                        </div>
                        <div class="mt-1 font-semibold text-slate-900 text-sm truncate">
                            {{ getCustomerName(detailSale) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-slate-400 flex items-center gap-1">
                            <Clock :size="12" /> Ngày
                        </div>
                        <div class="mt-1 font-medium text-slate-800 text-xs">
                            {{ formatDateTime(detailSale.created_at) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-emerald-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-emerald-600 flex items-center gap-1">
                            <DollarSign :size="12" /> Tổng Tiền
                        </div>
                        <div class="mt-1 font-bold text-emerald-600 text-sm">
                            {{ formatMoney(saleTotal(detailSale)) }} đ
                        </div>
                    </div>
                </div>

                <!-- Bảng chi tiết sản phẩm -->
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[650px] text-sm text-left">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                                <th class="px-3 py-2">Sản phẩm</th>
                                <th class="px-3 py-2">IMEI</th>
                                <th class="px-3 py-2 text-center">SL</th>
                                <th class="px-3 py-2 text-right">Đơn giá</th>
                                <th class="px-3 py-2 text-right">Thành tiền</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in detailSale.items"
                                :key="item.id"
                                class="align-top"
                            >
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">
                                        {{ item.product?.name || '-' }}
                                    </div>
                                    <span
                                        v-if="item.variant?.attributes"
                                        class="block text-xs font-normal text-slate-500 mt-0.5"
                                    >
                                        {{ Object.values(item.variant.attributes).filter(Boolean).join(' / ') }}
                                    </span>
                                    <div v-if="item.discount_value > 0" class="text-xs text-rose-600 font-medium mt-0.5">
                                        Giảm:
                                        <span v-if="item.discount_type === 'percent'">{{ item.discount_value }}%</span>
                                        <span v-else>{{ formatMoney(item.discount_value) }} đ</span>
                                    </div>
                                    <div v-if="item.gifts && item.gifts.length" class="mt-1 space-y-0.5">
                                        <div
                                            v-for="gift in item.gifts"
                                            :key="gift.id"
                                            class="text-xs text-emerald-700 flex items-center gap-1"
                                        >
                                            <Gift :size="11" />
                                            <span>🎁 {{ gift.product?.name }} x{{ gift.quantity }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-2 font-mono text-xs text-slate-600">
                                    {{ item.product_imei?.imei ?? '-' }}
                                </td>

                                <td class="px-3 py-2 text-center">
                                    <div class="font-semibold text-slate-800">
                                        {{ saleQuantityText(item) }}
                                    </div>
                                </td>

                                <td class="px-3 py-2 text-right text-slate-700">
                                    {{ formatMoney(item.unit_price) }} đ
                                </td>

                                <td class="px-3 py-2 text-right font-semibold text-slate-900">
                                    {{ formatMoney(item.subtotal) }} đ
                                </td>
                            </tr>
                        </tbody>

                        <tfoot class="bg-slate-50 font-semibold border-t border-slate-200">
                            <tr v-if="detailSale.subtotal">
                                <td colspan="4" class="px-3 py-2 text-right text-xs text-slate-500 uppercase">Tạm tính:</td>
                                <td class="px-3 py-2 text-right text-slate-900">{{ formatMoney(detailSale.subtotal) }} đ</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="px-3 py-2 text-right text-xs text-slate-800 uppercase">Tổng cộng:</td>
                                <td class="px-3 py-2 text-right text-emerald-600 font-bold">
                                    {{ formatMoney(saleTotal(detailSale)) }} đ
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Thao tác Modal -->
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="detailSale = null"
                        class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Đóng
                    </button>
                    <button
                        type="button"
                        @click="openPrintWindow(detailSale.id)"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 transition"
                    >
                        <Printer :size="14" />
                        <span>In Hóa Đơn</span>
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>