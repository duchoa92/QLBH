<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import FloatingInput from '@/Components/UI/FloatingInput.vue';
import FloatingSelect from '@/Components/UI/FloatingSelect.vue';
import PaginationBar from '@/Components/UI/PaginationBar.vue';
import DataPanel from '@/Components/UI/DataPanel.vue';
import { openModal } from '@/Stores/modal';
import RepairFormModal from './RepairFormModal.vue';
import RepairDetailModal from './RepairDetailModal.vue';
import { useConfirm } from '@/Composables/useConfirm';
import axios from 'axios';
import { toast } from 'vue-sonner';
import api from '@/Services/api';
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue';
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue';
import { Search, SlidersHorizontal, RefreshCw, X, FileText, UserRound, Smartphone, Wrench, RotateCcw, Play } from 'lucide-vue-next';

const props = defineProps({
    repairs: Object,
    filters: Object,
    stats: Object,
});
const repairsData = ref(props.repairs);
const statsData = ref(props.stats || {});
const perPage = ref(Number(props.filters?.per_page ?? 20));
const loadingRepairs = ref(false);
const page = usePage();
const permissions = computed(() => page.props.auth?.permissions || []);
const can = (permission) => permissions.value.includes(permission);
const confirmBox = useConfirm();
const isSuperAdmin = computed(() => (page.props.auth?.roles || []).includes('Super Admin'));
const canViewCredentials = computed(() => can('repairs.create') || can('repairs.edit') || can('repairs.complete'));
const canProgress = (repair) => {
    if (repair.status === 'done') return can('repairs.return');
    if (repair.status === 'repairing') return can('repairs.complete') || can('repairs.edit');
    return can('repairs.edit');
};

// Giữ nguyên dữ liệu lọc không bị reset
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');
const sortBy = ref(props.filters?.sort_by ?? 'created_at');
const sortOrder = ref(props.filters?.sort_order ?? 'desc');

const detailRepair = ref(null);
const detailCredentials = ref(null);
const printPreviewUrl = ref('');
const paymentRepair = ref(null);
const paymentLoading = ref(false);
const isFilterExpanded = ref(Boolean(status.value || dateFrom.value || dateTo.value));

const reload = () => {
    router.reload({ only: ['repairs', 'stats'], preserveScroll: true });
};

let filterTimeout = null;
let repairsRequestController = null;
let repairsRequestId = 0;
onBeforeUnmount(() => {
    clearTimeout(filterTimeout);
    repairsRequestController?.abort();
    window.removeEventListener('popstate', openRepairFromQuery);
});

const syncFilterUrl = (filters) => {
    const url = new URL(window.location.href);
    const values = {
        search: filters.search,
        status: filters.status,
        date_from: filters.date_from,
        date_to: filters.date_to,
        sort_by: filters.sort_by,
        sort_order: filters.sort_order,
        per_page: filters.per_page,
    };
    Object.entries(values).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) url.searchParams.delete(key);
        else url.searchParams.set(key, String(value));
    });
    url.searchParams.delete('page');
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
};

const requestRepairs = async () => {
    repairsRequestController?.abort();
    const requestId = ++repairsRequestId;
    repairsRequestController = new AbortController();
    loadingRepairs.value = true;
    const params = {
        search: search.value || undefined,
        status: status.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        sort_by: sortBy.value,
        sort_order: sortOrder.value,
        per_page: perPage.value,
        page: 1,
    };

    try {
        const { data } = await api.get(route('repairs.index'), {
            params,
            signal: repairsRequestController.signal,
        });
        if (requestId !== repairsRequestId) return;
        repairsData.value = data.repairs;
        statsData.value = data.stats || {};
        syncFilterUrl(data.filters || params);
    } catch (error) {
        if (error.name !== 'CanceledError' && requestId === repairsRequestId) {
            toast.error('Không thể tải danh sách phiếu sửa.');
        }
    } finally {
        if (requestId === repairsRequestId) loadingRepairs.value = false;
    }
};

const applyFilters = () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(requestRepairs, 180);
};

const activeFilters = computed(() => ({
    search: search.value,
    status: status.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    sort_by: sortBy.value,
    sort_order: sortOrder.value,
    per_page: perPage.value,
}));

watch(() => props.repairs, (value) => { repairsData.value = value; });
watch(() => props.stats, (value) => { statsData.value = value || {}; });
watch(() => props.filters, (value) => {
    if (!value) return;
    search.value = value.search ?? '';
    status.value = value.status ?? '';
    dateFrom.value = value.date_from ?? '';
    dateTo.value = value.date_to ?? '';
    sortBy.value = value.sort_by ?? 'created_at';
    sortOrder.value = value.sort_order ?? 'desc';
    perPage.value = Number(value.per_page ?? 20);
}, { deep: true });
watch([search, status, dateFrom, dateTo, sortBy, sortOrder], applyFilters);

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

const openRepairFromQuery = () => {
    const url = new URL(window.location.href);
    const repairId = Number(url.searchParams.get('open_repair'));
    if (!repairId) { detailRepair.value = null; detailCredentials.value = null; return; }
    const repair = repairsData.value?.data?.find(item => Number(item.id) === repairId) || null;
    if (Number(detailRepair.value?.id) !== repairId) {
        detailRepair.value = repair;
        detailCredentials.value = null;
        loadDetailCredentials(repair);
    }
};

let detailCredentialRequest = 0;
const loadDetailCredentials = async (repair) => {
    const requestId = ++detailCredentialRequest;
    detailCredentials.value = null;
    if (!canViewCredentials.value || !repair?.id) return;
    try {
        const { data } = await axios.get(route('repairs.credentials', repair.id));
        if (requestId !== detailCredentialRequest || Number(detailRepair.value?.id) !== Number(repair.id)) return;
        detailCredentials.value = data;
    } catch {
        if (requestId === detailCredentialRequest && Number(detailRepair.value?.id) === Number(repair.id)) toast.error('Không tải được thông tin bảo mật của phiếu.');
    }
};

const closeRepairDetail = () => {
    detailRepair.value = null;
    detailCredentials.value = null;
    detailCredentialRequest += 1;
    const url = new URL(window.location.href);
    if (!url.searchParams.has('open_repair')) return;
    url.searchParams.delete('open_repair');
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true });
};

const openRepairDetail = (repair) => {
    if (!repair?.id) return;
    const url = new URL(window.location.href);
    url.searchParams.set('open_repair', String(repair.id));
    router.push({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true });
    detailRepair.value = repair;
    loadDetailCredentials(repair);
};

window.addEventListener('popstate', openRepairFromQuery);

onMounted(() => { openCreateFromQuery(); openRepairFromQuery(); });
watch(() => page.url, () => { openCreateFromQuery(); openRepairFromQuery(); });
watch(repairsData, openRepairFromQuery);

const onRepairCompleted = (repair) => {
    reload();
    onProgressPayment(repair);
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

const removeRepair = (repair) => {
    if (!isSuperAdmin.value || !repair?.id) return;
    const notes = [];
    if (repair.parts?.length) notes.push('linh kiện đã dùng sẽ được hoàn lại kho');
    if (['done', 'returned'].includes(repair.status)) notes.push('công nợ phát sinh từ phiếu (nếu có) sẽ được đảo lại');
    confirmBox.show({
        title: 'Xóa phiếu sửa chữa',
        message: `Xóa phiếu ${repair.code} (${repair.device_name})?` + (notes.length ? ` Khi xóa, ${notes.join(' và ')}.` : '') + ' Thao tác này không thể hoàn tác trên giao diện.',
        confirmText: 'Xóa phiếu',
        cancelText: 'Hủy',
        onConfirm: () => new Promise((resolve, reject) => {
            router.delete(route('repairs.destroy', repair.id), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Đã xóa phiếu sửa chữa');
                    closeRepairDetail();
                    reload();
                    resolve();
                },
                onError: (errors) => reject(new Error(Object.values(errors || {}).flat()[0] || 'Không thể xóa phiếu sửa chữa.')),
            });
        }),
    });
};

const detailProgressLabel = computed(() => {
    const repair = detailRepair.value;
    if (!repair || !['pending', 'repairing', 'done'].includes(repair.status) || !canProgress(repair)) return '';
    if (repair.status === 'done') return 'Thanh toán / trả máy';
    return repair.status === 'pending' ? 'Chuyển sang bước sửa' : 'Cập nhật sửa chữa';
});

const toggleSort = (field) => {
    if (sortBy.value === field) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortOrder.value = 'desc';
    }
};

const clearSearch = () => { search.value = ''; };
const quickStatuses = [
    { value: '', label: 'Tất cả', countKey: 'total_count' },
    { value: 'pending', label: 'Tiếp nhận', countKey: 'pending_count' },
    { value: 'repairing', label: 'Đang sửa', countKey: 'repairing_count' },
    { value: 'done', label: 'Hoàn tất sửa', countKey: 'done_count' },
    { value: 'returned', label: 'Đã trả khách', countKey: 'returned_count' },
    { value: 'cancelled', label: 'Hủy', countKey: 'cancelled_count' },
];
const resetFilters = () => {
    search.value = '';
    status.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    sortBy.value = 'created_at';
    sortOrder.value = 'desc';
};
const repairIssueText = (repair) => Array.isArray(repair.issue)
    ? repair.issue.filter(Boolean).join(', ')
    : String(repair.issue || '').trim();
const searchableText = (value) => {
    const text = String(value || '');
    let normalized = '';
    const sourceIndexes = [];
    Array.from(text).forEach((character, sourceIndex) => {
        const folded = character.normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLocaleLowerCase('vi-VN');
        Array.from(folded).forEach((foldedCharacter) => {
            normalized += foldedCharacter;
            sourceIndexes.push(sourceIndex);
        });
    });
    return { text, normalized, sourceIndexes };
};
const highlightedSearchSegments = (value) => {
    const searchable = searchableText(value);
    const keyword = searchableText(search.value.trim()).normalized;
    if (!keyword) return [{ text: searchable.text, match: false }];
    const startIndex = searchable.normalized.indexOf(keyword);
    if (startIndex < 0) return [{ text: searchable.text, match: false }];
    const start = searchable.sourceIndexes[startIndex];
    const end = searchable.sourceIndexes[startIndex + keyword.length - 1] + 1;
    return [
        { text: searchable.text.slice(0, start), match: false },
        { text: searchable.text.slice(start, end), match: true },
        { text: searchable.text.slice(end), match: false },
    ].filter((segment) => segment.text);
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
    <div class="space-y-3 text-slate-800">
        <DataPanel detached>
            <template #header>
                <div class="space-y-3 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:p-4">
                    <div class="flex flex-col items-stretch gap-2.5 lg:flex-row lg:items-center">
                        <div class="relative min-w-0 flex-1">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="search_repair" v-model="search" type="text" autocomplete="off" placeholder="Tìm mã phiếu, khách hàng, SĐT, thiết bị hoặc IMEI..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm font-medium text-slate-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100" />
                            <button v-if="search" type="button" class="absolute right-3 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-slate-200 text-slate-500 transition hover:bg-slate-300" title="Xóa từ khóa" @click="clearSearch"><X class="h-3 w-3" /></button>
                        </div>
                        <div class="flex min-w-0 items-center gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1" :class="loadingRepairs ? 'opacity-70' : ''">
                            <button v-for="item in quickStatuses" :key="item.value || 'all'" type="button" class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition sm:px-3" :class="status === item.value ? (item.value === 'pending' ? 'bg-amber-500 text-white shadow-sm' : item.value === 'repairing' ? 'bg-violet-600 text-white shadow-sm' : item.value === 'done' ? 'bg-emerald-600 text-white shadow-sm' : item.value === 'returned' ? 'bg-slate-700 text-white shadow-sm' : item.value === 'cancelled' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white text-slate-900 shadow-sm') : 'text-slate-600 hover:bg-white/70 hover:text-slate-900'" @click="status = item.value"><span>{{ item.label }}</span><span class="rounded-full px-1.5 py-0.5 text-[10px] leading-none" :class="status === item.value ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-600'">{{ statsData?.[item.countKey] ?? 0 }}</span></button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition lg:flex-none" :class="isFilterExpanded ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="isFilterExpanded = !isFilterExpanded"><SlidersHorizontal class="h-3.5 w-3.5" />{{ isFilterExpanded ? 'Ẩn lọc' : 'Lọc nâng cao' }}</button>
                            <button v-if="can('repairs.create')" type="button" @click="openCreate" class="inline-flex flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl bg-blue-600 px-3.5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700 lg:flex-none"><span class="text-base leading-none">+</span> Nhận máy mới</button>
                        </div>
                    </div>
                    <div v-if="isFilterExpanded" class="grid grid-cols-1 gap-2 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_1fr_1fr_auto]">
                        <FloatingSelect v-model="status" label="Trạng thái phiếu" id="repair_status_filter" :options="statusOptions" :show-placeholder="false" option-label="label" option-value="value" />
                        <FloatingInput v-model="dateFrom" type="date" label="Từ ngày" id="repair_date_from" />
                        <FloatingInput v-model="dateTo" type="date" label="Đến ngày" id="repair_date_to" />
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 text-xs text-slate-500 sm:col-span-2 lg:col-span-1"><span>Hiển thị <strong class="text-slate-800">{{ repairsData?.data?.length || 0 }} / {{ repairsData?.total || 0 }}</strong></span><button type="button" class="inline-flex items-center gap-1 font-semibold text-slate-600 hover:text-emerald-700" @click="resetFilters"><RefreshCw class="h-3.5 w-3.5" /> Đặt lại</button></div>
                    </div>
                </div>
            </template>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden overflow-x-auto md:block">
                    <table class="app-data-table w-full min-w-[900px] border-collapse text-left text-xs">
                        <thead><tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th v-for="column in [{ key: 'code', label: 'Mã phiếu' }, { key: 'customer', label: 'Khách hàng' }, { key: 'device_name', label: 'Thiết bị / IMEI' }, { key: 'issue', label: 'Lỗi máy' }, { key: 'created_at', label: 'Ngày nhận' }, { key: 'status', label: 'Trạng thái & thao tác' }]" :key="column.key" class="cursor-pointer px-3.5 py-2.5 transition hover:bg-slate-100" :class="column.key === 'status' ? 'text-center' : ''" @click="!['customer', 'issue'].includes(column.key) && toggleSort(column.key)"><div class="flex items-center gap-1" :class="column.key === 'status' ? 'justify-center' : ''"><span>{{ column.label }}</span><span v-if="sortBy === column.key" class="text-emerald-700">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span></div></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <tr v-for="repair in repairsData?.data" :key="repair.id" tabindex="0" class="cursor-pointer transition-colors hover:bg-emerald-50/70" title="Bấm để xem chi tiết phiếu" @click="openRepairDetail(repair)" @keydown.enter.self="openRepairDetail(repair)">
                                <td class="px-3.5 py-2.5"><button type="button" class="font-bold text-emerald-700 hover:text-emerald-900 hover:underline"><template v-for="(segment, index) in highlightedSearchSegments(`#${repair.code}`)" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></button></td>
                                <td class="px-3.5 py-2.5"><div class="font-bold text-slate-900"><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.name || repair.customer_name || 'Khách lẻ')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-400"><UserRound class="h-3 w-3" /><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.phone || repair.contact_phone || '—')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></td>
                                <td class="px-3.5 py-2.5"><div class="font-bold text-slate-800"><template v-for="(segment, index) in highlightedSearchSegments(repair.device_name)" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex items-center gap-1 font-mono text-[10px] text-slate-400"><Smartphone class="h-3 w-3" /><template v-for="(segment, index) in highlightedSearchSegments(repair.imei || repair.serial || 'Không IMEI')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></td>
                                <td class="max-w-[220px] px-3.5 py-2.5"><span v-if="repairIssueText(repair)" class="block truncate text-[11px] text-rose-700" :title="repairIssueText(repair)"><template v-for="(segment, index) in highlightedSearchSegments(repairIssueText(repair))" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></span><span v-else class="text-[11px] text-slate-400">Chưa ghi nhận lỗi</span></td>
                                <td class="whitespace-nowrap px-3.5 py-2.5 text-[11px] text-slate-600">{{ repair.received_at || repair.created_at || '—' }}</td>
                                <td class="px-3 py-2"><div class="flex min-w-[190px] items-center justify-center gap-2">
                                    <span :class="['inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[10px] font-bold', statusClasses[repair.status]]"><span class="h-1.5 w-1.5 rounded-full bg-current" />{{ statusLabels[repair.status] || repair.status }}</span>
                                    <span v-if="repair.waiting_for_parts" class="max-w-[120px] truncate text-[9px] font-semibold text-amber-700" :title="repair.waiting_mode === 'wait_repair' ? 'Tạm chờ sửa' : `Chờ ${repair.parts_needed || 'linh kiện'}`">{{ repair.waiting_mode === 'wait_repair' ? 'Tạm chờ sửa' : `Chờ ${repair.parts_needed || 'linh kiện'}` }}<span v-if="repair.expected_days !== null"> · {{ repair.expected_days }} ngày</span></span>
                                    <button v-if="['pending', 'repairing', 'done'].includes(repair.status) && canProgress(repair)" type="button" class="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-[10px] font-bold text-white shadow-sm transition hover:bg-blue-700" @click.stop="openRepairWorkflow(repair)"><Play v-if="repair.status === 'pending'" class="h-3 w-3 fill-current" /><Wrench v-else-if="repair.status === 'repairing'" class="h-3 w-3" /><RotateCcw v-else class="h-3 w-3" />Tiếp tục</button>
                                </div></td>
                            </tr>
                            <tr v-if="!repairsData?.data?.length"><td colspan="6" class="px-4 py-12 text-center"><FileText class="mx-auto mb-3 h-10 w-10 text-slate-300" /><p class="text-sm font-bold text-slate-700">Không tìm thấy phiếu sửa nào</p><p class="mt-1 text-xs text-slate-400">Thử thay đổi bộ lọc hoặc từ khóa</p></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-2.5 bg-slate-50/70 p-2.5 md:hidden">
                    <article v-for="repair in repairsData?.data" :key="`mobile-${repair.id}`" class="space-y-3 rounded-xl border border-slate-200 border-l-4 bg-white p-3.5 shadow-sm transition hover:border-slate-300 hover:shadow-md active:bg-slate-50" :class="repair.status === 'pending' ? 'border-l-amber-400' : repair.status === 'repairing' ? 'border-l-violet-500' : repair.status === 'done' ? 'border-l-emerald-500' : repair.status === 'returned' ? 'border-l-slate-400' : 'border-l-rose-400'" @click="openRepairDetail(repair)">
                        <div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-2.5 py-2"><button type="button" class="min-w-0 truncate text-left text-sm font-extrabold text-emerald-700" @click.stop="openRepairDetail(repair)">#{{ repair.code }}</button><div class="flex shrink-0 items-center gap-2"><span :class="['rounded-full border px-2 py-1 text-[10px] font-bold', statusClasses[repair.status]]">{{ statusLabels[repair.status] || repair.status }}</span><button v-if="['pending', 'repairing', 'done'].includes(repair.status) && canProgress(repair)" type="button" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-[10px] font-bold text-white hover:bg-blue-700" @click.stop="openRepairWorkflow(repair)"><Play v-if="repair.status === 'pending'" class="h-3 w-3 fill-current" /><Wrench v-else-if="repair.status === 'repairing'" class="h-3 w-3" /><RotateCcw v-else class="h-3 w-3" />Tiếp tục</button></div></div>
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="truncate text-sm font-bold text-slate-900"><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.name || repair.customer_name || 'Khách lẻ')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400"><UserRound class="h-3 w-3" /><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.phone || repair.contact_phone || '—')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></div><div class="min-w-0 text-right"><div class="truncate text-xs font-bold text-slate-800"><template v-for="(segment, index) in highlightedSearchSegments(repair.device_name)" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex truncate font-mono text-[10px] text-slate-500">IMEI: <template v-for="(segment, index) in highlightedSearchSegments(repair.imei || repair.serial || '—')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></div></div>
                        <div class="rounded-lg border border-rose-100 bg-rose-50/60 px-2.5 py-2 text-[10px]" :class="repairIssueText(repair) ? 'text-rose-800' : 'text-slate-500'"><span class="font-bold">Lỗi máy: </span><template v-if="repairIssueText(repair)"><template v-for="(segment, index) in highlightedSearchSegments(repairIssueText(repair))" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></template><span v-else>Chưa ghi nhận lỗi</span></div>
                        <div v-if="repair.waiting_for_parts" class="rounded-lg bg-amber-50 px-2.5 py-1.5 text-[10px] font-semibold text-amber-800">{{ repair.waiting_mode === 'wait_repair' ? 'Tạm chờ sửa' : `Chờ ${repair.parts_needed || 'linh kiện'}` }}<span v-if="repair.expected_days !== null"> · Dự kiến {{ repair.expected_days }} ngày</span></div>
                        <div class="border-t border-slate-100 pt-2 text-[10px] text-slate-500">Ngày nhận: {{ repair.received_at || repair.created_at || '—' }}</div>
                    </article>
                    <div v-if="!repairsData?.data?.length" class="p-10 text-center"><FileText class="mx-auto mb-3 h-9 w-9 text-slate-300" /><p class="text-sm font-bold text-slate-700">Không tìm thấy phiếu phù hợp</p><p class="mt-1 text-xs text-slate-400">Thử thay đổi bộ lọc hoặc từ khóa</p></div>
                </div>
                <PaginationBar :data="repairsData" :filters="activeFilters" />
            </div>
        </DataPanel>

        <RepairDetailModal
            v-if="detailRepair"
            :repair="detailRepair"
            :credentials="detailCredentials"
            :can-view-credentials="canViewCredentials"
            :can-print="can('repairs.view')"
            :can-edit="can('repairs.edit')"
            :can-delete="isSuperAdmin"
            :progress-label="detailProgressLabel"
            :status-labels="statusLabels"
            :status-classes="statusClasses"
            @close="closeRepairDetail"
            @delete="removeRepair(detailRepair)"
            @print="printPreviewUrl = route('repairs.print', detailRepair.id)"
            @edit="openRepairWorkflow(detailRepair, 'intake'); detailRepair = null"
            @progress="openRepairWorkflow(detailRepair); detailRepair = null"
        />
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
        <PrintPreviewModal :show="Boolean(printPreviewUrl)" title="Xem trước phiếu sửa chữa" :src="printPreviewUrl" @close="printPreviewUrl = ''" />
    </div>
</template>
