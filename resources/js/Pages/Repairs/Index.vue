<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import PaginationBar from '@/Components/UI/PaginationBar.vue';
import { useAutoRefresh } from '@/Composables/useAutoRefresh';
import DataPanel from '@/Components/UI/DataPanel.vue';
import ListToolbar from '@/Components/UI/ListToolbar.vue';
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue';
import DateRangeFilter from '@/Components/UI/DateRangeFilter.vue';
import ActionButton from '@/Components/UI/ActionButton.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import { openModal } from '@/Stores/modal';
import RepairFormModal from './RepairFormModal.vue';
import RepairDetailModal from './RepairDetailModal.vue';
import RepairAccessoriesNotice from './RepairAccessoriesNotice.vue';
import { useRepairAccess, fetchRepairCredentials } from '@/Composables/useRepairAccess';
import axios from 'axios';
import { toast } from 'vue-sonner';
import api from '@/Services/api';
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue';
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue';
import { AlertTriangle, ClipboardList, FileText, Plus, UserRound, Smartphone, Wrench, RotateCcw, Play, XCircle } from 'lucide-vue-next';
import { REPAIR_STATUS_CLASSES, REPAIR_STATUS_LABELS } from '@/utils/repairStatus';

const props = defineProps({
    repairs: Object,
    filters: Object,
    stats: Object,
    cancelReasonSuggestions: { type: Array, default: () => [] },
});
const repairsData = ref(props.repairs);
const statsData = ref(props.stats || {});
const perPage = ref(Number(props.filters?.per_page ?? 20));
const currentPage = ref(Number(props.repairs?.current_page ?? 1));
const loadingRepairs = ref(false);
const page = usePage();
const { can, canViewCredentials, canProgress, progressLabel, removeRepair: removeRepairWithConfirm } = useRepairAccess();

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
const returnConfirmRepair = ref(null);
const returnSubmitting = ref(false);
// Phiếu truyền vào từ quy trình sửa có thể không kèm phụ kiện, nên lấy thêm từ danh sách đang hiển thị.
const accessoriesOf = (repair) => repair?.accessories
    ?? repairsData.value?.data?.find((item) => Number(item.id) === Number(repair?.id))?.accessories
    ?? [];
const cancelRepairModalOpen = ref(false);
const repairToCancel = ref(null);
const cancelReason = ref('');
const cancelSubmitting = ref(false);
const cancelReasonSuggestions = ref(props.cancelReasonSuggestions || []);
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

const requestRepairs = async (pageNumber = 1, { fresh = false } = {}) => {
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
        page: pageNumber,
        ...(fresh ? { _: Date.now() } : {}),
    };

    try {
        const { data } = await api.get(route('repairs.index'), {
            params,
            signal: repairsRequestController.signal,
        });
        if (requestId !== repairsRequestId) return;
        repairsData.value = data.repairs;
        statsData.value = data.stats || {};
        cancelReasonSuggestions.value = data.cancelReasonSuggestions || cancelReasonSuggestions.value;
        currentPage.value = Number(data.repairs?.current_page ?? pageNumber);
        if (!data.repairs?.data?.length && currentPage.value > 1) {
            currentPage.value -= 1;
            return requestRepairs(currentPage.value, { fresh });
        }
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

watch(() => props.repairs, (value) => {
    repairsData.value = value;
    currentPage.value = Number(value?.current_page ?? 1);
});
watch(() => props.stats, (value) => { statsData.value = value || {}; });
watch(() => props.cancelReasonSuggestions, (value) => { cancelReasonSuggestions.value = value || []; });
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

// Tự cập nhật danh sách + thống kê khi phiếu sửa/khách hàng/linh kiện đổi (do bất kỳ ai), giữ bộ lọc và trang đang xem.
useAutoRefresh({
    channels: ['repairs', 'customers', 'products'],
    keys: ['repairs', 'stats'],
    apply: (next) => {
        if ('repairs' in next) {
            repairsData.value = next.repairs;
            currentPage.value = Number(next.repairs?.current_page ?? currentPage.value);
        }
        if ('stats' in next) statsData.value = next.stats || {};
    },
    enabled: () => !loadingRepairs.value && !filterTimeout,
});

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
    if (repair) {
        const isDifferentRepair = Number(detailRepair.value?.id) !== repairId;
        detailRepair.value = repair;
        if (isDifferentRepair) {
            detailCredentials.value = null;
            loadDetailCredentials(repair);
        }
    } else if (Number(detailRepair.value?.id) !== repairId) {
        detailRepair.value = null;
        detailCredentials.value = null;
    }
};

let detailCredentialRequest = 0;
const loadDetailCredentials = async (repair) => {
    const requestId = ++detailCredentialRequest;
    detailCredentials.value = null;
    if (!canViewCredentials.value || !repair?.id) return;
    const isCurrent = () => requestId === detailCredentialRequest && Number(detailRepair.value?.id) === Number(repair.id);
    const data = await fetchRepairCredentials(repair.id, isCurrent);
    if (data && isCurrent()) detailCredentials.value = data;
};

const closeRepairDetail = () => {
    detailRepair.value = null;
    detailCredentials.value = null;
    detailCredentialRequest += 1;
    const url = new URL(window.location.href);
    if (!url.searchParams.has('open_repair')) return;
    url.searchParams.delete('open_repair');
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
};

// Chuyển từ màn chi tiết sang quy trình sửa: phải đóng chi tiết đúng cách (xóa cả ?open_repair trên URL),
// nếu không khi danh sách tải lại màn chi tiết sẽ tự mở lại và che quy trình đang làm.
const openWorkflowFromDetail = (startAt) => {
    const repair = detailRepair.value;
    closeRepairDetail();
    if (repair) openRepairWorkflow(repair, startAt);
};

// Ảnh chụp props hiện tại của trang, lấy từ dữ liệu cục bộ mới nhất (danh sách/thống kê đã đồng bộ từ server qua axios).
const inertiaPropsSnapshot = () => ({
    ...page.props,
    repairs: repairsData.value,
    stats: statsData.value,
    cancelReasonSuggestions: cancelReasonSuggestions.value,
});

// Ghi dữ liệu mới nhất vào props/history hiện tại của Inertia (không gọi server) để các lần điều hướng
// phía client sau đó không khôi phục lại props cũ.
const syncInertiaProps = () => {
    const url = new URL(window.location.href);
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, props: inertiaPropsSnapshot(), preserveState: true, preserveScroll: true });
};

const openRepairDetail = (repair) => {
    if (!repair?.id) return;
    const url = new URL(window.location.href);
    url.searchParams.set('open_repair', String(repair.id));
    // Phải truyền props hiện tại của trang: nếu không, Inertia dùng lại props cũ lúc tải trang (phiếu chưa hủy)
    // và watch(props.repairs) sẽ ghi đè dữ liệu đã cập nhật, làm phiếu hủy quay về trạng thái cũ.
    router.push({ url: `${url.pathname}${url.search}${url.hash}`, props: inertiaPropsSnapshot(), preserveState: true, preserveScroll: true });
    detailRepair.value = repair;
    loadDetailCredentials(repair);
    refreshRepairDetail(repair.id);
};

// Mỗi lần mở chi tiết đều lấy lại bản ghi đích từ máy chủ. Điều này tránh mở lại
// một đối tượng cũ trong danh sách ngay sau khi trạng thái vừa thay đổi (như hủy phiếu).
const refreshRepairDetail = async (repairId) => {
    try {
        const { data } = await api.get(route('repairs.index'), {
            params: { open_repair: repairId, per_page: 10, _: Date.now() },
            headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' },
        });
        const latestRepair = data.repairs?.data?.find((item) => Number(item.id) === Number(repairId));
        if (!latestRepair) return null;

        if (repairsData.value?.data) {
            repairsData.value = {
                ...repairsData.value,
                data: repairsData.value.data.map((item) => Number(item.id) === Number(repairId) ? latestRepair : item),
            };
        }
        if (Number(detailRepair.value?.id) === Number(repairId)) detailRepair.value = latestRepair;
        return latestRepair;
    } catch {
        // Vẫn giữ dữ liệu đang có để người dùng không bị đóng modal chỉ vì một lần tải lại thất bại.
        return null;
    }
};

window.addEventListener('popstate', openRepairFromQuery);

onMounted(() => {
    openCreateFromQuery();
    openRepairFromQuery();
});
watch(() => page.url, () => { openCreateFromQuery(); openRepairFromQuery(); });
watch(repairsData, openRepairFromQuery);

const onRepairCompleted = (repair) => {
    // Prepare the checkout modal before refreshing the list so the completion
    // flow remains open and the newly completed ticket is immediately payable.
    onProgressPayment(repair);
};

const onProgressPayment = async (repair) => {
    if (Number(repair.final_cost || 0) <= 0) {
        // Không phát sinh thanh toán vẫn phải cho nhân viên đối chiếu phụ kiện trước khi giao máy.
        returnConfirmRepair.value = repair;
        return;
    }
    paymentRepair.value = repair;
    requestRepairs(currentPage.value);
};

const confirmZeroReturn = async () => {
    const repair = returnConfirmRepair.value;
    if (!repair || returnSubmitting.value) return;
    returnSubmitting.value = true;
    try {
        await axios.post(route('repairs.return', repair.id), {
            payment_method: 'cash',
            paid_amount: 0,
            note: repair.warranty_covered_amount > 0 ? 'Toàn bộ chi phí được bảo hành' : 'Không phát sinh thanh toán',
        });
        toast.success('Đã xác nhận trả máy, không phát sinh thanh toán');
        returnConfirmRepair.value = null;
        requestRepairs(currentPage.value);
    } catch (error) {
        toast.error(Object.values(error.response?.data?.errors || {}).flat()[0] || 'Không thể xác nhận trả máy');
    } finally {
        returnSubmitting.value = false;
    }
};

const removeRepair = (repair) => removeRepairWithConfirm(repair, () => {
    closeRepairDetail();
    return requestRepairs(currentPage.value);
});

const openCancelRepair = (repair) => {
    if (!repair?.id || repair.status === 'cancelled' || !can('repairs.edit')) return;
    repairToCancel.value = repair;
    cancelReason.value = '';
    cancelRepairModalOpen.value = true;
};
const closeCancelRepairModal = () => {
    if (cancelSubmitting.value) return;
    cancelRepairModalOpen.value = false;
    repairToCancel.value = null;
    cancelReason.value = '';
};

const repairStatusCountKey = {
    pending: 'pending_count',
    repairing: 'repairing_count',
    done: 'done_count',
    returned: 'returned_count',
    cancelled: 'cancelled_count',
};

const markRepairCancelled = (repair, reason) => {
    const now = new Date().toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
    const updatedRepair = {
        ...repair,
        status: 'cancelled',
        waiting_for_parts: false,
        waiting_mode: 'repair_now',
        parts_needed: null,
        expected_days: null,
        timelines: [{
            id: `cancelled-${repair.id}-${Date.now()}`,
            title: ['done', 'returned'].includes(repair.status)
                ? 'Hủy phiếu đã hoàn tất · Đã hoàn kho và đảo công nợ'
                : 'Đã hủy phiếu sửa',
            description: reason,
            status: 'cancelled',
            issue: [],
            waiting_for_parts: false,
            waiting_mode: 'repair_now',
            parts_needed: null,
            expected_days: null,
            images: [],
            created_at: now,
            user: 'Bạn',
        }, ...(repair.timelines || [])],
    };

    if (repairsData.value?.data) {
        repairsData.value = {
            ...repairsData.value,
            data: repairsData.value.data.map((item) => Number(item.id) === Number(repair.id) ? updatedRepair : item),
        };
    }
    if (Number(detailRepair.value?.id) === Number(repair.id)) detailRepair.value = updatedRepair;

    const previousCountKey = repairStatusCountKey[repair.status];
    if (previousCountKey && repair.status !== 'cancelled') {
        statsData.value = {
            ...statsData.value,
            [previousCountKey]: Math.max(0, Number(statsData.value?.[previousCountKey] || 0) - 1),
            cancelled_count: Number(statsData.value?.cancelled_count || 0) + 1,
        };
    }
};

const cancelRepair = async () => {
    const repair = repairToCancel.value;
    const reason = cancelReason.value.trim();
    if (!repair || reason.length < 5) {
        toast.error('Vui lòng nhập lý do hủy tối thiểu 5 ký tự.');
        return;
    }
    cancelSubmitting.value = true;
    try {
        if (['done', 'returned'].includes(repair.status)) {
            await axios.post(route('repairs.cancel-completed', repair.id), { reason });
            toast.success('Đã hủy phiếu, hoàn kho linh kiện và đảo công nợ.');
        } else {
            await axios.patch(route('repairs.update-status', repair.id), {
                status: 'cancelled',
                description: reason,
            });
            toast.success('Đã hủy phiếu sửa chữa.');
        }
        cancelRepairModalOpen.value = false;
        repairToCancel.value = null;
        cancelReason.value = '';
        // Phản hồi ngay trên danh sách và modal chi tiết; lần tải sau là để đồng bộ dữ liệu chuẩn từ server.
        markRepairCancelled(repair, reason);
        await refreshRepairDetail(repair.id);
        await requestRepairs(currentPage.value, { fresh: true });
        syncInertiaProps();
    } catch (error) {
        toast.error(Object.values(error.response?.data?.errors || {}).flat()[0] || 'Không thể hủy phiếu sửa');
    } finally {
        cancelSubmitting.value = false;
    }
};

const cancelReasonOptions = computed(() => cancelReasonSuggestions.value
    .filter((item) => String(item?.reason || '').trim())
    .slice()
    .sort((a, b) => Number(b.usage_count || 0) - Number(a.usage_count || 0) || String(a.reason).localeCompare(String(b.reason), 'vi')));

const detailProgressLabel = computed(() => progressLabel(detailRepair.value));

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
    { value: 'cancelled', label: 'Đã hủy', countKey: 'cancelled_count' },
];
const statusOptions = computed(() => quickStatuses.map(item => ({ value: item.value, label: `${item.label} (${statsData.value?.[item.countKey] ?? 0})` })));
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

const statusClasses = REPAIR_STATUS_CLASSES;
const statusLabels = REPAIR_STATUS_LABELS;
const repairStatusLabel = (repair) => {
    if (repair?.status === 'repairing' && repair.waiting_for_parts) {
        return repair.waiting_mode === 'wait_repair' ? 'Tạm chờ sửa' : 'Chờ linh kiện';
    }
    return statusLabels[repair?.status] || repair?.status;
};
const repairStatusClass = (repair) => {
    if (repair?.status === 'repairing' && repair.waiting_for_parts) {
        return repair.waiting_mode === 'wait_repair'
            ? 'bg-indigo-50 text-indigo-700 border-indigo-200'
            : 'bg-amber-50 text-amber-700 border-amber-200';
    }
    return statusClasses[repair?.status];
};

const confirmPayment = async (payment) => {
    paymentLoading.value = true;
    try {
        await axios.post(route('repairs.return', paymentRepair.value.id), payment);
        toast.success('Đã thanh toán và trả máy cho khách');
        paymentRepair.value = null;
        requestRepairs(currentPage.value);
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
                    <ListToolbar v-model="search" search-id="search_repair" :loading="loadingRepairs" placeholder="Tìm mã phiếu, khách hàng, SĐT, thiết bị hoặc IMEI..." @clear="clearSearch">
                        <template #filters>
                            <ListFilterSelect v-model="status" label="Trạng thái" :show-placeholder="false" :options="statusOptions" />
                            <DateRangeFilter v-model:from="dateFrom" v-model:to="dateTo" />
                        </template>
                        <template #actions><ActionButton v-if="can('repairs.create')" @click="openCreate"><Plus :size="16" />Nhận máy mới</ActionButton></template>
                    </ListToolbar>
            </template>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden overflow-x-auto md:block">
                    <table class="m-cards app-data-table w-full min-w-[900px] border-collapse text-left text-xs">
                        <thead><tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th v-for="column in [{ key: 'code', label: 'Mã phiếu' }, { key: 'customer', label: 'Khách hàng' }, { key: 'device_name', label: 'Thiết bị / IMEI' }, { key: 'issue', label: 'Lỗi máy' }, { key: 'created_at', label: 'Ngày nhận' }, { key: 'status', label: 'Trạng thái & thao tác' }]" :key="column.key" class="cursor-pointer px-3.5 py-2.5 transition hover:bg-slate-100" :class="column.key === 'status' ? 'text-center' : ''" @click="!['customer', 'issue'].includes(column.key) && toggleSort(column.key)"><div class="flex items-center gap-1" :class="column.key === 'status' ? 'justify-center' : ''"><span>{{ column.label }}</span><span v-if="sortBy === column.key" class="text-emerald-700">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span></div></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <tr v-for="repair in repairsData?.data" :key="repair.id" tabindex="0" class="cursor-pointer transition-colors hover:bg-emerald-50/70" title="Bấm để xem chi tiết phiếu" @click="openRepairDetail(repair)" @keydown.enter.self="openRepairDetail(repair)">
                                <td class="px-3.5 py-2.5"><button type="button" class="font-bold text-emerald-700 hover:text-emerald-900 hover:underline"><template v-for="(segment, index) in highlightedSearchSegments(`#${repair.code}`)" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></button></td>
                                <td class="px-3.5 py-2.5"><div class="font-bold text-slate-900"><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.name || repair.customer_name || 'Khách lẻ')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-400"><UserRound class="h-3 w-3" /><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.phone || repair.contact_phone || '—')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></td>
                                <td class="px-3.5 py-2.5"><div class="font-bold text-slate-800"><template v-for="(segment, index) in highlightedSearchSegments(repair.device_name)" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex items-center gap-1 font-mono text-[10px] text-slate-400"><Smartphone class="h-3 w-3" /><template v-for="(segment, index) in highlightedSearchSegments(repair.imei || repair.serial || 'Không IMEI')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></td>
                                <td class="max-w-[220px] px-3.5 py-2.5"><span v-if="repairIssueText(repair)" class="block truncate text-[11px] text-slate-600" :title="repairIssueText(repair)"><template v-for="(segment, index) in highlightedSearchSegments(repairIssueText(repair))" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></span><span v-else class="text-[11px] text-slate-400">Chưa ghi nhận tình trạng</span></td>
                                <td class="whitespace-nowrap px-3.5 py-2.5 text-[11px] text-slate-600">{{ repair.received_at || repair.created_at || '—' }}</td>
                                <td class="px-3 py-2"><div class="flex min-w-[190px] items-center justify-center gap-2">
                                    <span :class="['inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[10px] font-bold', repairStatusClass(repair)]"><span class="h-1.5 w-1.5 rounded-full bg-current" />{{ repairStatusLabel(repair) }}</span>
                                    <span v-if="repair.waiting_for_parts && repair.waiting_mode !== 'wait_repair'" class="max-w-[120px] truncate text-[9px] font-semibold text-amber-700" :title="`Chờ ${repair.parts_needed || 'linh kiện'}`">Chờ {{ repair.parts_needed || 'linh kiện' }}<span v-if="repair.expected_days !== null"> · {{ repair.expected_days }} ngày</span></span>
                                    <button v-if="['pending', 'repairing', 'done'].includes(repair.status) && canProgress(repair)" type="button" class="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-[10px] font-bold text-white shadow-sm transition hover:bg-blue-700" @click.stop="openRepairWorkflow(repair)"><Play v-if="repair.status === 'pending'" class="h-3 w-3 fill-current" /><Wrench v-else-if="repair.status === 'repairing'" class="h-3 w-3" /><RotateCcw v-else class="h-3 w-3" />Tiếp tục</button>
                                    <button v-if="repair.status !== 'cancelled' && can('repairs.edit')" type="button" class="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[10px] font-bold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100" title="Hủy phiếu sửa" @click.stop="openCancelRepair(repair)"><XCircle class="h-3.5 w-3.5" />Hủy phiếu</button>
                                </div></td>
                            </tr>
                            <tr v-if="!repairsData?.data?.length"><td colspan="6" class="px-4 py-12 text-center"><FileText class="mx-auto mb-3 h-10 w-10 text-slate-300" /><p class="text-sm font-bold text-slate-700">Không tìm thấy phiếu sửa nào</p><p class="mt-1 text-xs text-slate-400">Thử thay đổi bộ lọc hoặc từ khóa</p></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-2.5 md:hidden">
                    <article v-for="repair in repairsData?.data" :key="`mobile-${repair.id}`" class="space-y-3 rounded-xl border border-slate-200 border-l-4 bg-white p-3.5 shadow-sm transition hover:border-slate-300 hover:shadow-md active:bg-slate-50" :class="repair.status === 'pending' ? 'border-l-amber-400' : repair.status === 'repairing' ? 'border-l-violet-500' : repair.status === 'done' ? 'border-l-emerald-500' : repair.status === 'returned' ? 'border-l-slate-400' : 'border-l-rose-400'" @click="openRepairDetail(repair)">
                        <div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-2.5 py-2"><button type="button" class="min-w-0 truncate text-left text-sm font-extrabold text-emerald-700" @click.stop="openRepairDetail(repair)">#{{ repair.code }}</button><div class="flex shrink-0 items-center gap-2"><span :class="['rounded-full border px-2 py-1 text-[10px] font-bold', repairStatusClass(repair)]">{{ repairStatusLabel(repair) }}</span><button v-if="['pending', 'repairing', 'done'].includes(repair.status) && canProgress(repair)" type="button" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-[10px] font-bold text-white hover:bg-blue-700" @click.stop="openRepairWorkflow(repair)"><Play v-if="repair.status === 'pending'" class="h-3 w-3 fill-current" /><Wrench v-else-if="repair.status === 'repairing'" class="h-3 w-3" /><RotateCcw v-else class="h-3 w-3" />Tiếp tục</button><button v-if="repair.status !== 'cancelled' && can('repairs.edit')" type="button" class="inline-flex h-7 items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 text-[10px] font-bold text-rose-700 active:bg-rose-100" @click.stop="openCancelRepair(repair)"><XCircle class="h-3.5 w-3.5" />Hủy</button></div></div>
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="truncate text-sm font-bold text-slate-900"><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.name || repair.customer_name || 'Khách lẻ')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400"><UserRound class="h-3 w-3" /><template v-for="(segment, index) in highlightedSearchSegments(repair.customer?.phone || repair.contact_phone || '—')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></div><div class="min-w-0 text-right"><div class="truncate text-xs font-bold text-slate-800"><template v-for="(segment, index) in highlightedSearchSegments(repair.device_name)" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div><div class="mt-0.5 flex truncate font-mono text-[10px] text-slate-500">IMEI: <template v-for="(segment, index) in highlightedSearchSegments(repair.imei || repair.serial || '—')" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></div></div></div>
                        <div class="flex items-start gap-1.5 rounded-lg border border-sky-100 bg-sky-50/70 px-2.5 py-2 text-[10px] text-slate-600"><ClipboardList class="mt-0.5 h-3.5 w-3.5 shrink-0 text-sky-600" /><div class="min-w-0"><span class="font-bold text-sky-800">Tình trạng tiếp nhận: </span><template v-if="repairIssueText(repair)"><template v-for="(segment, index) in highlightedSearchSegments(repairIssueText(repair))" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><span v-else>{{ segment.text }}</span></template></template><span v-else class="text-slate-400">Chưa ghi nhận tình trạng</span></div></div>
                        <div v-if="repair.waiting_for_parts && repair.waiting_mode !== 'wait_repair'" class="rounded-lg bg-amber-50 px-2.5 py-1.5 text-[10px] font-semibold text-amber-800">Chờ {{ repair.parts_needed || 'linh kiện' }}<span v-if="repair.expected_days !== null"> · Dự kiến {{ repair.expected_days }} ngày</span></div>
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
            @cancel-completed="openCancelRepair(detailRepair)"
            @print="printPreviewUrl = route('repairs.print', detailRepair.id)"
            @edit="openWorkflowFromDetail('intake')"
            @progress="openWorkflowFromDetail('repair')"
        />
        <BaseModal v-if="cancelRepairModalOpen" title="Hủy phiếu sửa chữa" size="md" @close="closeCancelRepairModal">
            <div class="space-y-4 p-1">
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <p class="text-xs leading-relaxed text-amber-900">
                        <template v-if="['done', 'returned'].includes(repairToCancel?.status)">Hủy phiếu <strong>{{ repairToCancel?.code }}</strong> sẽ hoàn kho linh kiện và đảo công nợ/thanh toán liên quan.</template>
                        <template v-else>Phiếu <strong>{{ repairToCancel?.code }}</strong> sẽ chuyển sang trạng thái đã hủy và không thể tiếp tục quy trình sửa.</template>
                    </p>
                </div>
                <div>
                    <label for="repair_cancel_reason" class="mb-1.5 block text-xs font-bold text-slate-700">Lý do hủy phiếu <span class="text-rose-600">*</span></label>
                    <textarea id="repair_cancel_reason" v-model="cancelReason" rows="3" :disabled="cancelSubmitting" placeholder="Nhập lý do hủy phiếu..." class="w-full resize-none rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-rose-400 focus:ring-2 focus:ring-rose-100 disabled:bg-slate-50" />
                    <p class="mt-1 text-[11px] text-slate-500">Tối thiểu 5 ký tự. Lý do được lưu vào lịch sử phiếu.</p>
                </div>
                <div v-if="cancelReasonOptions.length" class="space-y-1.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Lý do đã dùng · ưu tiên dùng nhiều nhất</p>
                    <div class="flex max-h-32 flex-wrap content-start gap-1.5 overflow-y-auto pr-1">
                        <button v-for="item in cancelReasonOptions" :key="item.reason" type="button" :disabled="cancelSubmitting" class="rounded-lg border px-2.5 py-1.5 text-left text-[11px] font-medium transition" :class="cancelReason === item.reason ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-600 hover:border-rose-200 hover:bg-rose-50'" @click="cancelReason = item.reason">
                            {{ item.reason }}
                        </button>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" :disabled="cancelSubmitting" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50" @click="closeCancelRepairModal">Quay lại</button>
                    <button type="button" :disabled="cancelSubmitting || cancelReason.trim().length < 5" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50" @click="cancelRepair"><XCircle class="h-3.5 w-3.5" />{{ cancelSubmitting ? 'Đang hủy...' : 'Xác nhận hủy phiếu' }}</button>
                </div>
            </div>
        </BaseModal>
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
        >
            <template #notice>
                <RepairAccessoriesNotice :accessories="accessoriesOf(paymentRepair)" />
            </template>
        </CheckoutModal>
        <BaseModal v-if="returnConfirmRepair" title="Xác nhận trả máy" size="md" @close="!returnSubmitting && (returnConfirmRepair = null)">
            <div class="space-y-4">
                <p class="text-sm text-slate-600">Phiếu <strong>{{ returnConfirmRepair.code }}</strong> không phát sinh thanh toán. Kiểm tra phụ kiện trước khi giao máy cho khách.</p>
                <RepairAccessoriesNotice :accessories="accessoriesOf(returnConfirmRepair)" />
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" :disabled="returnSubmitting" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50" @click="returnConfirmRepair = null">Quay lại</button>
                    <button type="button" :disabled="returnSubmitting" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50" @click="confirmZeroReturn">{{ returnSubmitting ? 'Đang xác nhận...' : 'Xác nhận trả máy' }}</button>
                </div>
            </div>
        </BaseModal>
        <PrintPreviewModal :show="Boolean(printPreviewUrl)" title="Xem trước phiếu sửa chữa" :src="printPreviewUrl" @close="printPreviewUrl = ''" />
    </div>
</template>
