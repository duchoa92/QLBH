<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import PatternLock from '@/Components/PatternLock.vue';
import { formatCurrency, formatDate, formatDateTime } from '@/utils/format';
import {
    AlertTriangle, Check, CreditCard, Cpu, Eye, EyeOff, KeyRound, Pencil,
    Phone, Printer, Shield, ShieldCheck, Smartphone, Trash2, X, ZoomIn,
} from 'lucide-vue-next';

const props = defineProps({
    repair: { type: Object, required: true },
    // Dữ liệu bảo mật lấy từ route repairs.credentials (null nếu chưa tải / không có quyền)
    credentials: { type: Object, default: null },
    canViewCredentials: { type: Boolean, default: false },
    canPrint: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    // Chỉ Super Admin mới thấy nút xóa phiếu
    canDelete: { type: Boolean, default: false },
    // Nhãn nút xử lý tiếp theo; rỗng/null nghĩa là không hiện nút
    progressLabel: { type: String, default: '' },
    statusLabels: { type: Object, default: () => ({}) },
    statusClasses: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'print', 'edit', 'progress', 'delete']);

/* ---------- Trạng thái hiển thị ---------- */
const statusDot = {
    pending: 'bg-amber-500',
    repairing: 'bg-purple-500',
    done: 'bg-indigo-600',
    returned: 'bg-emerald-500',
    cancelled: 'bg-rose-500',
};
const statusCard = {
    pending: 'bg-amber-50/40 border-amber-100',
    repairing: 'bg-purple-50/40 border-purple-100',
    done: 'bg-slate-50 border-slate-100',
    returned: 'bg-emerald-50/50 border-emerald-100',
    cancelled: 'bg-rose-50/50 border-rose-100',
};

const toText = (value) => Array.isArray(value) ? value.filter(Boolean).join(', ') : String(value ?? '').trim();
const money = (value) => formatCurrency(Number(value || 0));

const customerName = computed(() => props.repair.customer?.name || props.repair.customer_name || 'Khách lẻ');
const customerPhone = computed(() => props.repair.customer?.phone || props.repair.contact_phone || '');
const initials = computed(() => {
    const words = customerName.value.trim().split(/\s+/).filter(Boolean);
    if (!words.length) return 'KL';
    const letters = words.length === 1 ? words[0].slice(0, 2) : words[0][0] + words[words.length - 1][0];
    return letters.toUpperCase();
});

const handlingType = computed(() => {
    const r = props.repair;
    const open = ['pending', 'repairing'].includes(r.status);
    if (r.intake_type === 'warranty') return 'Bảo hành';
    if (r.warranty_status === 'declined') return 'Sửa dịch vụ (đã từ chối BH)';
    if (r.warranty_status === 'service') return 'Sửa dịch vụ (có căn cứ BH)';
    if (r.warranty_source_type && open) return 'Chưa chốt · có căn cứ BH';
    if (open) return 'Chưa chốt loại sửa';
    return 'Sửa dịch vụ';
});

const issueText = computed(() => toText(props.repair.issue));
const accessoriesText = computed(() => toText(props.repair.accessories));
const coveredAmount = computed(() => Number(props.repair.warranty_covered_amount || 0));

/* ---------- Tài chính ---------- */
const paymentLabels = {
    cash: 'Tiền mặt',
    bank: 'Chuyển khoản',
    transfer: 'Chuyển khoản',
    card: 'Quẹt thẻ',
    debt: 'Ghi nợ',
};
const paymentLabel = computed(() => paymentLabels[props.repair.payment_method] || props.repair.payment_method || '');
const hasPaid = computed(() => Number(props.repair.paid_amount || 0) > 0);
const showEstimate = computed(() => Number(props.repair.estimated_cost || 0) > 0);
const estimateStruck = computed(() => showEstimate.value
    && Number(props.repair.final_cost || 0) > 0
    && Number(props.repair.estimated_cost) !== Number(props.repair.final_cost));

/* ---------- Bảo mật: che mặc định, bấm mới hiện ---------- */
const revealSecrets = ref(false);
watch(() => props.repair.id, () => { revealSecrets.value = false; });
const mask = (value) => value ? (revealSecrets.value ? value : '••••••') : '-';
const hasCredentialData = computed(() => {
    const c = props.credentials;
    return Boolean(c && (c.screen_password || c.screen_pattern || c.account_type || c.account_email || c.account_password));
});

/* ---------- Lightbox ảnh ---------- */
const lightboxUrl = ref('');
const onLightboxKey = (event) => {
    if (event.key !== 'Escape') return;
    // Chặn Esc đóng luôn modal chính khi đang xem ảnh phóng to
    event.stopImmediatePropagation();
    closeLightbox();
};
const openLightbox = (url) => {
    lightboxUrl.value = url;
    window.addEventListener('keydown', onLightboxKey, true);
};
const closeLightbox = () => {
    lightboxUrl.value = '';
    window.removeEventListener('keydown', onLightboxKey, true);
};
onBeforeUnmount(() => window.removeEventListener('keydown', onLightboxKey, true));
</script>

<template>
    <BaseModal
        size="2xl"
        modal-class="!max-w-5xl"
        body-class="p-4 sm:p-5"
        @close="emit('close')"
    >
        <!-- HEADER -->
        <template #header>
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <ShieldCheck class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-base font-bold text-slate-800">Phiếu sửa {{ repair.code }}</h2>
                        <span :class="['inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold', statusClasses[repair.status]]">
                            <span class="h-1.5 w-1.5 rounded-full bg-current" />
                            {{ statusLabels[repair.status] || repair.status }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Tiếp nhận lúc: {{ formatDateTime(repair.created_at) }}
                        <template v-if="repair.technician"> · KTV: {{ repair.technician }}</template>
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    v-if="canPrint"
                    type="button"
                    class="hidden items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:inline-flex"
                    @click="emit('print')"
                >
                    <Printer class="h-3.5 w-3.5 text-slate-500" />
                    In phiếu
                </button>
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-200/60 hover:text-slate-600"
                    aria-label="Đóng"
                    @click="emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
        </template>

        <!-- BODY: 2 cột 7/5 -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- CỘT TRÁI -->
            <div class="space-y-5 lg:col-span-7">
                <!-- Khách hàng & thiết bị -->
                <div class="space-y-3 rounded-xl border border-slate-200/80 bg-slate-50/70 p-4">
                    <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Thông tin khách hàng</span>
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100 font-bold text-indigo-700">{{ initials }}</div>
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-bold text-slate-800">{{ customerName }}</div>
                                    <div class="flex items-center gap-1 font-mono text-[11px] text-slate-500">
                                        <Phone class="h-3 w-3 text-slate-400" />
                                        {{ customerPhone || '-' }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="repair.contact_phone && repair.contact_phone !== customerPhone" class="text-[11px] text-slate-500">
                                SĐT liên hệ: <span class="font-mono font-medium text-slate-700">{{ repair.contact_phone }}</span>
                            </div>
                            <div v-if="repair.customer?.identity_card" class="text-[11px] text-slate-500">
                                CCCD: <span class="font-mono font-medium text-slate-700">{{ repair.customer.identity_card }}</span>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Thông tin thiết bị</span>
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                    <Smartphone class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-bold text-slate-800">{{ repair.device_name || '-' }}</div>
                                    <div v-if="repair.model" class="font-mono text-[11px] text-slate-500">
                                        Model: <span class="font-bold text-slate-700">{{ repair.model }}</span>
                                    </div>
                                    <div class="font-mono text-[11px] text-slate-500">
                                        IMEI: <span class="font-bold text-slate-700">{{ repair.imei || '-' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="repair.serial" class="text-[11px] text-slate-500">
                                Serial: <span class="font-mono font-medium text-slate-700">{{ repair.serial }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-2 border-t border-slate-200/70 pt-2.5 text-[11px] sm:grid-cols-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-slate-400">Loại xử lý:</span>
                            <span class="rounded border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700">{{ handlingType }}</span>
                        </div>
                        <div v-if="repair.repair_warranty_expires_at" class="flex flex-wrap items-center gap-1.5">
                            <span class="text-slate-400">BH sau sửa đến:</span>
                            <span class="rounded border border-indigo-100 bg-indigo-50 px-2 py-0.5 font-bold text-indigo-600">{{ formatDate(repair.repair_warranty_expires_at) }}</span>
                        </div>
                    </div>
                    <div v-if="repair.warranty_expires_at" class="flex items-center gap-1 text-[11px] text-slate-400">
                        <Shield class="h-3.5 w-3.5" />
                        Hạn bảo hành gốc theo HĐ: <span class="font-medium text-slate-600">{{ formatDate(repair.warranty_expires_at) }}</span>
                    </div>
                    <div v-if="repair.warranty_decline_reason" class="text-[11px] text-rose-600">
                        Lý do từ chối BH: {{ repair.warranty_decline_reason }}
                    </div>
                </div>

                <!-- Triệu chứng -->
                <div class="space-y-1.5">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Triệu chứng & tình trạng tiếp nhận</span>
                    <div class="flex items-start gap-2.5 rounded-xl border border-rose-100 bg-rose-50/70 p-3 text-xs">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-rose-500" />
                        <div class="min-w-0 space-y-1">
                            <div>
                                <span class="mb-0.5 block font-bold text-rose-900">Lỗi báo từ khách:</span>
                                <p class="font-medium text-rose-700">{{ issueText || 'Chưa ghi nhận lỗi' }}</p>
                            </div>
                            <div v-if="repair.repair_request">
                                <span class="block font-bold text-rose-900">Yêu cầu sửa chữa:</span>
                                <p class="text-rose-700">{{ repair.repair_request }}</p>
                            </div>
                            <div v-if="accessoriesText">
                                <span class="block font-bold text-rose-900">Phụ kiện kèm theo:</span>
                                <p class="text-rose-700">{{ accessoriesText }}</p>
                            </div>
                            <div v-if="repair.note">
                                <span class="block font-bold text-rose-900">Ghi chú:</span>
                                <p class="text-rose-700">{{ repair.note }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bảo mật & mật khẩu -->
                <div v-if="canViewCredentials && hasCredentialData" class="space-y-3 rounded-xl border border-amber-200/70 bg-amber-50/50 p-4 text-xs">
                    <div class="flex items-center justify-between gap-2 border-b border-amber-200/50 pb-2">
                        <div class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-amber-900">
                            <KeyRound class="h-4 w-4 text-amber-600" />
                            Bảo mật & mật khẩu
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-white px-2 py-1 text-[11px] font-semibold text-amber-800 transition hover:bg-amber-100"
                            @click="revealSecrets = !revealSecrets"
                        >
                            <component :is="revealSecrets ? EyeOff : Eye" class="h-3.5 w-3.5" />
                            {{ revealSecrets ? 'Ẩn thông tin' : 'Hiện thông tin' }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <div v-if="credentials.screen_password">
                                <span class="block text-[10px] text-slate-400">Mật khẩu màn hình:</span>
                                <span class="block rounded border border-amber-200 bg-white px-2 py-1 font-mono font-bold text-slate-800">{{ mask(credentials.screen_password) }}</span>
                            </div>
                            <div v-if="credentials.account_type">
                                <span class="block text-[10px] text-slate-400">Loại tài khoản:</span>
                                <span class="font-bold uppercase text-slate-800">{{ credentials.account_type }}</span>
                            </div>
                            <div v-if="credentials.account_email">
                                <span class="block text-[10px] text-slate-400">Email / SĐT tài khoản:</span>
                                <span class="block truncate rounded border border-amber-200 bg-white px-2 py-1 font-mono text-slate-700">{{ mask(credentials.account_email) }}</span>
                            </div>
                            <div v-if="credentials.account_password">
                                <span class="block text-[10px] text-slate-400">Mật khẩu tài khoản:</span>
                                <span class="block rounded border border-amber-200 bg-white px-2 py-1 font-mono font-bold text-slate-800">{{ mask(credentials.account_password) }}</span>
                            </div>
                        </div>

                        <div v-if="credentials.screen_pattern" class="flex flex-col items-center justify-center rounded-xl border border-amber-200/80 bg-white p-3">
                            <template v-if="revealSecrets">
                                <PatternLock :model-value="credentials.screen_pattern" readonly />
                            </template>
                            <p v-else class="py-8 text-center text-[11px] text-slate-400">Mẫu hình mở khóa đang được ẩn.<br />Bấm “Hiện thông tin” để xem.</p>
                        </div>
                    </div>
                </div>

                <!-- Ảnh tiếp nhận -->
                <div v-if="repair.images?.length" class="space-y-2">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Ảnh tiếp nhận ({{ repair.images.length }} ảnh)</span>
                    <div class="grid grid-cols-4 gap-2.5">
                        <button
                            v-for="image in repair.images"
                            :key="image.id"
                            type="button"
                            class="group relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm"
                            @click="openLightbox(image.url)"
                        >
                            <img :src="image.url" alt="Ảnh tiếp nhận" class="h-full w-full object-cover transition duration-300 group-hover:scale-110" loading="lazy" />
                            <span class="absolute inset-0 flex items-center justify-center bg-slate-900/40 text-white opacity-0 transition group-hover:opacity-100">
                                <ZoomIn class="h-5 w-5" />
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI -->
            <div class="space-y-5 lg:col-span-5">
                <!-- Thẻ tài chính -->
                <div class="relative space-y-4 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 to-indigo-950 p-5 text-white shadow-xl">
                    <div class="pointer-events-none absolute -bottom-6 -right-6 h-32 w-32 rounded-full bg-indigo-500/10 blur-2xl" />

                    <div class="flex items-center justify-between border-b border-slate-700/80 pb-3">
                        <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-indigo-300">
                            <CreditCard class="h-4 w-4 text-indigo-400" />
                            Tổng kết thanh toán
                        </span>
                        <span v-if="showEstimate" class="text-xs text-slate-400">
                            Dự kiến: <span :class="estimateStruck ? 'line-through' : ''">{{ money(repair.estimated_cost) }}</span>
                        </span>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-300">
                            <span>Tiền linh kiện:</span>
                            <span class="font-mono">{{ money(repair.parts_total) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-300">
                            <span>Công kỹ thuật sửa chữa:</span>
                            <span class="font-mono">{{ money(repair.labor_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-300">
                            <span>Phụ phí phát sinh:</span>
                            <span class="font-mono">{{ money(repair.surcharge) }}</span>
                        </div>
                        <div v-if="coveredAmount > 0" class="flex justify-between text-emerald-300">
                            <span>Được bảo hành chi trả:</span>
                            <span class="font-mono">{{ money(coveredAmount) }}</span>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-slate-700/80 pt-3">
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs font-semibold text-slate-300">Thực tế thu:</span>
                            <span class="font-mono text-2xl font-black tracking-tight text-amber-400">{{ Number(repair.final_cost || 0) > 0 ? money(repair.final_cost) : '-' }}</span>
                        </div>
                        <div
                            v-if="hasPaid"
                            class="flex items-center justify-between rounded-lg border border-emerald-800/50 bg-emerald-950/50 px-2.5 py-1.5 text-[11px] text-emerald-400"
                        >
                            <span class="flex items-center gap-1">
                                <Check class="h-3.5 w-3.5" />
                                Đã thanh toán<template v-if="paymentLabel"> ({{ paymentLabel }})</template>:
                            </span>
                            <span class="font-mono font-bold">{{ money(repair.paid_amount) }}</span>
                        </div>
                        <div v-if="Number(repair.change_amount || 0) > 0" class="flex justify-between text-[11px] text-slate-400">
                            <span>Tiền thừa trả khách:</span>
                            <span class="font-mono">{{ money(repair.change_amount) }}</span>
                        </div>
                        <p v-if="repair.payment_note" class="text-[11px] text-slate-400">{{ repair.payment_note }}</p>
                    </div>
                </div>

                <!-- Linh kiện -->
                <div class="overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3.5 py-2 text-[11px] font-bold uppercase text-slate-600">
                        <span class="flex items-center gap-1.5">
                            <Cpu class="h-3.5 w-3.5 text-indigo-600" />
                            Linh kiện thay thế
                        </span>
                        <span class="text-[10px] text-slate-400">{{ repair.parts?.length || 0 }} món</span>
                    </div>
                    <div class="divide-y divide-slate-100 bg-white text-xs">
                        <div v-for="part in repair.parts || []" :key="part.id" class="flex items-center justify-between gap-3 p-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-slate-800">{{ part.product_name }}</div>
                                <div v-if="part.sku" class="font-mono text-[10px] text-slate-400">{{ part.sku }}</div>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="font-mono text-slate-500">x{{ part.quantity }}</span>
                                <span class="font-mono font-bold text-slate-700">{{ money(part.subtotal) }}</span>
                            </div>
                        </div>
                        <div v-if="!repair.parts?.length" class="p-3 text-slate-400">Không thay linh kiện</div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Lịch sử tiến trình sửa chữa</span>
                        <span class="text-[10px] font-medium text-indigo-600">{{ repair.timelines?.length || 0 }} nhật ký</span>
                    </div>

                    <div
                        v-if="repair.timelines?.length"
                        class="relative space-y-4 pl-5 before:absolute before:bottom-2 before:left-[7px] before:top-2 before:w-0.5 before:bg-slate-200"
                    >
                        <div v-for="timeline in repair.timelines" :key="timeline.id" class="relative">
                            <div :class="['absolute -left-[17px] top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full text-white ring-4 ring-white', statusDot[timeline.status] || 'bg-slate-400']">
                                <Check v-if="timeline.status === 'returned'" class="h-2.5 w-2.5" />
                            </div>
                            <div :class="['space-y-1 rounded-xl border p-2.5 text-xs', statusCard[timeline.status] || 'bg-slate-50 border-slate-100']">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-800">{{ timeline.title }}</span>
                                    <span class="shrink-0 font-mono text-[10px] text-slate-400">{{ timeline.created_at }}</span>
                                </div>
                                <p v-if="timeline.description" class="text-[11px] text-slate-600">{{ timeline.description }}</p>
                                <p v-if="timeline.issue?.length" class="text-[11px] font-medium text-rose-600">Lỗi phát sinh: {{ toText(timeline.issue) }}</p>
                                <p v-if="timeline.waiting_for_parts" class="text-[11px] font-medium text-amber-700">
                                    {{ timeline.waiting_mode === 'wait_repair' ? 'Tạm chờ sửa' : 'Đang chờ linh kiện' }}<template v-if="timeline.parts_needed">: {{ timeline.parts_needed }}</template><template v-if="timeline.expected_days !== null && timeline.expected_days !== undefined"> · Dự kiến {{ timeline.expected_days }} ngày</template>
                                </p>
                                <div v-if="timeline.images?.length" class="flex flex-wrap gap-1.5 pt-1">
                                    <button
                                        v-for="image in timeline.images"
                                        :key="image.id"
                                        type="button"
                                        class="h-12 w-12 overflow-hidden rounded border border-slate-200"
                                        @click="openLightbox(image.url)"
                                    >
                                        <img :src="image.url" alt="Ảnh tiến trình" class="h-full w-full object-cover" loading="lazy" />
                                    </button>
                                </div>
                                <span class="block text-[10px] font-medium text-slate-400">Thực hiện bởi: {{ timeline.user || 'Hệ thống' }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-400">Chưa có nhật ký tiến trình.</p>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <template #footer>
            <div class="flex items-center justify-between gap-3">
                <div class="hidden items-center gap-3 text-xs text-slate-400 sm:flex">
                    <button
                        v-if="canDelete"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 font-semibold text-rose-700 transition hover:bg-rose-100"
                        @click="emit('delete')"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        Xóa phiếu
                    </button>
                </div>
                <div class="flex w-full flex-wrap items-center justify-end gap-2.5 sm:w-auto">
                    <button
                        v-if="canDelete"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 sm:hidden"
                        @click="emit('delete')"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        Xóa phiếu
                    </button>
                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-200/70" @click="emit('close')">Đóng</button>
                    <button
                        v-if="canPrint"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100 sm:hidden"
                        @click="emit('print')"
                    >
                        <Printer class="h-3.5 w-3.5 text-slate-500" />
                        In phiếu
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100"
                        @click="emit('edit')"
                    >
                        <Pencil class="h-3.5 w-3.5 text-slate-500" />
                        Sửa phiếu
                    </button>
                    <button
                        v-if="progressLabel"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700"
                        @click="emit('progress')"
                    >
                        {{ progressLabel }}
                    </button>
                </div>
            </div>
        </template>
    </BaseModal>

    <!-- LIGHTBOX -->
    <Teleport to="body">
        <div
            v-if="lightboxUrl"
            class="fixed inset-0 z-[1100] flex cursor-zoom-out items-center justify-center bg-black/80 p-4 backdrop-blur-md"
            @click="closeLightbox"
        >
            <div class="relative max-h-[85vh] max-w-3xl">
                <img :src="lightboxUrl" alt="Ảnh phóng to" class="max-h-[85vh] w-auto rounded-xl object-contain shadow-2xl" />
                <button
                    type="button"
                    class="absolute -right-4 -top-4 rounded-full bg-white p-2 text-slate-900 shadow-lg transition hover:bg-slate-200"
                    aria-label="Đóng ảnh"
                    @click.stop="closeLightbox"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
        </div>
    </Teleport>
</template>
