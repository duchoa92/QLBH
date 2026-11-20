<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Camera, CameraOff, Clock, Footprints, History, RefreshCw, ScanFace, Trash2, User, UserCheck, UserPlus, UserRoundPlus, UsersRound, TriangleAlert, Video } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import PaginationBar from '@/Components/UI/PaginationBar.vue'
import api from '@/Services/api'
import { useConfirm } from '@/Composables/useConfirm'
import { useFaceCheckIn } from '@/Modules/POS/Customer/Composables/useFaceCheckIn'
import DoorCameraStream from './Components/DoorCameraStream.vue'

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

// The first render comes from Inertia. Subsequent filters, pagination and
// live updates replace only this state through JSON, so video peers and modal
// state stay intact instead of the page navigating again.
const filters = ref({ ...props.filters })
const summary = ref({ ...props.summary })
const hours = ref([...props.hours])
const days = ref([...props.days])
const visits = ref(props.visits)
const trackingEnabled = ref(props.trackingEnabled)
const doorCamera = ref({ ...props.doorCamera })
const retentionDays = ref(props.retentionDays)
const canManage = ref(props.canManage)
const canCreateCustomer = ref(props.canCreateCustomer)

const applyDashboard = data => {
    filters.value = { ...data.filters }
    summary.value = { ...data.summary }
    hours.value = Array.isArray(data.hours) ? data.hours : []
    days.value = Array.isArray(data.days) ? data.days : []
    visits.value = data.visits || { data: [] }
    trackingEnabled.value = !!data.trackingEnabled
    doorCamera.value = data.doorCamera || { enabled: false, online: false, camera_ok: false }
    retentionDays.value = Number(data.retentionDays) || 30
    canManage.value = !!data.canManage
    canCreateCustomer.value = !!data.canCreateCustomer
}

/* ---------------- tự cập nhật khi có thay đổi ---------------- */
const lastUpdated = ref(new Date())
let refreshTimer = null
let pollTimer = null
let requestSequence = 0
const requestParams = value => {
    const params = {
        range: value.range || 'today',
        status: value.status || undefined,
        per_page: value.per_page || 20,
        page: value.page || undefined,
    }
    if (params.range === 'custom') {
        params.date_from = value.date_from || undefined
        params.date_to = value.date_to || undefined
    }
    return Object.fromEntries(Object.entries(params).filter(([, current]) => current !== undefined && current !== ''))
}
const updateAddress = params => {
    const search = new URLSearchParams(params).toString()
    window.history.replaceState({}, '', `${window.location.pathname}${search ? `?${search}` : ''}`)
}
const fetchDashboard = async (next = filters.value, { updateUrl = false } = {}) => {
    if (document.hidden && !updateUrl) return
    const params = requestParams(next)
    const sequence = ++requestSequence
    if (updateUrl) updateAddress(params)
    try {
        const { data } = await api.get('/api/customer-visits/dashboard', { params })
        if (sequence !== requestSequence) return
        applyDashboard(data)
        dateFrom.value = data.filters?.date_from || dateFrom.value
        dateTo.value = data.filters?.date_to || dateTo.value
        lastUpdated.value = new Date()
    } catch (error) {
        // Keep the last successful state visible if the network hiccups.
        console.warn('Không thể cập nhật thống kê lượt khách.', error)
    }
}
const scheduleRefresh = () => {
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => fetchDashboard(), 500)
}
const onVisibilityChange = () => { if (!document.hidden) fetchDashboard() }
onMounted(() => {
    // Keep new camera events feeling live without navigating/recreating the
    // video stream. Hidden tabs do not request because fetchDashboard exits.
    pollTimer = setInterval(() => fetchDashboard(), 2000)
    document.addEventListener('visibilitychange', onVisibilityChange)
})
onBeforeUnmount(() => {
    clearInterval(pollTimer)
    clearTimeout(refreshTimer)
    document.removeEventListener('visibilitychange', onVisibilityChange)
})

/* ---------------- camera xem trực tiếp + bắt khuôn mặt ---------------- */
// Hai nguồn xem, đổi qua lại bằng hai nút trên đầu khung:
//  - Camera cửa: video WebRTC từ MediaMTX; khung mặt và nhãn từ Camera Intelligence được vẽ đè ở đây.
//  - Camera máy: camera của thiết bị đang mở trang này, bắt mặt ngay trên trình duyệt (đổi được camera trước/sau).
const hasDoor = computed(() => !!doorCamera.value.enabled)
const readFlag = (key, fallback) => { try { const value = localStorage.getItem(key); return value === null ? fallback : value } catch { return fallback } }
const writeFlag = (key, value) => { try { localStorage.setItem(key, value) } catch { /* bỏ qua */ } }
const doorCameras = computed(() => doorCamera.value.configured_cameras || [])
const receivingDoorCameras = computed(() => doorCameras.value.filter(camera => camera.receiving))
const rememberedDoorCamera = readFlag('visits.doorCamera', '')
// Do not reopen a camera that has lost its RTSP source just because it was the
// previous browser selection. Prefer a currently receiving camera; operators
// can still explicitly choose another camera from the selector.
const selectedDoorCamera = ref(
    doorCameras.value.find(camera => camera.id === rememberedDoorCamera && camera.receiving)?.id
    || doorCameras.value.find(camera => camera.receiving)?.id
    || rememberedDoorCamera
    || doorCameras.value[0]?.id
    || 'door-1',
)
watch(doorCameras, cameras => {
    if (!cameras.some(camera => camera.id === selectedDoorCamera.value)) {
        selectedDoorCamera.value = cameras.find(camera => camera.receiving)?.id || cameras[0]?.id || 'door-1'
    }
}, { deep: true, immediate: true })
const selectedDoorCameraInfo = computed(() => doorCameras.value.find(camera => camera.id === selectedDoorCamera.value) || null)
const setDoorCamera = value => { selectedDoorCamera.value = value; writeFlag('visits.doorCamera', value) }
const cameraSource = ref(hasDoor.value && readFlag('visits.cameraSource', 'door') !== 'local' ? 'door' : 'local')
const localOn = ref(readFlag('visits.localCameraOn', '0') === '1')
const showDoor = computed(() => hasDoor.value && cameraSource.value === 'door')
const setSource = (value) => { cameraSource.value = value; writeFlag('visits.cameraSource', value) }
const toggleLocal = () => { localOn.value = !localOn.value; writeFlag('visits.localCameraOn', localOn.value ? '1' : '0') }

const {
    featureOn: faceFeatureOn,
    active: cameraActive,
    failed: cameraFailed,
    facing: cameraFacing,
    switching: cameraSwitching,
    liveFaces,
    statusText: cameraStatus,
    attachPreview,
    switchCamera,
} = useFaceCheckIn({
    storageKey: 'visits.camera',
    onVisit: scheduleRefresh,
    // Camera máy chỉ chạy khi đang chọn nguồn "Camera máy" và đã bấm bật; chọn Camera cửa thì tự tắt.
    activeWhen: () => !showDoor.value && localOn.value,
})

const doorStatus = computed(() => {
    if (!doorCamera.value.online) {
        return 'Camera Intelligence chưa kết nối'
    }
    if (!receivingDoorCameras.value.length) return selectedDoorCameraInfo.value ? `${selectedDoorCameraInfo.value.name}: ${selectedDoorCameraInfo.value.message || 'chưa nhận được khung hình'}` : 'Chưa nhận được khung hình'
    if (!doorCamera.value.camera_ok) return 'Camera Intelligence không lấy được ảnh camera'
    return `Đang phát và dò khuôn mặt tại ${receivingDoorCameras.value.length}/${doorCameras.value.length} camera`
})

const videoRatio = ref(4 / 3)
const onMeta = (event) => {
    const video = event.target
    if (video.videoWidth && video.videoHeight) videoRatio.value = video.videoWidth / video.videoHeight
}
const mirrored = computed(() => cameraFacing.value === 'user')
const stageStyle = computed(() => ({ aspectRatio: String(videoRatio.value), width: `min(100%, calc(62vh * ${videoRatio.value}))` }))
// Toạ độ khung mặt là 0..1 theo khung hình; camera trước lật gương nên phải lật khung theo.
const boxStyle = (face, mirror = false) => ({
    left: `${(mirror ? 1 - face.x - face.w : face.x) * 100}%`,
    top: `${face.y * 100}%`,
    width: `${face.w * 100}%`,
    height: `${face.h * 100}%`,
})
const boxTone = { known: 'border-emerald-400', unknown: 'border-amber-400', scanning: 'border-cyan-300' }
const labelTone = { known: 'bg-emerald-500 text-white', unknown: 'bg-amber-500 text-white', scanning: 'bg-black/70 text-cyan-100' }
const dotTone = { known: 'bg-emerald-400', unknown: 'bg-amber-400', scanning: 'bg-cyan-300' }
const chipTone = { known: 'ring-emerald-400', unknown: 'ring-amber-400', scanning: 'ring-cyan-300' }
// Mặt sát mép trên thì đặt nhãn tên vào trong khung để không bị cắt.
const labelPos = face => (face.y < 0.08 ? 'top-0.5 left-0.5' : '-top-6 left-0')

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
const dateFrom = ref(filters.value.date_from)
const dateTo = ref(filters.value.date_to)

const go = (changes = {}) => {
    const params = {
        range: filters.value.range,
        status: filters.value.status,
        per_page: filters.value.per_page,
        ...changes,
    }
    if (params.range === 'custom') {
        params.date_from = changes.date_from ?? dateFrom.value
        params.date_to = changes.date_to ?? dateTo.value
    }
    return fetchDashboard(params, { updateUrl: true })
}
const setRange = value => go({ range: value, page: undefined })
const setStatus = value => go({ status: value, page: undefined })
const applyCustom = () => { if (dateFrom.value && dateTo.value) go({ range: 'custom', date_from: dateFrom.value, date_to: dateTo.value, page: undefined }) }

/* ---------------- biểu đồ ---------------- */
const hourMax = computed(() => Math.max(1, ...hours.value))
const peakHour = computed(() => {
    const max = Math.max(...hours.value, 0)
    return max > 0 ? hours.value.indexOf(max) : null
})
const dayMax = computed(() => Math.max(1, ...days.value.map(day => day.count)))
const showDays = computed(() => days.value.length > 1)
const barHeight = (value, max) => `${value > 0 ? Math.max(6, Math.round((value / max) * 100)) : 0}%`

const cards = computed(() => [
    { key: 'total', label: 'Tổng lượt khách', value: summary.value.total, icon: Footprints, tone: 'text-blue-600 bg-blue-50' },
    { key: 'known', label: 'Khách quen nhận ra', value: summary.value.known, icon: UserCheck, tone: 'text-emerald-600 bg-emerald-50' },
    { key: 'unknown', label: 'Chưa có tên', value: summary.value.unknown, icon: UserPlus, tone: 'text-amber-600 bg-amber-50' },
    { key: 'distinct', label: 'Số khách khác nhau', value: summary.value.distinct_customers, icon: UsersRound, tone: 'text-violet-600 bg-violet-50' },
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
const consent = ref(true) // DEMO: mặc định đồng ý, sửa lại thành false khi chạy thật
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
    consent.value = true // DEMO: mặc định đồng ý
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

const refresh = () => fetchDashboard()

const navigatePage = url => {
    if (!url) return
    const search = new URL(url, window.location.origin).searchParams
    return fetchDashboard({
        ...filters.value,
        ...Object.fromEntries(search.entries()),
    }, { updateUrl: true })
}
const changeVisitPageSize = value => go({ per_page: value, page: undefined })

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

/* ---------------- lịch sử của một lượt khách ---------------- */
const historyOf = ref(null)
const history = ref(null)
const historyLoading = ref(false)
const historyError = ref('')
let historySeq = 0

const openHistory = async (visit) => {
    historyOf.value = visit
    history.value = null
    historyError.value = ''
    historyLoading.value = true
    const seq = ++historySeq
    try {
        const { data } = await api.get(`/customer-visits/${visit.id}/history`)
        if (seq === historySeq) history.value = data
    } catch (e) {
        if (seq === historySeq) historyError.value = e?.response?.data?.message || 'Không tải được lịch sử, vui lòng thử lại.'
    } finally {
        if (seq === historySeq) historyLoading.value = false
    }
}
const closeHistory = () => { historySeq++; historyOf.value = null; history.value = null }
const assignFromHistory = () => { const visit = historyOf.value; closeHistory(); if (visit) openAssign(visit) }

// Gom các lượt theo ngày cho dễ đọc.
const historyGroups = computed(() => {
    const groups = []
    for (const item of history.value?.visits || []) {
        const key = dateText(item.first_seen_at)
        let group = groups[groups.length - 1]
        if (!group || group.date !== key) { group = { date: key, items: [] }; groups.push(group) }
        group.items.push(item)
    }
    return groups
})
const historyNote = computed(() => {
    if (!history.value) return ''
    if (history.value.matched_by === 'face') return 'Chưa có tên: các lượt dưới đây được gom theo khuôn mặt giống nhau (chỉ còn trong thời hạn lưu dữ liệu).'
    if (history.value.matched_by === 'single') return 'Lượt này không còn dữ liệu khuôn mặt nên không gom được các lần đến khác.'
    return ''
})

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
                (cần bật cả nhận diện khuôn mặt). Lịch sử lượt đến và ảnh camera tự xóa sau {{ retentionDays }} ngày.
            </p>
        </div>

        <!-- Trạng thái Camera Intelligence nền (chỉ hiện khi đã bật chế độ camera cửa) -->
        <div
            v-if="trackingEnabled && doorCamera.enabled"
            class="flex items-start gap-2 rounded-xl border p-3 text-sm"
            :class="doorCamera.online && doorCamera.camera_ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
        >
            <Camera v-if="doorCamera.online && doorCamera.camera_ok" class="mt-0.5 h-4 w-4 shrink-0" />
            <CameraOff v-else class="mt-0.5 h-4 w-4 shrink-0" />
            <p v-if="!doorCamera.online">
                Video camera cửa có thể vẫn xem được, nhưng Camera Intelligence đã mất heartbeat nên chưa ghi nhận lượt khách mới.
                Kiểm tra dịch vụ trong <Link href="/settings" class="font-semibold underline">Cài đặt → Khách hàng → Camera cửa</Link>.
            </p>
            <p v-else-if="!doorCamera.camera_ok">Camera Intelligence đang chạy nhưng không lấy được ảnh từ camera{{ doorCamera.message ? ': ' + doorCamera.message : '' }}. Kiểm tra nguồn RTSP trong <Link href="/settings" class="font-semibold underline">Cài đặt → Khách hàng → Camera cửa</Link>.</p>
            <p v-else>Camera Intelligence và camera cửa đang hoạt động.</p>
        </div>

        <!-- Camera xem trực tiếp -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <ScanFace class="h-4 w-4 shrink-0 text-blue-600" />
                    <h3 class="truncate text-sm font-bold text-slate-800">Camera ra vào</h3>
                    <span v-if="showDoor && doorCameras.length > 1" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700">{{ receivingDoorCameras.length }}/{{ doorCameras.length }} camera</span>
                    <span v-if="faceFeatureOn && showDoor" class="truncate text-xs text-slate-500">{{ doorStatus }}</span>
                    <span v-else-if="faceFeatureOn && cameraActive" class="truncate text-xs text-slate-500">{{ cameraStatus }}</span>
                </div>

                <div v-if="faceFeatureOn" class="flex shrink-0 items-center gap-1.5">
                    <!-- Chuyển qua lại giữa camera cửa và camera máy -->
                    <div v-if="hasDoor" class="grid grid-cols-2 gap-0.5 rounded-lg bg-slate-100 p-0.5 text-xs font-semibold">
                        <button type="button" class="flex min-h-9 items-center gap-1 rounded-md px-2.5 transition" :class="showDoor ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" @click="setSource('door')"><Video class="h-4 w-4" />Camera cửa</button>
                        <button type="button" class="flex min-h-9 items-center gap-1 rounded-md px-2.5 transition" :class="!showDoor ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" @click="setSource('local')"><Camera class="h-4 w-4" />Camera máy</button>
                    </div>
                    <template v-if="!showDoor">
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
                            @click="toggleLocal"
                        >
                            <CameraOff v-if="cameraActive" class="h-5 w-5" />
                            <Camera v-else class="h-5 w-5" />
                        </button>
                    </template>
                </div>
            </div>

            <p v-if="!faceFeatureOn" class="p-4 text-sm text-slate-500">
                Chưa bật nhận diện khuôn mặt. Bật trong <Link href="/settings" class="font-semibold text-blue-600 underline">Cài đặt → Khách hàng</Link> để xem camera tại đây.
            </p>

            <!-- Mỗi camera có peer WebRTC riêng; AI nhận diện chúng song song. -->
            <div v-else-if="showDoor" class="bg-slate-900 p-2">
                <div class="grid gap-2" :class="doorCameras.length > 1 ? 'md:grid-cols-2' : 'grid-cols-1'">
                    <DoorCameraStream v-for="camera in doorCameras" :key="camera.id" :camera="camera" :enabled="faceFeatureOn && showDoor" :source-hash="doorCamera.source_hash || ''" :webrtc-url="doorCamera.webrtc_url || ''" />
                </div>
            </div>

            <p v-else-if="!cameraActive" class="p-4 text-sm text-slate-500">Bấm biểu tượng camera để xem trực tiếp và bắt khuôn mặt khách bằng camera của máy này.</p>

            <!-- Camera máy: bắt mặt ngay trên trình duyệt -->
            <div v-else class="bg-slate-900 p-2">
                <div class="relative mx-auto overflow-hidden rounded-lg bg-black" :style="stageStyle">
                    <video v-show="!cameraFailed" :ref="attachPreview" class="absolute inset-0 h-full w-full" :class="mirrored ? '-scale-x-100' : ''" autoplay muted playsinline @loadedmetadata="onMeta"></video>
                    <div v-if="cameraFailed" class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-rose-300">{{ cameraStatus }}</div>

                    <!-- Khung bắt khuôn mặt + tên -->
                    <div
                        v-for="face in liveFaces"
                        :key="face.id"
                        class="pointer-events-none absolute rounded-md border-2 transition-all duration-150"
                        :class="boxTone[face.kind]"
                        :style="boxStyle(face, mirrored)"
                    >
                        <span class="absolute max-w-[12rem] truncate rounded px-1.5 py-0.5 text-[11px] font-semibold leading-none" :class="[labelTone[face.kind], labelPos(face)]">{{ face.label }}</span>
                    </div>
                </div>

                <!-- Ảnh khuôn mặt vừa bắt được + tên -->
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
                <div
                    v-for="visit in visits.data"
                    :key="visit.id"
                    role="button"
                    tabindex="0"
                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm transition hover:border-blue-300 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                    title="Xem lịch sử các lần đến"
                    @click="openHistory(visit)"
                    @keydown.enter.self.prevent="openHistory(visit)"
                    @keydown.space.self.prevent="openHistory(visit)"
                >
                    <img v-if="visit.thumb" :src="visit.thumb" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover ring-1 ring-slate-200">
                    <span v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400"><User class="h-6 w-6" /></span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span v-if="visit.present" class="h-2 w-2 shrink-0 rounded-full bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.16)]" title="Đang ở cửa hàng" aria-label="Đang ở cửa hàng" />
                            <span class="truncate text-sm font-bold text-slate-900">{{ visit.customer ? visit.customer.full_name : 'Khách chưa có tên' }}</span>
                        </div>
                        <div v-if="visit.customer?.phone" class="truncate text-xs text-slate-500">{{ visit.customer.phone }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ dateText(visit.first_seen_at) }} · {{ timeText(visit.first_seen_at) }}<span v-if="visit.minutes"> – {{ timeText(visit.last_seen_at) }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="kindOf(visit).cls">{{ kindOf(visit).text }}</span>
                            <span v-if="visit.visit_count > 1" class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-700 ring-1 ring-violet-200">{{ visit.visit_count }} lần ghé</span>
                            <span v-if="visit.present" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">Đang ở CH</span>
                            <span
                                v-for="camera in visit.cameras"
                                :key="camera.id"
                                class="inline-flex max-w-32 items-center gap-1 truncate rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200"
                                :title="`Ghi nhận từ ${camera.name}`"
                            >
                                <Video class="h-3 w-3 shrink-0" />
                                <span class="truncate">{{ camera.name }}</span>
                            </span>
                            <span class="text-[11px] text-slate-400">{{ durationText(visit.minutes) }}</span>
                            <History class="ml-auto h-3.5 w-3.5 text-slate-300" aria-hidden="true" />
                        </div>
                    </div>

                    <div v-if="canManage" class="flex shrink-0 flex-col gap-1.5" @click.stop @keydown.stop>
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
            <PaginationBar :data="visits" :filters="filters" ajax @navigate="navigatePage" @per-page="changeVisitPageSize" />
        </div>
        <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            Chưa có lượt khách nào trong khoảng này.
        </div>

        <!-- Lịch sử các lần đến của người trong lượt khách đã bấm -->
        <BaseModal v-if="historyOf" title="Lịch sử lượt khách" size="md" @close="closeHistory">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <img v-if="history?.person?.thumb || historyOf.thumb" :src="history?.person?.thumb || historyOf.thumb" alt="" class="h-16 w-16 shrink-0 rounded-full object-cover ring-1 ring-slate-200">
                    <span v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400"><User class="h-7 w-7" /></span>
                    <div class="min-w-0">
                        <div class="truncate text-base font-bold text-slate-900">{{ history?.person?.name || historyOf.customer?.full_name || 'Khách chưa có tên' }}</div>
                        <div v-if="history?.person?.phone || historyOf.customer?.phone" class="truncate text-sm text-slate-500">{{ history?.person?.phone || historyOf.customer?.phone }}</div>
                        <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="kindOf(historyOf).cls">{{ kindOf(historyOf).text }}</span>
                    </div>
                </div>

                <p v-if="historyLoading" class="py-6 text-center text-sm text-slate-400">Đang tải lịch sử…</p>
                <p v-else-if="historyError" class="rounded-lg bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ historyError }}</p>

                <template v-else-if="history">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-2.5">
                            <div class="text-xl font-bold leading-none text-slate-900 tabular-nums">{{ history.stats.count }}</div>
                            <div class="mt-1 text-[11px] text-slate-500">Số lượt đến</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2.5">
                            <div class="text-xl font-bold leading-none text-slate-900 tabular-nums">{{ history.stats.days }}</div>
                            <div class="mt-1 text-[11px] text-slate-500">Số ngày khác nhau</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2.5">
                            <div class="text-sm font-bold leading-none text-slate-900">{{ dateText(history.stats.first_at) }}</div>
                            <div class="mt-1 text-[11px] text-slate-500">Lần đầu thấy</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2.5">
                            <div class="text-sm font-bold leading-none text-slate-900">{{ dateText(history.stats.last_at) }}</div>
                            <div class="mt-1 text-[11px] text-slate-500">Gần nhất · ở TB {{ durationText(history.stats.avg_minutes) }}</div>
                        </div>
                    </div>

                    <p v-if="historyNote" class="rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800">{{ historyNote }}</p>

                    <div class="max-h-72 space-y-3 overflow-y-auto pr-1">
                        <div v-for="group in historyGroups" :key="group.date">
                            <div class="mb-1 text-xs font-bold text-slate-500">{{ group.date }}</div>
                            <div class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                                <div v-for="item in group.items" :key="item.id" class="flex items-center gap-2.5 px-2.5 py-2 text-sm" :class="item.current ? 'bg-blue-50' : 'bg-white'">
                                    <img v-if="item.thumb" :src="item.thumb" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-slate-200">
                                    <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400"><User class="h-4 w-4" /></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-slate-800 tabular-nums">{{ timeText(item.first_seen_at) }}<span v-if="item.minutes"> – {{ timeText(item.last_seen_at) }}</span></div>
                                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                            <span>{{ durationText(item.minutes) }}</span>
                                            <span v-if="item.camera" class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700 ring-1 ring-sky-200">
                                                <Video class="h-3 w-3" />{{ item.camera.name }}
                                            </span>
                                        </div>
                                    </div>
                                    <span v-if="item.current" class="shrink-0 rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white">Lượt đang xem</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="history.truncated" class="text-xs text-slate-400">Chỉ hiện 100 lượt gần nhất.</p>
                </template>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button v-if="canManage && !historyOf.customer" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100" @click="assignFromHistory"><UserRoundPlus class="h-4 w-4" />Gán tên</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="closeHistory">Đóng</button>
                </div>
            </template>
        </BaseModal>

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
