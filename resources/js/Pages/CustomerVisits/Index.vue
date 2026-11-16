<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Camera, CameraOff, Clock, Footprints, RefreshCw, ScanFace, Trash2, User, UserCheck, UserPlus, UserRoundPlus, UsersRound, TriangleAlert } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import PaginationBar from '@/Components/UI/PaginationBar.vue'
import api from '@/Services/api'
import { useConfirm } from '@/Composables/useConfirm'
import { useFaceCheckIn } from '@/Modules/POS/Customer/Composables/useFaceCheckIn'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    filters: Object,
    summary: Object,
    hours: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    visits: Object,
    trackingEnabled: Boolean,
    doorCamera: { type: Object, default: () => ({ enabled: false, online: false, camera_ok: false, message: null }) },
    retentionDays: { type: Number, default: 30 },
    canManage: Boolean,
    canCreateCustomer: Boolean,
})

const confirmBox = useConfirm()

/* ---------------- tự cập nhật khi có thay đổi ---------------- */
const lastUpdated = ref(new Date())
let reloadTimer = null
let pollTimer = null
const reloadNow = () => {
    if (document.hidden || target.value) return
    router.reload({
        only: ['summary', 'hours', 'days', 'visits'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { lastUpdated.value = new Date() },
    })
}
const scheduleReload = () => {
    clearTimeout(reloadTimer)
    reloadTimer = setTimeout(reloadNow, 1000)
}
onMounted(() => {
    pollTimer = setInterval(reloadNow, 10000)
    document.addEventListener('visibilitychange', reloadNow)
})
onBeforeUnmount(() => {
    clearInterval(pollTimer)
    clearTimeout(reloadTimer)
    document.removeEventListener('visibilitychange', reloadNow)
})

/* ---------------- camera xem trực tiếp + bắt khuôn mặt ---------------- */
// Dùng chung bộ nhận diện của POS: thấy ai thì ghi lượt (nếu đã bật ghi nhận) và trang tự cập nhật ngay.
const {
    featureOn: faceFeatureOn,
    active: cameraActive,
    failed: cameraFailed,
    facing: cameraFacing,
    switching: cameraSwitching,
    liveFaces,
    statusText: cameraStatus,
    attachPreview,
    toggle: toggleCamera,
    switchCamera,
} = useFaceCheckIn({ storageKey: 'visits.camera', onVisit: scheduleReload })

const videoRatio = ref(4 / 3)
const onMeta = (event) => {
    const video = event.target
    if (video.videoWidth && video.videoHeight) videoRatio.value = video.videoWidth / video.videoHeight
}
const mirrored = computed(() => cameraFacing.value === 'user')
const stageStyle = computed(() => ({ aspectRatio: String(videoRatio.value), width: `min(100%, calc(62vh * ${videoRatio.value}))` }))
const boxStyle = (face) => ({
    left: `${(mirrored.value ? 1 - face.x - face.w : face.x) * 100}%`,
    top: `${face.y * 100}%`,
    width: `${face.w * 100}%`,
    height: `${face.h * 100}%`,
})
const boxTone = { known: 'border-emerald-400 text-emerald-300', unknown: 'border-amber-400 text-amber-300', scanning: 'border-cyan-300 text-cyan-200' }
const chipTone = { known: 'ring-emerald-400', unknown: 'ring-amber-400', scanning: 'ring-cyan-300' }

/* ---------------- bộ lọc ---------------- */
const ranges = [
    { value: 'today', label: 'Hôm nay' },
    { value: 'yesterday', label: 'Hôm qua' },
    { value: '7d', label: '7 ngày' },
    { value: '30d', label: '30 ngày' },
    { value: 'custom', label: 'Tùy chọn' },
]
const statuses = [
    { value: '', label: 'Tất cả' },
    { value: 'known', label: 'Khách quen' },
    { value: 'unknown', label: 'Chưa có tên' },
    { value: 'assigned', label: 'Đã gán tên' },
]
const dateFrom = ref(props.filters.date_from)
const dateTo = ref(props.filters.date_to)
watch(() => props.filters, (value) => { dateFrom.value = value.date_from; dateTo.value = value.date_to })

const go = (changes = {}) => {
    const params = {
        range: props.filters.range,
        status: props.filters.status,
        per_page: props.filters.per_page,
        ...changes,
    }
    if (params.range === 'custom') {
        params.date_from = changes.date_from ?? dateFrom.value
        params.date_to = changes.date_to ?? dateTo.value
    }
    Object.keys(params).forEach((key) => { if (params[key] === '' || params[key] == null) delete params[key] })
    router.get('/customer-visits', params, { preserveState: true, preserveScroll: true, replace: true })
}
const setRange = value => go({ range: value, page: undefined })
const setStatus = value => go({ status: value, page: undefined })
const applyCustom = () => { if (dateFrom.value && dateTo.value) go({ range: 'custom', date_from: dateFrom.value, date_to: dateTo.value, page: undefined }) }

/* ---------------- biểu đồ ---------------- */
const hourMax = computed(() => Math.max(1, ...props.hours))
const peakHour = computed(() => {
    const max = Math.max(...props.hours, 0)
    return max > 0 ? props.hours.indexOf(max) : null
})
const dayMax = computed(() => Math.max(1, ...props.days.map(day => day.count)))
const showDays = computed(() => props.days.length > 1)
const barHeight = (value, max) => `${value > 0 ? Math.max(6, Math.round((value / max) * 100)) : 0}%`

const cards = computed(() => [
    { key: 'total', label: 'Tổng lượt khách', value: props.summary.total, icon: Footprints, tone: 'text-blue-600 bg-blue-50' },
    { key: 'known', label: 'Khách quen nhận ra', value: props.summary.known, icon: UserCheck, tone: 'text-emerald-600 bg-emerald-50' },
    { key: 'unknown', label: 'Chưa có tên', value: props.summary.unknown, icon: UserPlus, tone: 'text-amber-600 bg-amber-50' },
    { key: 'distinct', label: 'Số khách khác nhau', value: props.summary.distinct_customers, icon: UsersRound, tone: 'text-violet-600 bg-violet-50' },
])

/* ---------------- hiển thị ---------------- */
const timeText = (iso) => iso ? new Date(iso).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : ''
const dateText = (iso) => iso ? new Date(iso).toLocaleDateString('vi-VN') : ''
const durationText = (minutes) => {
    if (!minutes) return 'dưới 1 phút'
    if (minutes < 60) return `${minutes} phút`
    return `${Math.floor(minutes / 60)} giờ ${minutes % 60} phút`
}
const kindOf = (visit) => {
    if (visit.recognized) return { text: 'Khách quen', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200' }
    if (visit.customer) return { text: 'Đã gán tên', cls: 'bg-blue-50 text-blue-700 ring-blue-200' }
    return { text: 'Chưa có tên', cls: 'bg-amber-50 text-amber-700 ring-amber-200' }
}

/* ---------------- gán tên / thêm khách ---------------- */
const target = ref(null)
const mode = ref('search') // search | create
const keyword = ref('')
const results = ref([])
const searching = ref(false)
const newName = ref('')
const newPhone = ref('')
const consent = ref(false)
const saving = ref(false)
const error = ref('')
let searchTimer = null
let searchSeq = 0

const openAssign = (visit) => {
    target.value = visit
    mode.value = 'search'
    keyword.value = ''
    results.value = []
    newName.value = ''
    newPhone.value = ''
    consent.value = false
    error.value = ''
}
const closeAssign = () => { target.value = null }

watch(keyword, (value) => {
    clearTimeout(searchTimer)
    if (!value.trim()) { results.value = []; return }
    searchTimer = setTimeout(async () => {
        const seq = ++searchSeq
        searching.value = true
        try {
            const { data } = await api.get('/api/customers/search', { params: { search: value.trim() } })
            if (seq === searchSeq) results.value = Array.isArray(data) ? data : (data?.data || [])
        } catch { if (seq === searchSeq) results.value = [] } finally { if (seq === searchSeq) searching.value = false }
    }, 300)
})

const refresh = () => router.reload({ preserveScroll: true })

const linkTo = async (customerId) => {
    saving.value = true
    error.value = ''
    try {
        await api.post(`/api/customers/visits/${target.value.id}/link`, { customer_id: customerId, consent: consent.value && target.value.has_face })
        closeAssign()
        refresh()
    } catch (e) {
        error.value = e?.response?.data?.message || 'Không gán được, vui lòng thử lại.'
    } finally {
        saving.value = false
    }
}

const createAndLink = async () => {
    if (!newName.value.trim()) { error.value = 'Nhập họ tên khách.'; return }
    saving.value = true
    error.value = ''
    try {
        const { data } = await api.post('/api/customers', { full_name: newName.value.trim(), phone: newPhone.value.trim() })
        const customer = data?.data || data
        await api.post(`/api/customers/visits/${target.value.id}/link`, { customer_id: customer.id, consent: consent.value && target.value.has_face })
        closeAssign()
        refresh()
    } catch (e) {
        error.value = e?.response?.data?.message || 'Không tạo được khách, vui lòng thử lại.'
    } finally {
        saving.value = false
    }
}

const remove = (visit) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Xóa lượt khách này khỏi thống kê?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: async () => {
            try {
                await api.delete(`/customer-visits/${visit.id}`)
                refresh()
            } catch (e) {
                console.error(e)
            }
        },
    })
}
</script>

<template>
    <div class="space-y-4">
        <!-- Cảnh báo đang tắt ghi nhận -->
        <div v-if="!trackingEnabled" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
            <p>
                Chưa bật ghi nhận lượt khách nên POS chưa lưu thêm lượt mới. Bật trong
                <Link href="/settings" class="font-semibold underline">Cài đặt → Khách hàng</Link>
                (cần bật cả nhận diện khuôn mặt). Ảnh và dữ liệu khuôn mặt chưa gán tên tự xóa sau {{ retentionDays }} ngày.
            </p>
        </div>

        <!-- Trạng thái worker camera cửa (chỉ hiện khi đã bật chế độ camera cửa) -->
        <div
            v-if="trackingEnabled && doorCamera.enabled"
            class="flex items-start gap-2 rounded-xl border p-3 text-sm"
            :class="doorCamera.online && doorCamera.camera_ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
        >
            <Camera v-if="doorCamera.online && doorCamera.camera_ok" class="mt-0.5 h-4 w-4 shrink-0" />
            <CameraOff v-else class="mt-0.5 h-4 w-4 shrink-0" />
            <p v-if="!doorCamera.online">Worker camera cửa chưa chạy nên chưa có lượt khách mới. Xem lệnh cài ở Cài đặt → Khách hàng → Camera cửa, hoặc kiểm tra dịch vụ <code>door-worker</code> trên máy chạy nền.</p>
            <p v-else-if="!doorCamera.camera_ok">Worker đang chạy nhưng không lấy được ảnh từ camera{{ doorCamera.message ? ': ' + doorCamera.message : '' }}.</p>
            <p v-else>Camera cửa đang hoạt động.</p>
        </div>

        <!-- Camera xem trực tiếp -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <ScanFace class="h-4 w-4 shrink-0 text-blue-600" />
                    <h3 class="truncate text-sm font-bold text-slate-800">Camera cửa ra vào</h3>
                    <span v-if="cameraActive" class="truncate text-xs text-slate-500">{{ cameraStatus }}</span>
                </div>
                <div v-if="faceFeatureOn" class="flex shrink-0 items-center gap-1.5">
                    <button
                        v-if="cameraActive"
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition hover:bg-slate-200 active:scale-95 disabled:opacity-50"
                        :disabled="cameraSwitching"
                        title="Đổi camera trước / sau"
                        aria-label="Đổi camera trước hoặc sau"
                        @click="switchCamera"
                    >
                        <RefreshCw class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-lg transition active:scale-95"
                        :class="cameraActive ? 'bg-rose-50 text-rose-600 hover:bg-rose-100' : 'bg-blue-600 text-white hover:bg-blue-700'"
                        :title="cameraActive ? 'Tắt camera' : 'Bật camera'"
                        :aria-label="cameraActive ? 'Tắt camera' : 'Bật camera'"
                        @click="toggleCamera"
                    >
                        <CameraOff v-if="cameraActive" class="h-5 w-5" />
                        <Camera v-else class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <p v-if="!faceFeatureOn" class="p-4 text-sm text-slate-500">
                Chưa bật nhận diện khuôn mặt. Bật trong <Link href="/settings" class="font-semibold text-blue-600 underline">Cài đặt → Khách hàng</Link> để xem camera tại đây.
            </p>
            <p v-else-if="!cameraActive" class="p-4 text-sm text-slate-500">Bấm biểu tượng camera để xem trực tiếp và bắt khuôn mặt khách vào cửa hàng.</p>
            <div v-else class="bg-slate-900 p-2">
                <div class="relative mx-auto overflow-hidden rounded-lg bg-black" :style="stageStyle">
                    <video v-show="!cameraFailed" :ref="attachPreview" class="absolute inset-0 h-full w-full" :class="mirrored ? '-scale-x-100' : ''" autoplay muted playsinline @loadedmetadata="onMeta"></video>
                    <div v-if="cameraFailed" class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-rose-300">{{ cameraStatus }}</div>

                    <!-- Khung bắt khuôn mặt -->
                    <div
                        v-for="face in liveFaces"
                        :key="face.id"
                        class="pointer-events-none absolute rounded-md border-2 transition-all duration-150"
                        :class="boxTone[face.kind]"
                        :style="boxStyle(face)"
                    >
                        <span class="absolute -top-5 left-0 max-w-[10rem] truncate rounded bg-black/70 px-1.5 py-0.5 text-[11px] font-semibold leading-none">{{ face.label }}</span>
                    </div>
                </div>

                <!-- Ảnh khuôn mặt vừa bắt được + mã -->
                <div v-if="liveFaces.length" class="mt-2 flex gap-2 overflow-x-auto pb-1">
                    <div v-for="face in liveFaces" :key="face.id" class="w-20 shrink-0 text-center">
                        <img v-if="face.thumb" :src="face.thumb" alt="" class="mx-auto h-16 w-16 rounded-lg object-cover ring-2" :class="chipTone[face.kind]">
                        <span v-else class="mx-auto flex h-16 w-16 items-center justify-center rounded-lg bg-slate-700 text-slate-400 ring-2" :class="chipTone[face.kind]"><User class="h-6 w-6" /></span>
                        <div class="mt-1 truncate text-[11px] font-medium text-slate-100">{{ face.label }}</div>
                    </div>
                </div>
            </div>
        </div>

        <p class="flex items-center gap-1.5 text-xs text-slate-400">
            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
            Tự cập nhật · lần cuối {{ lastUpdated.toLocaleTimeString('vi-VN') }}
        </p>

        <!-- Bộ lọc thời gian -->
        <div class="space-y-2">
            <div class="flex gap-1.5 overflow-x-auto pb-1">
                <button
                    v-for="item in ranges"
                    :key="item.value"
                    type="button"
                    class="min-h-10 shrink-0 rounded-lg border px-3.5 text-sm font-semibold transition"
                    :class="filters.range === item.value ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                    @click="setRange(item.value)"
                >
                    {{ item.label }}
                </button>
            </div>
            <div v-if="filters.range === 'custom'" class="flex flex-wrap items-center gap-2">
                <input v-model="dateFrom" type="date" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm">
                <span class="text-slate-400">→</span>
                <input v-model="dateTo" type="date" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm">
                <button type="button" class="min-h-10 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700" @click="applyCustom">Xem</button>
            </div>
        </div>

        <!-- Tổng quan -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="card in cards" :key="card.key" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="card.tone"><component :is="card.icon" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <div class="text-2xl font-bold leading-none text-slate-900 tabular-nums">{{ card.value }}</div>
                    <div class="mt-1 truncate text-xs text-slate-500">{{ card.label }}</div>
                </div>
            </div>
        </div>

        <!-- Biểu đồ -->
        <div class="grid gap-3 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-slate-800">Lượt khách theo giờ</h3>
                    <span v-if="peakHour !== null" class="inline-flex items-center gap-1 text-xs text-slate-500"><Clock class="h-3.5 w-3.5" /> Đông nhất {{ peakHour }}h ({{ hours[peakHour] }} lượt)</span>
                </div>
                <div class="flex h-32 items-end gap-0.5">
                    <div v-for="(count, hour) in hours" :key="hour" class="flex h-full min-w-0 flex-1 flex-col items-center justify-end" :title="`${hour}h: ${count} lượt`">
                        <div class="w-full rounded-t bg-blue-500/80" :style="{ height: barHeight(count, hourMax) }"></div>
                    </div>
                </div>
                <div class="mt-1 flex justify-between text-[10px] text-slate-400"><span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>23h</span></div>
            </div>

            <div v-if="showDays" class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <h3 class="mb-2 text-sm font-bold text-slate-800">Lượt khách theo ngày</h3>
                <div class="flex h-32 items-end gap-0.5 overflow-x-auto">
                    <div v-for="day in days" :key="day.date" class="flex h-full min-w-[10px] flex-1 flex-col items-center justify-end" :title="`${day.label}: ${day.count} lượt`">
                        <div class="w-full rounded-t bg-emerald-500/80" :style="{ height: barHeight(day.count, dayMax) }"></div>
                    </div>
                </div>
                <div class="mt-1 flex justify-between text-[10px] text-slate-400"><span>{{ days[0]?.label }}</span><span>{{ days[days.length - 1]?.label }}</span></div>
            </div>
        </div>

        <!-- Lọc trạng thái -->
        <div class="flex gap-1.5 overflow-x-auto pb-1">
            <button
                v-for="item in statuses"
                :key="item.value"
                type="button"
                class="min-h-10 shrink-0 rounded-full border px-3.5 text-sm font-medium transition"
                :class="(filters.status || '') === item.value ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                @click="setStatus(item.value)"
            >
                {{ item.label }}
            </button>
        </div>

        <!-- Danh sách lượt khách: thẻ trên mobile, lưới trên màn lớn -->
        <div v-if="visits.data.length" class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
            <div class="grid gap-2 p-2 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="visit in visits.data" :key="visit.id" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                    <img v-if="visit.thumb" :src="visit.thumb" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover ring-1 ring-slate-200">
                    <span v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400"><User class="h-6 w-6" /></span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate text-sm font-bold text-slate-900">{{ visit.customer ? visit.customer.full_name : 'Khách chưa có tên' }}</span>
                        </div>
                        <div v-if="visit.customer?.phone" class="truncate text-xs text-slate-500">{{ visit.customer.phone }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ dateText(visit.first_seen_at) }} · {{ timeText(visit.first_seen_at) }}<span v-if="visit.minutes"> – {{ timeText(visit.last_seen_at) }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="kindOf(visit).cls">{{ kindOf(visit).text }}</span>
                            <span class="text-[11px] text-slate-400">{{ durationText(visit.minutes) }}</span>
                        </div>
                    </div>

                    <div v-if="canManage" class="flex shrink-0 flex-col gap-1.5">
                        <button
                            v-if="!visit.customer"
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 transition hover:bg-blue-100 active:scale-95"
                            title="Gán tên khách / thêm khách mới"
                            aria-label="Gán tên khách"
                            @click="openAssign(visit)"
                        >
                            <UserRoundPlus class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-50 text-rose-600 transition hover:bg-rose-100 active:scale-95"
                            title="Xóa lượt khách"
                            aria-label="Xóa lượt khách"
                            @click="remove(visit)"
                        >
                            <Trash2 class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>
            <PaginationBar :data="visits" :filters="filters" />
        </div>
        <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            Chưa có lượt khách nào trong khoảng này.
        </div>

        <!-- Gán tên / thêm khách mới -->
        <BaseModal v-if="target" title="Gán tên cho khách" size="md" @close="closeAssign">
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-2">
                    <img v-if="target.thumb" :src="target.thumb" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover">
                    <span v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white text-slate-400"><User class="h-6 w-6" /></span>
                    <p class="text-xs text-amber-800">Lượt vào lúc {{ timeText(target.first_seen_at) }} ngày {{ dateText(target.first_seen_at) }}. Các lượt giống khuôn mặt này cũng được gán cùng.</p>
                </div>

                <div class="grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1 text-sm font-semibold">
                    <button type="button" class="min-h-10 rounded-md" :class="mode === 'search' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" @click="mode = 'search'">Khách đã có</button>
                    <button v-if="canCreateCustomer" type="button" class="min-h-10 rounded-md" :class="mode === 'create' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" @click="mode = 'create'">Thêm khách mới</button>
                </div>

                <div v-if="mode === 'search'" class="space-y-2">
                    <input v-model="keyword" placeholder="Tìm theo tên hoặc số điện thoại" class="w-full rounded-lg border border-slate-300 p-3 text-sm">
                    <div v-if="searching" class="text-xs text-slate-400">Đang tìm…</div>
                    <div v-else-if="keyword.trim() && !results.length" class="text-xs text-slate-400">Không thấy khách phù hợp.</div>
                    <div class="max-h-60 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200" v-if="results.length">
                        <button
                            v-for="customer in results"
                            :key="customer.id"
                            type="button"
                            :disabled="saving"
                            class="flex min-h-11 w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 disabled:opacity-50"
                            @click="linkTo(customer.id)"
                        >
                            <span class="min-w-0 truncate font-semibold text-slate-800">{{ customer.full_name }}</span>
                            <span class="shrink-0 text-xs text-slate-500">{{ customer.phone }}</span>
                        </button>
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <label class="block text-sm font-semibold text-slate-700">
                        Họ tên
                        <input v-model="newName" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">
                        Số điện thoại
                        <input v-model="newPhone" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
                    </label>
                </div>

                <label v-if="target.has_face" class="flex min-h-11 items-start gap-2 text-sm text-slate-700">
                    <input v-model="consent" type="checkbox" class="mt-1 h-4 w-4 shrink-0">
                    <span>Khách đồng ý lưu khuôn mặt để nhận ra lần sau</span>
                </label>
                <p v-else class="text-xs text-slate-400">Dữ liệu khuôn mặt của lượt này đã hết hạn nên chỉ gán tên, không lưu khuôn mặt.</p>

                <p v-if="error" class="text-sm font-medium text-rose-600">{{ error }}</p>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="closeAssign">Hủy</button>
                    <button v-if="mode === 'create'" type="button" :disabled="saving" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50" @click="createAndLink">Lưu & gán</button>
                </div>
            </template>
        </BaseModal>
    </div>
</template>
