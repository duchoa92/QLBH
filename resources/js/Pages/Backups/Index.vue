<script setup>
import { router, useForm } from '@inertiajs/vue3'
import { computed, onUnmounted, ref, watch } from 'vue'
import {
    DatabaseBackup,
    Download,
    HardDrive,
    Play,
    Trash2,
    ScanSearch,
    RotateCcw,
    Upload,
    X,
    Clock,
    AlertTriangle,
    FileArchive,
    Cloud,
    Link2,
    ChevronDown,
} from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { useConfirm } from '@/Composables/useConfirm'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    hide_header: { type: Boolean, default: false },
    backups: { type: Array, default: () => [] },
    cloud_connections: { type: Array, default: () => [] },
    cloud_oauth: { type: Object, default: () => ({}) },
    schedule: {
        type: Object,
        default: () => ({
            enabled: false,
            frequency: 'daily',
            time: '02:00',
            weekday: 1,
            monthday: 1,
        }),
    },
    restore_preview: { type: Object, default: null },
})

const scheduleForm = useForm({
    enabled: Boolean(props.schedule.enabled),
    frequency: props.schedule.frequency || 'daily',
    time: props.schedule.time || '02:00',
    weekday: Number(props.schedule.weekday ?? 1),
    monthday: Number(props.schedule.monthday ?? 1),
})

const restoreForm = useForm({ mode: 'merge', conflict_policy: 'keep_current', confirmed: false, operation_id: null })
const uploadForm = useForm({ backup_file: null, operation_id: null })
const cloudForm = useForm({ provider: 's3', name: '', key: '', secret: '', region: 'us-east-1', bucket: '', endpoint: '', base_path: 'backups', path_style: false, url: '', username: '', token: '' })
const confirmBox = useConfirm()
const selectedFileName = ref('')
const restorePreview = ref(props.restore_preview)
const showRestorePreview = ref(Boolean(restorePreview.value))
const showCloudStorage = ref(false)
const operationProgress = ref({ active: false, percent: 0, message: '', state: 'running' })
let progressTimer = null
let activeOperation = null

const progressPercent = computed(() => uploadForm.progress?.percentage ?? operationProgress.value.percent)
const progressVisible = computed(() => operationProgress.value.active || operationProgress.value.state !== 'running' || Boolean(uploadForm.progress))
const backupMonth = ref('')
const backupPage = ref(1)
const selectedBackupNames = ref([])
const backupMonths = computed(() => [...new Set(props.backups
    .map((backup) => String(backup.created_at || '').slice(0, 7))
    .filter((month) => /^\d{4}-\d{2}$/.test(month)))].sort().reverse())
const filteredBackups = computed(() => props.backups.filter((backup) => !backupMonth.value || String(backup.created_at || '').startsWith(backupMonth.value)))
const backupPageSize = 10
const backupPageCount = computed(() => Math.max(1, Math.ceil(filteredBackups.value.length / backupPageSize)))
const paginatedBackups = computed(() => filteredBackups.value.slice((backupPage.value - 1) * backupPageSize, backupPage.value * backupPageSize))
const currentPageSelected = computed(() => paginatedBackups.value.length > 0 && paginatedBackups.value.every((backup) => selectedBackupNames.value.includes(backup.name)))
const scheduleDirty = computed(() =>
    scheduleForm.enabled !== Boolean(props.schedule.enabled)
    || scheduleForm.frequency !== (props.schedule.frequency || 'daily')
    || scheduleForm.time !== (props.schedule.time || '02:00')
    || Number(scheduleForm.weekday) !== Number(props.schedule.weekday ?? 1)
    || Number(scheduleForm.monthday) !== Number(props.schedule.monthday ?? 1)
)

watch(backupMonth, () => { backupPage.value = 1 })
watch(() => props.backups, (backups) => {
    const existingNames = new Set(backups.map((backup) => backup.name))
    selectedBackupNames.value = selectedBackupNames.value.filter((name) => existingNames.has(name))
    backupPage.value = Math.min(backupPage.value, backupPageCount.value)
})

// randomUUID() is unavailable on non-secure origins in some browsers (for
// example, when the app is opened by a LAN IP over HTTP). Keep the server's
// UUID validation while allowing those browsers to start backup operations.
const createOperationId = () => {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID()
    }

    const bytes = new Uint8Array(16)
    if (typeof globalThis.crypto?.getRandomValues === 'function') {
        globalThis.crypto.getRandomValues(bytes)
    } else {
        for (let index = 0; index < bytes.length; index += 1) {
            bytes[index] = Math.floor(Math.random() * 256)
        }
    }

    bytes[6] = (bytes[6] & 0x0f) | 0x40
    bytes[8] = (bytes[8] & 0x3f) | 0x80
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

const startProgress = () => {
    activeOperation = createOperationId()
    operationProgress.value = { active: true, percent: 1, message: 'Đang khởi chạy thao tác…', state: 'running' }
    clearInterval(progressTimer)
    progressTimer = setInterval(async () => {
        try {
            const response = await fetch(`/backups/progress/${activeOperation}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            if (!response.ok) return
            const result = await response.json()
            if (result) {
                operationProgress.value = { ...operationProgress.value, ...result, active: result.state === 'running' }
                if (result.state !== 'running') {
                    clearInterval(progressTimer)
                }
            }
        } catch (_) { /* Polling retries on the next interval. */ }
    }, 500)
    return activeOperation
}

onUnmounted(() => clearInterval(progressTimer))

const dismissProgress = () => {
    if (operationProgress.value.active) return
    operationProgress.value = { active: false, percent: 0, message: '', state: 'running' }
}

const backupPath = (name) => `/backups/${encodeURIComponent(name)}`

const runBackup = () => {
    confirmBox.show({
        title: 'Tạo bản sao lưu',
        message: 'Tạo bản sao lưu cơ sở dữ liệu và các tệp người dùng đã tải lên?',
        confirmText: 'Sao lưu',
        onConfirm: () => {
            router.post('/backups', { operation_id: startProgress() }, { preserveScroll: true })
        },
    })
}

const removeBackup = (backup) => {
    confirmBox.show({
        title: 'Xóa bản sao lưu',
        message: `Xóa tệp sao lưu ${backup.name}?`,
        confirmText: 'Xóa',
        onConfirm: () => {
            router.delete(backupPath(backup.name), { data: { operation_id: startProgress() }, preserveScroll: true })
        },
    })
}

const toggleBackupSelection = (name) => {
    selectedBackupNames.value = selectedBackupNames.value.includes(name)
        ? selectedBackupNames.value.filter((selected) => selected !== name)
        : [...selectedBackupNames.value, name]
}

const toggleCurrentPageSelection = () => {
    const pageNames = paginatedBackups.value.map((backup) => backup.name)
    selectedBackupNames.value = currentPageSelected.value
        ? selectedBackupNames.value.filter((name) => !pageNames.includes(name))
        : [...new Set([...selectedBackupNames.value, ...pageNames])]
}

const removeSelectedBackups = () => {
    const names = [...selectedBackupNames.value]
    if (!names.length) return
    confirmBox.show({
        title: 'Xóa nhiều bản sao lưu',
        message: `Xóa ${names.length} tệp sao lưu đã chọn? Thao tác này không thể hoàn tác.`,
        confirmText: `Xóa ${names.length} tệp`,
        onConfirm: () => {
            router.delete('/backups/bulk', {
                data: { names, operation_id: startProgress() },
                preserveScroll: true,
                onSuccess: () => { selectedBackupNames.value = [] },
            })
        },
    })
}

const saveSchedule = () => scheduleForm.put('/backups/schedule', { preserveScroll: true })
const openRestorePreview = (preview) => {
    if (!preview?.summary || !preview?.tables) return
    restorePreview.value = preview
    showRestorePreview.value = true
    restoreForm.mode = preview.summary.schema_compatible && preview.summary.new_tables === 0 ? 'merge' : 'replace'
}

const applyPreviewFromPage = (page) => openRestorePreview(page?.props?.restore_preview)

watch(() => props.restore_preview, openRestorePreview, { immediate: true })

const inspectBackup = (backup) => router.post(`${backupPath(backup.name)}/inspect`, { operation_id: startProgress() }, {
    preserveScroll: true,
    onSuccess: applyPreviewFromPage,
})
const inspectCloudBackup = (connection, backup) => router.post(`/backups/cloud/${connection.id}/inspect`, { name: backup.name, operation_id: startProgress() }, {
    preserveScroll: true,
    onSuccess: applyPreviewFromPage,
})
const saveCloudConnection = () => {
    if (['google_drive', 'onedrive'].includes(cloudForm.provider)) {
        router.get(`/backups/cloud/oauth/${cloudForm.provider}/start`, { name: cloudForm.name }, { preserveScroll: true })
        return
    }
    cloudForm.post('/backups/cloud-connections', {
        preserveScroll: true,
        onSuccess: () => { cloudForm.key = ''; cloudForm.secret = ''; cloudForm.token = '' },
    })
}
const toggleCloudConnection = (connection) => router.patch(`/backups/cloud-connections/${connection.id}/toggle`, {}, { preserveScroll: true })
const removeCloudConnection = (connection) => confirmBox.show({
    title: 'Xóa kết nối đám mây',
    message: `Xóa kết nối “${connection.name}”? Các bản sao lưu đã có trên dịch vụ đám mây sẽ được giữ nguyên.`,
    confirmText: 'Xóa kết nối',
    onConfirm: () => router.delete(`/backups/cloud-connections/${connection.id}`, { preserveScroll: true }),
})

const selectBackupFile = (event) => {
    uploadForm.backup_file = event.target.files?.[0] || null
    selectedFileName.value = uploadForm.backup_file?.name || ''
}

const inspectUploadedFile = () => {
    if (!uploadForm.backup_file) return
    uploadForm.operation_id = startProgress()
    uploadForm.post('/backups/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            applyPreviewFromPage(page)
            uploadForm.reset()
            selectedFileName.value = ''
        },
        onFinish: () => { uploadForm.progress = null },
    })
}

const restoreBackup = () => {
    const preview = restorePreview.value
    if (!preview) return
    const warning = restoreForm.mode === 'replace'
        ? 'Thay toàn bộ cơ sở dữ liệu hiện tại bằng bản sao lưu? Hệ thống sẽ tự tạo một bản cứu hộ trước khi thực hiện.'
        : restoreForm.conflict_policy === 'use_backup'
            ? 'Gộp dữ liệu và ghi đè các dòng trùng bằng giá trị trong bản sao lưu?'
            : 'Gộp dữ liệu mới, giữ nguyên các dòng trùng đang có?'
    confirmBox.show({
        title: restoreForm.mode === 'replace' ? 'Thay toàn bộ dữ liệu?' : 'Gộp dữ liệu sao lưu?',
        message: warning,
        confirmText: restoreForm.mode === 'replace' ? 'Thay và khôi phục' : 'Gộp dữ liệu',
        onConfirm: () => {
            restoreForm.confirmed = true
            restoreForm.operation_id = startProgress()
            restoreForm.post(`${backupPath(preview.backup)}/restore`, {
                preserveScroll: true,
                onFinish: () => { restoreForm.confirmed = false },
            })
        },
    })
}

const formatSize = (size) => size < 1024 * 1024
    ? `${(size / 1024).toFixed(1)} KB`
    : `${(size / (1024 * 1024)).toFixed(2)} MB`

// Dữ liệu options cho FloatingSelect
const frequencyOptions = [
    { value: 'daily', label: 'Mỗi ngày' },
    { value: 'weekly', label: 'Mỗi tuần' },
    { value: 'monthly', label: 'Mỗi tháng' },
]

const weekdayOptions = [
    { value: 1, label: 'Thứ Hai' },
    { value: 2, label: 'Thứ Ba' },
    { value: 3, label: 'Thứ Tư' },
    { value: 4, label: 'Thứ Năm' },
    { value: 5, label: 'Thứ Sáu' },
    { value: 6, label: 'Thứ Bảy' },
    { value: 0, label: 'Chủ Nhật' },
]

const monthdayOptions = Array.from({ length: 31 }, (_, i) => ({
    value: i + 1,
    label: `Ngày ${i + 1}`
}))

const restoreModeOptions = [
    { value: 'merge', label: 'Gộp dữ liệu, giữ các dòng hiện có' },
    { value: 'replace', label: 'Thay toàn bộ cơ sở dữ liệu bằng bản sao lưu' },
]

const conflictPolicyOptions = [
    { value: 'keep_current', label: 'Giữ nguyên dữ liệu hiện tại' },
    { value: 'use_backup', label: 'Ghi đè bằng dữ liệu bản sao lưu' },
]
</script>

<template>
    <div class="space-y-3">
        <Teleport to="body">
            <div v-if="progressVisible" class="fixed inset-0 z-[10020] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-live="polite" aria-label="Tiến trình xử lý sao lưu">
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-slate-800">{{ operationProgress.active ? 'Đang xử lý' : operationProgress.state === 'failed' ? 'Thao tác thất bại' : 'Đã hoàn tất' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ operationProgress.message || 'Đang xử lý bản sao lưu…' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-bold tabular-nums text-emerald-700">{{ Math.round(progressPercent) }}%</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100" role="progressbar" :aria-valuenow="Math.round(progressPercent)" aria-valuemin="0" aria-valuemax="100" aria-label="Tiến trình xử lý">
                        <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300" :style="{ width: `${Math.max(3, progressPercent)}%` }"></div>
                    </div>
                    <div v-if="!operationProgress.active" class="mt-5 flex justify-end">
                        <button type="button" class="h-10 min-w-24 rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700" @click="dismissProgress">OK</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- KHỐI QUẢN LÝ SAO LƯU TỔNG HỢP (GOM CHUNG THIẾT LẬP LỊCH VÀ SAO LƯU NGAY) -->
        <DataPanel class="p-3.5 sm:p-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/20">
                        <HardDrive :size="20" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Quản lý sao lưu hệ thống</h2>
                        <p class="text-xs text-slate-500">Tạo bản sao lưu thủ công hoặc thiết lập lịch tự động chạy qua Laravel Scheduler.</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <div v-if="schedule.last_run" class="flex items-center gap-1.5 text-xs font-medium text-slate-500 bg-white px-3 py-1.5 rounded-lg border border-slate-200/80 shadow-2xs">
                        <Clock :size="14" class="text-slate-400" />
                        <span>Lần chạy gần nhất: <strong class="text-slate-700">{{ schedule.last_run }}</strong></span>
                    </div>
                </div>
                
            </div>

            <!-- Form Lịch sao lưu tự động -->
            <form class="mt-4" @submit.prevent="saveSchedule">
                <div class="flex flex-wrap items-center gap-3">
                    <ActionButton :disabled="scheduleForm.processing || operationProgress.active" class="!bg-emerald-600 hover:!bg-emerald-700 !text-white h-10 px-4 font-semibold shadow-sm transition-all shrink-0" @click="runBackup">
                        <DatabaseBackup :size="17" /> Sao lưu ngay
                    </ActionButton>
                    <label class="inline-flex h-10 items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 cursor-pointer select-none">
                        <input v-model="scheduleForm.enabled" type="checkbox" class="sr-only peer" :disabled="scheduleForm.processing">
                        <span class="relative h-5 w-9 rounded-full bg-slate-300 transition-colors peer-checked:bg-emerald-600 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4"></span>
                        <span class="text-xs font-semibold text-slate-700">Sao lưu tự động</span>
                    </label>
                    <template v-if="scheduleForm.enabled">
                        <div class="w-40">
                            <!-- Tần suất -->
                            <FloatingSelect
                                name="frequency"
                                v-model="scheduleForm.frequency"
                                label="Tần suất"
                                :options="frequencyOptions"
                                :disabled="scheduleForm.processing"
                            />
                        </div>
                        <div class="w-40">
                            <FloatingInput
                                name="time"
                                type="time"
                                v-model="scheduleForm.time"
                                label="Giờ chạy hàng ngày"
                                required
                                :disabled="scheduleForm.processing"
                            />
                        </div>
                        <div v-if="scheduleForm.frequency === 'weekly'" class="w-40">
                            <FloatingSelect
                                name="weekday"
                                v-model.number="scheduleForm.weekday"
                                label="Ngày trong tuần"
                                :options="weekdayOptions"
                                :disabled="scheduleForm.processing"
                            />
                        </div>
                        <div v-if="scheduleForm.frequency === 'monthly'" class="w-40">
                            <FloatingSelect
                                name="monthday"
                                v-model.number="scheduleForm.monthday"
                                label="Ngày trong tháng"
                                :options="monthdayOptions"
                                :disabled="scheduleForm.processing"
                            />
                        </div>
                        <ActionButton type="submit" :disabled="scheduleForm.processing || !scheduleDirty" class="!bg-slate-900 hover:!bg-slate-800 !text-white h-10 px-5 font-semibold shadow-sm">
                                <Play :size="15" /> Lưu cấu hình lịch
                        </ActionButton>
                    </template>
                </div>
            </form>

            <div v-if="schedule.last_error" class="mt-3 flex items-start gap-2.5 rounded-xl bg-red-50 p-3 text-xs font-medium text-red-700 ring-1 ring-red-200/80">
                <AlertTriangle :size="16" class="shrink-0 mt-0.5 text-red-500" />
                <span>Lần sao lưu gần nhất gặp lỗi: {{ schedule.last_error }}</span>
            </div>
        </DataPanel>

        <DataPanel class="p-3.5 sm:p-4">
            <button
                type="button"
                class="flex w-full items-center gap-3 text-left"
                :aria-expanded="showCloudStorage"
                aria-controls="cloud-backup-storage"
                @click="showCloudStorage = !showCloudStorage"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-600/15"><Cloud :size="20" /></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2 text-base font-bold text-slate-800">Kho sao lưu đám mây <span v-if="cloud_connections.length" class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">{{ cloud_connections.length }} kết nối</span></span>
                    <span class="mt-0.5 block text-xs text-slate-500">Nhấn để {{ showCloudStorage ? 'thu gọn' : 'quản lý kết nối và bản sao lưu' }}.</span>
                </span>
                <ChevronDown :size="18" class="shrink-0 text-slate-500 transition-transform duration-200" :class="showCloudStorage && 'rotate-180'" />
            </button>

            <div v-if="showCloudStorage" id="cloud-backup-storage" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
            <div class="text-xs text-slate-500">Kết nối S3/R2/Wasabi, WebDAV/Nextcloud, Google Drive hoặc OneDrive. Bản sao lưu mới sẽ được tải lên mọi kết nối đang bật.</div>
            <form class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3" @submit.prevent="saveCloudConnection">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-xs font-semibold text-slate-600">Loại kho
                        <select v-model="cloudForm.provider" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="s3">S3 tương thích (AWS / R2 / Wasabi)</option>
                            <option value="webdav">WebDAV (Nextcloud / ownCloud)</option>
                            <option value="google_drive">Google Drive</option>
                            <option value="onedrive">OneDrive</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Tên kết nối
                        <input v-model="cloudForm.name" required maxlength="100" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Ví dụ: Kho dự phòng R2">
                    </label>
                    <template v-if="cloudForm.provider === 's3'">
                        <label class="text-xs font-semibold text-slate-600">Access key
                            <input v-model="cloudForm.key" required autocomplete="off" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        </label>
                        <label class="text-xs font-semibold text-slate-600">Secret key
                            <input v-model="cloudForm.secret" required type="password" autocomplete="new-password" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        </label>
                        <label class="text-xs font-semibold text-slate-600">Bucket
                            <input v-model="cloudForm.bucket" required class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        </label>
                        <label class="text-xs font-semibold text-slate-600">Region
                            <input v-model="cloudForm.region" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="us-east-1 (R2: auto)">
                        </label>
                        <label class="text-xs font-semibold text-slate-600">Endpoint tùy chọn
                            <input v-model="cloudForm.endpoint" type="url" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="https://… (R2 / S3 compatible)">
                        </label>
                    </template>
                    <template v-else-if="cloudForm.provider === 'webdav'">
                        <label class="text-xs font-semibold text-slate-600 sm:col-span-2">WebDAV URL
                            <input v-model="cloudForm.url" type="url" required class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="https://cloud.example.com/remote.php/dav/files/user">
                        </label>
                        <label class="text-xs font-semibold text-slate-600">Tên đăng nhập
                            <input v-model="cloudForm.username" required autocomplete="username" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        </label>
                        <label class="text-xs font-semibold text-slate-600">Mật khẩu ứng dụng / token
                            <input v-model="cloudForm.token" required type="password" autocomplete="new-password" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        </label>
                    </template>
                    <template v-else>
                        <div class="space-y-1 rounded-lg border border-sky-100 bg-sky-50/70 p-3 text-xs text-slate-600 sm:col-span-2 lg:col-span-4">
                            <p class="font-semibold text-slate-800">Kết nối an toàn bằng OAuth</p>
                            <p>Nhập OAuth Client ID/Secret vào cấu hình máy chủ rồi khai báo URI chuyển hướng bên dưới trong ứng dụng Google Cloud hoặc Microsoft Entra. Secret không được nhập hay gửi qua trình duyệt.</p>
                            <p v-if="cloudForm.provider === 'google_drive'" class="break-all">GOOGLE_DRIVE_CLIENT_ID / GOOGLE_DRIVE_CLIENT_SECRET · Redirect URI: <code>{{ cloud_oauth.google_redirect_uri }}</code></p>
                            <p v-else class="break-all">MICROSOFT_ONEDRIVE_CLIENT_ID / MICROSOFT_ONEDRIVE_CLIENT_SECRET · Redirect URI: <code>{{ cloud_oauth.onedrive_redirect_uri }}</code></p>
                            <p v-if="cloudForm.provider === 'onedrive'">Quyền delegated yêu cầu: Files.ReadWrite và offline_access.</p>
                            <p v-if="!cloud_oauth[cloudForm.provider]" class="font-semibold text-amber-700">Chưa cấu hình OAuth app trên máy chủ.</p>
                        </div>
                    </template>
                    <label v-if="['s3', 'webdav'].includes(cloudForm.provider)" class="text-xs font-semibold text-slate-600">Thư mục lưu
                        <input v-model="cloudForm.base_path" class="mt-1 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500" placeholder="backups">
                    </label>
                </div>
                <label v-if="cloudForm.provider === 's3'" class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                    <input v-model="cloudForm.path_style" type="checkbox" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"> Dùng path-style endpoint (thường dùng với MinIO)
                </label>
                <p v-if="cloudForm.errors.provider || cloudForm.errors.name || cloudForm.errors.key || cloudForm.errors.secret || cloudForm.errors.bucket || cloudForm.errors.endpoint || cloudForm.errors.url || cloudForm.errors.username || cloudForm.errors.token" class="text-xs font-medium text-red-600">
                    {{ cloudForm.errors.provider || cloudForm.errors.name || cloudForm.errors.key || cloudForm.errors.secret || cloudForm.errors.bucket || cloudForm.errors.endpoint || cloudForm.errors.url || cloudForm.errors.username || cloudForm.errors.token }}
                </p>
                <div class="flex justify-end">
                    <ActionButton type="submit" :disabled="cloudForm.processing || (['google_drive', 'onedrive'].includes(cloudForm.provider) && !cloud_oauth[cloudForm.provider])" class="h-10 !bg-sky-700 px-4 text-xs font-semibold !text-white hover:!bg-sky-800">
                        <Link2 :size="15" /> {{ cloudForm.processing ? 'Đang kiểm tra kết nối…' : ['google_drive', 'onedrive'].includes(cloudForm.provider) ? `Kết nối ${cloudForm.provider === 'google_drive' ? 'Google Drive' : 'OneDrive'}` : 'Kiểm tra & kết nối' }}
                    </ActionButton>
                </div>
            </form>

            <div v-if="cloud_connections.length" class="mt-4 space-y-3">
                <div v-for="connection in cloud_connections" :key="connection.id" class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3">
                        <div>
                            <div class="flex items-center gap-2 text-sm font-bold text-slate-800"><Cloud :size="15" class="text-sky-700" />{{ connection.name }} <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500">{{ connection.provider }}</span></div>
                            <p class="mt-1 text-[11px]" :class="connection.error ? 'text-red-600' : 'text-slate-500'">{{ connection.error || (connection.enabled ? 'Đang đồng bộ bản sao lưu mới' : 'Đã tạm dừng đồng bộ') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="toggleCloudConnection(connection)">{{ connection.enabled ? 'Tạm dừng' : 'Bật đồng bộ' }}</button>
                            <button type="button" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50" @click="removeCloudConnection(connection)">Xóa kết nối</button>
                        </div>
                    </div>
                    <div v-if="connection.backups?.length" class="max-h-56 space-y-1 overflow-y-auto border-t border-slate-100 bg-slate-50/60 p-2">
                        <div v-for="backup in connection.backups" :key="backup.name" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2">
                            <div class="min-w-0"><p class="break-all text-xs font-semibold text-slate-700">{{ backup.name }}</p><p class="text-[10px] text-slate-500">{{ formatSize(backup.size) }}<span v-if="backup.created_at"> · {{ backup.created_at }}</span></p></div>
                            <button type="button" class="shrink-0 rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100" :disabled="operationProgress.active" @click="inspectCloudBackup(connection, backup)"><ScanSearch :size="13" class="mr-1 inline" />Kiểm tra & khôi phục</button>
                        </div>
                    </div>
                    <p v-else-if="!connection.error" class="border-t border-slate-100 px-3 py-2 text-xs text-slate-500">Chưa có bản sao lưu trên kho này.</p>
                </div>
            </div>
            </div>
        </DataPanel>

        <!-- Danh sách các bản sao lưu -->
        <DataPanel class="p-3.5 sm:p-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                <h2 class="text-base font-bold text-slate-800">Danh sách các bản sao lưu</h2>
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">Tổng số: {{ backups.length }} tệp</span>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <label class="text-xs font-semibold text-slate-600">Lọc theo tháng
                        <select v-model="backupMonth" class="ml-2 h-9 min-w-40 rounded-lg border-slate-300 py-1 pl-3 pr-8 text-xs focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Tất cả các tháng</option>
                            <option v-for="month in backupMonths" :key="month" :value="month">Tháng {{ month.slice(5, 7) }}/{{ month.slice(0, 4) }}</option>
                        </select>
                    </label>
                    <label v-if="paginatedBackups.length" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600">
                        <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" :checked="currentPageSelected" @change="toggleCurrentPageSelection">
                        Chọn trang này
                    </label>
                </div>
                <button v-if="selectedBackupNames.length" type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-50" :disabled="operationProgress.active" @click="removeSelectedBackups">
                    <Trash2 :size="14" /> Xóa {{ selectedBackupNames.length }} tệp đã chọn
                </button>
            </div>

            <!-- Upload file ngoài -->
            <form class="mb-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-3 sm:p-3.5 transition-colors hover:border-emerald-400" @submit.prevent="inspectUploadedFile">
                <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Khôi phục từ tệp sao lưu bên ngoài (.zip)</label>
                        <input type="file" accept=".zip,application/zip" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-slate-700 file:shadow-sm file:ring-1 file:ring-slate-200 hover:file:bg-slate-100 cursor-pointer" @change="selectBackupFile">
                        <span class="mt-1 block text-[11px] text-slate-500">{{ selectedFileName || 'Chọn tệp sao lưu chuẩn định dạng .zip từ máy tính (tối đa 512 MB).' }}</span>
                    </div>
                    <ActionButton type="submit" :disabled="!uploadForm.backup_file || uploadForm.processing" class="h-10 shrink-0 !bg-slate-800 hover:!bg-slate-900 !text-white px-4 text-xs font-semibold">
                        <Upload :size="15" /> {{ uploadForm.processing ? 'Đang tải lên...' : 'Tải lên & Đối chiếu' }}
                    </ActionButton>
                </div>
            </form>

            <!-- Table bản sao lưu -->
            <div v-if="paginatedBackups.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200/80 overflow-hidden bg-white">
                <div v-for="backup in paginatedBackups" :key="backup.name" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 transition-colors hover:bg-slate-50/80">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" class="mt-3 rounded border-slate-300 text-blue-600 focus:ring-blue-500" :checked="selectedBackupNames.includes(backup.name)" :aria-label="`Chọn ${backup.name}`" @change="toggleBackupSelection(backup.name)">
                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <FileArchive :size="18" />
                        </div>
                        <div>
                            <p class="font-bold text-sm text-slate-800 break-all">{{ backup.name }}</p>
                            <p class="mt-0.5 text-xs font-medium text-slate-500 flex items-center gap-2">
                                <span>{{ backup.created_at }}</span>
                                <span>•</span>
                                <span class="font-semibold text-slate-700">{{ formatSize(backup.size) }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 self-end sm:self-auto shrink-0">
                        <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50/50 px-2.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-colors" @click="inspectBackup(backup)">
                            <ScanSearch :size="14" /> Kiểm tra & Khôi phục
                        </button>
                        <a :href="`${backupPath(backup.name)}/download`" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                            <Download :size="14" /> Tải về
                        </a>
                        <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-red-200 bg-red-50/50 px-2.5 text-xs font-semibold text-red-600 hover:bg-red-100 transition-colors" @click="removeBackup(backup)">
                            <Trash2 :size="14" /> Xóa
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-8 text-center">
                <DatabaseBackup class="mx-auto text-slate-300 mb-2" :size="32" />
                <p class="text-sm font-semibold text-slate-600">{{ backups.length ? 'Không có bản sao lưu trong tháng này' : 'Chưa có bản sao lưu nào' }}</p>
                <p v-if="!backups.length" class="text-xs text-slate-400 mt-1">Bấm nút "Sao lưu ngay" ở trên để khởi tạo bản sao lưu đầu tiên.</p>
            </div>

            <div v-if="filteredBackups.length > backupPageSize" class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-500">Trang {{ backupPage }} / {{ backupPageCount }} · {{ filteredBackups.length }} tệp</p>
                <div class="flex items-center gap-2">
                    <button type="button" class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40" :disabled="backupPage <= 1" @click="backupPage -= 1">Trước</button>
                    <button type="button" class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40" :disabled="backupPage >= backupPageCount" @click="backupPage += 1">Tiếp</button>
                </div>
            </div>

            <p class="mt-3 text-[11px] text-slate-400">Tệp sao lưu gốc được lưu trữ an toàn tại thư mục <code class="rounded bg-slate-100 px-1 py-0.5 text-slate-600 font-mono">storage/app/backups</code>.</p>
        </DataPanel>

        <!-- Modal Đối chiếu & Khôi phục -->
        <Teleport to="body">
        <div v-if="showRestorePreview && restorePreview" class="fixed inset-0 z-[9990] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6" @click.self="showRestorePreview = false">
            <section class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10">
                <!-- Modal Header -->
                <header class="flex shrink-0 items-center justify-between border-b border-slate-200/80 bg-slate-50 px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <ScanSearch :size="20" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800">Đối chiếu dữ liệu trước khi khôi phục</h2>
                            <p class="text-xs font-medium text-slate-500 break-all">{{ restorePreview.backup }} · {{ restorePreview.created_at || 'Không xác định ngày tạo' }}</p>
                        </div>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 transition-colors" aria-label="Đóng" @click="showRestorePreview = false">
                        <X :size="20" />
                    </button>
                </header>

                <!-- Modal Body -->
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-6 text-xs sm:text-sm">
                    <!-- Thẻ thống kê tổng quan -->
                    <div class="grid gap-2.5 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-200/70">
                            <span class="block text-[11px] font-semibold text-slate-500 uppercase">Tổng số bảng</span>
                            <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ restorePreview.summary.backup_tables }} sao lưu / {{ restorePreview.summary.current_tables }} hiện tại</span>
                        </div>
                        <div class="rounded-xl p-2.5 border" :class="restorePreview.summary.row_count_difference ? 'bg-amber-50/80 border-amber-200 text-amber-900' : 'bg-slate-50 border-slate-200/70 text-slate-800'">
                            <span class="block text-[11px] font-semibold uppercase opacity-75">Số bản ghi (Dòng)</span>
                            <span class="text-sm font-bold mt-0.5 block">
                                {{ restorePreview.summary.backup_rows }} / {{ restorePreview.summary.current_rows }}
                                <span v-if="restorePreview.summary.row_count_difference" class="text-xs font-semibold">({{ restorePreview.summary.row_count_difference > 0 ? '+' : '' }}{{ restorePreview.summary.row_count_difference }})</span>
                            </span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-200/70">
                            <span class="block text-[11px] font-semibold text-slate-500 uppercase">Bảng đã mất</span>
                            <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ restorePreview.summary.missing_tables }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-200/70">
                            <span class="block text-[11px] font-semibold text-slate-500 uppercase">Bảng mới</span>
                            <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ restorePreview.summary.new_tables }}</span>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-2.5 border border-amber-200 col-span-2 sm:col-span-1">
                            <span class="block text-[11px] font-semibold text-amber-700 uppercase">Dữ liệu bị lệch</span>
                            <span class="text-sm font-bold text-amber-800 mt-0.5 block">{{ restorePreview.summary.mismatched_data_tables }} bảng</span>
                        </div>
                    </div>

                    <!-- Bảng chi tiết từng Table -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 uppercase text-[10px] font-bold tracking-wider text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Tên Bảng</th>
                                    <th class="py-2.5 px-3">Dòng (Sao lưu)</th>
                                    <th class="py-2.5 px-3">Dòng (Hiện tại)</th>
                                    <th class="py-2.5 px-3">Lệch</th>
                                    <th class="py-2.5 px-3">Cấu trúc Schema</th>
                                    <th class="py-2.5 px-3">Khớp dữ liệu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <tr v-for="(table, name) in restorePreview.tables" :key="name" class="hover:bg-slate-50/50">
                                    <td class="py-2 px-3 font-semibold text-slate-800">{{ name }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ table.backup_rows }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ table.current_rows }}</td>
                                    <td class="py-2 px-3" :class="table.backup_rows !== table.current_rows ? 'font-bold text-amber-600' : 'text-slate-400'">
                                        {{ table.backup_rows - table.current_rows }}
                                    </td>
                                    <td class="py-2 px-3" :class="table.schema_compatible ? 'text-emerald-600 font-semibold' : 'font-bold text-amber-600'">
                                        {{ table.schema_compatible ? 'Tương thích' : 'Không tương thích' }}
                                    </td>
                                    <td class="py-2 px-3" :class="table.data_matches ? 'text-emerald-600 font-semibold' : 'font-bold text-amber-600'">
                                        {{ table.data_matches ? 'Trùng khớp' : 'Có khác biệt' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cấu hình khôi phục -->
                    <div class="grid gap-3 sm:grid-cols-2 pt-2 border-t border-slate-100">
                        <!-- Mode -->
                        <FloatingSelect
                            name="restore_mode"
                            v-model="restoreForm.mode"
                            label="Phương thức khôi phục"
                            :options="restoreModeOptions"
                        />

                        <!-- Conflict Policy -->
                        <FloatingSelect
                            v-if="restoreForm.mode === 'merge'"
                            name="conflict_policy"
                            v-model="restoreForm.conflict_policy"
                            label="Xử lý khi bị trùng dữ liệu"
                            :options="conflictPolicyOptions"
                        />
                    </div>

                    <!-- Cảnh báo & Lưu ý -->
                    <div class="space-y-2 text-xs">
                        <p v-if="restoreForm.mode === 'merge'" class="text-slate-500">
                            * Chế độ Gộp sẽ thêm các dòng chưa tồn tại; các dòng trùng khóa chính/duy nhất sẽ được xử lý theo lựa chọn cài đặt ở trên.
                        </p>
                        <p v-if="restorePreview.summary.mismatched_data_tables > 0" class="rounded-xl bg-amber-50 p-2.5 text-amber-800 border border-amber-200">
                            ⚠️ Dữ liệu hiện tại lệch ở {{ restorePreview.summary.mismatched_data_tables }} bảng. Vui lòng kiểm tra kỹ trước khi bấm xác nhận.
                        </p>
                        <p v-if="!restorePreview.summary.schema_compatible || restorePreview.summary.new_tables > 0" class="rounded-xl bg-amber-50 p-2.5 text-amber-800 border border-amber-200">
                            ⚠️ Cấu trúc bảng khác nhau hoặc có bảng mới. Chế độ Gộp bị vô hiệu hóa để đảm bảo an toàn dữ liệu; vui lòng chọn "Thay toàn bộ" hoặc Hủy.
                        </p>
                        <p v-if="restorePreview.has_files" class="text-slate-500">
                            * Bản sao lưu bao gồm tệp đính kèm. Khôi phục sẽ áp dụng quy tắc tương ứng đối với thư mục tệp tin public/private.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <footer class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-6">
                    <button type="button" class="h-9 rounded-xl border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors" @click="showRestorePreview = false">
                        Hủy bỏ
                    </button>
                    <button type="button" :disabled="restoreForm.processing" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-blue-600 px-4 text-xs font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 transition-colors" @click="restoreBackup">
                        <RotateCcw :size="15" /> {{ restoreForm.processing ? 'Đang khôi phục...' : 'Thực hiện khôi phục' }}
                    </button>
                </footer>
            </section>
        </div>
        </Teleport>
    </div>
</template>
