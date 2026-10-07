<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import FloatingInput from '@/Components/UI/FloatingInput.vue';
import FloatingSelect from '@/Components/UI/FloatingSelect.vue';
import DetailModal from '@/Components/UI/DetailModal.vue';
import { openModal } from '@/Stores/modal';
import RepairFormModal from './RepairFormModal.vue';
import PatternLock from '@/Components/PatternLock.vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue';

const props = defineProps({
    repairs: Object,
    filters: Object,
    stats: Object,
});
const page = usePage();

// Giữ nguyên dữ liệu lọc không bị reset
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const sortBy = ref(props.filters?.sort_by ?? 'created_at');
const sortOrder = ref(props.filters?.sort_order ?? 'desc');

const detailRepair = ref(null);
const paymentRepair = ref(null);
const paymentLoading = ref(false);

const reload = () => {
    router.reload({ only: ['repairs', 'stats'], preserveScroll: true });
};

const openCreate = () => {
    openRepairWorkflow();
};

const openRepairWorkflow = (repair = null, startAt = 'repair') => {
    openModal(RepairFormModal, {
        props: {
            initialRepair: repair,
            startAt,
            onCompleted: onRepairCompleted,
            onPay: onProgressPayment,
        },
        onUpdated: reload,
    });
};

const openCreateFromQuery = () => {
    const url = new URL(window.location.href);
    if (url.searchParams.get('create') === '1') {
        openCreate();
        url.searchParams.delete('create');
        window.history.replaceState(window.history.state, '', url);
    }
};

onMounted(openCreateFromQuery);
watch(() => page.url, openCreateFromQuery);

const onRepairCompleted = (repair) => {
    paymentRepair.value = repair;
    reload();
};

const onProgressPayment = async (repair) => {
    if (Number(repair.final_cost || 0) <= 0) {
        try {
            await axios.post(route('repairs.return', repair.id), {
                payment_method: 'cash',
                paid_amount: 0,
                note: repair.warranty_covered_amount > 0 ? 'Toàn bộ chi phí được bảo hành' : 'Không phát sinh thanh toán',
            });
            toast.success('Đã xác nhận trả máy, không phát sinh thanh toán');
            reload();
        } catch (error) {
            toast.error(Object.values(error.response?.data?.errors || {}).flat()[0] || 'Không thể xác nhận trả máy');
        }
        return;
    }
    paymentRepair.value = repair;
};

const detailRows = computed(() => {
    const repair = detailRepair.value;
    if (!repair) return [];
    return [
        { label: 'Khách hàng', value: repair.customer?.name || 'Khách lẻ' },
        { label: 'Số điện thoại', value: repair.customer?.phone || '-' },
        { label: 'SĐT liên hệ', value: repair.contact_phone || '-' },
        { label: 'CCCD', value: repair.customer?.identity_card || '-' },
        { label: 'Thiết bị', value: repair.device_name },
        { label: 'IMEI / Serial', value: repair.imei || '-' },
        { label: 'Loại xử lý', value: repair.intake_type === 'warranty' ? 'Bảo hành' : repair.warranty_status === 'declined' ? 'Sửa dịch vụ (đã từ chối BH)' : repair.warranty_status === 'service' ? 'Sửa dịch vụ (có căn cứ BH)' : repair.warranty_source_type && ['pending', 'repairing'].includes(repair.status) ? 'Chưa chốt · có căn cứ BH' : ['pending', 'repairing'].includes(repair.status) ? 'Chưa chốt loại sửa' : 'Sửa dịch vụ' },
        ...(repair.warranty_expires_at ? [{ label: 'Hạn bảo hành gốc', value: new Date(repair.warranty_expires_at).toLocaleDateString('vi-VN') }] : []),
        ...(repair.warranty_decline_reason ? [{ label: 'Lý do từ chối BH', value: repair.warranty_decline_reason, full: true }] : []),
        ...(Number(repair.warranty_covered_amount || 0) > 0 ? [{ label: 'Chi phí được bảo hành', value: `${Number(repair.warranty_covered_amount).toLocaleString('vi-VN')} đ` }] : []),
        ...(repair.repair_warranty_expires_at ? [{ label: 'BH sau sửa đến', value: new Date(repair.repair_warranty_expires_at).toLocaleDateString('vi-VN') }] : []),
        { label: 'Mật khẩu màn hình', value: repair.screen_password || '-' },
        { label: 'Mẫu hình (thứ tự chấm)', value: repair.screen_pattern || '-' },
        { label: 'Loại tài khoản', value: repair.account_type || '-' },
        { label: 'Email / SĐT tài khoản', value: repair.account_email || '-' },
        { label: 'Mật khẩu tài khoản', value: repair.account_password || '-' },
        { label: 'Trạng thái', value: statusLabels[repair.status] || repair.status },
        { label: 'Ngày nhận', value: repair.created_at },
        { label: 'Triệu chứng', value: Array.isArray(repair.issue) ? repair.issue.join(', ') : repair.issue || '-' , full: true },
        { label: 'Yêu cầu sửa chữa', value: repair.repair_request || '-', full: true },
        { label: 'Phụ kiện kèm theo', value: Array.isArray(repair.accessories) ? repair.accessories.join(', ') : repair.accessories || '-', full: true },
        { label: 'Chi phí dự kiến', value: repair.estimated_cost ? `${Number(repair.estimated_cost).toLocaleString('vi-VN')} đ` : '-' },
        { label: 'Chi phí thực tế', value: repair.final_cost ? `${Number(repair.final_cost).toLocaleString('vi-VN')} đ` : '-' },
        { label: 'Tiền linh kiện', value: `${Number(repair.parts_total || 0).toLocaleString('vi-VN')} đ` },
        { label: 'Công sửa', value: `${Number(repair.labor_cost || 0).toLocaleString('vi-VN')} đ` },
        { label: 'Phụ phí', value: `${Number(repair.surcharge || 0).toLocaleString('vi-VN')} đ` },
        { label: 'Linh kiện', value: repair.parts?.map((part) => `${part.product_name} × ${part.quantity}`).join(', ') || 'Không thay linh kiện', full: true },
        { label: 'Đã thanh toán', value: `${Number(repair.paid_amount || 0).toLocaleString('vi-VN')} đ` },
        { label: 'Tiền thừa', value: `${Number(repair.change_amount || 0).toLocaleString('vi-VN')} đ` },
        { label: 'Ghi chú', value: repair.note || '-', full: true },
    ];
});

// Cập nhật URL & Lọc dữ liệu
let timeout = null;
const applyFilters = () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('repairs.index'),
            {
                search: search.value || undefined,
                status: status.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
                sort_by: sortBy.value,
                sort_order: sortOrder.value,
            },
            { 
                preserveState: true,
                preserveScroll: true,
                replace: true 
            }
        );
    }, 300);
};

watch([search, status, dateFrom, dateTo, sortBy, sortOrder], applyFilters);

const toggleSort = (field) => {
    if (sortBy.value === field) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortOrder.value = 'desc';
    }
};

const statusClasses = {
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    repairing: 'bg-purple-50 text-purple-700 border-purple-200',
    done: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    returned: 'bg-slate-100 text-slate-600 border-slate-200',
    cancelled: 'bg-rose-50 text-rose-700 border-rose-200',
};

const statusLabels = {
    pending: 'Tiếp nhận',
    repairing: 'Đang sửa',
    done: 'Hoàn tất sửa',
    returned: 'Đã trả khách',
    cancelled: 'Hủy',
};

const statusOptions = [
    { value: '', label: 'Tất cả trạng thái' },
    { value: 'pending', label: 'Tiếp nhận' },
    { value: 'repairing', label: 'Đang sửa' },
    { value: 'done', label: 'Hoàn tất sửa' },
    { value: 'returned', label: 'Đã trả khách' },
    { value: 'cancelled', label: 'Hủy' },
];

const confirmPayment = async (payment) => {
    paymentLoading.value = true;
    try {
        await axios.post(route('repairs.return', paymentRepair.value.id), payment);
        toast.success('Đã thanh toán và trả máy cho khách');
        paymentRepair.value = null;
        reload();
    } catch (error) {
        const message = Object.values(error.response?.data?.errors || {}).flat()[0];
        toast.error(message || 'Không thể xác nhận thanh toán');
    } finally {
        paymentLoading.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-50/50 text-slate-800 p-2 sm:p-3 space-y-2">
        <!-- HEADER & THỐNG KÊ + NÚT TẠO MỚI -->
        <div class="flex flex-wrap items-center justify-between gap-2 bg-white px-3 py-2 rounded-xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="text-lg font-bold text-slate-900 tracking-tight">Quản lý sửa chữa</h1>
                </div>

                <!-- THỐNG KÊ NHANH (Chữ to & sát lề) -->
                <div class="hidden md:flex items-center gap-2 pl-3 border-l border-slate-200 text-xs">
                    <div class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200/60 text-amber-800">
                        <span>Chưa xong: </span>
                        <span class="font-bold text-sm">{{ props.stats?.pending_count ?? 0 }}</span>
                    </div>
                    <div class="px-2.5 py-1 rounded-lg bg-purple-50 border border-purple-200/60 text-purple-800">
                        <span>Đang sửa: </span>
                        <span class="font-bold text-sm">{{ props.stats?.repairing_count ?? 0 }}</span>
                    </div>
                    <div class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200/60 text-emerald-800">
                        <span>Hoàn tất sửa: </span>
                        <span class="font-bold text-sm">{{ props.stats?.done_count ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <button
                type="button"
                @click="openCreate"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold text-sm rounded-lg shadow-sm transition shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nhận máy mới</span>
            </button>
        </div>

        <!-- BỘ LỌC DỮ LIỆU -->
        <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 shadow-sm space-y-2">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2">
                <!-- Tìm kiếm -->
                <div class="lg:col-span-2">
                    <FloatingInput
                        v-model="search"
                        label="Tìm mã phiếu, tên khách, SĐT, IMEI..."
                        id="search_repair"
                    />
                </div>

                <!-- Lọc trạng thái -->
                <div>
                    <FloatingSelect
                        v-model="status"
                        label="Trạng thái"
                        id="status_filter"
                        :options="statusOptions"
                    />
                </div>

                <!-- Từ ngày -->
                <div>
                    <FloatingInput
                        v-model="dateFrom"
                        type="date"
                        label="Từ ngày"
                        id="date_from"
                    />
                </div>

                <!-- Đến ngày -->
                <div>
                    <FloatingInput
                        v-model="dateTo"
                        type="date"
                        label="Đến ngày"
                        id="date_to"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-xs text-slate-500">
                <div>
                    Nhấn vào <span class="font-semibold text-slate-700">tiêu đề cột</span> để sắp xếp.
                </div>
                <div>
                    Hiển thị <span class="font-bold text-slate-800">{{ props.repairs?.data?.length || 0 }}</span> / <span class="font-bold text-slate-800">{{ props.repairs?.total || 0 }}</span> phiếu
                </div>
            </div>
        </div>

        <!-- BẢNG DỮ LIỆU (Chữ to, dòng thoáng hơn) -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider select-none">
                            <th @click="toggleSort('code')" class="py-2.5 px-3 cursor-pointer hover:bg-slate-100 hover:text-blue-600 transition">
                                <div class="flex items-center gap-1">
                                    <span>Mã phiếu</span>
                                    <span v-if="sortBy === 'code'" class="text-blue-600 font-bold text-sm">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th class="py-2.5 px-3">Khách hàng</th>
                            <th @click="toggleSort('device_name')" class="py-2.5 px-3 cursor-pointer hover:bg-slate-100 hover:text-blue-600 transition">
                                <div class="flex items-center gap-1">
                                    <span>Thiết bị / IMEI</span>
                                    <span v-if="sortBy === 'device_name'" class="text-blue-600 font-bold text-sm">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th @click="toggleSort('status')" class="py-2.5 px-3 cursor-pointer hover:bg-slate-100 hover:text-blue-600 transition">
                                <div class="flex items-center gap-1">
                                    <span>Trạng thái</span>
                                    <span v-if="sortBy === 'status'" class="text-blue-600 font-bold text-sm">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th @click="toggleSort('created_at')" class="py-2.5 px-3 cursor-pointer hover:bg-slate-100 hover:text-blue-600 transition">
                                <div class="flex items-center gap-1">
                                    <span>Ngày nhận</span>
                                    <span v-if="sortBy === 'created_at'" class="text-blue-600 font-bold text-sm">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th class="py-2.5 px-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="repair in props.repairs?.data" :key="repair.id" class="hover:bg-slate-50/80 transition">
                            <td class="py-2.5 px-3 font-bold text-blue-600">
                                    <button type="button" class="font-bold text-blue-600 hover:underline" @click="detailRepair = repair">{{ repair.code }}</button>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-semibold text-slate-900">{{ repair.customer?.name || 'Khách lẻ' }}</div>
                                <div class="text-xs text-slate-500">{{ repair.customer?.phone || '---' }}</div>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-semibold text-slate-800">{{ repair.device_name }}</div>
                                <div class="text-xs text-slate-400 font-mono">{{ repair.imei || 'Không IMEI' }}</div>
                            </td>
                            <td class="py-2.5 px-3">
                                <span :class="['inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold', statusClasses[repair.status]]">
                                    {{ statusLabels[repair.status] || repair.status }}
                                </span>
                                <div v-if="repair.waiting_for_parts" class="mt-1 text-[11px] font-medium text-amber-700">
                                    <template v-if="repair.waiting_mode === 'wait_repair'">Tạm chờ sửa</template><template v-else>Chờ {{ repair.parts_needed || 'linh kiện' }}<span v-if="repair.expected_days !== null"> · {{ repair.expected_days }} ngày</span></template>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-xs text-slate-600 font-medium">
                                {{ repair.created_at }}
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click="detailRepair = repair"
                                        class="px-2.5 py-1 rounded-md border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition"
                                    >
                                        Xem
                                    </button>
                                    <button
                                        type="button"
                                        @click="openRepairWorkflow(repair, 'intake')"
                                        class="px-2.5 py-1 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold transition"
                                    >
                                        Sửa
                                    </button>
                                    <button
                                        type="button"
                                        @click="openRepairWorkflow(repair)"
                                        v-if="['pending', 'repairing', 'done'].includes(repair.status)"
                                        class="px-2.5 py-1 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold transition"
                                    >
                                        {{ repair.status === 'done' ? 'Thanh toán / trả khách' : repair.status === 'pending' ? 'Chuyển sang bước sửa' : 'Cập nhật tiến trình' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.repairs?.data?.length">
                            <td colspan="6" class="py-8 text-center text-slate-400 text-sm">
                                Không tìm thấy phiếu sửa chữa phù hợp
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <DetailModal
            v-if="detailRepair"
            :title="`Phiếu sửa ${detailRepair.code}`"
            :rows="detailRows"
            size="lg"
            @close="detailRepair = null"
        >
            <div v-if="detailRepair.images?.length" class="mt-4">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Ảnh tiếp nhận</h3>
                <div class="flex flex-wrap gap-2">
                    <a v-for="image in detailRepair.images" :key="image.id" :href="image.url" target="_blank" class="block h-20 w-20 overflow-hidden rounded-lg border border-slate-200">
                        <img :src="image.url" alt="Ảnh thiết bị" class="h-full w-full object-cover" />
                    </a>
                </div>
            </div>
            <div v-if="detailRepair.timelines?.length" class="mt-4">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Lịch sử tiến trình</h3>
                <div class="space-y-2">
                    <div v-for="timeline in detailRepair.timelines" :key="timeline.id" class="rounded-lg border border-slate-200 px-3 py-2">
                        <div class="flex flex-wrap justify-between gap-x-3 text-sm font-semibold text-slate-800"><span>{{ timeline.title }}</span><span class="text-xs font-normal text-slate-400">{{ timeline.created_at }} · {{ timeline.user || 'Hệ thống' }}</span></div>
                        <p v-if="timeline.description" class="mt-1 text-sm text-slate-600">{{ timeline.description }}</p>
                        <p v-if="timeline.issue?.length" class="mt-1 text-sm text-rose-700"><strong>Lỗi phát sinh:</strong> {{ timeline.issue.join(', ') }}</p>
                        <p v-if="timeline.waiting_for_parts" class="mt-1 text-sm text-amber-700"><strong>{{ timeline.waiting_mode === 'wait_repair' ? 'Tạm chờ sửa' : 'Đang chờ linh kiện' }}:</strong><template v-if="timeline.parts_needed"> {{ timeline.parts_needed }}</template><span v-if="timeline.expected_days !== null"> · Dự kiến {{ timeline.expected_days }} ngày</span></p>
                        <div v-if="timeline.images?.length" class="mt-2 flex flex-wrap gap-2">
                            <a v-for="image in timeline.images" :key="image.id" :href="image.url" target="_blank" class="block h-16 w-16 overflow-hidden rounded border border-slate-200"><img :src="image.url" alt="Ảnh tiến trình" class="h-full w-full object-cover" /></a>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="detailRepair.screen_pattern" class="mt-4 rounded-lg border border-slate-200 p-3">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Mẫu hình mở khóa</h3>
                <PatternLock :model-value="detailRepair.screen_pattern" readonly />
            </div>
            <div class="mt-4 flex justify-end gap-2 border-t border-slate-200 pt-3">
                <a :href="route('repairs.print', detailRepair.id)" target="_blank" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">In phiếu</a>
                <button type="button" class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100" @click="openRepairWorkflow(detailRepair, 'intake'); detailRepair = null">Sửa phiếu</button>
                <button v-if="['pending', 'repairing', 'done'].includes(detailRepair.status)" type="button" class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100" @click="openRepairWorkflow(detailRepair); detailRepair = null">{{ detailRepair.status === 'done' ? 'Thanh toán / trả máy' : detailRepair.status === 'pending' ? 'Chuyển sang bước sửa' : 'Cập nhật sửa chữa' }}</button>
            </div>
        </DetailModal>
        <CheckoutModal
            v-if="paymentRepair"
            :key="paymentRepair.id"
            :show="true"
            :loading="paymentLoading"
            :grand-total="Number(paymentRepair.final_cost || 0)"
            :selected-customer="paymentRepair.customer?.id ? { id: paymentRepair.customer.id, full_name: paymentRepair.customer.name, debt_balance: paymentRepair.customer.debt_balance } : null"
            :cart="[]"
            @close="paymentRepair = null"
            @confirm="confirmPayment"
        />
    </div>
</template>
