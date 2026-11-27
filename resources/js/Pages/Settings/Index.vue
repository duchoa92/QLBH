<script setup>
import { useSilentRefresh } from '@/Composables/useSilentRefresh'
import { onLiveChange } from '@/Services/liveSync'
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import LabelTemplateEditor from './LabelTemplateEditor.vue'
import SettingSwitch from './SettingSwitch.vue'
import SettingCollapse from './SettingCollapse.vue'
import SettingBlock from './SettingBlock.vue'
import DoorCameraSetup from './DoorCameraSetup.vue'
import CloudStorageSettings from './CloudStorageSettings.vue'
import MailSettings from './MailSettings.vue'
import { mergeLabelTemplate } from '@/utils/printLabels'
import { Plus, Save, Trash2, Mail, Store, DatabaseBackup, Cloud, ChevronRight, CreditCard, Boxes, Printer, QrCode, HardDrive, RefreshCw, Volume2, Award } from 'lucide-vue-next'
import BackupsIndex from '@/Pages/Backups/Index.vue'
import { VIET_QR_THEMES } from '@/Modules/POS/Payment/Utils/qrThemes'
import { hasPaymentVoice, testPaymentVoice as playPaymentVoiceTest } from '@/Modules/POS/Payment/Utils/paymentAlerts'
import { appBaseUrl } from '@/utils/appPaths'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    settings: Object,
    banks: { type: Array, default: () => [] },
    can_update_bank_settings: Boolean,
    can_edit_settings: { type: Boolean, default: false },
    mail_settings: { type: Object, default: null },
    door_camera: { type: Object, default: null },
    can_manage_payment_accounts: { type: Boolean, default: false },
    can_manage_inventory_settings: { type: Boolean, default: false },
    can_manage_backups: Boolean,
    active_section: String,
    backups: { type: Array, default: () => [] },
    cloud_connections: { type: Array, default: () => [] },
    cloud_oauth: { type: Object, default: () => ({}) },
    schedule: { type: Object, default: () => ({}) },
    restore_preview: { type: Object, default: null },
    purchase_contract_defaults: { type: Object, default: () => ({ title: '', content: '' }) },
})

const activeModal = ref(null)
const confirmBox = useConfirm()
const paymentVoiceAvailable = ref(false)
const paymentVoiceChecked = ref(false)
const paymentVoiceTestMessage = ref('')
const cleanupLoading = ref(false)
const cleanupDeleting = ref(false)
const cleanupResult = ref(null)
const cleanupError = ref('')
const selectedCleanupPaths = ref([])
const clearDataForm = useForm({ confirmation: '', operation_id: '' })
const clearDataPhrase = ref('')
const showClearDataDialog = ref(false)
const clearDataProgress = ref({ active: false, percent: 0, message: '', state: 'running', report: null })
let clearDataProgressTimer = null
let clearDataActiveOperation = null
const scanUnusedFiles = async () => {
    cleanupLoading.value = true
    cleanupError.value = ''
    try {
        const response = await window.axios.get(route('settings.cleanup.scan'))
        cleanupResult.value = response.data
        selectedCleanupPaths.value = (response.data.files || []).map(file => file.path)
    } catch (error) {
        cleanupError.value = error.response?.data?.message || 'Không thể quét file lúc này.'
    } finally {
        cleanupLoading.value = false
    }
}
const cleanupUnusedFiles = async () => {
    if (!selectedCleanupPaths.value.length) return
    const paths = [...selectedCleanupPaths.value]
    confirmBox.show({
        title: 'Xóa file không còn sử dụng',
        message: `Xóa ${paths.length} file mồ côi đã chọn? Thao tác này không thể hoàn tác.`,
        confirmText: `Xóa ${paths.length} file`,
        onConfirm: async () => {
            cleanupDeleting.value = true
            cleanupError.value = ''
            try {
                let deleted = 0
                let bytes = 0
                for (let index = 0; index < paths.length; index += 500) {
                    const response = await window.axios.delete(route('settings.cleanup.destroy'), { data: { paths: paths.slice(index, index + 500) } })
                    deleted += Number(response.data.deleted || 0)
                    bytes += Number(response.data.bytes || 0)
                }
                await scanUnusedFiles()
                cleanupResult.value = { ...cleanupResult.value, deleted, deleted_bytes: bytes }
            } catch (error) {
                cleanupError.value = error.response?.data?.message || 'Không thể xóa các file đã chọn.'
            } finally {
                cleanupDeleting.value = false
            }
        },
    })
}
const createOperationId = () => {
    if (typeof globalThis.crypto?.randomUUID === 'function') return globalThis.crypto.randomUUID()
    const bytes = new Uint8Array(16)
    globalThis.crypto?.getRandomValues?.(bytes)
    if (!globalThis.crypto?.getRandomValues) for (let index = 0; index < bytes.length; index += 1) bytes[index] = Math.floor(Math.random() * 256)
    bytes[6] = (bytes[6] & 0x0f) | 0x40
    bytes[8] = (bytes[8] & 0x3f) | 0x80
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}
const beginClearDataProgress = () => {
    const operation = createOperationId()
    clearDataActiveOperation = operation
    clearDataProgress.value = { active: true, percent: 1, message: 'Đang khởi chạy…', state: 'running', report: null }
    clearInterval(clearDataProgressTimer)
    clearDataProgressTimer = setInterval(async () => {
        try {
            const response = await window.axios.get(route('backups.progress', { operation }))
            if (clearDataActiveOperation !== operation) return
            clearDataProgress.value = { ...response.data, active: response.data.state === 'running' }
            if (response.data.state !== 'running') clearInterval(clearDataProgressTimer)
        } catch (_) { /* Retry on next poll while server writes progress. */ }
    }, 500)
    return operation
}
const clearBusinessData = () => {
    clearDataForm.confirmation = 'XOA DU LIEU'
    clearDataForm.operation_id = beginClearDataProgress()
    showClearDataDialog.value = false
    clearDataForm.post(route('backups.clear-data'), {
        preserveScroll: true,
        onError: errors => { cleanupError.value = errors.confirmation || errors.operation_id || 'Không thể bắt đầu dọn dữ liệu.' },
    })
}
const dismissClearDataProgress = () => {
    if (clearDataProgress.value.active) return
    clearInterval(clearDataProgressTimer)
    clearDataActiveOperation = null
    clearDataProgress.value = { active: false, percent: 0, message: '', state: 'running', report: null }
}
const formatBytes = value => {
    const bytes = Number(value || 0)
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
}
const updatePaymentVoiceAvailability = () => {
    paymentVoiceAvailable.value = hasPaymentVoice(form.payment_voice_language || 'vi-VN')
    paymentVoiceChecked.value = true
}
const testSelectedPaymentVoice = () => {
    paymentVoiceTestMessage.value = playPaymentVoiceTest(form.payment_voice_language || 'vi-VN')
        ? 'Đã phát câu thử. Nếu chưa nghe, hãy kiểm tra âm lượng hoặc thiết bị đầu ra.'
        : 'Không tìm thấy giọng đọc tương ứng trên máy này.'
}
// Mở/đóng mục thiết lập hoàn toàn ở phía trình duyệt: không gọi lại server nên không bị tải lại.
const applySection = (section) => {
    if (section === 'backups' && props.can_manage_backups) activeModal.value = 'backups'
    else if (section === 'cloud' && props.can_manage_backups) activeModal.value = 'cloud'
    else if (section === 'cleanup' && props.can_edit_settings) { activeModal.value = section; scanUnusedFiles() }
    else if (section === 'system' && props.can_edit_settings) activeModal.value = section
    else if (section === 'payments' && props.can_manage_payment_accounts) activeModal.value = section
    else if (section === 'inventory' && props.can_manage_inventory_settings) activeModal.value = section
    else if (section === 'printing' && props.can_edit_settings) activeModal.value = section
    else if (section === 'loyalty' && props.can_edit_settings) activeModal.value = section
    else if (section === 'mail' && props.can_edit_settings) activeModal.value = section
    else if (!section) activeModal.value = null
}

// Chỉ đồng bộ địa chỉ trang (để F5 hoặc chia sẻ link vẫn mở đúng mục), giữ nguyên state của Inertia.
const syncSectionUrl = (section) => {
    const url = new URL(window.location.href)
    if (section) url.searchParams.set('section', section)
    else url.searchParams.delete('section')
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
}

// Chạy khi vào trang bằng link có ?section=... hoặc khi server trả về section mới.
// Sau khi lưu, server redirect về địa chỉ còn ?section=... nên prop active_section đổi giá trị ngay sau khi modal vừa đóng
// và sẽ mở lại modal (lần lưu thứ hai prop không đổi nên mới đóng được). Bỏ qua thay đổi đó trong lúc đang lưu.
// Mốc thời gian đặt trên window để còn hiệu lực cả khi trang bị dựng lại sau lần lưu (state trong component sẽ mất).
const ignoreServerSectionForAWhile = () => { window.__settingsIgnoreSectionUntil = Date.now() + 4000 }
watch(() => props.active_section, (section) => {
    if (Date.now() < (window.__settingsIgnoreSectionUntil || 0)) return
    applySection(section)
}, { immediate: true })

// Nạp ngầm cài đặt mới nhất từ server (không chặn giao diện, không tải lại trang).
// Chỉ ghi vào form khi chưa có chỉnh sửa dở, tránh đè lên dữ liệu người dùng đang nhập.
const { refresh: refreshSettingsSilently } = useSilentRefresh({
    keys: ['settings'],
    enabled: () => !form.isDirty,
    apply: (next) => {
        if (!next.settings || form.isDirty) return
        liveSettings.value = next.settings
        form.defaults(buildFormValues())
        form.reset()
        form.clearErrors()
        selectedBankAccountId.value = String(form.bank_accounts.find(account => account.is_default)?.id || form.bank_accounts[0]?.id || '')
    },
})

// Cài đặt đổi ở máy khác: nạp lại ngầm (useSilentRefresh tự bỏ qua nếu form đang chỉnh dở).
const offSettingsLive = onLiveChange(['settings'], () => refreshSettingsSilently(true))
onBeforeUnmount(() => offSettingsLive())

const openModal = (section) => {
    applySection(section)
    syncSectionUrl(section)
    refreshSettingsSilently(true)
}

const closeModal = () => {
    activeModal.value = null
    syncSectionUrl(null)
}

// Mỗi mục chỉ gửi và lưu đúng các trường của mình, tránh ghi đè cài đặt khác bằng dữ liệu cũ của trang.
// Địa chỉ RTSP (rtsp://...) do worker camera cửa đọc bằng ffmpeg; địa chỉ http là ảnh chụp tĩnh.
const isRtspCamera = camera => /^rtsps?:\/\//i.test(String(camera?.snapshot_url || '').trim())
const faceCctvAuthOptions = [
    { value: 'none', label: 'Không cần đăng nhập' },
    { value: 'basic', label: 'Tài khoản (Basic)' },
    { value: 'digest', label: 'Tài khoản (Digest, Hikvision/Dahua…)' },
]
const cameraBrandOptions = [
    { value: 'hikvision', label: 'Hikvision / HiLook' }, { value: 'dahua', label: 'Dahua' },
    { value: 'imou', label: 'IMOU' }, { value: 'ezviz', label: 'EZVIZ' },
    { value: 'reolink', label: 'Reolink' }, { value: 'uniview', label: 'Uniview / UNV' },
    { value: 'vigi', label: 'TP-Link VIGI' }, { value: 'custom', label: 'Khác / tự nhập URL' },
]
const cameraPreset = (brand, host, channel) => {
    const target = String(host || '').trim().replace(/^rtsps?:\/\//i, '').replace(/\/.*$/, '')
    if (!target || brand === 'custom') return null
    const c = Math.max(1, Number(channel) || 1)
    const h = `${c}0`
    return {
        hikvision: [`rtsp://${target}:554/Streaming/Channels/${h}1`, `rtsp://${target}:554/Streaming/Channels/${h}2`],
        dahua: [`rtsp://${target}:554/cam/realmonitor?channel=${c}&subtype=0`, `rtsp://${target}:554/cam/realmonitor?channel=${c}&subtype=1`],
        imou: [`rtsp://${target}:554/cam/realmonitor?channel=${c}&subtype=0`, `rtsp://${target}:554/cam/realmonitor?channel=${c}&subtype=1`],
        ezviz: [`rtsp://${target}:554/H.264/ch${c}/main/av_stream`, `rtsp://${target}:554/H.264/ch${c}/sub/av_stream`],
        reolink: [`rtsp://${target}:554/h264Preview_${String(c).padStart(2, '0')}_main`, `rtsp://${target}:554/h264Preview_${String(c).padStart(2, '0')}_sub`],
        uniview: [`rtsp://${target}:554/media/video${(c - 1) * 2 + 1}`, `rtsp://${target}:554/media/video${(c - 1) * 2 + 2}`],
        vigi: [`rtsp://${target}:554/stream${(c - 1) * 2 + 1}`, `rtsp://${target}:554/stream${(c - 1) * 2 + 2}`],
    }[brand] || null
}
const applyCameraPreset = camera => {
    const urls = cameraPreset(camera.brand, camera.host, camera.channel)
    if (!urls) return
    ;[camera.snapshot_url, camera.view_rtsp_url] = urls
    camera.auth = 'basic'
}

const SECTION_KEYS = {
    system: ['shop_name', 'print_shop_subtitle', 'print_shop_address', 'print_shop_phone', 'currency_symbol', 'currency_format', 'app_locale', 'app_timezone', 'date_format'],
    printing: ['print_template', 'print_paper_size', 'print_orientation', 'label_template', 'purchase_contract_title', 'purchase_contract_content'],
    inventory: ['allow_negative_stock', 'inventory_alert_enabled', 'inventory_alert_threshold', 'stock_import_vat_default', 'stock_import_vat_rate', 'pos_vat_default_enabled', 'pos_vat_default_scope', 'pos_vat_rate', 'pos_vat_used_enabled', 'pos_vat_used_default', 'pos_cost_price_suggestion_enabled', 'work_shift_opening_count_enabled', 'work_shift_auto_open_enabled'],
    loyalty: ['customer_points_enabled', 'customer_points_amount_per_point', 'customer_points_basis', 'customer_points_debt_enabled', 'customer_points_debt_mode', 'customer_points_debt_percent', 'face_recognition_enabled', 'face_notify_modal_enabled', 'face_visit_tracking_enabled', 'face_door_camera_enabled', 'face_visit_retention_days', 'face_cameras', 'face_auto_learn_enabled', 'face_auto_learn_min_percent', 'face_auto_learn_margin_percent', 'face_auto_learn_cooldown_minutes'],
    payments: ['bank_accounts', 'bank_qr_theme', 'payment_voice_enabled', 'payment_voice_language'],
}

// Đóng mà không lưu: bỏ các chỉnh sửa dở để lần mở sau không hiện số liệu chưa lưu.
const cancelModal = () => {
    const section = activeModal.value
    if (section && SECTION_KEYS[section]) {
        form.reset(...SECTION_KEYS[section])
        form.clearErrors()
        if (section === 'payments') {
            selectedBankAccountId.value = String(form.bank_accounts.find(account => account.is_default)?.id || form.bank_accounts[0]?.id || '')
        }
    }
    closeModal()
}

// Nguồn dữ liệu để dựng form: đổi khi server đẩy props mới hoặc khi nạp ngầm xong.
const liveSettings = ref(props.settings)
watch(() => props.settings, value => { liveSettings.value = value }, { flush: 'sync' })

const mapBankAccounts = () => (liveSettings.value.bank_accounts?.length
        ? liveSettings.value.bank_accounts
        : (liveSettings.value.bank_bin || liveSettings.value.bank_account
            ? [{
                id: 'legacy-default',
                bank_bin: liveSettings.value.bank_bin || '',
                bank_account: liveSettings.value.bank_account || '',
                bank_account_name: liveSettings.value.bank_account_name || '',
                is_default: true,
            }]
            : [])).map((account, index) => ({
        id: account.id || `bank-${index + 1}`,
        bank_bin: account.bank_bin || '',
        bank_name: account.bank_name || props.banks.find(bank => String(bank.bin) === String(account.bank_bin))?.shortName || '',
        bank_account: account.bank_account || '',
        bank_account_name: account.bank_account_name || '',
        transfer_content_mode: account.transfer_content_mode === 'invoice' ? 'invoice' : 'default',
        vat_preferred: Boolean(account.vat_preferred),
        payment_provider: account.payment_provider || '',
        payment_provider_account_id: account.payment_provider_account_id || '',
        payment_api_key: '',
        payment_api_access_key: '',
        payment_api_secret_key: '',
        payment_webhook_secret: '',
        payment_api_key_configured: Boolean(account.payment_api_key_configured),
        payment_api_access_key_configured: Boolean(account.payment_api_access_key_configured),
        payment_api_secret_key_configured: Boolean(account.payment_api_secret_key_configured),
        payment_webhook_secret_configured: Boolean(account.payment_webhook_secret_configured),
        payment_auto_enabled: Boolean(account.payment_auto_enabled),
        is_default: liveSettings.value.bank_accounts?.some(saved => saved.is_default)
            ? Boolean(account.is_default)
            : index === 0,
    }))

const mapFaceCameras = () => {
    const saved = Array.isArray(liveSettings.value.face_cameras) && liveSettings.value.face_cameras.length
        ? liveSettings.value.face_cameras
        : [{
            id: 'door-1', name: 'Camera cửa', enabled: true,
            brand: liveSettings.value.face_camera_brand || 'custom', host: liveSettings.value.face_camera_host || '',
            channel: Number(liveSettings.value.face_camera_channel) || 1,
            snapshot_url: liveSettings.value.face_cctv_snapshot_url || '', view_rtsp_url: liveSettings.value.face_cctv_view_rtsp_url || '',
            auth: liveSettings.value.face_cctv_auth || 'none', username: liveSettings.value.face_cctv_username || '',
            has_password: Boolean(liveSettings.value.face_cctv_has_password),
        }]
    return saved.map((camera, index) => ({
        id: camera.id || (index === 0 ? 'door-1' : `camera-${index + 1}`),
        name: camera.name || (index === 0 ? 'Camera cửa' : `Camera ${index + 1}`),
        enabled: camera.enabled !== false && camera.enabled !== 0 && camera.enabled !== '0',
        brand: camera.brand || 'custom', host: camera.host || '', channel: Number(camera.channel) || 1,
        snapshot_url: camera.snapshot_url || '', view_rtsp_url: camera.view_rtsp_url || '',
        auth: camera.auth || 'none', username: camera.username || '', password: '', has_password: Boolean(camera.has_password),
    }))
}

const buildFormValues = () => ({
    shop_name: liveSettings.value.shop_name || '',
    currency_symbol: liveSettings.value.currency_symbol || '₫',
    currency_format: liveSettings.value.currency_format || 'vi-VN',
    app_locale: liveSettings.value.app_locale || 'vi',
    app_timezone: liveSettings.value.app_timezone || 'Asia/Ho_Chi_Minh',
    date_format: liveSettings.value.date_format || 'd/m/Y',
    allow_negative_stock: liveSettings.value.allow_negative_stock || false,
    inventory_alert_enabled: liveSettings.value.inventory_alert_enabled !== false
        && liveSettings.value.inventory_alert_enabled !== 0
        && liveSettings.value.inventory_alert_enabled !== '0',
    inventory_alert_threshold: Number(liveSettings.value.inventory_alert_threshold ?? 5),
    stock_import_vat_default: Boolean(liveSettings.value.stock_import_vat_default),
    stock_import_vat_rate: Number(liveSettings.value.stock_import_vat_rate ?? 10),
    pos_vat_default_enabled: Boolean(liveSettings.value.pos_vat_default_enabled),
    pos_vat_default_scope: liveSettings.value.pos_vat_default_scope === 'vat_products' ? 'vat_products' : 'all',
    pos_vat_rate: Number(liveSettings.value.pos_vat_rate ?? 10),
    pos_vat_used_enabled: Boolean(liveSettings.value.pos_vat_used_enabled),
    pos_vat_used_default: Boolean(liveSettings.value.pos_vat_used_default),
    pos_cost_price_suggestion_enabled: liveSettings.value.pos_cost_price_suggestion_enabled !== false
        && liveSettings.value.pos_cost_price_suggestion_enabled !== 0
        && liveSettings.value.pos_cost_price_suggestion_enabled !== '0',
    work_shift_opening_count_enabled: liveSettings.value.work_shift_opening_count_enabled !== false
        && liveSettings.value.work_shift_opening_count_enabled !== 0
        && liveSettings.value.work_shift_opening_count_enabled !== '0',
    work_shift_auto_open_enabled: liveSettings.value.work_shift_auto_open_enabled !== false
        && liveSettings.value.work_shift_auto_open_enabled !== 0
        && liveSettings.value.work_shift_auto_open_enabled !== '0',
    customer_points_enabled: liveSettings.value.customer_points_enabled !== false
        && liveSettings.value.customer_points_enabled !== 0
        && liveSettings.value.customer_points_enabled !== '0',
    customer_points_amount_per_point: Number(liveSettings.value.customer_points_amount_per_point ?? 10000),
    customer_points_basis: liveSettings.value.customer_points_basis || 'revenue',
    customer_points_debt_enabled: liveSettings.value.customer_points_debt_enabled !== false
        && liveSettings.value.customer_points_debt_enabled !== 0
        && liveSettings.value.customer_points_debt_enabled !== '0',
    customer_points_debt_mode: liveSettings.value.customer_points_debt_mode || 'same',
    customer_points_debt_percent: Number(liveSettings.value.customer_points_debt_percent ?? 50),
    print_paper_size: liveSettings.value.print_paper_size || 'a4',
    print_orientation: liveSettings.value.print_orientation || 'portrait',
    print_template: liveSettings.value.print_template || 'standard',
    print_shop_subtitle: liveSettings.value.print_shop_subtitle || '',
    print_shop_address: liveSettings.value.print_shop_address || '',
    print_shop_phone: liveSettings.value.print_shop_phone || '',
    label_template: mergeLabelTemplate(liveSettings.value.label_template),
    purchase_contract_title: liveSettings.value.purchase_contract_title || 'Hợp đồng mua bán điện thoại',
    purchase_contract_content: liveSettings.value.purchase_contract_content || '',
    bank_bin: liveSettings.value.bank_bin || '',
    bank_account: liveSettings.value.bank_account || '',
    bank_account_name: liveSettings.value.bank_account_name || '',
    bank_transfer_content: liveSettings.value.bank_transfer_content || 'Thanh toan don hang',
    bank_qr_theme: liveSettings.value.bank_qr_theme || 'classic',
    payment_voice_language: liveSettings.value.payment_voice_language || 'vi-VN',
    face_recognition_enabled: liveSettings.value.face_recognition_enabled === true
        || liveSettings.value.face_recognition_enabled === 1
        || liveSettings.value.face_recognition_enabled === '1',
    face_notify_modal_enabled: liveSettings.value.face_notify_modal_enabled === true
        || liveSettings.value.face_notify_modal_enabled === 1
        || liveSettings.value.face_notify_modal_enabled === '1',
    face_visit_tracking_enabled: liveSettings.value.face_visit_tracking_enabled === true
        || liveSettings.value.face_visit_tracking_enabled === 1
        || liveSettings.value.face_visit_tracking_enabled === '1',
    face_door_camera_enabled: liveSettings.value.face_door_camera_enabled === true
        || liveSettings.value.face_door_camera_enabled === 1
        || liveSettings.value.face_door_camera_enabled === '1',
    face_visit_retention_days: Number(liveSettings.value.face_visit_retention_days) || 30,
    face_cameras: mapFaceCameras(),
    face_auto_learn_enabled: liveSettings.value.face_auto_learn_enabled !== false
        && liveSettings.value.face_auto_learn_enabled !== 0
        && liveSettings.value.face_auto_learn_enabled !== '0',
    face_auto_learn_min_percent: Number(liveSettings.value.face_auto_learn_min_percent ?? 75),
    face_auto_learn_margin_percent: Number(liveSettings.value.face_auto_learn_margin_percent ?? 15),
    face_auto_learn_cooldown_minutes: Number(liveSettings.value.face_auto_learn_cooldown_minutes ?? 120),
    payment_voice_enabled: liveSettings.value.payment_voice_enabled !== false
        && liveSettings.value.payment_voice_enabled !== 0
        && liveSettings.value.payment_voice_enabled !== '0',
    bank_accounts: mapBankAccounts(),
})
const form = useForm(buildFormValues())

const addCamera = () => {
    if (form.face_cameras.length >= 8) return
    const index = form.face_cameras.length + 1
    form.face_cameras.push({
        id: `camera-${index}`, name: `Camera ${index}`, enabled: true, brand: 'custom', host: '', channel: 1,
        snapshot_url: '', view_rtsp_url: '', auth: 'basic', username: '', password: '', has_password: false,
    })
}
const removeCamera = index => {
    if (form.face_cameras.length <= 1) return
    form.face_cameras.splice(index, 1)
}

watch(
    () => [props.settings.purchase_contract_title, props.settings.purchase_contract_content],
    ([title, content]) => {
        form.purchase_contract_title = title || 'Hợp đồng mua bán điện thoại'
        form.purchase_contract_content = content || ''
    },
)

// Tiêu đề từng hộp thoại cài đặt.
const modalTitles = {
    system: 'Thông tin & hệ thống',
    printing: 'In ấn',
    inventory: 'Kho & bán hàng',
    payments: 'Tài khoản thanh toán',
    loyalty: 'Khách hàng',
}

// Thẻ mục ở trang chính, gom theo nhóm công việc: cửa hàng, bán hàng, dữ liệu.
const settingGroups = computed(() => [
    {
        title: 'Cửa hàng',
        cards: [
            { key: 'system', title: 'Thông tin & hệ thống', description: 'Tên, địa chỉ, số điện thoại, tiền tệ, ngày giờ.', icon: Store, tone: 'bg-blue-50 text-blue-700', hover: 'hover:border-blue-300', visible: props.can_edit_settings },
            { key: 'mail', title: 'Email gửi thư', description: 'SMTP để gửi liên kết quên mật khẩu.', icon: Mail, tone: 'bg-indigo-50 text-indigo-700', hover: 'hover:border-indigo-300', visible: props.can_edit_settings },
            { key: 'printing', title: 'In ấn', description: 'Hóa đơn, khổ giấy, tem sản phẩm, hợp đồng.', icon: Printer, tone: 'bg-sky-50 text-sky-700', hover: 'hover:border-sky-300', visible: props.can_edit_settings },
        ],
    },
    {
        title: 'Bán hàng',
        cards: [
            { key: 'inventory', title: 'Kho & bán hàng', description: 'Tồn kho, POS, VAT.', icon: Boxes, tone: 'bg-amber-50 text-amber-700', hover: 'hover:border-amber-300', visible: props.can_manage_inventory_settings },
            { key: 'payments', title: 'Tài khoản thanh toán', description: 'Ngân hàng, VietQR, tự đối soát.', icon: CreditCard, tone: 'bg-violet-50 text-violet-700', hover: 'hover:border-violet-300', visible: props.can_manage_payment_accounts },
            { key: 'loyalty', title: 'Khách hàng', description: 'Tích điểm, nhận diện khuôn mặt.', icon: Award, tone: 'bg-emerald-50 text-emerald-700', hover: 'hover:border-emerald-300', visible: props.can_edit_settings },
        ],
    },
    {
        title: 'Dữ liệu',
        cards: [
            { key: 'backups', title: 'Sao lưu & khôi phục', description: 'Sao lưu tự động, đám mây, khôi phục.', icon: DatabaseBackup, tone: 'bg-emerald-50 text-emerald-700', hover: 'hover:border-emerald-300', visible: props.can_manage_backups },
            { key: 'cloud', title: 'Kho lưu trữ đám mây', description: 'Kết nối Google Drive, OneDrive để lưu bản sao lưu.', icon: Cloud, tone: 'bg-sky-50 text-sky-700', hover: 'hover:border-sky-300', visible: props.can_manage_backups },
            { key: 'cleanup', title: 'Dọn dẹp hệ thống', description: 'Xóa file thừa, xóa dữ liệu nghiệp vụ.', icon: HardDrive, tone: 'bg-rose-50 text-rose-700', hover: 'hover:border-rose-300', visible: props.can_edit_settings },
        ],
    },
].map(group => ({ ...group, cards: group.cards.filter(card => card.visible) })).filter(group => group.cards.length))

const resetContractDefaults = () => {
    form.purchase_contract_title = props.purchase_contract_defaults.title || ''
    form.purchase_contract_content = props.purchase_contract_defaults.content || ''
}

// Each settings dialog owns a small part of the shared form.  Send only that
// part so data opened in another dialog cannot overwrite a newer value.
const save = () => {
    const section = activeModal.value
    const keys = SECTION_KEYS[section]
    if (!keys || form.processing) return

    form.transform(data => Object.fromEntries(
        keys.map(key => [key, data[key]])
    ))

    // The response keeps ?section=... for a reloadable URL.  It updates the
    // active_section prop before Inertia calls onSuccess, so ignore it now;
    // otherwise the first successful save opens this dialog again.
    ignoreServerSectionForAWhile()

    form.post(route('settings.update', { section }), {
        preserveScroll: true,
        onSuccess: () => {
            liveSettings.value = props.settings
            form.defaults(buildFormValues())
            form.reset()
            form.clearErrors()
            closeModal()
        },
    })
}

const selectedBankAccountId = ref(String(form.bank_accounts.find(account => account.is_default)?.id || form.bank_accounts[0]?.id || ''))
const selectedBankAccount = computed(() => form.bank_accounts.find(account => String(account.id) === String(selectedBankAccountId.value)) || null)
const selectedBankAccountIndex = computed(() => form.bank_accounts.findIndex(account => String(account.id) === String(selectedBankAccountId.value)))
const visibleBankAccounts = computed(() => selectedBankAccount.value ? [selectedBankAccount.value] : [])
const transferContentOptions = [
    { value: 'default', label: 'Để ngân hàng mặc định' },
    { value: 'invoice', label: 'Mã hóa đơn hiện tại' },
]
const paymentProviderOptions = [
    { value: 'apipay', label: 'ApiPay · tạo payment request và QR, đối soát bằng webhook' },
    { value: 'sepay', label: 'SePay · API Key hoặc webhook secret' },
]
const paymentVoiceOptions = [
    { value: 'vi-VN', label: 'Tiếng Việt' },
    { value: 'en-US', label: 'English (United States)' },
]
watch(() => form.payment_voice_language, updatePaymentVoiceAvailability)
onMounted(() => {
    updatePaymentVoiceAvailability()
    window.speechSynthesis?.addEventListener?.('voiceschanged', updatePaymentVoiceAvailability)
})
onBeforeUnmount(() => {
    window.speechSynthesis?.removeEventListener?.('voiceschanged', updatePaymentVoiceAvailability)
    clearInterval(clearDataProgressTimer)
})

const bankWebhookEndpoint = computed(() => `${appBaseUrl}api/webhooks/bank-transactions`)

const bankOptions = computed(() => {
    const options = props.banks.map(bank => ({
        ...bank,
        label: `${bank.shortName || bank.code || bank.name} — ${bank.name || 'Ngân hàng'} (BIN: ${bank.bin})`,
    }))

    form.bank_accounts.forEach(account => {
        if (account.bank_bin && !options.some(bank => String(bank.bin) === String(account.bank_bin))) {
            options.unshift({ bin: account.bank_bin, label: `Mã đang cấu hình: ${account.bank_bin}` })
        }
    })

    return [{ bin: '', label: 'Chọn ngân hàng...' }, ...options]
})

const addBankAccount = () => {
    if (!props.can_update_bank_settings || form.bank_accounts.length >= 10) return

    const id = `bank-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    form.bank_accounts.push({
        id,
        bank_bin: '',
        bank_account: '',
        bank_account_name: '',
        transfer_content_mode: 'default',
        is_default: form.bank_accounts.length === 0,
        vat_preferred: false,
        payment_provider: '',
        payment_provider_account_id: '',
        payment_api_key: '',
        payment_api_access_key: '',
        payment_api_secret_key: '',
        payment_webhook_secret: '',
        payment_api_key_configured: false,
        payment_api_access_key_configured: false,
        payment_api_secret_key_configured: false,
        payment_webhook_secret_configured: false,
    })
    selectedBankAccountId.value = id
}

const removeBankAccount = () => {
    const account = selectedBankAccount.value
    if (!account) return

    confirmBox.show({
        title: 'Xác nhận xóa tài khoản',
        message: `Bạn có chắc muốn xóa tài khoản ${bankAccountLabel(account)} · ${account.bank_account || ''}?`,
        confirmText: 'Xóa tài khoản',
        cancelText: 'Giữ lại',
        onConfirm: () => {
            const index = selectedBankAccountIndex.value
            form.bank_accounts.splice(index, 1)
            if (form.bank_accounts.length && !form.bank_accounts.some(item => item.is_default)) {
                form.bank_accounts[0].is_default = true
            }
            selectedBankAccountId.value = String(form.bank_accounts[Math.min(index, form.bank_accounts.length - 1)]?.id || '')
        },
    })
}

const bankAccountLabel = (account) => {
    const bank = props.banks.find(item => String(item.bin) === String(account.bank_bin))
    return bank?.shortName || bank?.name || account.bank_name || (account.bank_bin ? `BIN ${account.bank_bin}` : 'Chưa chọn ngân hàng')
}

const setAutoReconcile = (account, enabled) => {
    account.payment_provider = enabled ? (account.payment_provider || 'apipay') : ''
}

const setVatPreferredAccount = (account, enabled) => {
    form.bank_accounts.forEach(item => { item.vat_preferred = enabled && item.id === account.id })
}

const setDefaultAccount = (account) => {
    form.bank_accounts.forEach(item => { item.is_default = item.id === account.id })
}

const setAccountBank = (account, bin) => {
    account.bank_bin = bin
    account.bank_name = props.banks.find(bank => String(bank.bin) === String(bin))?.shortName || ''
}

const currencyFormatOptions = [
    { value: 'vi-VN', label: 'Việt Nam' },
    { value: 'en-US', label: 'US' },
]

const localeOptions = [
    { value: 'vi', label: 'Tiếng Việt' },
    { value: 'en', label: 'English' },
]

const timezoneOptions = [
    { value: 'Asia/Ho_Chi_Minh', label: 'Việt Nam (UTC+7)' },
    { value: 'Asia/Bangkok', label: 'Bangkok (UTC+7)' },
    { value: 'UTC', label: 'UTC' },
]

const dateFormatOptions = [
    { value: 'd/m/Y', label: 'Ngày/Tháng/Năm' },
    { value: 'Y-m-d', label: 'Năm-Tháng-Ngày' },
    { value: 'm/d/Y', label: 'Tháng/Ngày/Năm' },
]

const printPaperOptions = [
    { value: 'a4', label: 'A4 (210 × 297 mm)' },
    { value: 'a5', label: 'A5 (148 × 210 mm)' },
    { value: '80mm', label: 'Máy in nhiệt 80 mm' },
    { value: '58mm', label: 'Máy in nhiệt 58 mm' },
]

const printOrientationOptions = [
    { value: 'portrait', label: 'Dọc' },
    { value: 'landscape', label: 'Ngang' },
]

const printTemplateOptions = [
    { value: 'standard', label: 'Tiêu chuẩn' },
    { value: 'compact', label: 'Rút gọn' },
]

const bankLockedText = computed(() =>
    props.can_update_bank_settings
        ? 'Admin có thể cập nhật thông tin nhận chuyển khoản.'
        : 'Chỉ Admin được thay đổi thông tin nhận chuyển khoản.'
)
</script>

<template>
    <div class="space-y-5">
        <PageHeader title="Thiết lập"
            description=""
        >
        </PageHeader>

        <section v-for="group in settingGroups" :key="group.title" class="space-y-2">
            <h2 class="px-1 text-xs font-bold uppercase tracking-wide text-slate-500">{{ group.title }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <button
                    v-for="card in group.cards"
                    :key="card.key"
                    type="button"
                    class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:shadow-md"
                    :class="card.hover"
                    @click="openModal(card.key)"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="card.tone"><component :is="card.icon" :size="22" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold leading-6 text-slate-900">{{ card.title }}</span>
                        <span class="mt-0.5 block text-sm leading-5 text-slate-500">{{ card.description }}</span>
                    </span>
                    <ChevronRight class="mt-3 shrink-0 text-slate-400 transition group-hover:translate-x-1" :size="19" />
                </button>
            </div>
        </section>


        <BaseModal
            v-if="(activeModal === 'system' && can_edit_settings) || (activeModal === 'payments' && can_manage_payment_accounts) || (activeModal === 'inventory' && can_manage_inventory_settings) || (activeModal === 'printing' && can_edit_settings) || (activeModal === 'loyalty' && can_edit_settings)"
            :title="modalTitles[activeModal]"
            :size="'lg'"
            body-class="max-h-[70dvh] overflow-y-auto bg-slate-50/60 p-3 sm:p-4"
            @close="cancelModal"
        >
        <div class="space-y-3">
            <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">
                <div v-for="(message, key) in form.errors" :key="key">{{ message }}</div>
            </div>

            <!-- ===== THÔNG TIN & HỆ THỐNG ===== -->
            <template v-if="activeModal === 'system'">
                <SettingCollapse title="Thông tin cửa hàng" description="Hiện trên hóa đơn, phiếu sửa chữa và màn hình POS. Chỗ nào để trống sẽ không in.">
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        <FloatingInput v-model="form.shop_name" name="shop_name" label="Tên cửa hàng" :error="form.errors.shop_name" />
                        <FloatingInput v-model="form.print_shop_subtitle" name="print_shop_subtitle" label="Ngành hàng / dòng giới thiệu" :error="form.errors.print_shop_subtitle" />
                        <FloatingInput v-model="form.print_shop_phone" name="print_shop_phone" label="Số điện thoại" :error="form.errors.print_shop_phone" />
                        <FloatingInput v-model="form.print_shop_address" name="print_shop_address" label="Địa chỉ" :error="form.errors.print_shop_address" />
                    </div>
                </SettingCollapse>
                <SettingCollapse title="Hiển thị chung" description="Tiền tệ, ngôn ngữ, múi giờ và định dạng ngày.">
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        <FloatingInput v-model="form.currency_symbol" name="currency_symbol" label="Ký hiệu tiền" :error="form.errors.currency_symbol" />
                        <FloatingSelect v-model="form.currency_format" name="currency_format" label="Định dạng số tiền" :options="currencyFormatOptions" />
                        <FloatingSelect v-model="form.app_locale" name="app_locale" label="Ngôn ngữ" :options="localeOptions" />
                        <FloatingSelect v-model="form.date_format" name="date_format" label="Định dạng ngày" :options="dateFormatOptions" />
                        <FloatingSelect v-model="form.app_timezone" name="app_timezone" label="Múi giờ" :options="timezoneOptions" class="sm:col-span-2" />
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600 sm:col-span-2">
                            Ngôn ngữ chỉ áp dụng cho phiếu in và thông báo (giao diện quản trị chỉ có tiếng Việt). Đổi múi giờ chỉ thay cách hiển thị giờ, không đổi dữ liệu đã lưu.
                        </p>
                    </div>
                </SettingCollapse>
            </template>

            <!-- ===== IN ẤN ===== -->
            <template v-if="activeModal === 'printing'">
                <SettingCollapse title="Hóa đơn và khổ giấy" description="Áp dụng cho hóa đơn bán hàng và phiếu sửa chữa. Thông tin cửa hàng chỉnh ở mục “Thông tin & hệ thống”.">
                    <div class="grid gap-3 p-4 sm:grid-cols-3">
                        <FloatingSelect v-model="form.print_template" name="print_template" label="Mẫu hóa đơn" :options="printTemplateOptions" :show-placeholder="false" />
                        <FloatingSelect v-model="form.print_paper_size" name="print_paper_size" label="Khổ giấy" :options="printPaperOptions" />
                        <FloatingSelect v-model="form.print_orientation" name="print_orientation" label="Hướng giấy" :options="printOrientationOptions" />
                    </div>
                </SettingCollapse>
                <SettingCollapse title="Tem sản phẩm" description="Khổ tem, nội dung và cỡ chữ; bản xem trước đúng kích thước thật." :default-open="false">
                    <LabelTemplateEditor v-model="form.label_template" :shop-name="form.shop_name" :errors="form.errors" />
                </SettingCollapse>
                <SettingCollapse title="Hợp đồng mua bán" description="Nội dung in trên hợp đồng khi nhập hàng từ khách." :default-open="false">
                    <div class="space-y-3 p-4">
                        <FloatingInput v-model="form.purchase_contract_title" name="purchase_contract_title" label="Tiêu đề hợp đồng" :error="form.errors.purchase_contract_title" />
                        <div>
                            <label for="purchase_contract_content" class="mb-1 block text-sm font-medium text-slate-700">Nội dung / điều khoản</label>
                            <textarea id="purchase_contract_content" v-model="form.purchase_contract_content" name="purchase_contract_content" rows="8" maxlength="5000" class="w-full rounded-xl border-slate-300 text-sm leading-6 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <div class="mt-1 flex items-center justify-between gap-3 text-xs text-slate-500">
                                <span>{{ (form.purchase_contract_content || '').length }}/5000 ký tự</span>
                                <button type="button" class="font-semibold text-indigo-600 hover:text-indigo-800" @click="resetContractDefaults">Dùng mẫu mặc định</button>
                            </div>
                            <p v-if="form.errors.purchase_contract_content" class="mt-1 text-xs text-rose-600">{{ form.errors.purchase_contract_content }}</p>
                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                Biến dùng được: <code>&#123;&#123;date&#125;&#125;</code>, <code>&#123;&#123;delivery_date&#125;&#125;</code>, <code>&#123;&#123;import_code&#125;&#125;</code>, <code>&#123;&#123;buyer_name&#125;&#125;</code>, <code>&#123;&#123;seller_name&#125;&#125;</code>, <code>&#123;&#123;total&#125;&#125;</code>, <code>&#123;&#123;note&#125;&#125;</code>.
                            </p>
                        </div>
                    </div>
                </SettingCollapse>
            </template>

            <!-- ===== KHO & BÁN HÀNG ===== -->
            <template v-if="activeModal === 'inventory'">
                <SettingCollapse title="Kho hàng" description="Tồn kho âm và cảnh báo hàng sắp hết.">
                    <div class="divide-y divide-slate-100">
                        <SettingBlock>
                            <SettingSwitch
                                v-model="form.allow_negative_stock"
                                title="Cho phép tồn kho âm"
                                description="Bán trước, nhập bù sau. Phần bán vượt tồn chưa có lô nhập nên chưa tính được giá vốn theo lô."
                            />
                        </SettingBlock>
                        <SettingBlock>
                            <SettingSwitch
                                v-model="form.inventory_alert_enabled"
                                title="Cảnh báo hàng sắp hết"
                                description="Áp dụng cho sản phẩm chưa đặt ngưỡng riêng."
                            />
                            <FloatingInput
                                v-if="form.inventory_alert_enabled"
                                v-model.number="form.inventory_alert_threshold"
                                name="inventory_alert_threshold"
                                label="Ngưỡng mặc định (số lượng)"
                                type="number"
                                min="0"
                                class="sm:max-w-xs"
                                :error="form.errors.inventory_alert_threshold"
                            />
                        </SettingBlock>
                    </div>
                </SettingCollapse>

                <SettingCollapse title="Bán hàng tại quầy (POS)" description="Gợi ý giá nhập và kiểm kê đầu ca.">
                    <div class="divide-y divide-slate-100">
                        <SettingBlock>
                            <SettingSwitch
                                v-model="form.pos_cost_price_suggestion_enabled"
                                title="Gợi ý giá nhập trên POS"
                                description="Nhấn giữ ảnh sản phẩm hoặc dòng IMEI/biến thể để xem giá nhập."
                            />
                        </SettingBlock>
                        <SettingBlock>
                            <SettingSwitch
                                v-model="form.work_shift_opening_count_enabled"
                                title="Kiểm kê đầu ca"
                                description="Nhập bảng kê tiền và hàng khi mở ca."
                            />
                            <div v-if="form.work_shift_opening_count_enabled" class="rounded-lg bg-slate-50 p-3">
                                <SettingSwitch
                                    v-model="form.work_shift_auto_open_enabled"
                                    title="Tự hiện bảng kiểm kê khi vào POS"
                                    description="Tắt thì mở ca thủ công từ POS."
                                />
                            </div>
                        </SettingBlock>
                    </div>
                </SettingCollapse>

                <SettingCollapse title="Thuế VAT" description="Giá trị mặc định, vẫn đổi lại được cho từng phiếu.">
                    <div class="divide-y divide-slate-100">
                        <SettingBlock title="Nhập hàng">
                            <SettingSwitch
                                v-model="form.stock_import_vat_default"
                                title="Mặc định có VAT"
                                description="Áp dụng cho mặt hàng mới trong phiếu nhập."
                            />
                            <FloatingInput
                                v-model.number="form.stock_import_vat_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.1"
                                name="stock_import_vat_rate"
                                label="Thuế suất (%)"
                                class="sm:max-w-xs"
                                :error="form.errors.stock_import_vat_rate"
                            />
                        </SettingBlock>
                        <SettingBlock title="Bán hàng">
                            <SettingSwitch
                                v-model="form.pos_vat_default_enabled"
                                title="Mặc định tính và cộng VAT"
                                description="Nhãn VAT theo hàng nhập chỉ để nhận biết, không tự cộng thuế."
                            />
                            <FloatingInput
                                v-model.number="form.pos_vat_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.1"
                                name="pos_vat_rate"
                                label="Thuế suất (%)"
                                class="sm:max-w-xs"
                                :error="form.errors.pos_vat_rate"
                            />
                            <div v-if="form.pos_vat_default_enabled" class="space-y-2 rounded-lg bg-slate-50 p-3">
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-700">
                                    <input v-model="form.pos_vat_default_scope" type="radio" value="all" class="h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    Tính VAT cho tất cả sản phẩm
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-700">
                                    <input v-model="form.pos_vat_default_scope" type="radio" value="vat_products" class="h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    Chỉ tính cho sản phẩm có nhãn VAT khi nhập hàng
                                </label>
                            </div>
                        </SettingBlock>
                        <SettingBlock title="Hàng cũ (nhập từ khách)">
                            <SettingSwitch
                                v-model="form.pos_vat_used_enabled"
                                title="Cho phép tính VAT"
                                description="Mặc định hàng cũ không chịu VAT. Bật để thu ngân được chọn VAT trên POS và khi sửa hóa đơn."
                            />
                            <div v-if="form.pos_vat_used_enabled" class="rounded-lg bg-slate-50 p-3">
                                <SettingSwitch
                                    v-model="form.pos_vat_used_default"
                                    title="Mặc định chọn tính VAT"
                                    description="Tắt thì thu ngân tự bấm VAT cho từng dòng hàng cũ."
                                />
                            </div>
                        </SettingBlock>
                    </div>
                </SettingCollapse>

            </template>

            <!-- ===== THANH TOÁN ===== -->
            <template v-if="activeModal === 'payments'">
                <SettingCollapse title="Tài khoản nhận tiền" :description="`${form.bank_accounts.length}/10 tài khoản · dùng cho VietQR và tự đối soát`">
                    <div class="space-y-3 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div v-if="form.bank_accounts.length" class="flex min-w-0 flex-1 flex-wrap gap-2" role="tablist" aria-label="Tài khoản ngân hàng">
                                <button
                                    v-for="item in form.bank_accounts"
                                    :key="item.id"
                                    type="button"
                                    role="tab"
                                    :aria-selected="String(item.id) === String(selectedBankAccountId)"
                                    class="max-w-full truncate rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="String(item.id) === String(selectedBankAccountId)
                                        ? 'border-violet-500 bg-violet-50 text-violet-800 ring-2 ring-violet-100'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                    @click="selectedBankAccountId = String(item.id)"
                                >
                                    {{ bankAccountLabel(item) }} · {{ item.bank_account || 'Chưa có số' }}
                                    <span v-if="item.is_default" class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">Mặc định</span>
                                    <span v-if="item.vat_preferred" class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">VAT</span>
                                </button>
                            </div>
                            <p v-else class="min-w-0 flex-1 text-sm text-slate-500">Chưa có tài khoản nào. Bấm “Thêm tài khoản” để nhập thông tin nhận chuyển khoản.</p>
                            <button
                                v-if="can_update_bank_settings && form.bank_accounts.length < 10"
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 hover:bg-violet-100"
                                @click="addBankAccount"
                            >
                                <Plus :size="14" /> Thêm tài khoản
                            </button>
                        </div>
                        <div v-if="!banks.length" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                            Chưa tải được danh sách VietQR. Bạn có thể nhập mã BIN thủ công.
                        </div>
                        <p v-if="!can_update_bank_settings" class="text-xs text-slate-500">{{ bankLockedText }}</p>
                        <div v-for="account in visibleBankAccounts" :key="account.id" class="space-y-3 border-t border-slate-100 pt-3">
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-800" :class="account.is_default || !can_update_bank_settings ? 'cursor-default' : 'cursor-pointer'">
                                <input
                                    type="checkbox"
                                    :checked="Boolean(account.is_default)"
                                    :disabled="!can_update_bank_settings || account.is_default"
                                    class="h-4 w-4 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    @change="setDefaultAccount(account)"
                                >
                                <span>{{ account.is_default ? 'Tài khoản mặc định trên POS' : 'Đặt làm tài khoản mặc định' }}</span>
                            </label>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!can_update_bank_settings"
                                @click="removeBankAccount"
                            >
                                <Trash2 :size="15" /> Xóa tài khoản
                            </button>
                        </div>
                        <div class="grid gap-2.5 md:grid-cols-3">
                            <FloatingSelect
                                v-if="banks.length"
                                v-model="account.bank_bin"
                                :name="`bank_accounts_${selectedBankAccountIndex}_bin`"
                                label="Ngân hàng"
                                :options="bankOptions"
                                option-label="label"
                                option-value="bin"
                                :disabled="!can_update_bank_settings"
                                :error="form.errors[`bank_accounts.${selectedBankAccountIndex}.bank_bin`]"
                                @update:model-value="setAccountBank(account, $event)"
                            />
                            <FloatingInput
                                v-else
                                v-model="account.bank_bin"
                                :name="`bank_accounts_${selectedBankAccountIndex}_bin`"
                                label="Mã ngân hàng VietQR / BIN"
                                placeholder-text="Ví dụ: 970422"
                                :disabled="!can_update_bank_settings"
                                :error="form.errors[`bank_accounts.${selectedBankAccountIndex}.bank_bin`]"
                            />
                            <FloatingInput
                                v-model="account.bank_account"
                                :name="`bank_accounts_${selectedBankAccountIndex}_account`"
                                label="Số tài khoản"
                                autocomplete="off"
                                :disabled="!can_update_bank_settings"
                                :error="form.errors[`bank_accounts.${selectedBankAccountIndex}.bank_account`]"
                            />
                            <FloatingInput
                                v-model="account.bank_account_name"
                                :name="`bank_accounts_${selectedBankAccountIndex}_account_name`"
                                label="Tên chủ tài khoản"
                                :disabled="!can_update_bank_settings"
                                :error="form.errors[`bank_accounts.${selectedBankAccountIndex}.bank_account_name`]"
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs font-semibold text-slate-800">
                                <input type="checkbox" :checked="Boolean(account.vat_preferred)" :disabled="!can_update_bank_settings" class="h-4 w-4 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" @change="setVatPreferredAccount(account, $event.target.checked)">
                                <span>Ưu tiên khi hóa đơn có VAT <span class="block font-normal text-slate-500">Chỉ một tài khoản được chọn.</span></span>
                            </label>
                            <FloatingSelect
                                v-model="account.transfer_content_mode"
                                :name="`bank_accounts_${selectedBankAccountIndex}_transfer_content_mode`"
                                label="Nội dung CK"
                                :options="transferContentOptions"
                                :disabled="!can_update_bank_settings"
                                :error="form.errors[`bank_accounts.${selectedBankAccountIndex}.transfer_content_mode`]"
                                :show-placeholder="false"
                            />
                        </div>

                        <div class="pt-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <SettingSwitch
                                    class="min-w-[16rem] flex-1"
                                    :model-value="Boolean(account.payment_provider)"
                                    :disabled="!can_update_bank_settings"
                                    title="Tự đối soát giao dịch"
                                    description="Tự nhận biết khách đã chuyển khoản qua ApiPay hoặc SePay."
                                    @update:model-value="setAutoReconcile(account, $event)"
                                />
                                <span v-if="account.payment_auto_enabled" class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-700">ĐANG BẬT</span>
                                <span v-else-if="account.payment_provider" class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-800">CHƯA ĐỦ CẤU HÌNH</span>
                            </div>
                            <div v-if="account.payment_provider" class="mt-3 space-y-3">
                            <FloatingSelect
                                v-model="account.payment_provider"
                                :name="`bank_accounts_${selectedBankAccountIndex}_payment_provider`"
                                label="Dịch vụ nhận giao dịch"
                                :options="paymentProviderOptions"
                                :disabled="!can_update_bank_settings"
                                :show-placeholder="false"
                            />
                            <div v-if="account.payment_provider === 'apipay'" class="mt-2 grid gap-3 sm:grid-cols-2">
                                <FloatingInput v-model="account.payment_provider_account_id" :name="`bank_accounts_${selectedBankAccountIndex}_provider_account_id`" label="Bank Account Public ID ApiPay" placeholder-text="bk_..." :disabled="!can_update_bank_settings" input-class="disabled:bg-slate-100" />
                                <FloatingInput v-model="account.payment_api_access_key" :name="`bank_accounts_${selectedBankAccountIndex}_api_access_key`" label="ApiPay Access Key" type="password" autocomplete="new-password" :placeholder-text="account.payment_api_access_key_configured ? 'Đã lưu · nhập giá trị mới để thay' : 'ak_live_… hoặc ak_test_…'" :disabled="!can_update_bank_settings" input-class="disabled:bg-slate-100" />
                                <FloatingInput v-model="account.payment_api_secret_key" :name="`bank_accounts_${selectedBankAccountIndex}_api_secret_key`" label="ApiPay Secret Key" type="password" autocomplete="new-password" :placeholder-text="account.payment_api_secret_key_configured ? 'Đã lưu · nhập giá trị mới để thay' : 'Secret key của API Key'" :disabled="!can_update_bank_settings" input-class="disabled:bg-slate-100" />
                                <div>
                                    <FloatingInput v-model="account.payment_webhook_secret" :name="`bank_accounts_${selectedBankAccountIndex}_webhook_secret`" label="Webhook signing secret" type="password" autocomplete="new-password" :placeholder-text="account.payment_webhook_secret_configured ? 'Đã lưu · nhập giá trị mới để thay' : 'Secret ApiPay trả về khi tạo webhook'" :disabled="!can_update_bank_settings" input-class="disabled:bg-slate-100" />
                                    <span v-if="account.payment_webhook_secret_configured" class="mt-1 block text-[11px] font-normal text-emerald-700">Đã lưu secret.</span>
                                </div>
                            </div>
                            <p v-if="account.payment_provider === 'apipay' && !account.payment_auto_enabled" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11px] leading-relaxed text-amber-800">
                                ApiPay cần Bank Account Public ID, Access Key, Secret Key và Webhook signing secret. Hãy nhập đủ các thông tin này rồi lưu cài đặt.
                            </p>
                            <div v-else-if="account.payment_provider === 'sepay'" class="mt-2 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <FloatingInput v-model="account.payment_api_key" :name="`bank_accounts_${selectedBankAccountIndex}_api_key`" label="SePay API Key" type="password" autocomplete="new-password" :placeholder-text="account.payment_api_key_configured ? 'Đã lưu · nhập giá trị mới để thay' : 'API key cấu hình webhook'" :disabled="!can_update_bank_settings" input-class="disabled:bg-slate-100" />
                                    <span v-if="account.payment_api_key_configured" class="mt-1 block text-[11px] font-normal text-emerald-700">Đã lưu API key.</span>
                                </div>
                                <div>
                                    <FloatingInput v-model="account.payment_webhook_secret" :name="`bank_accounts_${selectedBankAccountIndex}_webhook_secret`" label="Webhook secret tùy chỉnh" type="password" autocomplete="new-password" :placeholder-text="account.payment_webhook_secret_configured ? 'Đã lưu · nhập giá trị mới để thay' : 'Không bắt buộc'" :disabled="!can_update_bank_settings" input-class="disabled:bg-slate-100" />
                                    <span v-if="account.payment_webhook_secret_configured" class="mt-1 block text-[11px] font-normal text-emerald-700">Đã lưu secret.</span>
                                </div>
                            </div>
                            <details class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <summary class="cursor-pointer font-semibold text-slate-700">Hướng dẫn cấu hình webhook</summary>
                                <p class="mt-2">Webhook URL: <code class="select-all break-all font-semibold text-violet-700">{{ bankWebhookEndpoint }}</code></p>
                                <p v-if="account.payment_provider === 'apipay'" class="mt-1 leading-relaxed">Đăng ký URL này trong ApiPay → Cài đặt → Webhooks (cần HTTPS, truy cập được từ Internet) rồi nhập secret được trả về. QR của ApiPay sẽ hiển thị trên POS và webhook ghép giao dịch theo mã hóa đơn.</p>
                                <p v-else class="mt-1 leading-relaxed">Dán URL này vào cấu hình webhook của dịch vụ (cần truy cập được từ Internet). POS gửi mã hóa đơn trong nội dung thanh toán để tự đối soát.</p>
                            </details>
                            </div>
                        </div>
                        </div>
                    </div>
                </SettingCollapse>

                <SettingCollapse title="Mã QR và âm báo" description="Áp dụng cho mọi tài khoản ngân hàng." :default-open="false">
                    <div class="divide-y divide-slate-100">
                        <SettingBlock title="Màu mã QR">
                            <p class="text-xs text-slate-500">Nền luôn sáng để mã dễ quét.</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <button
                                v-for="theme in VIET_QR_THEMES"
                                :key="theme.value"
                                type="button"
                                class="flex items-center gap-2 rounded-xl border p-2 text-left transition"
                                :class="form.bank_qr_theme === theme.value
                                    ? 'border-emerald-500 bg-emerald-50/60 ring-2 ring-emerald-100'
                                    : 'border-slate-200 bg-white hover:border-slate-300'"
                                :disabled="!can_update_bank_settings"
                                :aria-pressed="form.bank_qr_theme === theme.value"
                                @click="form.bank_qr_theme = theme.value"
                            >
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200"
                                    :style="{ backgroundColor: theme.background, color: theme.foreground }"
                                >
                                    <QrCode class="h-6 w-6" />
                                </span>
                                <span class="text-xs font-semibold text-slate-700">{{ theme.label }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors.bank_qr_theme" class="mt-1 text-xs text-rose-600">{{ form.errors.bank_qr_theme }}</p>
                        </SettingBlock>
                        <SettingBlock title="Âm báo khi nhận tiền">
                        <SettingSwitch
                            v-model="form.payment_voice_enabled"
                            :disabled="!can_update_bank_settings"
                            title="Âm báo bằng giọng nói"
                            description="Đọc thông báo khi đối soát nhận được tiền."
                        />
                        <div v-if="form.payment_voice_enabled" class="mt-3 grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2">
                            <FloatingSelect
                                v-model="form.payment_voice_language"
                                name="payment_voice_language"
                                label="Ngôn ngữ đọc"
                                :options="paymentVoiceOptions"
                                :disabled="!can_update_bank_settings"
                                :show-placeholder="false"
                            />
                            <button type="button" :disabled="!paymentVoiceAvailable" :aria-label="paymentVoiceAvailable ? 'Nghe thử giọng đọc' : 'Không có giọng đọc phù hợp'" :title="paymentVoiceAvailable ? 'Nghe thử giọng đọc' : 'Không có giọng đọc phù hợp'" class="mb-px inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" @click="testSelectedPaymentVoice"><Volume2 :size="18" /></button>
                            <p v-if="paymentVoiceTestMessage" class="col-span-2 text-xs text-slate-500">{{ paymentVoiceTestMessage }}</p>
                            <p v-if="paymentVoiceChecked && !paymentVoiceAvailable" class="col-span-2 text-xs text-amber-700">Máy chưa có giọng đọc {{ form.payment_voice_language === 'vi-VN' ? 'Tiếng Việt' : 'English' }}; POS vẫn phát âm báo dạng tiếng bíp.</p>
                        </div>
                        </SettingBlock>
                    </div>
                </SettingCollapse>
            </template>

            <!-- ===== KHÁCH HÀNG ===== -->
            <template v-if="activeModal === 'loyalty'">
                <SettingCollapse title="Tích điểm" description="Cộng điểm cho khách theo giá trị đơn hàng.">
                    <div class="space-y-4 p-4">
                        <SettingSwitch v-model="form.customer_points_enabled" title="Bật tích điểm khách hàng" />
                        <template v-if="form.customer_points_enabled">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <FloatingSelect
                                    v-model="form.customer_points_basis"
                                    name="customer_points_basis"
                                    label="Tính điểm theo"
                                    :options="[
                                        { value: 'revenue', label: 'Doanh thu' },
                                        { value: 'profit', label: 'Lợi nhuận' },
                                    ]"
                                    :show-placeholder="false"
                                    :error="form.errors.customer_points_basis"
                                />
                                <FloatingInput v-model.number="form.customer_points_amount_per_point" name="customer_points_amount_per_point" label="Số tiền cho 1 điểm (₫)" type="number" min="1" format-thousands :error="form.errors.customer_points_amount_per_point" />
                            </div>
                            <div class="space-y-3 rounded-lg bg-slate-50 p-3">
                                <SettingSwitch v-model="form.customer_points_debt_enabled" title="Tích điểm cho đơn còn nợ" />
                                <div v-if="form.customer_points_debt_enabled" class="grid gap-3 sm:grid-cols-2">
                                    <FloatingSelect
                                        v-model="form.customer_points_debt_mode"
                                        name="customer_points_debt_mode"
                                        label="Điểm của đơn nợ"
                                        :options="[
                                            { value: 'same', label: 'Như đơn đã thanh toán' },
                                            { value: 'half', label: '50% điểm' },
                                            { value: 'custom', label: 'Tự đặt' },
                                        ]"
                                        :show-placeholder="false"
                                        :error="form.errors.customer_points_debt_mode"
                                    />
                                    <FloatingInput
                                        v-if="form.customer_points_debt_mode === 'custom'"
                                        v-model.number="form.customer_points_debt_percent"
                                        name="customer_points_debt_percent"
                                        label="Tỷ lệ điểm (%)"
                                        type="number"
                                        min="1"
                                        max="100"
                                        :error="form.errors.customer_points_debt_percent"
                                    />
                                </div>
                            </div>
                        </template>
                    </div>
                </SettingCollapse>

                <SettingCollapse title="Nhận diện khuôn mặt" description="Tự nhận ra khách quen khi đến quầy POS." :default-open="false">
                    <div class="space-y-4 p-4">
                        <SettingSwitch
                            v-model="form.face_recognition_enabled"
                            title="Bật nhận diện khuôn mặt"
                            description="Cho phép đăng ký khuôn mặt ở trang Khách hàng và gợi ý khách tại POS."
                        />
                        <template v-if="form.face_recognition_enabled">
                            <div class="space-y-3 rounded-lg bg-slate-50 p-3">
                                <SettingSwitch
                                    v-model="form.face_notify_modal_enabled"
                                    title="Hộp thông báo chi tiết khi nhận ra khách"
                                    description="Hiện tên, SĐT, CCCD, công nợ để nhân viên xác nhận. Tắt thì chỉ hỏi ngay trong ô khách hàng."
                                />
                                <SettingSwitch
                                    v-model="form.face_visit_tracking_enabled"
                                    title="Ghi nhận lượt khách ra vào"
                                    description="Lưu mọi người xuất hiện trước camera (cả khách lạ) để thống kê ở trang Lượt khách. Chỉ bật khi cửa hàng đã có biển báo dùng camera."
                                />
                                <SettingSwitch
                                    v-if="form.face_visit_tracking_enabled"
                                    v-model="form.face_door_camera_enabled"
                                    title="Camera cửa chạy nền (Camera AI)"
                                    description="AI nhận diện liên tục kể cả khi không mở POS; POS hiện video trực tiếp và danh sách khách đang ở cửa."
                                />
                            </div>

                            <SettingCollapse title="Danh sách camera" :description="`${form.face_cameras.length}/8 camera · mỗi camera cần luồng chính và sub-stream H.264`">
                                <div class="space-y-3 p-3">
                                    <div class="flex justify-end">
                                        <button type="button" :disabled="form.face_cameras.length >= 8" class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white disabled:opacity-50" @click="addCamera"><Plus :size="14" />Thêm camera</button>
                                    </div>
                            <div v-for="(camera, index) in form.face_cameras" :key="camera.id" class="space-y-2 rounded-lg border border-slate-200 bg-white p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="min-w-0 flex-1 text-xs font-bold text-slate-800">{{ index === 0 ? 'Camera cửa mặc định' : `Camera bổ sung ${index}` }}</span>
                                    <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><input v-model="camera.enabled" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Hoạt động</label>
                                    <button v-if="camera.id !== 'door-1'" type="button" class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700" @click="removeCamera(index)"><Trash2 :size="13" />Xóa</button>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem]">
                                    <FloatingInput v-model="camera.name" :name="`face_cameras.${index}.name`" label="Tên hiển thị" placeholder-text="Camera lối vào" />
                                    <FloatingInput v-model="camera.id" :name="`face_cameras.${index}.id`" label="Mã camera" :disabled="index === 0" placeholder-text="camera-2" />
                                </div>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <FloatingSelect v-model="camera.brand" :name="`face_cameras.${index}.brand`" label="Hãng camera" :options="cameraBrandOptions" :show-placeholder="false" />
                                    <FloatingInput v-model="camera.host" :name="`face_cameras.${index}.host`" label="IP / tên miền" placeholder-text="192.168.1.50" />
                                    <FloatingInput v-model.number="camera.channel" :name="`face_cameras.${index}.channel`" type="number" min="1" max="64" label="Kênh" />
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" :disabled="camera.brand === 'custom' || !camera.host" class="rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50" @click="applyCameraPreset(camera)">Điền URL theo hãng</button>
                                    <span class="text-[11px] text-slate-500">URL tự sửa sẽ được giữ nguyên; sub-stream phải là H.264.</span>
                                </div>
                                <FloatingInput v-model="camera.snapshot_url" :name="`face_cameras.${index}.snapshot_url`" label="Luồng chính (RTSP main-stream)" placeholder-text="rtsp://192.168.1.50:554/..." />
                                <p v-if="form.errors[`face_cameras.${index}.snapshot_url`]" class="text-xs text-rose-600">{{ form.errors[`face_cameras.${index}.snapshot_url`] }}</p>
                                <FloatingInput v-if="isRtspCamera(camera)" v-model="camera.view_rtsp_url" :name="`face_cameras.${index}.view_rtsp_url`" label="Luồng AI và xem trực tiếp (RTSP sub-stream H.264)" placeholder-text="rtsp://192.168.1.50:554/...sub-stream..." />
                                <p v-if="form.errors[`face_cameras.${index}.view_rtsp_url`]" class="text-xs text-rose-600">{{ form.errors[`face_cameras.${index}.view_rtsp_url`] }}</p>
                                <div v-if="camera.snapshot_url" class="grid gap-2 sm:grid-cols-3">
                                    <FloatingSelect v-if="!isRtspCamera(camera)" v-model="camera.auth" :name="`face_cameras.${index}.auth`" label="Đăng nhập" :options="faceCctvAuthOptions" :show-placeholder="false" />
                                    <FloatingInput v-if="isRtspCamera(camera) || camera.auth !== 'none'" v-model="camera.username" :name="`face_cameras.${index}.username`" label="Tài khoản" autocomplete="off" />
                                    <FloatingInput v-if="isRtspCamera(camera) || camera.auth !== 'none'" v-model="camera.password" :name="`face_cameras.${index}.password`" type="password" autocomplete="new-password" :label="camera.has_password ? 'Mật khẩu (để trống = giữ nguyên)' : 'Mật khẩu'" />
                                </div>
                            </div>
                                </div>
                            </SettingCollapse>

                            <DoorCameraSetup v-if="form.face_visit_tracking_enabled && form.face_door_camera_enabled && door_camera" :info="door_camera" />

                            <SettingCollapse v-if="form.face_visit_tracking_enabled" title="Lưu trữ và tự học" description="Thời gian giữ lịch sử khách và cách bổ sung mẫu khuôn mặt." :default-open="false">
                                <div class="space-y-4 p-4">
                                    <div class="max-w-xs">
                                        <FloatingInput v-model="form.face_visit_retention_days" name="face_visit_retention_days" type="number" min="1" max="365" label="Tự xóa lịch sử khách sau (ngày)" />
                                        <p class="mt-1 text-xs text-slate-500">Hết hạn sẽ xóa lượt đến, ảnh camera và dữ liệu gộp. Ảnh mẫu đã đăng ký chỉ xóa khi xóa dữ liệu khuôn mặt của khách.</p>
                                        <p v-if="form.errors.face_visit_retention_days" class="text-xs text-rose-600">{{ form.errors.face_visit_retention_days }}</p>
                                    </div>
                                    <SettingSwitch
                                        v-model="form.face_auto_learn_enabled"
                                        title="Tự bổ sung mẫu khuôn mặt"
                                        description="Chỉ lưu thêm khi khách được nhận rõ và cách biệt đủ với người giống thứ hai."
                                    />
                                    <div v-if="form.face_auto_learn_enabled" class="grid gap-3 sm:grid-cols-3">
                                        <FloatingInput v-model.number="form.face_auto_learn_min_percent" name="face_auto_learn_min_percent" type="number" min="60" max="95" label="Độ giống tối thiểu (%)" />
                                        <FloatingInput v-model.number="form.face_auto_learn_margin_percent" name="face_auto_learn_margin_percent" type="number" min="5" max="50" label="Cách biệt tối thiểu (%)" />
                                        <FloatingInput v-model.number="form.face_auto_learn_cooldown_minutes" name="face_auto_learn_cooldown_minutes" type="number" min="10" max="1440" label="Chờ trước khi học lại (phút)" />
                                    </div>
                                    <p class="text-[11px] text-slate-500">Engine cố định: YuNet + SFace/OpenVINO, để mẫu đăng ký và luồng nền luôn cùng loại vector.</p>
                                </div>
                            </SettingCollapse>
                        </template>
                        <p class="rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">
                            Khuôn mặt là dữ liệu sinh trắc học: chỉ đăng ký khi khách <strong>đồng ý</strong> và biết mục đích. Hệ thống chỉ lưu dữ liệu đặc trưng, không lưu ảnh. Tắt tính năng thì POS ngừng nhận diện; dữ liệu đã đăng ký vẫn giữ và xóa được ở trang Khách hàng.
                        </p>
                    </div>
                </SettingCollapse>
            </template>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton variant="secondary" @click="cancelModal">Đóng</ActionButton>
                <ActionButton :disabled="form.processing" @click="save">
                    <Save :size="16" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu cài đặt' }}
                </ActionButton>
            </div>
        </template>
        </BaseModal>

        <BaseModal v-if="activeModal === 'backups' && can_manage_backups" title="Sao lưu và khôi phục" size="xl" body-class="bg-slate-50 p-4 sm:p-5" @close="closeModal">
            <BackupsIndex hide-header :backups="backups" :cloud_connections="cloud_connections" :cloud_oauth="cloud_oauth" :schedule="schedule" :restore_preview="restore_preview" @manage-cloud="openModal('cloud')" />
        </BaseModal>
        <BaseModal v-if="activeModal === 'mail' && can_edit_settings" title="Email gửi thư" size="lg" body-class="max-h-[70dvh] overflow-y-auto bg-slate-50/60 p-3 sm:p-4" @close="closeModal">
            <MailSettings :mail="mail_settings || {}" />
        </BaseModal>
        <BaseModal v-if="activeModal === 'cloud' && can_manage_backups" title="Kho lưu trữ đám mây" size="lg" body-class="max-h-[70dvh] overflow-y-auto bg-slate-50/60 p-3 sm:p-4" @close="closeModal">
            <CloudStorageSettings :connections="cloud_connections" :oauth="cloud_oauth" />
        </BaseModal>
        <BaseModal v-if="activeModal === 'cleanup' && can_edit_settings" title="Dọn dẹp hệ thống" size="xl" body-class="bg-slate-50 p-4 sm:p-5" @close="closeModal">
            <div class="space-y-4">
                <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">File thừa</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Ảnh sản phẩm, khách hàng, sửa chữa, avatar, IMEI không còn được dữ liệu dùng. File sao lưu không bị quét hay xóa.</p>
                    </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div v-if="cleanupResult" class="text-sm text-slate-700">Tìm thấy <strong>{{ cleanupResult.count }}</strong> file · {{ formatBytes(cleanupResult.bytes) }} có thể giải phóng</div>
                    <div v-else class="text-sm text-slate-500">{{ cleanupLoading ? 'Đang quét...' : 'Chưa quét file.' }}</div>
                    <div class="flex gap-2">
                        <ActionButton variant="secondary" :disabled="cleanupLoading || cleanupDeleting" @click="scanUnusedFiles"><RefreshCw :size="15" /> Quét lại</ActionButton>
                        <ActionButton variant="danger" :disabled="!selectedCleanupPaths.length || cleanupLoading || cleanupDeleting" @click="cleanupUnusedFiles"><Trash2 :size="15" /> {{ cleanupDeleting ? 'Đang xóa...' : `Xóa đã chọn (${selectedCleanupPaths.length})` }}</ActionButton>
                    </div>
                </div>
                <p v-if="cleanupError" class="rounded-md bg-rose-50 p-3 text-sm text-rose-700">{{ cleanupError }}</p>
                <p v-if="cleanupResult?.deleted" class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">Đã xóa {{ cleanupResult.deleted }} file, giải phóng {{ formatBytes(cleanupResult.deleted_bytes) }}.</p>
                <div v-if="cleanupResult?.files?.length" class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center gap-2 border-b border-slate-100 px-3 py-2 text-xs text-slate-600"><input type="checkbox" :checked="selectedCleanupPaths.length === cleanupResult.files.length" @change="selectedCleanupPaths = $event.target.checked ? cleanupResult.files.map(file => file.path) : []" /><span>Chọn tất cả</span></div>
                    <div class="max-h-[48dvh] divide-y divide-slate-100 overflow-y-auto">
                        <label v-for="file in cleanupResult.files" :key="file.path" class="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-slate-50"><input v-model="selectedCleanupPaths" type="checkbox" :value="file.path" class="mt-1" /><span class="min-w-0 flex-1"><span class="block break-all font-mono text-xs text-slate-700">{{ file.path }}</span><span class="mt-1 block text-[11px] text-slate-400">{{ formatBytes(file.size) }} · {{ new Date(file.last_modified * 1000).toLocaleString() }}</span></span></label>
                    </div>
                </div>
                <div v-else-if="cleanupResult && !cleanupLoading" class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Không tìm thấy file mồ côi trong các thư mục được quét.</div>
                </section>
                <section v-if="can_manage_backups" class="rounded-xl border border-red-200 bg-red-50/50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700"><Trash2 :size="19" /></span>
                            <div>
                                <h2 class="text-sm font-bold text-red-900">Xóa dữ liệu nghiệp vụ</h2>
                                <p class="mt-1 max-w-3xl text-xs leading-5 text-red-800">Tự sao lưu cứu hộ trước, rồi xóa dữ liệu bán hàng, kho, khách hàng, sửa chữa, chi phí và tệp tải lên. Tài khoản, phân quyền, cấu hình và bản sao lưu được giữ lại.</p>
                            </div>
                        </div>
                        <ActionButton variant="danger" :disabled="clearDataForm.processing" @click="clearDataPhrase = ''; showClearDataDialog = true"><Trash2 :size="15" /> Xóa dữ liệu nghiệp vụ</ActionButton>
                    </div>
                </section>
            </div>
        </BaseModal>

        <Teleport to="body">
            <div v-if="showClearDataDialog" class="fixed inset-0 z-[10010] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="clear-data-title">
                <section class="w-full max-w-lg rounded-2xl border border-red-200 bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700"><Trash2 :size="21" /></div>
                        <div>
                            <h2 id="clear-data-title" class="text-base font-bold text-red-900">Xác nhận xóa sạch dữ liệu nghiệp vụ</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Thao tác không thể hoàn tác trực tiếp. Hệ thống sẽ tạo bản sao lưu cứu hộ trước khi xóa. Tài khoản, quyền và cấu hình sẽ được giữ lại.</p>
                        </div>
                    </div>
                    <label class="mt-5 block text-xs font-semibold text-slate-700">Nhập chính xác <code class="rounded bg-red-50 px-1.5 py-0.5 text-red-700">XOA DU LIEU</code> để xác nhận</label>
                    <input v-model="clearDataPhrase" type="text" autocomplete="off" class="mt-1.5 h-11 w-full rounded-xl border-slate-300 text-sm focus:border-red-500 focus:ring-red-500" placeholder="XOA DU LIEU">
                    <p v-if="cleanupError" class="mt-2 text-xs text-red-700">{{ cleanupError }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <ActionButton variant="secondary" @click="showClearDataDialog = false">Hủy</ActionButton>
                        <ActionButton variant="danger" :disabled="clearDataPhrase !== 'XOA DU LIEU' || clearDataForm.processing" @click="clearBusinessData"><Trash2 :size="14" /> Tạo backup và xóa dữ liệu</ActionButton>
                    </div>
                </section>
            </div>
            <div v-if="clearDataProgress.active || clearDataProgress.state !== 'running'" class="fixed inset-0 z-[10020] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-live="polite" aria-label="Tiến trình xóa dữ liệu">
                <section class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div><h2 class="text-sm font-bold text-slate-800">{{ clearDataProgress.active ? 'Đang dọn dẹp hệ thống' : clearDataProgress.state === 'failed' ? 'Dọn dẹp thất bại' : 'Dọn dẹp hoàn tất' }}</h2><p class="mt-1 text-sm text-slate-600">{{ clearDataProgress.message }}</p></div>
                        <span class="rounded-full bg-red-50 px-2.5 py-1 text-sm font-bold tabular-nums text-red-700">{{ Math.round(clearDataProgress.percent || 0) }}%</span>
                    </div>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-red-600 transition-[width] duration-300" :style="{ width: `${Math.max(3, clearDataProgress.percent || 0)}%` }"></div></div>
                    <div v-if="clearDataProgress.report" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900">
                        <p class="font-bold">{{ clearDataProgress.report.title }}</p>
                        <p v-if="clearDataProgress.report.tables_cleared !== undefined" class="mt-1">{{ clearDataProgress.report.tables_cleared }} bảng · {{ Number(clearDataProgress.report.rows_cleared || 0).toLocaleString() }} dòng · {{ clearDataProgress.report.files_cleared }} tệp đã xóa</p>
                        <p v-if="clearDataProgress.report.recovery_backup" class="mt-1 break-all">Bản cứu hộ: {{ clearDataProgress.report.recovery_backup }}</p>
                    </div>
                    <div v-if="!clearDataProgress.active" class="mt-5 flex justify-end"><ActionButton @click="dismissClearDataProgress">OK</ActionButton></div>
                </section>
            </div>
        </Teleport>
    </div>
</template>
