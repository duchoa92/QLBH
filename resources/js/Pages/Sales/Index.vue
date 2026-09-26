<script setup>
import { ref, watch, computed } from 'vue'
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
    Undo2
} from 'lucide-vue-next'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
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

// Kiểm tra quyền Admin từ User đăng nhập
const page = usePage()
const isAdmin = page.props.auth?.user?.role === 'admin' || page.props.auth?.roles?.includes('Super Admin')

// Trạng thái tìm kiếm & Modal
const search = ref(props.filters?.search ?? '')
const detailSale = ref(null)
const cancelReason = ref('')
const showCancelModal = ref(false)
const selectedSaleForCancel = ref(null)

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

// Xử lý lấy tên khách hàng chính xác
const getCustomerName = (sale) => {
    if (!sale?.customer) return 'Khách lẻ'
    return sale.customer.full_name || sale.customer.name || 'Khách lẻ'
}

// Mở cửa sổ in hóa đơn
const openPrintWindow = (saleId) => {
    window.open(route('sales.receipt', saleId), '_blank', 'width=400,height=650')
}

// Mở Modal xác nhận Hủy hóa đơn
const confirmCancel = (sale) => {
    selectedSaleForCancel.value = sale
    cancelReason.value = ''
    showCancelModal.value = true
}

// Xử lý gửi request Hủy hóa đơn về Backend
const handleCancelSale = () => {
    if (!selectedSaleForCancel.value) return

    const reasonText = cancelReason.value.trim()

    router.post(route('sales.cancel', selectedSaleForCancel.value.id), {
        reason: reasonText,
        cancel_reason: reasonText
    }, {
        onSuccess: () => {
            showCancelModal.value = false

            // Tìm và cập nhật trực tiếp vào item tương ứng trong danh sách sales.data
            const targetSale = props.sales?.data?.find(s => s.id === selectedSaleForCancel.value.id)
            if (targetSale) {
                targetSale.status = 'cancelled'
                targetSale.cancel_reason = reasonText
                targetSale.reason = reasonText
            }

            // Nếu đang mở Modal chi tiết của đơn này, cập nhật luôn
            if (detailSale.value && detailSale.value.id === selectedSaleForCancel.value.id) {
                detailSale.value.status = 'cancelled'
                detailSale.value.cancel_reason = reasonText
                detailSale.value.reason = reasonText
            }

            selectedSaleForCancel.value = null
            cancelReason.value = ''

            // Yêu cầu Inertia làm mới lại trang để đồng bộ CSDL
            router.reload({ only: ['sales'] })
        }
    })
}


// 1. Danh sách các lý do hủy mẫu định sẵn
const defaultReasons = [
    'Khách đổi ý không mua nữa',
    'Đổi sang sản phẩm khác',
    'Nhầm sản phẩm / số lượng',
    'Nhập sai giá / giảm giá',
    'Chưa nhận được tiền',
    'Trùng hóa đơn'
]

// 2. Tự động thu thập thêm các lý do hủy thực tế đã có trong danh sách hóa đơn
const predefinedCancelReasons = computed(() => {
    const existingReasons = (props.sales?.data || [])
        .map(s => s.cancel_reason || s.reason)
        .filter(Boolean)

    // Nối lý do mẫu + lý do đã dùng trước đây, bỏ trùng lặp bằng Set
    return Array.from(new Set([...defaultReasons, ...existingReasons]))
})

</script>

<template>
    <div class="space-y-5 p-6 font-sans text-slate-800">
        <!-- HEADER TRANG -->
        <PageHeader
            title="Quản lý Hóa đơn & Bán hàng"
            description="Tra cứu lịch sử giao dịch, chi tiết sản phẩm, IMEI, in lại hóa đơn hoặc hoàn hủy đơn hàng"
        />

        <!-- THỐNG KÊ TÀI CHÍNH NHANH (KPI CARDS) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Doanh thu kỳ này</div>
                    <div class="text-xl font-black text-slate-900 mt-1">
                        {{ formatMoney(stats.totalRevenue || sales.data.reduce((acc, s) => s.status !== 'cancelled' ? acc + saleTotal(s) : acc, 0)) }} đ
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <DollarSign :size="20" />
                </div>
            </div>

            <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tổng số đơn hàng</div>
                    <div class="text-xl font-black text-slate-900 mt-1">
                        {{ stats.totalOrders || sales.total || sales.data.length }} hóa đơn
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <FileText :size="20" />
                </div>
            </div>

            <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tổng công nợ gối đầu</div>
                    <div class="text-xl font-black text-rose-600 mt-1">
                        {{ formatMoney(stats.totalDebt || 0) }} đ
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <CreditCard :size="20" />
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
                            class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition"
                        >
                    </div>

                    <div class="text-xs font-medium text-slate-500 self-end sm:self-center">
                        Hiển thị <span class="text-slate-900 font-bold">{{ sales.data.length }}</span> / <span class="text-slate-900 font-bold">{{ sales.total || sales.data.length }}</span> hóa đơn
                    </div>
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-sm text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-3">Mã HD</th>
                            <th class="px-4 py-3">Khách Hàng</th>
                            <th class="px-4 py-3">Thu Ngân</th>
                            <th class="px-4 py-3 text-right">Tổng Tiền</th>
                            <th class="px-4 py-3 text-center">Trạng Thái</th>
                            <th class="px-4 py-3">Ngày Tạo</th>
                            <th class="w-28 px-4 py-3 text-center">Thao Tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="sale in sales.data"
                            :key="sale.id"
                            class="hover:bg-slate-50/70 transition"
                        >
                            <!-- Mã HD -->
                            <td class="px-4 py-3 font-bold text-slate-900">
                                #{{ sale.code }}
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
                            <td class="px-4 py-3 text-right font-bold text-slate-900">
                                {{ formatMoney(saleTotal(sale)) }} đ
                            </td>

                            <!-- Trạng thái -->
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="sale.status === 'cancelled'"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-600 border border-rose-200"
                                >
                                    <XCircle :size="12" /> Đã hủy
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200"
                                >
                                    <CheckCircle2 :size="12" /> Hoàn thành
                                </span>
                            </td>

                            <!-- Ngày tạo -->
                            <td class="px-4 py-3 text-slate-500 text-xs">
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
                                        <Printer class="h-4 w-4 text-indigo-600" />
                                    </ActionButton>

                                    <!-- 🔴 NÚT HỦY ĐƠN HÀNG (Hiển thị nếu tài khoản là Admin & đơn chưa hủy) -->
                                    <ActionButton
                                        v-if="isAdmin && sale.status !== 'cancelled'"
                                        variant="ghost"
                                        title="Hủy hóa đơn & Hoàn kho"
                                        @click="confirmCancel(sale)"
                                    >
                                        <Undo2 class="h-4 w-4 text-rose-500" />
                                    </ActionButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Dữ liệu trống -->
                        <tr v-if="!sales.data || !sales.data.length">
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                Không có dữ liệu hóa đơn nào.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DataPanel>

        <InvoiceDetailModal
            :show="Boolean(detailSale)"
            :invoice="detailSale"
            :can-cancel="isAdmin && detailSale?.status !== 'cancelled'"
            can-print
            @close="detailSale = null"
            @cancel="confirmCancel"
            @print="openPrintWindow($event.id)"
        />

        <!-- 🔴 MODAL XÁC NHẬN HỦY HÓA ĐƠN (Tích hợp Gợi ý lý do) -->
        <BaseModal
            v-if="showCancelModal"
            title="Xác nhận hủy hóa đơn"
            size="md"
            @close="showCancelModal = false"
        >
            <div class="space-y-4 p-1">
                <!-- Cảnh báo hoàn kho -->
                <div class="flex items-start gap-3 p-3.5 bg-amber-50 rounded-2xl border border-amber-200">
                    <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div class="text-xs text-amber-800 leading-relaxed">
                        <strong>Lưu ý quan trọng:</strong> Khi hủy hóa đơn <strong>#{{ selectedSaleForCancel?.code }}</strong>, toàn bộ số lượng sản phẩm và mã IMEI/Serial liên quan sẽ được <strong>tự động hoàn trả về kho</strong>.
                    </div>
                </div>

                <!-- Ô nhập lý do & Danh sách gợi ý -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Lý do hủy hóa đơn:
                    </label>

                    <!-- Ô nhập Textarea hỗ trợ gợi ý qua datalist -->
                    <textarea
                        v-model="cancelReason"
                        rows="3"
                        list="cancel-reasons-list"
                        placeholder="Nhập lý do hủy hoặc chọn từ các gợi ý bên dưới..."
                        class="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 transition"
                    ></textarea>

                    <!-- Danh sách HTML datalist hỗ trợ autocomplete tự động -->
                    <datalist id="cancel-reasons-list">
                        <option v-for="(reason, index) in predefinedCancelReasons" :key="index" :value="reason" />
                    </datalist>

                    <!-- Dãy thẻ Gợi ý nhanh (Bấm để chọn) -->
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

                <!-- Nút thao tác -->
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
                        class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Xác nhận Hủy Đơn
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
