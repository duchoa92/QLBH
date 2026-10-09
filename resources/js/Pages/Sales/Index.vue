<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
    Search,
    Eye,
    Printer,
    FileText,
    DollarSign,
    CreditCard,
    AlertTriangle,
    CheckCircle2,
    XCircle,
    Undo2,
    RefreshCw,
    ArrowUpDown,
    ArrowUp,
    ArrowDown
} from 'lucide-vue-next'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
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


// Kiểm tra quyền theo hành động thay vì suy ra từ tên vai trò.
const page = usePage()
const canCancelSales = computed(() => page.props.auth?.permissions?.includes('sales.cancel') ?? false)

// Cập nhật lại các ref khi props.filters thay đổi từ Server gửi về
watch(
    () => props.filters,
    (newFilters) => {
        if (newFilters) {
            search.value = newFilters.search ?? ''
            status.value = newFilters.status ?? ''
            dateFrom.value = newFilters.date_from ?? newFilters.start_date ?? ''
            dateTo.value = newFilters.date_to ?? newFilters.end_date ?? ''
            sortKey.value = newFilters.sort_by ?? 'created_at'
            sortOrder.value = newFilters.sort_order ?? 'desc'
        }
    },
    { deep: true }
)

// Lấy giá trị khởi tạo từ URL / Props
const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')

const detailSale = ref(null)
const cancelReason = ref('')
const showCancelModal = ref(false)
const selectedSaleForCancel = ref(null)

// --- QUẢN LÝ SORTING (SẮP XẾP BẢNG) ---
const sortKey = ref(props.filters?.sort_by ?? 'created_at')
const sortOrder = ref(props.filters?.sort_order ?? 'desc')

const requestSales = () => router.get(
    route('sales.index'),
    {
        search: search.value || undefined,
        status: status.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        sort_by: sortKey.value,
        sort_order: sortOrder.value,
    },
    { preserveState: true, preserveScroll: true, replace: true }
)

const handleSort = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortOrder.value = 'asc'
    }
    clearTimeout(searchTimeout)
    requestSales()
}

// --- QUẢN LÝ TÍNH NĂNG CLICK LỌC NHANH ---
const filterByCustomer = (customerName) => {
    if (!customerName || customerName === 'Khách lẻ') return
    search.value = customerName
    handleFilterChange()
}

const filterByCashier = (cashierName) => {
    if (!cashierName) return
    search.value = cashierName
    handleFilterChange()
}

const filterByStatus = (statusValue) => {
    status.value = statusValue
    handleFilterChange()
}

// Danh sách options cho bộ lọc Trạng thái
const statusOptions = [
    { label: 'Tất cả trạng thái', value: '' },
    { label: 'Hoàn thành', value: 'completed' },
    { label: 'Đã hủy', value: 'cancelled' }
]

let searchTimeout = null

// Hàm kích hoạt lọc (chỉ gửi request khi có thay đổi)
const handleFilterChange = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(requestSales, 350)
}

// Reset toàn bộ lọc
const resetFilters = () => {
    search.value = ''
    status.value = ''
    dateFrom.value = ''
    dateTo.value = ''
    sortKey.value = 'created_at'
    sortOrder.value = 'desc'
    clearTimeout(searchTimeout)
    router.get(route('sales.index'), {}, { preserveState: true, preserveScroll: true, replace: true })
}

// Tính tổng tiền hóa đơn
const saleTotal = (sale) => sale.grand_total ?? sale.total_amount ?? sale.subtotal ?? 0

// Lấy tên khách hàng
const getCustomerName = (sale) => {
    if (!sale?.customer) return 'Khách lẻ'
    return sale.customer.full_name || sale.customer.name || 'Khách lẻ'
}

// In hóa đơn
const openPrintWindow = (saleId) => {
    window.open(route('sales.receipt', saleId), '_blank', 'width=400,height=650')
}

// Xác nhận Hủy
const confirmCancel = (sale) => {
    selectedSaleForCancel.value = sale
    cancelReason.value = ''
    showCancelModal.value = true
}

// Xử lý Hủy hóa đơn
const handleCancelSale = () => {
    if (!selectedSaleForCancel.value) return
    const reasonText = cancelReason.value.trim()

    router.post(
        route('sales.cancel', selectedSaleForCancel.value.id),
        { reason: reasonText, cancel_reason: reasonText },
        {
            onSuccess: () => {
                showCancelModal.value = false
                selectedSaleForCancel.value = null
                cancelReason.value = ''
                router.reload({ only: ['sales'] })
            }
        }
    )
}

// Lý do hủy mẫu
const defaultReasons = [
    'Khách đổi ý không mua nữa',
    'Đổi sang sản phẩm khác',
    'Nhầm sản phẩm / số lượng',
    'Nhập sai giá / giảm giá',
    'Chưa nhận được tiền',
    'Trùng hóa đơn'
]

const predefinedCancelReasons = computed(() => {
    const existingReasons = (props.sales?.data || [])
        .map(s => s.cancel_reason || s.reason)
        .filter(Boolean)

    return Array.from(new Set([...defaultReasons, ...existingReasons]))
})

const sortedSalesData = computed(() => props.sales?.data || [])
</script>

<template>
    <!-- THU NHỎ MARGIN / PADDING NGOÀI (p-2.5 sm:p-3 space-y-3) -->
    <div class="space-y-3 p-2.5 sm:p-3 font-sans text-slate-800">
        
        <!-- 1. THỐNG KÊ TÀI CHÍNH NHANH (TĂNG CỠ CHỮ CHÚT ÍT) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
            <div class="p-3 bg-white rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between hover:shadow-xs transition">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Doanh thu kỳ này</div>
                    <div class="text-xl font-black text-slate-900 mt-0.5">
                        {{ formatMoney(stats.totalRevenue || sales.data.reduce((acc, s) => s.status !== 'cancelled' ? acc + saleTotal(s) : acc, 0)) }}
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <DollarSign :size="20" />
                </div>
            </div>

            <div class="p-3 bg-white rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between hover:shadow-xs transition">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Tổng số đơn hàng</div>
                    <div class="text-xl font-black text-slate-900 mt-0.5">
                        {{ stats.totalOrders || sales.total || sales.data.length }} <span class="text-xs font-semibold text-slate-500">hóa đơn</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <FileText :size="20" />
                </div>
            </div>

            <div class="p-3 bg-white rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between hover:shadow-xs transition">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Tổng công nợ gối đầu</div>
                    <div class="text-xl font-black text-rose-600 mt-0.5">
                        {{ formatMoney(stats.totalDebt || 0) }}
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <CreditCard :size="20" />
                </div>
            </div>
        </div>

        <!-- 2. BẢNG DỮ LIỆU VÀ BỘ LỌC -->
        <DataPanel>
            <template #header>
                <div class="w-full">
                    <div class="flex flex-wrap items-center gap-2 w-full">
                        
                        <!-- FloatingInput: Ô tìm kiếm hóa đơn -->
                        <div class="flex-1 min-w-[260px]">
                            <FloatingInput
                                v-model="search"
                                label="Tìm Mã HD, Tên KH, SĐT, IMEI..."
                                name="search"
                                @input="handleFilterChange"
                            />
                        </div>

                        <!-- FloatingSelect: Ô lọc trạng thái -->
                        <div class="w-44 shrink-0">
                            <FloatingSelect
                                v-model="status"
                                label="Trạng thái"
                                name="status"
                                :options="statusOptions"
                                option-label="label"
                                option-value="value"
                                @update:modelValue="handleFilterChange"
                            />
                        </div>

                        <!-- FloatingInput: Từ ngày -->
                        <div class="w-36 shrink-0">
                            <FloatingInput
                                v-model="dateFrom"
                                label="Từ ngày"
                                type="date"
                                name="date_from"
                                @change="handleFilterChange"
                            />
                        </div>

                        <!-- FloatingInput: Đến ngày -->
                        <div class="w-36 shrink-0">
                            <FloatingInput
                                v-model="dateTo"
                                label="Đến ngày"
                                type="date"
                                name="date_to"
                                @change="handleFilterChange"
                            />
                        </div>

                        <!-- Nút Reset Lọc -->
                        <button
                            type="button"
                            @click="resetFilters"
                            class="inline-flex h-[38px] w-[38px] items-center justify-center rounded-lg border border-gray-300 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition active:scale-95 shrink-0"
                            title="Làm mới bộ lọc"
                        >
                            <RefreshCw :size="16" />
                        </button>

                    </div>
                </div>
            </template>

            <!-- DANH SÁCH BẢNG DỮ LIỆU CÓ SORT & CLICK LỌC -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 border-b border-slate-200 select-none">
                            
                            <!-- Mã HĐ Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('code')">
                                <div class="flex items-center gap-1">
                                    <span>Mã HD</span>
                                    <ArrowUp v-if="sortKey === 'code' && sortOrder === 'asc'" :size="13" class="text-indigo-600" />
                                    <ArrowDown v-else-if="sortKey === 'code' && sortOrder === 'desc'" :size="13" class="text-indigo-600" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <!-- Khách Hàng Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('customer')">
                                <div class="flex items-center gap-1">
                                    <span>Khách Hàng</span>
                                    <ArrowUp v-if="sortKey === 'customer' && sortOrder === 'asc'" :size="13" class="text-indigo-600" />
                                    <ArrowDown v-else-if="sortKey === 'customer' && sortOrder === 'desc'" :size="13" class="text-indigo-600" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <!-- Thu Ngân Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('user')">
                                <div class="flex items-center gap-1">
                                    <span>Thu Ngân</span>
                                    <ArrowUp v-if="sortKey === 'user' && sortOrder === 'asc'" :size="13" class="text-indigo-600" />
                                    <ArrowDown v-else-if="sortKey === 'user' && sortOrder === 'desc'" :size="13" class="text-indigo-600" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <!-- Tổng Tiền Sort -->
                            <th class="px-3.5 py-2.5 text-right cursor-pointer hover:bg-slate-100 transition" @click="handleSort('total_amount')">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Tổng Tiền</span>
                                    <ArrowUp v-if="sortKey === 'total_amount' && sortOrder === 'asc'" :size="13" class="text-indigo-600" />
                                    <ArrowDown v-else-if="sortKey === 'total_amount' && sortOrder === 'desc'" :size="13" class="text-indigo-600" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <th class="px-3.5 py-2.5 text-center">Trạng Thái</th>

                            <!-- Ngày Tạo Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('created_at')">
                                <div class="flex items-center gap-1">
                                    <span>Ngày Tạo</span>
                                    <ArrowUp v-if="sortKey === 'created_at' && sortOrder === 'asc'" :size="13" class="text-indigo-600" />
                                    <ArrowDown v-else-if="sortKey === 'created_at' && sortOrder === 'desc'" :size="13" class="text-indigo-600" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <th class="w-28 px-3.5 py-2.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 font-medium text-xs">
                        <tr
                            v-for="sale in sortedSalesData"
                            :key="sale.id"
                            class="hover:bg-slate-50 transition-colors"
                        >
                            <!-- 1. Mã HD (CLICK XEM CHI TIẾT) -->
                            <td class="px-3.5 py-2.5">
                                <button
                                    type="button"
                                    class="font-bold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer text-xs"
                                    title="Bấm để xem chi tiết hóa đơn"
                                    @click="detailSale = sale"
                                >
                                    #{{ sale.code }}
                                </button>
                            </td>

                            <!-- 2. Khách Hàng (CLICK ĐỂ LỌC KH) -->
                            <td class="px-3.5 py-2.5">
                                <span
                                    class="font-bold text-slate-900 cursor-pointer hover:text-indigo-600 hover:underline"
                                    title="Bấm để lọc theo khách hàng này"
                                    @click="filterByCustomer(getCustomerName(sale))"
                                >
                                    {{ getCustomerName(sale) }}
                                </span>
                            </td>

                            <!-- 3. Thu ngân (CLICK ĐỂ LỌC THU NGÂN) -->
                            <td class="px-3.5 py-2.5 text-slate-600">
                                <span
                                    class="cursor-pointer hover:text-indigo-600 hover:underline"
                                    title="Bấm để lọc theo thu ngân này"
                                    @click="filterByCashier(sale.user?.name)"
                                >
                                    {{ sale.user?.name || '-' }}
                                </span>
                            </td>

                            <!-- Tổng tiền (CỠ CHỮ TO ĐẬM) -->
                            <td class="px-3.5 py-2.5 text-right font-black text-slate-900 text-sm">
                                {{ formatMoney(saleTotal(sale)) }}
                            </td>

                            <!-- 4. Trạng thái (CLICK ĐỂ LỌC TRẠNG THÁI) -->
                            <td class="px-3.5 py-2.5 text-center">
                                <span
                                    v-if="sale.status === 'cancelled'"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-200 cursor-pointer hover:bg-rose-100"
                                    title="Bấm để lọc các đơn đã hủy"
                                    @click="filterByStatus('cancelled')"
                                >
                                    <XCircle :size="12" /> Đã hủy
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200 cursor-pointer hover:bg-emerald-100"
                                    title="Bấm để lọc các đơn hoàn thành"
                                    @click="filterByStatus('completed')"
                                >
                                    <CheckCircle2 :size="12" /> Hoàn thành
                                </span>
                            </td>

                            <!-- Ngày tạo -->
                            <td class="px-3.5 py-2.5 text-slate-500 text-[11px]">
                                {{ formatDateTime(sale.created_at) }}
                            </td>

                            <!-- Thao tác -->
                            <td class="px-3.5 py-2.5 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <ActionButton
                                        variant="ghost"
                                        title="Xem chi tiết"
                                        @click="detailSale = sale"
                                    >
                                        <Eye class="h-4 w-4 text-slate-600 hover:text-blue-600" />
                                    </ActionButton>

                                    <ActionButton
                                        variant="ghost"
                                        title="In hóa đơn"
                                        @click="openPrintWindow(sale.id)"
                                    >
                                        <Printer class="h-4 w-4 text-indigo-600" />
                                    </ActionButton>

                                    <ActionButton
                                        v-if="canCancelSales && sale.status !== 'cancelled'"
                                        variant="ghost"
                                        title="Hủy hóa đơn & Hoàn kho"
                                        @click="confirmCancel(sale)"
                                    >
                                        <Undo2 class="h-4 w-4 text-rose-500 hover:text-rose-700" />
                                    </ActionButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Bảng trống -->
                        <tr v-if="!sales.data || !sales.data.length">
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                                    <FileText :size="24" />
                                </div>
                                <p class="text-sm font-bold text-slate-700">Không tìm thấy hóa đơn nào</p>
                                <p class="text-xs text-slate-400 mt-1">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- FOOTER BẢNG PHÂN TRANG -->
            <template #footer>
                <div class="flex items-center justify-between text-xs font-medium text-slate-500 py-1">
                    <div>
                        Hiển thị <span class="text-slate-900 font-bold">{{ sales.data?.length || 0 }}</span> / <span class="text-slate-900 font-bold">{{ sales.total || sales.data?.length || 0 }}</span> hóa đơn
                    </div>
                </div>
            </template>
        </DataPanel>

        <!-- MODAL CHI TIẾT HÓA ĐƠN -->
        <InvoiceDetailModal
            :show="Boolean(detailSale)"
            :invoice="detailSale"
            :can-cancel="canCancelSales && detailSale?.status !== 'cancelled'"
            can-print
            @close="detailSale = null"
            @cancel="confirmCancel"
            @print="openPrintWindow($event.id)"
        />

        <!-- MODAL XÁC NHẬN HỦY HÓA ĐƠN -->
        <BaseModal
            v-if="showCancelModal"
            title="Xác nhận hủy hóa đơn"
            size="md"
            @close="showCancelModal = false"
        >
            <div class="space-y-4 p-1">
                <div class="flex items-start gap-3 p-3.5 bg-amber-50 rounded-2xl border border-amber-200">
                    <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div class="text-xs text-amber-800 leading-relaxed">
                        <strong>Lưu ý quan trọng:</strong> Khi hủy hóa đơn <strong>#{{ selectedSaleForCancel?.code }}</strong>, toàn bộ số lượng sản phẩm và mã IMEI/Serial liên quan sẽ được <strong>tự động hoàn trả về kho</strong>.
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Lý do hủy hóa đơn:
                    </label>

                    <textarea
                        v-model="cancelReason"
                        rows="3"
                        list="cancel-reasons-list"
                        placeholder="Nhập lý do hủy hoặc chọn từ các gợi ý bên dưới..."
                        class="w-full rounded-xl border border-slate-200 p-3 text-xs outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 transition"
                    ></textarea>

                    <datalist id="cancel-reasons-list">
                        <option v-for="(reason, index) in predefinedCancelReasons" :key="index" :value="reason" />
                    </datalist>

                    <div class="mt-2.5">
                        <div class="text-[11px] font-semibold text-slate-400 mb-1.5">
                            💡 Gợi ý lý do phổ biến (Bấm để chọn nhanh):
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="(reason, idx) in predefinedCancelReasons"
                                :key="idx"
                                type="button"
                                @click="cancelReason = reason"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-xs font-medium transition border',
                                    cancelReason === reason
                                        ? 'bg-rose-50 border-rose-300 text-rose-700 font-bold'
                                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                ]"
                            >
                                {{ reason }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showCancelModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Bỏ qua
                    </button>
                    <button
                        type="button"
                        @click="handleCancelSale"
                        :disabled="!cancelReason.trim()"
                        class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Xác nhận Hủy Đơn
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
