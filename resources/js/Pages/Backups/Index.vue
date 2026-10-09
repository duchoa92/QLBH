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
            timezone: 'Asia/Ho_Chi_Minh',
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
    timezone: props.schedule.timezone || 'Asia/Ho_Chi_Minh',
    weekday: Number(props.schedule.weekday ?? 1),
    monthday: Number(props.schedule.monthday ?? 1),
})

const restoreForm = useForm({ mode: 'merge', conflict_policy: 'keep_current', confirmed: false, operation_id: null })
const uploadForm = useForm({ backup_file: null, operation_id: null })
const cloudForm = useForm({ provider: 's3', name: '', key: '', secret: '', region: 'us-east-1', bucket: '', endpoint: '', base_path: 'backups', path_style: false, url: '', username: '', token: '' })
const confirmBox = useConfirm()
const selectedFileName = ref('')
const showRestorePreview = ref(Boolean(props.restore_preview))
const operationProgress = ref({ active: false, percent: 0, message: '', state: 'running' })
let progressTimer = null
let progressHideTimer = null
let activeOperation = null

const progressPercent = computed(() => uploadForm.progress?.percentage ?? operationProgress.value.percent)
const progressVisible = computed(() => operationProgress.value.active || operationProgress.value.state !== 'running' || Boolean(uploadForm.progress))

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
    clearTimeout(progressHideTimer)
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
                    progressHideTimer = setTimeout(() => {
                        operationProgress.value = { ...operationProgress.value, state: 'running', message: '' }
                    }, 5000)
                }
            }
        } catch (_) { /* Polling retries on the next interval. */ }
    }, 500)
    return activeOperation
}

onUnmounted(() => { clearInterval(progressTimer); clearTimeout(progressHideTimer) })

const backupPath = (name) => `/backups/${encodeURIComponent(name)}`

watch(() => props.restore_preview, (preview) => {
    if (preview) {
        showRestorePreview.value = true
        restoreForm.mode = preview.summary.schema_compatible && preview.summary.new_tables === 0 ? 'merge' : 'replace'
    }
}, { immediate: true })

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

const saveSchedule = () => scheduleForm.put('/backups/schedule', { preserveScroll: true })
const toggleAutoBackup = () => scheduleForm.put('/backups/schedule', { preserveScroll: true })
const inspectBackup = (backup) => router.post(`${backupPath(backup.name)}/inspect`, { operation_id: startProgress() }, { preserveScroll: true })
const inspectCloudBackup = (connection, backup) => router.post(`/backups/cloud/${connection.id}/inspect`, { name: backup.name, operation_id: startProgress() }, { preserveScroll: true })
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
        onSuccess: () => {
            uploadForm.reset()
            selectedFileName.value = ''
        },
        onFinish: () => { uploadForm.progress = null },
    })
}

const restoreBackup = () => {
    const preview = props.restore_preview
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

const timezoneOptions = [
    { value: 'Asia/Ho_Chi_Minh', label: 'Việt Nam (UTC+7)' },
    { value: 'Asia/Bangkok', label: 'Bangkok (UTC+7)' },
    { value: 'UTC', label: 'UTC' },
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
        <!-- Header -->
        <PageHeader v-if="!hide_header" title="Sao lưu & Khôi phục dữ liệu" description="Tạo bản sao lưu an toàn cho cơ sở dữ liệu và tệp tin đã tải lên hệ thống." />

        <DataPanel v-if="progressVisible" class="p-3.5 sm:p-4" aria-live="polite">
            <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                <span class="font-semibold text-slate-700">{{ operationProgress.message }}</span>
                <span class="shrink-0 font-bold tabular-nums text-emerald-700">{{ Math.round(progressPercent) }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300" :style="{ width: `${Math.max(3, progressPercent)}%` }"></div>
            </div>
        </DataPanel>

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

                <div class="flex flex-wrap items-center gap-3 self-start sm:self-auto">
                    <label class="inline-flex h-10 items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 cursor-pointer select-none">
                        <input v-model="scheduleForm.enabled" type="checkbox" class="sr-only peer" :disabled="scheduleForm.processing" @change="toggleAutoBackup">
                        <span class="relative h-5 w-9 rounded-full bg-slate-300 transition-colors peer-checked:bg-emerald-600 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4"></span>
                        <span class="text-xs font-semibold text-slate-700">Sao lưu tự động</span>
                    </label>
                    <ActionButton :disabled="scheduleForm.processing || operationProgress.active" class="!bg-emerald-600 hover:!bg-emerald-700 !text-white h-10 px-4 font-semibold shadow-sm transition-all shrink-0" @click="runBackup">
                        <DatabaseBackup :size="17" /> Sao lưu ngay
                    </ActionButton>
                </div>
            </div>

            <!-- Form Lịch sao lưu tự động -->
            <form class="mt-4 space-y-4" @submit.prevent="saveSchedule">
                <div class="flex justify-end">
                    <div v-if="schedule.last_run" class="flex items-center gap-1.5 text-xs font-medium text-slate-500 bg-white px-3 py-1.5 rounded-lg border border-slate-200/80 shadow-2xs">
                        <Clock :size="14" class="text-slate-400" />
                        <span>Lần chạy gần nhất: <strong class="text-slate-700">{{ schedule.last_run }}</strong></span>
                    </div>
                </div>

                <!-- CÁC Ô THIẾT LẬP CHỈ HIỂN THỊ KHIN TÍCH CHỌN BẬT SAO LƯU TỰ ĐỘNG -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="transform opacity-0 -translate-y-2"
                    enter-to-class="transform opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="transform opacity-100 translate-y-0"
                    leave-to-class="transform opacity-0 -translate-y-2"
                >
                    <div v-if="scheduleForm.enabled" class="space-y-4 pt-1">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <!-- Tần suất -->
                            <FloatingSelect
                                name="frequency"
                                v-model="scheduleForm.frequency"
                                label="Tần suất"
                                :options="frequencyOptions"
                                :disabled="scheduleForm.processing"
                            />

                            <!-- Giờ chạy -->
                            <FloatingInput
                                name="time"
                                type="time"
                                v-model="scheduleForm.time"
                                label="Giờ chạy hàng ngày"
                                required
                                :disabled="scheduleForm.processing"
                            />

                            <!-- Múi giờ -->
                            <FloatingSelect
                                name="timezone"
                                v-model="scheduleForm.timezone"
                                label="Múi giờ"
                                :options="timezoneOptions"
                                :disabled="scheduleForm.processing"
                            />

                            <!-- Ngày trong tuần -->
                            <FloatingSelect
                                v-if="scheduleForm.frequency === 'weekly'"
                                name="weekday"
                                v-model.number="scheduleForm.weekday"
                                label="Ngày trong tuần"
                                :options="weekdayOptions"
                                :disabled="scheduleForm.processing"
                            />

                            <!-- Ngày trong tháng -->
                            <FloatingSelect
                                v-if="scheduleForm.frequency === 'monthly'"
                                name="monthday"
                                v-model.number="scheduleForm.monthday"
                                label="Ngày trong tháng"
                                :options="monthdayOptions"
                                :disabled="scheduleForm.processing"
                            />
                        </div>

                        <div class="flex items-center justify-between">
                            <ActionButton type="submit" :disabled="scheduleForm.processing" class="!bg-slate-900 hover:!bg-slate-800 !text-white h-10 px-5 font-semibold shadow-sm">
                                <Play :size="15" /> Lưu cấu hình lịch
                            </ActionButton>
                        </div>
                    </div>
                </transition>
            </form>

            <div v-if="schedule.last_error" class="mt-3 flex items-start gap-2.5 rounded-xl bg-red-50 p-3 text-xs font-medium text-red-700 ring-1 ring-red-200/80">
                <AlertTriangle :size="16" class="shrink-0 mt-0.5 text-red-500" />
                <span>Lần sao lưu gần nhất gặp lỗi: {{ schedule.last_error }}</span>
            </div>
        </DataPanel>

        <DataPanel class="p-3.5 sm:p-4">
            <div class="mb-4 flex items-start gap-3 border-b border-slate-100 pb-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-600/15"><Cloud :size="20" /></div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">Kho sao lưu đám mây</h2>
                    <p class="text-xs text-slate-500">Kết nối S3/R2/Wasabi, WebDAV/Nextcloud, Google Drive hoặc OneDrive. Bản sao lưu mới sẽ được tải lên mọi kết nối đang bật.</p>
                </div>
            </div>

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
        </DataPanel>

        <!-- Danh sách các bản sao lưu -->
        <DataPanel class="p-3.5 sm:p-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                <h2 class="text-base font-bold text-slate-800">Danh sách các bản sao lưu</h2>
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">Tổng số: {{ backups.length }} tệp</span>
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
            <div v-if="backups.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200/80 overflow-hidden bg-white">
                <div v-for="backup in backups" :key="backup.name" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 transition-colors hover:bg-slate-50/80">
                    <div class="flex items-start gap-3">
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
                <p class="text-sm font-semibold text-slate-600">Chưa có bản sao lưu nào</p>
                <p class="text-xs text-slate-400 mt-1">Bấm nút "Sao lưu ngay" ở trên để khởi tạo bản sao lưu đầu tiên.</p>
            </div>

            <p class="mt-3 text-[11px] text-slate-400">Tệp sao lưu gốc được lưu trữ an toàn tại thư mục <code class="rounded bg-slate-100 px-1 py-0.5 text-slate-600 font-mono">storage/app/backups</code>.</p>
        </DataPanel>

        <!-- Modal Đối chiếu & Khôi phục -->
        <Teleport to="body">
        <div v-if="showRestorePreview && restore_preview" class="fixed inset-0 z-[9990] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6" @click.self="showRestorePreview = false">
            <section class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10">
                <!-- Modal Header -->
                <header class="flex shrink-0 items-center justify-between border-b border-slate-200/80 bg-slate-50 px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <ScanSearch :size="20" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800">Đối chiếu dữ liệu trước khi khôi phục</h2>
                            <p class="text-xs font-medium text-slate-500 break-all">{{ restore_preview.backup }} · {{ restore_preview.created_at || 'Không xác định ngày tạo' }}</p>
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
                            <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ restore_preview.summary.backup_tables }} sao lưu / {{ restore_preview.summary.current_tables }} hiện tại</span>
                        </div>
                        <div class="rounded-xl p-2.5 border" :class="restore_preview.summary.row_count_difference ? 'bg-amber-50/80 border-amber-200 text-amber-900' : 'bg-slate-50 border-slate-200/70 text-slate-800'">
                            <span class="block text-[11px] font-semibold uppercase opacity-75">Số bản ghi (Dòng)</span>
                            <span class="text-sm font-bold mt-0.5 block">
                                {{ restore_preview.summary.backup_rows }} / {{ restore_preview.summary.current_rows }}
                                <span v-if="restore_preview.summary.row_count_difference" class="text-xs font-semibold">({{ restore_preview.summary.row_count_difference > 0 ? '+' : '' }}{{ restore_preview.summary.row_count_difference }})</span>
                            </span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-200/70">
                            <span class="block text-[11px] font-semibold text-slate-500 uppercase">Bảng đã mất</span>
                            <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ restore_preview.summary.missing_tables }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-200/70">
                            <span class="block text-[11px] font-semibold text-slate-500 uppercase">Bảng mới</span>
                            <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ restore_preview.summary.new_tables }}</span>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-2.5 border border-amber-200 col-span-2 sm:col-span-1">
                            <span class="block text-[11px] font-semibold text-amber-700 uppercase">Dữ liệu bị lệch</span>
                            <span class="text-sm font-bold text-amber-800 mt-0.5 block">{{ restore_preview.summary.mismatched_data_tables }} bảng</span>
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
                                <tr v-for="(table, name) in restore_preview.tables" :key="name" class="hover:bg-slate-50/50">
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
                        <p v-if="restore_preview.summary.mismatched_data_tables > 0" class="rounded-xl bg-amber-50 p-2.5 text-amber-800 border border-amber-200">
                            ⚠️ Dữ liệu hiện tại lệch ở {{ restore_preview.summary.mismatched_data_tables }} bảng. Vui lòng kiểm tra kỹ trước khi bấm xác nhận.
                        </p>
                        <p v-if="!restore_preview.summary.schema_compatible || restore_preview.summary.new_tables > 0" class="rounded-xl bg-amber-50 p-2.5 text-amber-800 border border-amber-200">
                            ⚠️ Cấu trúc bảng khác nhau hoặc có bảng mới. Chế độ Gộp bị vô hiệu hóa để đảm bảo an toàn dữ liệu; vui lòng chọn "Thay toàn bộ" hoặc Hủy.
                        </p>
                        <p v-if="restore_preview.has_files" class="text-slate-500">
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
