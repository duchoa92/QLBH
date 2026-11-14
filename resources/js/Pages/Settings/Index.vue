<script setup>
import { useSilentRefresh } from '@/Composables/useSilentRefresh'
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'
import { openModal as openGlobalModal, registerUrlModal } from '@/Stores/modal'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import LabelTemplateEditor from './LabelTemplateEditor.vue'
import SettingSwitch from './SettingSwitch.vue'
import SettingCollapse from './SettingCollapse.vue'
import { mergeLabelTemplate } from '@/utils/printLabels'
import { Pencil, Plus, Power, Save, Trash2, X, Store, DatabaseBackup, ChevronRight, CreditCard, Boxes, Printer, QrCode, HardDrive, RefreshCw, Volume2, Award } from 'lucide-vue-next'
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
    can_manage_payment_accounts: { type: Boolean, default: false },
    can_manage_inventory_settings: { type: Boolean, default: false },
    units: {
        type: Array,
        default: () => [],
    },
    can_manage_backups: Boolean,
    active_section: String,
    backups: { type: Array, default: () => [] },
    cloud_connections: { type: Array, default: () => [] },
    cloud_oauth: { type: Object, default: () => ({}) },
    schedule: { type: Object, default: () => ({}) },
    restore_preview: { type: Object, default: null },
    purchase_contract_defaults: { type: Object, default: () => ({ title: '', content: '' }) },
})
const page = usePage()

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
    else if (section === 'cleanup' && props.can_edit_settings) { activeModal.value = section; scanUnusedFiles() }
    else if (section === 'system' && props.can_edit_settings) activeModal.value = section
    else if (section === 'payments' && props.can_manage_payment_accounts) activeModal.value = section
    else if (section === 'inventory' && props.can_manage_inventory_settings) activeModal.value = section
    else if (section === 'printing' && props.can_edit_settings) activeModal.value = section
    else if (section === 'loyalty' && props.can_edit_settings) activeModal.value = section
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
watch(() => props.active_section, applySection, { immediate: true })

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
const SECTION_KEYS = {
    system: ['shop_name', 'currency_symbol', 'currency_format', 'app_locale', 'app_timezone', 'date_format'],
    printing: ['shop_name', 'print_template', 'print_paper_size', 'print_orientation', 'print_shop_subtitle', 'print_shop_address', 'print_shop_phone', 'label_template', 'purchase_contract_title', 'purchase_contract_content'],
    inventory: ['allow_negative_stock', 'inventory_alert_enabled', 'inventory_alert_threshold', 'stock_import_vat_default', 'stock_import_vat_rate', 'pos_vat_default_enabled', 'pos_vat_default_scope', 'pos_vat_rate', 'pos_vat_used_enabled', 'pos_vat_used_default', 'pos_cost_price_suggestion_enabled', 'work_shift_opening_count_enabled', 'work_shift_auto_open_enabled'],
    loyalty: ['customer_points_enabled', 'customer_points_amount_per_point', 'customer_points_basis', 'customer_points_debt_enabled', 'customer_points_debt_mode', 'customer_points_debt_percent'],
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
    payment_voice_enabled: liveSettings.value.payment_voice_enabled !== false
        && liveSettings.value.payment_voice_enabled !== 0
        && liveSettings.value.payment_voice_enabled !== '0',
    bank_accounts: mapBankAccounts(),
})
const form = useForm(buildFormValues())

watch(
    () => [props.settings.purchase_contract_title, props.settings.purchase_contract_content],
    ([title, content]) => {
        form.purchase_contract_title = title || 'Hợp đồng mua bán điện thoại'
        form.purchase_contract_content = content || ''
    },
)

// Thẻ mục ở trang chính, gom theo nhóm công việc thay vì xếp rời 7 thẻ ngang hàng.
const settingGroups = computed(() => [
    {
        title: 'Cửa hàng',
        cards: [
            { key: 'system', title: 'Thiết lập hệ thống', description: 'Tên cửa hàng, ngôn ngữ, tiền tệ, ngày giờ.', icon: Store, tone: 'bg-blue-50 text-blue-700', hover: 'hover:border-blue-300', visible: props.can_edit_settings },
            { key: 'printing', title: 'Bản in và khổ giấy', description: 'Thông tin trên hóa đơn, khổ giấy, tem sản phẩm và mẫu hợp đồng.', icon: Printer, tone: 'bg-sky-50 text-sky-700', hover: 'hover:border-sky-300', visible: props.can_edit_settings },
        ],
    },
    {
        title: 'Bán hàng',
        cards: [
            { key: 'inventory', title: 'Bán hàng và kho hàng', description: 'Tồn kho âm, cảnh báo hàng sắp hết, VAT, kiểm kê đầu ca và đơn vị tính.', icon: Boxes, tone: 'bg-amber-50 text-amber-700', hover: 'hover:border-amber-300', visible: props.can_manage_inventory_settings },
            { key: 'payments', title: 'Tài khoản thanh toán', description: 'Tài khoản nhận chuyển khoản, VietQR và tự đối soát giao dịch.', icon: CreditCard, tone: 'bg-violet-50 text-violet-700', hover: 'hover:border-violet-300', visible: props.can_manage_payment_accounts },
            { key: 'loyalty', title: 'Điểm khách hàng', description: 'Cách tích điểm theo doanh thu hoặc lợi nhuận.', icon: Award, tone: 'bg-emerald-50 text-emerald-700', hover: 'hover:border-emerald-300', visible: props.can_edit_settings },
        ],
    },
    {
        title: 'Dữ liệu',
        cards: [
            { key: 'backups', title: 'Sao lưu và khôi phục', description: 'Tạo bản sao, đặt lịch tự động, kết nối đám mây và khôi phục dữ liệu.', icon: DatabaseBackup, tone: 'bg-emerald-50 text-emerald-700', hover: 'hover:border-emerald-300', visible: props.can_manage_backups },
            { key: 'cleanup', title: 'Dọn dẹp hệ thống', description: 'Tìm và xóa ảnh tải lên không còn được dữ liệu sử dụng.', icon: HardDrive, tone: 'bg-rose-50 text-rose-700', hover: 'hover:border-rose-300', visible: props.can_edit_settings },
        ],
    },
].map(group => ({ ...group, cards: group.cards.filter(card => card.visible) })).filter(group => group.cards.length))

const resetContractDefaults = () => {
    form.purchase_contract_title = props.purchase_contract_defaults.title || ''
    form.purchase_contract_content = props.purchase_contract_defaults.content || ''
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

const editingUnitId = ref(null)

const unitForm = useForm({
    name: '',
    short_name: '',
    is_active: true,
})

const save = () => {
    if (activeModal.value !== 'payments') {
        const keys = SECTION_KEYS[activeModal.value] || []
        form.transform(data => Object.fromEntries(Object.entries(data).filter(([key]) => keys.includes(key)))).post(route('settings.update'), {
            preserveScroll: true,
            onSuccess: () => {
                form.defaults()
                closeModal()
            },
        })
        return
    }

    const accountsToSave = form.bank_accounts.filter(account =>
        [account.bank_bin, account.bank_account, account.bank_account_name].some(value => String(value || '').trim() !== '')
    )
    const selectedAccountIsSaved = accountsToSave.some(account => String(account.id) === String(selectedBankAccountId.value))
    const defaultAccountId = selectedAccountIsSaved
        ? String(selectedBankAccountId.value)
        : String(accountsToSave.find(account => account.is_default)?.id || accountsToSave[0]?.id || '')
    accountsToSave.forEach(account => { account.is_default = String(account.id) === defaultAccountId })
    let vatPreferredAssigned = false
    accountsToSave.forEach(account => {
        account.vat_preferred = Boolean(account.vat_preferred) && !vatPreferredAssigned
        if (account.vat_preferred) vatPreferredAssigned = true
    })
    form.bank_accounts = accountsToSave

    const section = activeModal.value
    const keys = SECTION_KEYS[section] || []
    form.transform(data => Object.fromEntries(Object.entries(data).filter(([key]) => keys.includes(key)))).post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            if (section === 'payments') {
                // Nạp lại từ máy chủ: khóa bí mật vừa nhập đã được mã hóa, trạng thái "đang bật/chưa đủ cấu hình" cũng được cập nhật.
                form.bank_accounts = mapBankAccounts()
                selectedBankAccountId.value = String(form.bank_accounts.find(account => account.is_default)?.id || form.bank_accounts[0]?.id || defaultAccountId)
                localStorage.setItem('pos-payment-settings-updated', String(Date.now()))
            }
            form.defaults()
            closeModal()
        },
    })
}

const resetUnitForm = () => {
    editingUnitId.value = null
    unitForm.reset()
    unitForm.clearErrors()
    unitForm.name = ''
    unitForm.short_name = ''
    unitForm.is_active = true
}

const editUnit = (unit) => {
    editingUnitId.value = unit.id
    unitForm.clearErrors()
    unitForm.name = unit.name || ''
    unitForm.short_name = unit.short_name || ''
    unitForm.is_active = Boolean(unit.is_active)
}

const submitUnit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetUnitForm,
    }

    if (editingUnitId.value) {
        unitForm.put(route('units.update', editingUnitId.value), options)
        return
    }

    unitForm.post(route('units.store'), options)
}

const toggleUnitStatus = (unit) => {
    router.patch(route('units.toggleStatus', unit.id), {}, {
        preserveScroll: true,
    })
}

const unitDeleteError = computed(() => page.props.errors?.unit || '')

const deleteUnit = (unit) => {
    confirmBox.show({
        title: 'Xóa đơn vị tính',
        message: `Xóa đơn vị "${unit.name}"? Đơn vị đã dùng trong sản phẩm, phiếu nhập hoặc hóa đơn sẽ không xóa được, khi đó hãy chuyển sang "Tạm tắt".`,
        confirmText: 'Xóa đơn vị',
        cancelText: 'Giữ lại',
        onConfirm: () => router.delete(route('units.destroy', unit.id), { preserveScroll: true }),
    })
}

const unitDisplayName = (unit) =>
    unit?.short_name
        ? `${unit.name} (${unit.short_name})`
        : unit?.name

const openUnitDetailFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const unitId = Number(url.searchParams.get('open_unit'))
    if (!unitId) return
    url.searchParams.delete('open_unit')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true, onSuccess: () => openGlobalModal(RecordDetailModal, { props: { type: 'unit', recordId: unitId }, deepLink: { type: 'unit', id: unitId } }) })
}

registerUrlModal('unit', RecordDetailModal, id => ({ type: 'unit', recordId: id }))

watch(() => page.url, () => nextTick(openUnitDetailFromGlobalSearch), { immediate: true })
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
            :title="activeModal === 'system' ? 'Thiết lập hệ thống' : activeModal === 'payments' ? 'Tài khoản thanh toán' : activeModal === 'printing' ? 'Cài đặt bản in' : activeModal === 'loyalty' ? 'Điểm khách hàng' : 'Bán hàng và kho hàng'"
            :size="'lg'"
            :body-class="activeModal === 'payments' ? 'max-h-[70vh] overflow-y-auto px-5 py-2 sm:px-6 sm:py-3' : 'max-h-[70vh] overflow-y-auto p-3 sm:p-4'"
            @close="cancelModal"
        >
       
        <div class="grid gap-5">
            <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">
                <div v-for="(message, key) in form.errors" :key="key">{{ message }}</div>
            </div>
            <div class="space-y-5">
                <DataPanel v-if="activeModal === 'loyalty'">
                    <template #header>
                        <label class="relative flex cursor-pointer items-center justify-between gap-3">
                            <h2 class="text-sm font-bold text-slate-900">Tích điểm khách hàng</h2>
                            <input v-model="form.customer_points_enabled" type="checkbox" role="switch" aria-label="Tích điểm khách hàng" class="peer sr-only">
                            <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 peer-focus-visible:ring-offset-2 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></span>
                        </label>
                    </template>
                    <div v-if="form.customer_points_enabled" class="space-y-3 p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <FloatingSelect
                                v-model="form.customer_points_basis"
                                name="customer_points_basis"
                                label="Tính theo"
                                :options="[
                                    { value: 'revenue', label: 'Doanh thu' },
                                    { value: 'profit', label: 'Lợi nhuận' },
                                ]"
                                :show-placeholder="false"
                                :error="form.errors.customer_points_basis"
                            />
                            <FloatingInput v-model.number="form.customer_points_amount_per_point" name="customer_points_amount_per_point" label="Số tiền cho 1 điểm (₫)" type="number" min="1" format-thousands :error="form.errors.customer_points_amount_per_point" />
                            <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                                <label class="relative flex cursor-pointer items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-800">Tích điểm đơn còn nợ</span>
                                    <input v-model="form.customer_points_debt_enabled" type="checkbox" role="switch" aria-label="Tích điểm đơn còn nợ" class="peer sr-only">
                                    <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 peer-focus-visible:ring-offset-2 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></span>
                                </label>
                                <div v-if="form.customer_points_debt_enabled" class="grid gap-3 sm:grid-cols-2">
                                    <FloatingSelect
                                        v-model="form.customer_points_debt_mode"
                                        name="customer_points_debt_mode"
                                        label="Điểm đơn nợ"
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
                        </div>
                    </div>
                </DataPanel>
                <div v-if="activeModal === 'printing'" class="space-y-4">
<SettingCollapse title="Thông tin cửa hàng trên hóa đơn" description="Chỉ thông tin có nhập mới xuất hiện trên hóa đơn.">
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        <FloatingInput v-model="form.shop_name" name="shop_name" label="Tên cửa hàng" :error="form.errors.shop_name" />
                        <FloatingInput v-model="form.print_shop_subtitle" name="print_shop_subtitle" label="Ngành hàng / dòng giới thiệu" :error="form.errors.print_shop_subtitle" />
                        <FloatingInput v-model="form.print_shop_phone" name="print_shop_phone" label="Số điện thoại cửa hàng" :error="form.errors.print_shop_phone" />
                        <FloatingInput v-model="form.print_shop_address" name="print_shop_address" label="Địa chỉ cửa hàng" :error="form.errors.print_shop_address" />
                    </div>
                </SettingCollapse>
<SettingCollapse title="Mẫu hóa đơn và khổ giấy" description="Áp dụng cho hóa đơn bán hàng và phiếu sửa chữa.">
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        <FloatingSelect v-model="form.print_template" name="print_template" label="Mẫu hóa đơn" :options="printTemplateOptions" :show-placeholder="false" />
                        <FloatingSelect v-model="form.print_paper_size" name="print_paper_size" label="Khổ giấy" :options="printPaperOptions" />
                        <FloatingSelect v-model="form.print_orientation" name="print_orientation" label="Hướng giấy" :options="printOrientationOptions" />
                    </div>
                </SettingCollapse>
                </div>
                <SettingCollapse v-if="activeModal === 'printing'" title="Mẫu in tem sản phẩm" description="Chỉnh khổ tem, nội dung và cỡ chữ; bản xem trước hiển thị đúng kích thước thật." :default-open="false">
                    <LabelTemplateEditor v-model="form.label_template" :shop-name="form.shop_name" :errors="form.errors" />
                </SettingCollapse>
                <SettingCollapse v-if="activeModal === 'printing'" title="Nội dung hợp đồng mua bán" :default-open="false">
                    <template #description>Biến: <code>&#123;&#123;date&#125;&#125;</code>, <code>&#123;&#123;delivery_date&#125;&#125;</code>, <code>&#123;&#123;import_code&#125;&#125;</code>, <code>&#123;&#123;buyer_name&#125;&#125;</code>, <code>&#123;&#123;seller_name&#125;&#125;</code>, <code>&#123;&#123;total&#125;&#125;</code>, <code>&#123;&#123;note&#125;&#125;</code>.</template>
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
                        </div>
                    </div>
                </SettingCollapse>
                <template v-if="activeModal === 'system'">
                    <div class="space-y-4">
                        <div class="space-y-4">
<SettingCollapse title="Thông tin cửa hàng" description="Tên hiển thị trên hóa đơn, màn hình POS và báo cáo.">
                        <div class="grid gap-4 p-4 sm:grid-cols-2">
                            <FloatingInput v-model="form.shop_name" name="shop_name" label="Tên cửa hàng" :error="form.errors.shop_name" />
                        </div>
                    </SettingCollapse>
<SettingCollapse title="Tiền tệ" description="Cách hiển thị số tiền trên toàn hệ thống.">
                        <div class="grid gap-4 p-4 sm:grid-cols-2">
                            <FloatingInput v-model="form.currency_symbol" name="currency_symbol" label="Ký hiệu tiền" :error="form.errors.currency_symbol" />
                            <FloatingSelect v-model="form.currency_format" name="currency_format" label="Định dạng tiền" :options="currencyFormatOptions" />
                        </div>
                    </SettingCollapse>
<SettingCollapse title="Ngôn ngữ và thời gian" description="Ảnh hưởng đến cách hiển thị ngày giờ và thông báo.">
                        <div class="grid gap-4 p-4 sm:grid-cols-2">
                            <FloatingSelect v-model="form.app_locale" name="app_locale" label="Ngôn ngữ hệ thống" :options="localeOptions" />
                            <FloatingSelect v-model="form.app_timezone" name="app_timezone" label="Múi giờ hệ thống" :options="timezoneOptions" />
                            <FloatingSelect v-model="form.date_format" name="date_format" label="Định dạng ngày" :options="dateFormatOptions" />
                            <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600 sm:col-span-2">
                                <strong>Ngôn ngữ</strong> chỉ đổi thuộc tính ngôn ngữ của phiếu in và thông báo hệ thống; giao diện quản trị hiện chỉ có tiếng Việt.
                                <strong>Múi giờ</strong> chỉ thay cách hiển thị giờ, không chuyển đổi dữ liệu đã lưu, nên chỉ đổi khi thật sự cần.
                            </p>
                        </div>
                    </SettingCollapse>
                        </div>
                    </div>
                </template>

                <template v-if="activeModal === 'inventory'">
                    <div class="space-y-4">
                        <div class="space-y-4">
<SettingCollapse title="Tồn kho" description="Quy tắc bán hàng theo số lượng tồn và cảnh báo hàng sắp hết.">
                        <div class="grid sm:grid-cols-2">
                            <div class="border-b border-slate-100 p-4 sm:border-b-0 sm:border-r">
                                <SettingSwitch
                                    v-model="form.allow_negative_stock"
                                    title="Cho phép tồn kho âm"
                                    description="Cho bán trước rồi nhập bù sau. Phần bán vượt tồn chưa có lô nhập nên chưa tính được giá vốn theo lô."
                                />
                            </div>
                            <div class="space-y-3 p-4">
                                <SettingSwitch
                                    v-model="form.inventory_alert_enabled"
                                    title="Cảnh báo hàng sắp hết"
                                    description="Áp dụng cho sản phẩm chưa có ngưỡng riêng."
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
                            </div>
                        </div>
                    </SettingCollapse>
<SettingCollapse title="Bán hàng tại quầy (POS)" description="Gợi ý giá nhập và kiểm kê khi bắt đầu ca làm việc.">
                        <div class="grid sm:grid-cols-2">
                            <div class="border-b border-slate-100 p-4 sm:border-b-0 sm:border-r">
                                <SettingSwitch
                                    v-model="form.pos_cost_price_suggestion_enabled"
                                    title="Gợi ý giá nhập trên POS"
                                    description="Nhấn giữ ảnh sản phẩm hoặc dòng IMEI/biến thể để xem giá nhập."
                                />
                            </div>
                            <div class="space-y-3 p-4">
                                <SettingSwitch
                                    v-model="form.work_shift_opening_count_enabled"
                                    title="Kiểm kê đầu ca"
                                    description="Cho phép nhập bảng kê tiền và hàng khi mở ca."
                                />
                                <div v-if="form.work_shift_opening_count_enabled" class="rounded-lg bg-slate-50 p-3">
                                    <SettingSwitch
                                        v-model="form.work_shift_auto_open_enabled"
                                        title="Tự động hiện bảng kiểm kê khi vào POS"
                                        description="Khi tắt, bảng kiểm kê không tự bật lên; có thể mở ca thủ công từ POS."
                                    />
                                </div>
                            </div>
                        </div>
                    </SettingCollapse>
<SettingCollapse title="Thuế VAT" description="Mặc định khi nhập hàng và khi bán hàng; có thể đổi lại từng phiếu.">
                        <div class="grid sm:grid-cols-2">
                            <div class="space-y-3 border-b border-slate-100 p-4 sm:border-r">
                                <SettingSwitch
                                    v-model="form.stock_import_vat_default"
                                    title="Nhập hàng: mặc định có VAT"
                                    description="Áp dụng cho mặt hàng mới trong phiếu nhập."
                                />
                                <FloatingInput
                                    v-model.number="form.stock_import_vat_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    name="stock_import_vat_rate"
                                    label="Thuế suất VAT nhập hàng (%)"
                                    class="sm:max-w-xs"
                                    :error="form.errors.stock_import_vat_rate"
                                />
                            </div>
                            <div class="space-y-3 border-b border-slate-100 p-4">
                                <SettingSwitch
                                    v-model="form.pos_vat_default_enabled"
                                    title="Bán hàng: mặc định tính và cộng VAT"
                                    description="Nhãn VAT theo hàng nhập chỉ để nhận biết, không tự cộng thuế."
                                />
                                <FloatingInput
                                    v-model.number="form.pos_vat_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    name="pos_vat_rate"
                                    label="Thuế suất VAT bán hàng (%)"
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
                                        Chỉ tính VAT cho sản phẩm có nhãn VAT khi nhập hàng
                                    </label>
                                </div>
                            </div>
                            <div class="grid items-start gap-3 p-4 sm:col-span-2 sm:grid-cols-2">
                                <SettingSwitch
                                    v-model="form.pos_vat_used_enabled"
                                    title="Hàng cũ: cho phép tính VAT"
                                    description="Mặc định hàng cũ (nhập từ khách) không chịu VAT. Bật để thu ngân được chọn VAT cho hàng cũ trên POS và khi sửa hóa đơn."
                                />
                                <div v-if="form.pos_vat_used_enabled" class="rounded-lg bg-slate-50 p-3">
                                    <SettingSwitch
                                        v-model="form.pos_vat_used_default"
                                        title="Hàng cũ: mặc định chọn tính VAT"
                                        description="Khi tắt, thu ngân phải tự bấm VAT cho từng dòng hàng cũ."
                                    />
                                </div>
                            </div>
                        </div>
                    </SettingCollapse>
                        </div>
                    </div>
                </template>

                <SettingCollapse v-if="activeModal === 'inventory'" title="Đơn vị tính" description="Thêm, sửa, xóa đơn vị dùng cho sản phẩm. Thay đổi ở bảng này được lưu ngay, không cần bấm “Lưu cài đặt”." :default-open="false">

                    <div class="grid items-start gap-4 p-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                      <div class="space-y-4">
                        <div
                            v-if="unitDeleteError"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"
                            role="alert"
                        >
                            {{ unitDeleteError }}
                        </div>

                        <div
                            v-if="unitForm.hasErrors"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"
                        >
                            <div
                                v-for="message in Object.values(unitForm.errors)"
                                :key="message"
                            >
                                {{ message }}
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <FloatingInput
                                v-model="unitForm.name"
                                name="unit_name"
                                label="Tên đơn vị"
                                placeholder="Ví dụ: Thùng"
                                :error="unitForm.errors.name"
                            />

                            <FloatingInput
                                v-model="unitForm.short_name"
                                name="unit_short_name"
                                label="Viết tắt"
                                placeholder="hộp"
                                :error="unitForm.errors.short_name"
                            />

                            <ActionButton
                                class="h-[42px] sm:col-span-2"
                                :variant="editingUnitId ? 'info' : 'primary'"
                                :disabled="unitForm.processing"
                                @click="submitUnit"
                            >
                                <Save v-if="editingUnitId" :size="16" />
                                <Plus v-else :size="16" />
                                {{ editingUnitId ? 'Cập nhật' : 'Thêm' }}
                            </ActionButton>
                        </div>

                        <div
                            v-if="editingUnitId"
                            class="flex justify-end"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-slate-800"
                                @click="resetUnitForm"
                            >
                                <X :size="14" />
                                Hủy sửa
                            </button>
                        </div>

                        <div class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                            Ví dụ: Cái, Chiếc, Bộ, Hộp. Đơn vị dùng để hiển thị và quy đổi (ví dụ Thùng → Cái) khi cấu hình sản phẩm và nhập hàng; tồn kho luôn tính theo đơn vị gốc.
                        </div>
                      </div>
                      <div>
                        <div class="max-h-96 overflow-auto rounded-lg border border-slate-200">
                            <table class="app-data-table w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 text-xs font-bold uppercase text-slate-500">
                                        <th class="px-3 py-2 text-left">
                                            Đơn vị
                                        </th>
                                        <th class="w-28 px-3 py-2 text-left">
                                            Viết tắt
                                        </th>
                                        <th class="w-28 px-3 py-2 text-center">
                                            Trạng thái
                                        </th>
                                        <th class="w-28 px-3 py-2 text-center">
                                            Thao tác
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="unit in units"
                                        :key="unit.id"
                                        class="hover:bg-slate-50"
                                    >
                                        <td class="px-3 py-2 font-semibold text-slate-800">
                                            {{ unitDisplayName(unit) }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ unit.short_name || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span
                                                class="rounded-full px-2 py-1 text-xs font-bold"
                                                :class="unit.is_active
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-500'"
                                            >
                                                {{ unit.is_active ? 'Đang dùng' : 'Tạm tắt' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center justify-center gap-1">
                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50"
                                                    title="Sửa"
                                                    @click="editUnit(unit)"
                                                >
                                                    <Pencil :size="16" />
                                                </button>

                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-amber-600 hover:bg-amber-50"
                                                    title="Bật/tắt"
                                                    @click="toggleUnitStatus(unit)"
                                                >
                                                    <Power :size="16" />
                                                </button>

                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-red-600 hover:bg-red-50"
                                                    title="Xóa"
                                                    @click="deleteUnit(unit)"
                                                >
                                                    <Trash2 :size="16" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="!units.length">
                                        <td
                                            colspan="4"
                                            class="px-3 py-8 text-center text-sm font-medium text-slate-400"
                                        >
                                            Chưa có đơn vị tính nào
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                      </div>

                    </div>
                </SettingCollapse>
            </div>

            <section v-if="activeModal === 'payments'" class="w-full">
                <div class="space-y-3">
                    <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-slate-800">
                            Tài khoản nhận tiền
                            <span class="font-medium text-slate-400">({{ form.bank_accounts.length }}/10)</span>
                        </p>
                        <button
                            v-if="can_update_bank_settings && form.bank_accounts.length < 10"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 hover:bg-violet-100"
                            @click="addBankAccount"
                        >
                            <Plus :size="14" /> Thêm tài khoản
                        </button>
                    </div>
                    <div v-if="form.bank_accounts.length" class="flex flex-wrap gap-2" role="tablist" aria-label="Tài khoản ngân hàng">
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
                    <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        Chưa có tài khoản nào. Bấm “Thêm tài khoản” để nhập thông tin nhận chuyển khoản và VietQR.
                    </div>
                    <div v-if="!banks.length" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        Chưa tải được danh sách VietQR. Bạn có thể nhập BIN thủ công.
                    </div>
                    <div v-for="account in visibleBankAccounts" :key="account.id" class="space-y-3 pt-1">
                        <div class="space-y-3">
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
                            <div v-if="account.payment_provider" class="mt-2 rounded-lg bg-white/80 px-2.5 py-2 text-[11px] leading-relaxed text-slate-600">
                                Webhook URL: <code class="select-all break-all font-semibold text-violet-700">{{ bankWebhookEndpoint }}</code>
                                <p v-if="account.payment_provider === 'apipay'" class="mt-1">Trong ApiPay, đăng ký URL này tại Cài đặt → Webhooks (URL phải HTTPS và truy cập được từ Internet), rồi nhập secret trả về. QLBH gửi mã hóa đơn hiện tại làm nội dung khi tạo payment request; QR ApiPay trả về sẽ được hiển thị và webhook dùng chính nội dung đó để ghép giao dịch với hóa đơn.</p>
                                <p v-else class="mt-1">Dán URL này vào cấu hình webhook của dịch vụ. URL cần truy cập được từ Internet; POS gửi mã hóa đơn trong nội dung thanh toán để tự đối soát.</p>
                            </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <h3 class="text-sm font-bold text-slate-800">Cài đặt chung</h3>
                        <p class="text-xs text-slate-500">Áp dụng cho mọi tài khoản ngân hàng.</p>
                    </div>

                    <div class="grid items-start gap-4 lg:grid-cols-2">
                    <section class="rounded-lg bg-slate-50 p-3">
                        <p class="mb-1.5 text-sm font-semibold text-slate-700">Kiểu màu mã QR</p>
                        <p class="mb-3 text-xs text-slate-500">Chọn màu phù hợp với cửa hàng. Nền luôn sáng để mã dễ quét.</p>
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
                    </section>
                    <section class="rounded-lg bg-slate-50 p-3">
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
                    </section>
                    </div>

                </div>
            </section>
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
            <BackupsIndex hide-header :backups="backups" :cloud_connections="cloud_connections" :cloud_oauth="cloud_oauth" :schedule="schedule" :restore_preview="restore_preview" />
        </BaseModal>
        <BaseModal v-if="activeModal === 'cleanup' && can_edit_settings" title="Dọn dẹp hệ thống" size="xl" body-class="bg-slate-50 p-4 sm:p-5" @close="closeModal">
            <div class="space-y-4">
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><strong>Khu vực backup được bảo vệ:</strong> chức năng này không quét hoặc xóa file sao lưu. Chỉ ảnh sản phẩm, khách hàng, sửa chữa, avatar và ảnh IMEI không còn được dữ liệu sử dụng mới xuất hiện trong danh sách.</div>
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
                    <div class="max-h-[48vh] divide-y divide-slate-100 overflow-y-auto">
                        <label v-for="file in cleanupResult.files" :key="file.path" class="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-slate-50"><input v-model="selectedCleanupPaths" type="checkbox" :value="file.path" class="mt-1" /><span class="min-w-0 flex-1"><span class="block break-all font-mono text-xs text-slate-700">{{ file.path }}</span><span class="mt-1 block text-[11px] text-slate-400">{{ formatBytes(file.size) }} · {{ new Date(file.last_modified * 1000).toLocaleString() }}</span></span></label>
                    </div>
                </div>
                <div v-else-if="cleanupResult && !cleanupLoading" class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Không tìm thấy file mồ côi trong các thư mục được quét.</div>
                <section v-if="can_manage_backups" class="rounded-xl border border-red-200 bg-red-50/50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700"><Trash2 :size="19" /></span>
                            <div>
                                <h2 class="text-sm font-bold text-red-900">Xóa dữ liệu nghiệp vụ</h2>
                                <p class="mt-1 max-w-3xl text-xs leading-5 text-red-800">Hệ thống tạo bản sao lưu cứu hộ trước, sau đó xóa dữ liệu bán hàng, kho, khách hàng, sửa chữa, chi phí và tệp tải lên. Tài khoản, phân quyền, cấu hình và bản sao lưu được giữ lại.</p>
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
