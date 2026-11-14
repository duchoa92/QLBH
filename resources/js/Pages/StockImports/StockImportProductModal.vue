<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue'

import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { WARRANTY_UNIT_OPTIONS_PLURAL, formatWarranty } from '@/utils/warranty'
import UnitInput from '@/Components/UI/UnitInput.vue'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import { BrowserMultiFormatReader } from '@zxing/browser'
import { BarcodeFormat, DecodeHintType } from '@zxing/library'
import api from '@/Services/api'
import { filterByKeywords, highlightText } from '@/utils/searchHelper'
import { formatNumber, formatDateTime } from '@/utils/format'

import {
    Plus,
    Trash2,
    Pencil,
    Check,
    X,
    QrCode,
    Camera,
    CameraOff,
} from 'lucide-vue-next'

/* PROPS / EMITS */
const props = defineProps({
    item: { type: Object, required: true },
    customerImport: { type: Boolean, default: false },
    vatRate: { type: Number, default: 10 },
})
const emit = defineEmits(['close', 'completed'])

/* REFS */
const entryFormRef = ref(null)
const dropdownVariantRef = ref(null)
const videoRef = ref(null)
const scanning = ref(false)
const scanError = ref('')
const scanStatus = ref('')
const codeReader = new BrowserMultiFormatReader(new Map([
    [DecodeHintType.POSSIBLE_FORMATS, [
        BarcodeFormat.QR_CODE,
        BarcodeFormat.DATA_MATRIX,
        BarcodeFormat.PDF_417,
        BarcodeFormat.CODE_128,
        BarcodeFormat.CODE_39,
        BarcodeFormat.CODE_93,
        BarcodeFormat.ITF,
        BarcodeFormat.EAN_13,
        BarcodeFormat.EAN_8,
        BarcodeFormat.UPC_A,
        BarcodeFormat.UPC_E,
    ]],
    [DecodeHintType.TRY_HARDER, true],
]), { delayBetweenScanAttempts: 120, delayBetweenScanSuccess: 300 })
const scanBusy = ref(false)
const lastScanned = shallowRef({ value: '', at: 0 })
let scanSession = 0
let scanControls = null

const showVariantDropdown = ref(false)
const variantSearch = ref('')
const imeiLookup = ref(null)
const imeiLookupLoading = ref(false)
let imeiLookupTimeout = null
let imeiLookupRequest = 0

// Toggle mở rộng phần thông tin thiết bị và bảo hành nhà cung cấp.
const editingEntryIndex = ref(null)

/* MODAL STATE */
const entryModal = ref({
    mode: null,
    item: null,
    imei_input: '',
    variant_input: null,
    info_text_input: '',
    account_input: '',
    password_input: '',
    warranty_duration_value_input: null,
    warranty_duration_unit_input: 'months',
    customer_warranty_duration_value_input: null,
    customer_warranty_duration_unit_input: 'months',
    quantity_input: 1,
    cost_price_input: 0,
    sell_price_input: 0,
    price_unit_basis: 'base',
})
const entryError = ref('')
const warrantyUnitOptions = WARRANTY_UNIT_OPTIONS_PLURAL

const stopCamera = () => {
    scanSession += 1
    scanning.value = false
    scanBusy.value = false
    const stream = videoRef.value?.srcObject
    try {
        scanControls?.stop()
    } catch (error) {
        console.warn('Không thể dừng vòng quét IMEI:', error)
    }
    scanControls = null
    try {
        codeReader.reset()
    } catch (error) {
        console.warn('Không thể giải phóng reader IMEI:', error)
    }
    try {
        stream?.getTracks().forEach(track => track.stop())
    } catch (error) {
        console.warn('Không thể giải phóng video track:', error)
    }
    if (videoRef.value) {
        try {
            videoRef.value.pause()
        } catch (error) {
            console.warn('Không thể dừng phần xem trước camera:', error)
        }
        try {
            videoRef.value.srcObject = null
        } catch (error) {
            console.warn('Không thể xóa luồng camera khỏi phần xem trước:', error)
        }
    }
}

const startCamera = async () => {
    if (scanning.value) return
    const session = ++scanSession
    scanError.value = ''
    scanStatus.value = ''
    scanning.value = true
    await nextTick()

    try {
        if (!globalThis.isSecureContext || typeof globalThis.navigator?.mediaDevices?.getUserMedia !== 'function') {
            throw new Error('Trình duyệt chỉ cho phép quét camera trên HTTPS hoặc localhost. Hãy mở website bằng HTTPS (có thể cần bật SSL trong Laragon).')
        }

        // Request the rear camera directly; device enumeration is unavailable in some contexts.
        const controls = await codeReader.decodeFromConstraints({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
        }, videoRef.value, async (result) => {
            if (session !== scanSession || !result || scanBusy.value) return
            const value = result.getText().trim()
            const now = Date.now()
            if (!value || (value === lastScanned.value.value && now - lastScanned.value.at < 1600)) return

            lastScanned.value = { value, at: now }
            scanBusy.value = true
            entryModal.value.imei_input = value
            scanStatus.value = `Đã đọc mã: ${value}`
            try {
                if (entryModal.value.item?.type === 'imei_variant' && !entryModal.value.variant_input) {
                    entryError.value = 'Đã quét được IMEI. Hãy chọn biến thể rồi bấm Thêm.'
                    stopCamera()
                    return
                }
                await addEntry()
            } finally {
                scanBusy.value = false
            }
        })
        if (session !== scanSession) {
            controls.stop()
            codeReader.reset()
        } else {
            scanControls = controls
        }
    } catch (error) {
        if (session !== scanSession) return
        console.error('Không thể mở camera quét IMEI:', error)
        scanError.value = error?.message || 'Không thể mở camera. Hãy kiểm tra quyền truy cập camera.'
        stopCamera()
    }
}

/* HELPERS */
const formatPrice = formatNumber
const formatDate = (value) => value ? formatDateTime(value) : '-'
const vatRate = computed(() => Math.min(100, Math.max(0, Number(props.vatRate || 0))))
const vatEstimate = computed(() => {
    const item = entryModal.value.item
    if (!item) return 0

    const variantTotal = (item.rows || []).reduce(
        (sum, row) => sum + Math.max(0, Number(row.quantity || 0)) * Number(row.sell_price || 0),
        0
    )
    const imeiTotal = (item.imeis || []).reduce(
        (sum, row) => sum + Number(typeof row === 'string' ? item.sell_price : row.sell_price || item.sell_price || 0),
        0
    )
    if (entryModal.value.mode === 'normal') {
        return Math.round(Math.max(0, Number(entryModal.value.quantity_input || 0)) * Number(entryModal.value.sell_price_input || 0) * vatRate.value / 100)
    }

    const currentEntryTotal = editingEntryIndex.value === null && entryModal.value.mode === 'variant'
        ? Math.max(0, Number(entryModal.value.quantity_input || 0)) * Number(entryModal.value.sell_price_input || 0)
        : (editingEntryIndex.value === null && entryModal.value.mode === 'imei' ? Number(entryModal.value.sell_price_input || 0) : 0)

    return Math.round((variantTotal + imeiTotal + currentEntryTotal) * vatRate.value / 100)
})

const removeVietnameseTones = (value = '') =>
    String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')

const variantAttributes = (variant) => {
    if (!variant?.attributes) return []
    if (typeof variant.attributes === 'object' && !Array.isArray(variant.attributes)) {
        return Object.entries(variant.attributes).map(([key, value]) => ({ key, value }))
    }
    if (Array.isArray(variant.attributes)) {
        return variant.attributes.map((a, i) => ({
            key: a.name || a.key || `Attr ${i}`,
            value: a.value || a,
        }))
    }
    return []
}

const variantLabel = (variant) => {
    const attrs = variantAttributes(variant)
    return attrs.length ? attrs.map(a => a.value).join(' - ') : (variant?.sku || '')
}

const imeiVariantLabel = (row) => {
    if (typeof row === 'string') return '-'
    const variant = row?.variant || (entryModal.value.item?.available_variants || entryModal.value.item?.variants || [])
        .find(option => Number(option.id) === Number(row?.variant_id))
    return variant ? variantLabel(variant) : '-'
}

const buildExtraInfo = (modal) => {
    const customerWarrantyValue = Number(modal.customer_warranty_duration_value_input || 0)
    const extraInfo = {
        note: String(modal.info_text_input || '').trim(),
        account: String(modal.account_input || '').trim(),
        password: String(modal.password_input || '').trim(),
        customer_warranty_duration_value: customerWarrantyValue > 0 ? customerWarrantyValue : '',
        customer_warranty_duration_unit: customerWarrantyValue > 0 ? modal.customer_warranty_duration_unit_input : '',
    }
    const cleaned = Object.fromEntries(Object.entries(extraInfo).filter(([, value]) => value !== ''))
    return Object.keys(cleaned).length ? cleaned : null
}

const hasExtraInfo = (row) => {
    const extraInfo = row?.extra_info || {}
    return Boolean(extraInfo.note)
}

const hasAnyImeiExtraInfo = computed(() =>
    (entryModal.value.item?.imeis || []).some(row => typeof row !== 'string' && hasExtraInfo(row))
)

const lookupImei = async (imei) => {
    const value = String(imei || '').trim()
    const requestId = ++imeiLookupRequest
    if (!value) {
        imeiLookup.value = null
        return null
    }
    imeiLookupLoading.value = true
    try {
        const response = await api.get('/api/imeis/lookup', { params: { imei: value } })
        if (requestId === imeiLookupRequest) imeiLookup.value = response.data
        return response.data
    } catch (error) {
        console.error('Lỗi tra cứu IMEI:', error)
        if (requestId === imeiLookupRequest) imeiLookup.value = null
        return null
    } finally {
        if (requestId === imeiLookupRequest) imeiLookupLoading.value = false
    }
}

watch(() => entryModal.value.imei_input, (value) => {
    clearTimeout(imeiLookupTimeout)
    const imei = String(value || '').trim()
    imeiLookupRequest++
    imeiLookup.value = null
    imeiLookupLoading.value = false
    entryError.value = ''

    if (entryModal.value.mode !== 'imei' || imei.length < 4) return

    // Đang sửa đúng dòng này mà không đổi IMEI: không tra cứu kho để khỏi báo "đang còn trong kho"
    if (editingEntryIndex.value !== null) {
        const original = entryModal.value.item?.imeis?.[editingEntryIndex.value]
        const originalImei = String((typeof original === 'string' ? original : original?.imei) || '').trim()
        if (originalImei && originalImei.toLowerCase() === imei.toLowerCase()) return
    }

    imeiLookupTimeout = setTimeout(() => {
        lookupImei(imei)
    }, 350)
})

/* VARIANT SEARCH */
const filteredVariants = computed(() => {
    const variants = entryModal.value.item?.available_variants || entryModal.value.item?.variants || []
    return filterByKeywords(variants, variantSearch.value, variantLabel)
})

/* SELECT VARIANT */
const selectEntryVariant = () => {
    const modal = entryModal.value
    const item = modal.item
    if (!item) return

    const variants = item.available_variants || item.variants || []
    let variant = variants.find(v => Number(v.id) === Number(modal.variant_input))

    if (!variant && variantSearch.value) {
        const searchNormalized = removeVietnameseTones(variantSearch.value)
        const keywords = searchNormalized.split(/\s+/).filter(Boolean)
        variant = variants.find(v => {
            const labelNormalized = removeVietnameseTones(variantLabel(v))
            return keywords.every(keyword => labelNormalized.includes(keyword))
        })
    }

    if (!variant) return

    modal.variant_input = variant.id
    const factor = item.has_unit_conversion ? Math.max(1, Number(item.conversion_factor || 1)) : 1
    const priceFactor = modal.price_unit_basis === 'conversion' ? factor : 1
    const rawCost = variant.cost_price ?? variant.import_price
    const rawSell = variant.sell_price ?? variant.retail_price ?? variant.price
    const existingRow = (item.rows || []).find(row => Number(row.variant_id) === Number(variant.id))
    const existingImeiRow = (item.imeis || []).find(row => Number(row?.variant_id) === Number(variant.id))
    const savedPrice = existingRow || existingImeiRow
    const cost = !(Number(rawCost) > 0)
        ? Number(item.cost_price || 0)
        : Number(rawCost) * priceFactor
    const sell = !(Number(rawSell) > 0)
        ? Number(item.sell_price || 0)
        : Number(rawSell) * priceFactor

    entryModal.value.cost_price_input = savedPrice?.cost_price != null
        ? Number(savedPrice.cost_price)
        : cost
    entryModal.value.sell_price_input = savedPrice?.sell_price != null
        ? Number(savedPrice.sell_price)
        : sell

    // Biến thể có thể có BH mặc định riêng từ lần nhập gần nhất.
    if (!savedPrice && variant.last_customer_warranty?.value > 0) {
        entryModal.value.customer_warranty_duration_value_input = variant.last_customer_warranty.value
        entryModal.value.customer_warranty_duration_unit_input = variant.last_customer_warranty.unit || 'months'
    }
}

const selectVariant = (variant) => {
    if (!variant) return
    entryModal.value.variant_input = variant.id
    variantSearch.value = variantLabel(variant)
    showVariantDropdown.value = false
    nextTick(() => { selectEntryVariant() })
}

const selectScannedVariant = () => {
    const code = String(variantSearch.value || '').trim().toLocaleLowerCase()
    if (!code) return

    const variants = entryModal.value.item?.available_variants || entryModal.value.item?.variants || []
    const match = variants.find(variant => [variant.barcode, variant.sku]
        .some(value => String(value || '').trim().toLocaleLowerCase() === code))

    if (match) selectVariant(match)
}

const appendScannedInfo = (code) => {
    const value = String(code || '').trim()
    if (!value) return
    const current = String(entryModal.value.info_text_input || '').trim()
    if (current.split(/\r?\n/).some(line => line.trim() === value)) return
    entryModal.value.info_text_input = current ? `${current}\n${value}` : value
}

watch(variantSearch, () => { if (entryModal.value.item) selectEntryVariant() })
watch(() => entryModal.value.variant_input, () => { if (entryModal.value.item) selectEntryVariant() })

/* OPEN / RESET MODAL */
const openEntryModal = (item) => {
    item.extra_info = item.extra_info && typeof item.extra_info === 'object' ? item.extra_info : {}
    let mode = 'normal'
    if (item.type === 'imei' || item.type === 'imei_variant') mode = 'imei'
    else if (item.type === 'variant') mode = 'variant'

    const variants = item.available_variants || item.variants || []
    const defaultVariant = mode === 'variant' && variants.length ? variants[0] : null
    const factor = item.has_unit_conversion ? Math.max(1, Number(item.conversion_factor || 1)) : 1
    const priceUnitBasis = item.price_unit_basis || (item.has_unit_conversion ? 'conversion' : 'base')
    item.price_unit_basis = priceUnitBasis
    const rawVariantCost = defaultVariant?.cost_price ?? defaultVariant?.import_price
    const rawVariantSell = defaultVariant?.sell_price ?? defaultVariant?.retail_price ?? defaultVariant?.price
    const defaultCost = defaultVariant
        ? (!(Number(rawVariantCost) > 0) ? Number(item.cost_price || 0) : Number(rawVariantCost) * (priceUnitBasis === 'conversion' ? factor : 1))
        : Number(item.cost_price ?? item.import_price ?? 0)
    const defaultSell = defaultVariant
        ? (!(Number(rawVariantSell) > 0) ? Number(item.sell_price || 0) : Number(rawVariantSell) * (priceUnitBasis === 'conversion' ? factor : 1))
        : Number(item.sell_price ?? item.price ?? 0)

    entryModal.value = {
        mode,
        item,
        imei_input: '',
        variant_input: defaultVariant?.id ?? null,
        info_text_input: item.extra_info.note || '',
        account_input: item.extra_info.account || '',
        password_input: item.extra_info.password || '',
        warranty_duration_value_input: item.supplier_warranty_value ?? null,
        warranty_duration_unit_input: item.supplier_warranty_unit || 'months',
        customer_warranty_duration_value_input: item.extra_info.customer_warranty_duration_value ?? null,
        customer_warranty_duration_unit_input: item.extra_info.customer_warranty_duration_unit || 'months',
        quantity_input: 1,
        cost_price_input: defaultCost,
        sell_price_input: defaultSell,
        price_unit_basis: priceUnitBasis,
    }

    variantSearch.value = ''
    showVariantDropdown.value = false
    editingEntryIndex.value = null
    if (defaultVariant) nextTick(selectEntryVariant)
}

const resetEntryModal = () => {
    entryModal.value = {
        mode: null,
        item: null,
        imei_input: '',
        variant_input: null,
        info_text_input: '',
        account_input: '',
        password_input: '',
        warranty_duration_value_input: null,
        warranty_duration_unit_input: 'months',
        customer_warranty_duration_value_input: null,
        customer_warranty_duration_unit_input: 'months',
        quantity_input: 1,
        cost_price_input: 0,
        sell_price_input: 0,
        price_unit_basis: 'base',
    }
    variantSearch.value = ''
    showVariantDropdown.value = false
    editingEntryIndex.value = null
}

const changePriceUnitBasis = (basis) => {
    const modal = entryModal.value
    const item = modal.item
    if (!item || modal.price_unit_basis === basis) return
    const factor = item.has_unit_conversion ? Math.max(1, Number(item.conversion_factor || 1)) : 1
    const multiplier = basis === 'base' ? 1 / factor : factor
    const scale = (record) => {
        if (!record || typeof record !== 'object') return
        record.cost_price = Number(record.cost_price || 0) * multiplier
        record.sell_price = Number(record.sell_price || 0) * multiplier
    }
    scale(item)
    ;(item.rows || []).forEach(scale)
    ;(item.imeis || []).forEach(scale)
    modal.cost_price_input = Number(modal.cost_price_input || 0) * multiplier
    modal.sell_price_input = Number(modal.sell_price_input || 0) * multiplier
    modal.price_unit_basis = basis
    item.price_unit_basis = basis
}

const resetEntryInputs = (keepPrices = false, keepVariant = false) => {
    const modal = entryModal.value
    const item = modal.item
    if (!item) return

    const costPrice = modal.cost_price_input
    const sellPrice = modal.sell_price_input
    const variantId = keepVariant ? modal.variant_input : null
    const selectedVariant = keepVariant ? variantSearch.value : ''
    modal.imei_input = ''
    modal.quantity_input = 1
    modal.variant_input = variantId
    modal.info_text_input = ''
    modal.account_input = ''
    modal.password_input = ''
    modal.warranty_duration_value_input = item.supplier_warranty_value ?? null
    modal.warranty_duration_unit_input = item.supplier_warranty_unit || 'months'
    modal.customer_warranty_duration_value_input = item.extra_info?.customer_warranty_duration_value ?? null
    modal.customer_warranty_duration_unit_input = item.extra_info?.customer_warranty_duration_unit || 'months'
    editingEntryIndex.value = null
    imeiLookup.value = null
    variantSearch.value = selectedVariant
    showVariantDropdown.value = false

    modal.cost_price_input = keepPrices ? costPrice : Number(item.cost_price ?? item.import_price ?? 0)
    modal.sell_price_input = keepPrices ? sellPrice : Number(item.sell_price ?? item.price ?? 0)
    entryError.value = ''

}

// copy = true: bấm vào dòng để sao chép dữ liệu sang IMEI mới (không sửa dòng cũ); copy = false: sửa chính dòng đó
const selectImeiEntry = (index, copy = false) => {
    const row = entryModal.value.item?.imeis?.[index]
    if (!row) return
    editingEntryIndex.value = copy ? null : index
    entryError.value = ''
    entryModal.value.imei_input = copy ? '' : (typeof row === 'string' ? row : (row.imei || ''))
    entryModal.value.variant_input = typeof row === 'string' ? null : (row.variant_id || null)
    entryModal.value.info_text_input = row.extra_info?.note || ''
    entryModal.value.account_input = row.extra_info?.account || ''
    entryModal.value.password_input = row.extra_info?.password || ''
    entryModal.value.customer_warranty_duration_value_input = row.extra_info?.customer_warranty_duration_value || null
    entryModal.value.customer_warranty_duration_unit_input = row.extra_info?.customer_warranty_duration_unit || 'months'
    entryModal.value.warranty_duration_value_input = row.warranty_duration_value || null
    entryModal.value.warranty_duration_unit_input = row.warranty_duration_unit || 'months'
    imeiLookup.value = null
    const variant = (entryModal.value.item.available_variants || entryModal.value.item.variants || []).find((option) => Number(option.id) === Number(row.variant_id))
    variantSearch.value = variant ? variantLabel(variant) : ''
    nextTick(() => {
        entryModal.value.cost_price_input = Number(row.cost_price ?? entryModal.value.item.cost_price ?? 0)
        entryModal.value.sell_price_input = Number(row.sell_price ?? entryModal.value.item.sell_price ?? 0)
        entryFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        if (copy) document.getElementById('stock-import-imei-input')?.focus()
    })
}

// Bảo hành của một dòng biến thể (nhà cung cấp / khách), lưu riêng từng dòng như IMEI
const variantRowWarranty = (modal) => {
    const supplierValue = props.customerImport ? 0 : Number(modal.warranty_duration_value_input || 0)
    const customerValue = Number(modal.customer_warranty_duration_value_input || 0)
    return {
        supplier_warranty_value: supplierValue > 0 ? supplierValue : null,
        supplier_warranty_unit: modal.warranty_duration_unit_input || 'months',
        customer_warranty_value: customerValue > 0 ? customerValue : null,
        customer_warranty_unit: modal.customer_warranty_duration_unit_input || 'months',
    }
}

const sameRowWarranty = (row, warranty) =>
    Number(row.supplier_warranty_value || 0) === Number(warranty.supplier_warranty_value || 0)
    && Number(row.customer_warranty_value || 0) === Number(warranty.customer_warranty_value || 0)
    && (!warranty.supplier_warranty_value || row.supplier_warranty_unit === warranty.supplier_warranty_unit)
    && (!warranty.customer_warranty_value || row.customer_warranty_unit === warranty.customer_warranty_unit)

const rowWarrantyLines = (row) => {
    const lines = []
    if (!props.customerImport && Number(row.supplier_warranty_value) > 0) lines.push(`NCC: ${formatWarranty(row.supplier_warranty_value, row.supplier_warranty_unit)}`)
    if (Number(row.customer_warranty_value) > 0) lines.push(`Khách: ${formatWarranty(row.customer_warranty_value, row.customer_warranty_unit)}`)
    return lines
}

// copy = true: bấm vào dòng để sao chép dữ liệu thành dòng mới; copy = false: sửa chính dòng đó
const selectVariantEntry = (index, copy = false) => {
    const row = entryModal.value.item?.rows?.[index]
    if (!row) return
    editingEntryIndex.value = copy ? null : index
    entryError.value = ''
    entryModal.value.variant_input = row.variant_id
    entryModal.value.quantity_input = Number(row.quantity || 1)
    entryModal.value.warranty_duration_value_input = row.supplier_warranty_value || null
    entryModal.value.warranty_duration_unit_input = row.supplier_warranty_unit || 'months'
    entryModal.value.customer_warranty_duration_value_input = row.customer_warranty_value || null
    entryModal.value.customer_warranty_duration_unit_input = row.customer_warranty_unit || 'months'
    variantSearch.value = variantLabel(row.variant || (entryModal.value.item.available_variants || []).find((option) => Number(option.id) === Number(row.variant_id)))
    showVariantDropdown.value = false
    nextTick(() => {
        entryModal.value.cost_price_input = Number(row.cost_price || 0)
        entryModal.value.sell_price_input = Number(row.sell_price || 0)
        entryFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    })
}

/* ADD / REMOVE ENTRY */
const addEntry = async () => {
    const modal = entryModal.value
    const item = modal.item
    if (!item) return false

    if (modal.mode === 'variant') {
        const qty = Number(modal.quantity_input || 0)
        if (!qty) return false
        const variants = item.available_variants || item.variants || []
        const variant = variants.find(v => Number(v.id) === Number(modal.variant_input))
        if (!variant) return false

        if (!item.rows) item.rows = []
        if (editingEntryIndex.value !== null) {
            item.rows[editingEntryIndex.value] = {
                ...item.rows[editingEntryIndex.value],
                variant_id: variant.id,
                variant,
                quantity: qty,
                cost_price: Number(modal.cost_price_input),
                sell_price: Number(modal.sell_price_input),
                ...variantRowWarranty(modal),
            }
            resetEntryInputs()
            return true
        }

        // Không gộp hai lô cùng biến thể nếu giá nhập/giá bán khác nhau.
        // Mỗi giá là một lô tồn riêng để xuất FIFO và tính lãi chính xác.
        const rowWarranty = variantRowWarranty(modal)
        const exist = item.rows.find(row =>
            Number(row.variant_id) === Number(variant.id)
            && Number(row.cost_price) === Number(modal.cost_price_input)
            && Number(row.sell_price) === Number(modal.sell_price_input)
            && sameRowWarranty(row, rowWarranty)
        )

        if (exist) {
            exist.quantity += qty
            exist.cost_price = Number(modal.cost_price_input)
            exist.sell_price = Number(modal.sell_price_input)
        } else {
            item.rows.push({
                variant_id: variant.id,
                variant,
                quantity: qty,
                cost_price: Number(modal.cost_price_input),
                sell_price: Number(modal.sell_price_input),
                ...rowWarranty,
            })
        }
        resetEntryInputs()
        return true
    }

    if (modal.mode === 'imei') {
        const imei = String(modal.imei_input || '').trim()
        if (!imei) {
            entryError.value = 'Vui lòng nhập IMEI / Serial.'
            return false
        }
        if (item.type === 'imei_variant' && !modal.variant_input) {
            entryError.value = 'Vui lòng chọn biến thể cho IMEI này.'
            return false
        }
        if (!item.imeis) item.imeis = []
        const duplicate = item.imeis.some((row, index) => {
            const value = typeof row === 'string' ? row : row.imei
            return index !== editingEntryIndex.value && String(value).toLowerCase() === imei.toLowerCase()
        })
        if (duplicate) {
            entryError.value = 'IMEI / Serial đã có trong danh sách nhập.'
            return false
        }

        entryError.value = ''
        imeiLookupLoading.value = true
        try {
            const original = editingEntryIndex.value !== null ? item.imeis[editingEntryIndex.value] : null
            const originalImei = original ? String(typeof original === 'string' ? original : original.imei || '') : ''
            const unchanged = originalImei !== '' && originalImei.toLowerCase() === imei.toLowerCase()
            const lookup = unchanged ? null : await lookupImei(imei)
            if (lookup && !lookup.can_import) {
                entryError.value = lookup.message || `IMEI ${imei} không thể nhập.`
                return false
            }
        } finally {
            imeiLookupLoading.value = false
            imeiLookup.value = null
        }

        const variant = (item.available_variants || item.variants || []).find(option => Number(option.id) === Number(modal.variant_input))
        const nextRow = {
            imei,
            cost_price: Number(modal.cost_price_input),
            sell_price: Number(modal.sell_price_input),
            variant_id: item.type === 'imei_variant' ? modal.variant_input : null,
            variant: item.type === 'imei_variant' ? variant : null,
            extra_info: buildExtraInfo(modal),
            warranty_duration_value: props.customerImport ? null : (modal.warranty_duration_value_input || null),
            warranty_duration_unit: props.customerImport ? null : (modal.warranty_duration_unit_input || 'months'),
        }
        if (editingEntryIndex.value !== null) {
            item.imeis[editingEntryIndex.value] = nextRow
        } else {
            item.imeis.push(nextRow)
        }
        resetEntryInputs(true, item.type === 'imei_variant')
        nextTick(() => document.getElementById('stock-import-imei-input')?.focus())
        return true
    }
}

const parseMoneyInput = (value) => {
    const digits = String(value ?? '').replace(/[^\d]/g, '')
    return digits ? Number(digits) : 0
}
const updateRowMoney = (row, key, event) => { row[key] = parseMoneyInput(event.target.value) }

const complete = async () => {
    if (entryModal.value.mode === 'imei') {
        if (entryModal.value.imei_input.trim()) {
            const added = await addEntry()
            if (!added) return
        }
        if (!props.item.imeis?.length) {
            entryError.value = 'Vui lòng thêm ít nhất một IMEI / Serial trước khi hoàn tất.'
            return
        }
    }

    if (entryModal.value.mode === 'variant' && !props.item.rows?.length) {
        entryError.value = 'Vui lòng thêm ít nhất một biến thể trước khi hoàn tất.'
        return
    }

    if (['normal', 'variant'].includes(entryModal.value.mode)) {
        props.item.supplier_warranty_value = props.customerImport ? null : (entryModal.value.warranty_duration_value_input || null)
        props.item.supplier_warranty_unit = props.customerImport ? null : (entryModal.value.warranty_duration_unit_input || 'months')
        props.item.extra_info = buildExtraInfo(entryModal.value)
    }
    stopCamera()
    emit('completed', props.item)
    emit('close')
}

const removeImei = (item, index) => {
    item.imeis.splice(index, 1)
    if (editingEntryIndex.value === index) resetEntryInputs()
    else if (editingEntryIndex.value > index) editingEntryIndex.value -= 1
}
const removeVariantRow = (item, index) => {
    item.rows.splice(index, 1)
    if (editingEntryIndex.value === index) resetEntryInputs()
    else if (editingEntryIndex.value > index) editingEntryIndex.value -= 1
}

const sellPriceLabel = (item) => {
    const unit = entryModal.value.price_unit_basis === 'base'
        ? (item?.base_unit_name || 'đơn vị cơ sở')
        : (item?.unit || 'đơn vị nhập')
    return `Giá bán / ${unit}`
}

const priceBasisLabel = (basis, item) => basis === 'base'
    ? (item?.base_unit_name || 'đơn vị cơ sở')
    : (item?.unit || 'đơn vị nhập')
const costPriceLabel = (item) => `Giá nhập / ${priceBasisLabel(entryModal.value.price_unit_basis, item)}`

const handleClickOutside = (event) => {
    if (dropdownVariantRef.value && dropdownVariantRef.value.contains(event.target)) return
    showVariantDropdown.value = false
}

const close = () => { stopCamera(); resetEntryModal(); emit('close') }

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    openEntryModal(props.item)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
    clearTimeout(imeiLookupTimeout)
    imeiLookupRequest++
    stopCamera()
})
</script>

<template>
    <BaseModal
        :title="
            entryModal.mode === 'variant'
                ? `Cấu hình biến thể - ${entryModal.item?.name || ''}`
                : entryModal.mode === 'imei'
                    ? `Cấu hình IMEI - ${entryModal.item?.name || ''}`
                    : `Cấu hình sản phẩm - ${entryModal.item?.name || ''}`
        "
        size="xl"
        body-class="p-3 sm:p-4"
        footer-class="py-1.5"
        @close="close"
    >
        <div class="flex flex-col gap-3">
            <!-- Đặt kết quả IMEI lên đầu để người nhập thấy ngay trạng thái trước khi khai báo dòng hàng. -->
            <div
                v-if="entryModal.mode === 'imei' && imeiLookup?.can_import === false"
                class="rounded-xl border p-3 text-xs"
                :class="imeiLookup?.can_import === false ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-bold text-sm">{{ imeiLookup.message }}</div>
                        <div v-if="imeiLookup?.imei" class="mt-1">
                            Sản phẩm hiện lưu: <span class="font-semibold">{{ imeiLookup.imei.product?.name || '-' }}</span>
                            <span v-if="imeiLookup.imei.status" class="ml-2">Trạng thái: {{ imeiLookup.imei.status === 'in_stock' ? 'Còn trong kho' : 'Đã bán/rời kho' }}</span>
                        </div>
                    </div>
                    <div v-if="imeiLookup?.summary" class="shrink-0 text-right font-semibold">
                        <div>{{ imeiLookup.summary.import_count }} lần nhập | {{ imeiLookup.summary.sale_count }} lần bán</div>
                    </div>
                </div>
            </div>
            <div v-if="entryError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                {{ entryError }}
            </div>

            <!-- KHU VỰC 1: KHUNG DỰT FORM NHẬP TẬP TRUNG (CONTROL PANEL) -->
            <div ref="entryFormRef" class="order-1 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div v-if="scanning" class="relative mb-3 overflow-hidden rounded-lg bg-slate-950">
                    <video ref="videoRef" autoplay muted playsinline class="h-44 w-full object-cover sm:h-52" />
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="flex aspect-[1.7/1] w-[72%] items-center justify-center rounded-lg border border-white/80 shadow-[0_0_0_999px_rgba(15,23,42,0.24)]">
                            <span class="rounded bg-slate-950/60 px-2 py-1 text-[11px] font-medium text-white">Đưa mã IMEI vào khung quét</span>
                        </div>
                    </div>
                    <div class="absolute left-2 top-2 rounded bg-slate-950/65 px-2 py-1 text-[11px] text-white">
                        {{ scanBusy ? `Đã đọc ${entryModal.imei_input}, đang thêm...` : (scanStatus || 'Đưa mã vào khung để quét') }}
                    </div>
                    <button
                        type="button"
                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-md bg-slate-950/65 text-white hover:bg-rose-600"
                        title="Tắt camera"
                        aria-label="Tắt camera"
                        @click="stopCamera"
                    >
                        <CameraOff class="h-4 w-4" />
                    </button>
                </div>
                <div v-if="entryModal.item?.has_unit_conversion && Number(entryModal.item.conversion_factor || 1) > 1" class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                    <span class="font-semibold text-slate-700">Nhập giá theo:</span>
                    <label v-for="basis in ['base', 'conversion']" :key="basis" class="inline-flex cursor-pointer items-center gap-1.5">
                        <input type="radio" name="stock-import-price-basis" :checked="entryModal.price_unit_basis === basis" :value="basis" class="text-emerald-600 focus:ring-emerald-500" @change="changePriceUnitBasis(basis)" />
                        {{ priceBasisLabel(basis, entryModal.item) }}
                    </label>
                </div>
                <div
                    class="flex flex-wrap items-end gap-2.5 [&>*]:min-w-0 [&>*]:flex-[1_1_145px]"
                >

                    <!-- INPUT: SẢN PHẨM THƯỜNG -->
                    <div v-if="entryModal.mode === 'normal'" class="min-w-0">
                        <FloatingInput
                            v-model.number="entryModal.item.quantity"
                            type="number"
                            min="1"
                            label="Số lượng nhập"
                        />
                    </div>

                    <!-- IMEI / SERIAL -->
                    <div v-if="entryModal.mode === 'imei'" class="min-w-0" :class="entryModal.item?.type === 'imei_variant' ? 'md:!basis-[calc(33.333%-0.5rem)]' : ''">
                        <div class="relative">
                            <input
                                id="stock-import-imei-input"
                                v-model="entryModal.imei_input"
                                type="text"
                                autocomplete="off"
                                placeholder=" "
                                class="peer w-full rounded-lg border border-gray-300 px-2 pb-2 pt-2.5 pr-10 text-sm transition-all focus:border-blue-500 focus:outline-none focus:ring-0"
                                @keydown.enter.prevent="addEntry"
                            />
                            <label
                                for="stock-import-imei-input"
                                class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500 transition-all peer-focus:text-blue-600"
                            >IMEI / Serial</label>
                            <button
                                type="button"
                                class="absolute right-1 top-1 flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                :aria-label="scanning ? 'Tắt camera' : 'Bật camera quét IMEI'"
                                :title="scanning ? 'Tắt camera' : 'Bật camera quét IMEI'"
                                @click.stop="scanning ? stopCamera() : startCamera()"
                            >
                                <CameraOff v-if="scanning" class="h-3.5 w-3.5" />
                                <Camera v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- INPUT: BIẾN THỂ -->
                    <div v-if="entryModal.mode === 'variant' || entryModal.item?.type === 'imei_variant'" class="min-w-0" :class="entryModal.item?.type === 'imei_variant' ? 'md:!basis-[calc(33.333%-0.5rem)]' : ''">
                        <div ref="dropdownVariantRef" class="relative w-full">
                            <FloatingInput
                                v-model="variantSearch"
                                type="text"
                                label="Biến thể"
                                @focus="showVariantDropdown = true; $event.target.select()"
                                @keydown.enter.prevent="selectScannedVariant"
                            />
                            <div
                                v-if="showVariantDropdown"
                                class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                            >
                                <div
                                    v-for="variant in filteredVariants"
                                    :key="variant.id"
                                    class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-blue-50 hover:text-blue-600"
                                    @click="selectVariant(variant)"
                                >
                                    <span v-html="highlightText(variantLabel(variant), variantSearch)" />
                                </div>
                                <div v-if="!filteredVariants.length" class="px-3 py-2 text-center text-xs text-slate-400">
                                    Không tìm thấy
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SỐ LƯỢNG BIẾN THỂ -->
                    <div v-if="entryModal.mode === 'variant'" class="min-w-0">
                        <FloatingInput
                            v-model.number="entryModal.quantity_input"
                            type="number"
                            min="1"
                            label="Số lượng"
                        />
                    </div>

                    <!-- GIÁ NHẬP -->
                    <div class="min-w-0" :class="entryModal.item?.type === 'imei_variant' ? 'md:!basis-[calc(16.666%-0.5rem)]' : ''">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            :model-value="formatPrice(entryModal.cost_price_input)"
                            type="text"
                            inputmode="numeric"
                            :label="costPriceLabel(entryModal.item)"
                            @focus="$event.target.select()"
                            @update:model-value="entryModal.cost_price_input = parseMoneyInput($event)"
                        />
                        <FloatingInput
                            v-else
                            :model-value="formatPrice(entryModal.item.cost_price)"
                            type="text"
                            inputmode="numeric"
                            :label="costPriceLabel(entryModal.item)"
                            @update:model-value="entryModal.item.cost_price = parseMoneyInput($event)"
                        />
                    </div>

                    <!-- GIÁ BÁN -->
                    <div class="min-w-0" :class="entryModal.item?.type === 'imei_variant' ? 'md:!basis-[calc(16.666%-0.5rem)]' : ''">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            :model-value="formatPrice(entryModal.sell_price_input)"
                            type="text"
                            inputmode="numeric"
                            :label="sellPriceLabel(entryModal.item)"
                            @focus="$event.target.select()"
                            @update:model-value="entryModal.sell_price_input = parseMoneyInput($event)"
                        />
                        <FloatingInput
                            v-else
                            :model-value="formatPrice(entryModal.item.sell_price)"
                            type="text"
                            inputmode="numeric"
                            :label="sellPriceLabel(entryModal.item)"
                            @update:model-value="entryModal.item.sell_price = parseMoneyInput($event)"
                        />
                    </div>

                    <UnitInput v-if="!props.customerImport" stacked-label :class="entryModal.item?.type === 'imei_variant' ? 'md:!basis-[calc(33.333%-0.5rem)]' : ''"
                        v-model="entryModal.warranty_duration_value_input"
                        v-model:unit="entryModal.warranty_duration_unit_input"
                        :unit-options="warrantyUnitOptions"
                        type="number" min="0"
                        label="BH nhà cung cấp" unit-label="Đơn vị bảo hành nhà cung cấp"
                    />

                    <UnitInput stacked-label :class="entryModal.item?.type === 'imei_variant' ? 'md:!basis-[calc(33.333%-0.5rem)]' : ''"
                        v-model="entryModal.customer_warranty_duration_value_input"
                        v-model:unit="entryModal.customer_warranty_duration_unit_input"
                        :unit-options="warrantyUnitOptions"
                        type="number" min="0"
                        label="BH khách hàng" unit-label="Đơn vị bảo hành khách hàng"
                    />

                    <div v-if="entryModal.mode === 'imei' || props.customerImport" class="min-w-0 max-md:!basis-full" :class="entryModal.item?.type === 'imei_variant' ? (props.customerImport ? 'md:!basis-[calc(66.666%-0.5rem)]' : 'md:!basis-[calc(33.333%-0.5rem)]') : ''">
                        <label class="mb-0.5 block truncate text-[11px] font-medium text-slate-500">Thông tin thiết bị</label>
                        <div class="relative">
                            <FloatingInput
                                v-model="entryModal.info_text_input"
                                label="Thông tin thiết bị"
                                :floating-label="false"
                                placeholder-text="QR, service code, ghi chú..."
                                placeholder-class="right-11 overflow-hidden text-ellipsis whitespace-nowrap"
                                input-class="pr-12 !pt-2"
                            />
                            <BarcodeCameraButton
                                class="absolute right-1 top-1/2 z-10 -translate-y-1/2"
                                @scanned="appendScannedInfo"
                            />
                        </div>
                    </div>

                <div v-if="entryModal.item" class="contents">
                    <label class="inline-flex min-h-9 w-fit min-w-0 cursor-pointer items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800">
                        <input v-model="entryModal.item.is_used" type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500" @change="entryModal.item.is_used && (entryModal.item.has_vat = false)" />
                        Sản phẩm cũ
                    </label>
                    <label class="inline-flex min-h-9 w-fit min-w-0 items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-800" :class="entryModal.item.is_used ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'" :title="entryModal.item.is_used ? 'Sản phẩm cũ không áp dụng VAT' : ''">
                        <input v-model="entryModal.item.has_vat" :disabled="entryModal.item.is_used" type="checkbox" class="h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                        VAT
                    </label>
                    <div v-if="entryModal.item.has_vat" class="max-w-full rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs">
                        <span class="font-bold text-amber-800">Thuế dự kiến: {{ formatPrice(vatEstimate) }} {{ $currency() }}</span>
                        <span class="ml-1 text-amber-700">(giá bán chưa bao gồm thuế)</span>
                    </div>
                </div>
                </div>

                <p v-if="scanError" class="mt-2 text-xs font-medium text-rose-600">{{ scanError }}</p>

                <div v-if="entryModal.item && entryModal.mode !== 'normal'" class="mt-3 flex items-center justify-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none sm:min-w-[220px] sm:px-6"
                        :class="editingEntryIndex !== null ? 'bg-blue-600 hover:bg-blue-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                        :disabled="imeiLookupLoading"
                        @click="addEntry"
                    >
                        <Check v-if="editingEntryIndex !== null" class="h-4 w-4 shrink-0" />
                        <Plus v-else class="h-4 w-4 shrink-0" />
                        {{ editingEntryIndex !== null ? 'Cập nhật' : 'Thêm vào đơn' }}
                    </button>
                    <button
                        v-if="editingEntryIndex !== null"
                        type="button"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:min-w-[120px] sm:px-4"
                        @click="resetEntryInputs"
                    >
                        <X class="h-4 w-4" />
                        Hủy sửa
                    </button>
                </div>
            </div>

            <!-- KHU VỰC 2: DANH SÁCH BẢNG HIỂN THỊ (LIST ZONE) -->

            <!-- BẢNG IMEI -->
            <div v-if="entryModal.mode === 'imei'" class="order-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5 sm:p-3">
                <div v-if="entryModal.item?.imeis?.length" class="mb-2 flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold text-slate-800">IMEI đã thêm</h3>
                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">{{ entryModal.item.imeis.length }}</span>
                    </div>
                    <span class="text-[10px] text-slate-500">Bấm dòng để sao chép, bấm ✎ để sửa</span>
                </div>
                <div v-if="entryModal.item?.imeis?.length" class="hidden max-h-60 overflow-y-auto overflow-x-hidden rounded-lg border border-slate-200 bg-white md:block">
                    <table class="w-full table-fixed text-xs">
                        <thead class="bg-slate-50 font-semibold text-slate-600 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2.5 text-left">IMEI / Serial</th>
                                <th v-if="entryModal.item?.type === 'imei_variant'" class="w-48 px-3 py-2.5 text-left">Biến thể</th>
                                <th class="w-28 px-2 py-2.5 text-right">Giá nhập</th>
                                <th class="w-28 px-2 py-2.5 text-right">Giá bán</th>
                                <th class="w-28 px-2 py-2.5 text-left">Bảo hành</th>
                                <th v-if="hasAnyImeiExtraInfo" class="px-3 py-2.5 text-left">Thông tin thêm</th>
                                <th class="w-10 px-2 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in entryModal.item.imeis" :key="index" class="cursor-pointer hover:bg-blue-50/70" :class="editingEntryIndex === index ? 'bg-blue-50 ring-1 ring-inset ring-blue-300' : ''" @click="selectImeiEntry(index, true)">
                                <td class="px-3 py-2 font-mono font-medium text-slate-800">
                                    {{ typeof row === 'string' ? row : row.imei }}
                                </td>
                                <td v-if="entryModal.item?.type === 'imei_variant'" class="px-3 py-2">
                                    <span class="block whitespace-normal break-words font-medium leading-snug text-slate-700" :title="imeiVariantLabel(row)">
                                        {{ imeiVariantLabel(row) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input
                                        v-if="typeof row !== 'string'"
                                        :value="formatPrice(row.cost_price)"
                                        type="text"
                                        inputmode="numeric"
                                        class="h-7 w-full max-w-[6.5rem] rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        @input="updateRowMoney(row, 'cost_price', $event)"
                                        @click.stop
                                    />
                                    <span v-else class="text-slate-700">{{ formatPrice(entryModal.item.cost_price) }} {{ $currency() }}</span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input
                                        v-if="typeof row !== 'string'"
                                        :value="formatPrice(row.sell_price)"
                                        type="text"
                                        inputmode="numeric"
                                        class="h-7 w-full max-w-[6.5rem] rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        @input="updateRowMoney(row, 'sell_price', $event)"
                                        @click.stop
                                    />
                                    <span v-else class="text-slate-700">{{ formatPrice(entryModal.item.sell_price) }} {{ $currency() }}</span>
                                </td>
                                <td class="px-3 py-2 text-slate-600">
                                    <div v-if="!props.customerImport">{{
                                        typeof row !== 'string' && row.warranty_expired_at
                                            ? formatDate(row.warranty_expired_at)
                                            : (
                                                typeof row !== 'string' && row.warranty_duration_value
                                                    ? formatWarranty(row.warranty_duration_value, row.warranty_duration_unit)
                                                    : '-'
                                            )
                                    }}</div>
                                    <div v-if="typeof row !== 'string' && row.extra_info?.customer_warranty_duration_value" :class="props.customerImport ? '' : 'mt-0.5 text-[11px] text-blue-700'">
                                        Khách: {{ formatWarranty(row.extra_info.customer_warranty_duration_value, row.extra_info.customer_warranty_duration_unit) }}
                                    </div>
                                </td>
                                <td v-if="hasAnyImeiExtraInfo" class="px-3 py-2 text-slate-600">
                                    <div v-if="typeof row !== 'string' && hasExtraInfo(row)" class="space-y-0.5">
                                        <div v-if="row.extra_info?.note" class="text-slate-500 truncate max-w-[150px]">{{ row.extra_info.note }}</div>
                                    </div>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-2 text-center">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <button type="button" class="p-1 text-slate-400 hover:text-blue-600" title="Sửa" @click.stop="selectImeiEntry(index)">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="p-1 text-slate-400 hover:text-red-500"
                                            title="Xóa"
                                            @click.stop="removeImei(entryModal.item, index)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="entryModal.item?.imeis?.length" class="space-y-2 md:hidden">
                    <button
                        v-for="(row, index) in entryModal.item.imeis"
                        :key="`mobile-imei-${index}`"
                        type="button"
                        class="w-full rounded-xl border p-3 text-left shadow-sm transition"
                        :class="editingEntryIndex === index ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-200' : 'border-slate-200 bg-white hover:border-blue-300'"
                        @click="selectImeiEntry(index, true)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">IMEI / Serial · {{ index + 1 }}</div>
                                <div class="mt-0.5 break-all font-mono text-sm font-bold text-slate-900">{{ typeof row === 'string' ? row : row.imei }}</div>
                            </div>
                            <div class="flex shrink-0 items-center">
                                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-600" aria-label="Sửa IMEI" @click.stop="selectImeiEntry(index)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Xóa IMEI" @click.stop="removeImei(entryModal.item, index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div v-if="entryModal.item?.type === 'imei_variant'" class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2">
                            <div class="text-[10px] font-semibold uppercase text-slate-500">Biến thể</div>
                            <div class="mt-0.5 break-words text-xs font-medium text-slate-800">{{ imeiVariantLabel(row) }}</div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label class="min-w-0 rounded-lg bg-slate-50 px-2.5 py-2 text-[10px] font-semibold text-slate-500" @click.stop>
                                Giá nhập
                                <input v-if="typeof row !== 'string'" :value="formatPrice(row.cost_price)" type="text" inputmode="numeric" class="mt-1 h-8 w-full rounded-md border border-slate-200 bg-white px-2 text-right text-xs font-medium text-slate-800 outline-none focus:border-blue-500" @input="updateRowMoney(row, 'cost_price', $event)" />
                                <span v-else class="mt-1 block text-right text-xs font-medium text-slate-800">{{ formatPrice(entryModal.item.cost_price) }} {{ $currency() }}</span>
                            </label>
                            <label class="min-w-0 rounded-lg bg-slate-50 px-2.5 py-2 text-[10px] font-semibold text-slate-500" @click.stop>
                                Giá bán
                                <input v-if="typeof row !== 'string'" :value="formatPrice(row.sell_price)" type="text" inputmode="numeric" class="mt-1 h-8 w-full rounded-md border border-slate-200 bg-white px-2 text-right text-xs font-medium text-slate-800 outline-none focus:border-blue-500" @input="updateRowMoney(row, 'sell_price', $event)" />
                                <span v-else class="mt-1 block text-right text-xs font-medium text-slate-800">{{ formatPrice(entryModal.item.sell_price) }} {{ $currency() }}</span>
                            </label>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 pt-2 text-[11px]">
                            <span v-if="!props.customerImport" class="text-slate-500">Bảo hành NCC: <strong class="text-slate-700">{{ typeof row !== 'string' && row.warranty_duration_value ? formatWarranty(row.warranty_duration_value, row.warranty_duration_unit) : '-' }}</strong></span>
                            <span class="text-slate-500">Bảo hành khách: <strong class="text-blue-700">{{ typeof row !== 'string' && row.extra_info?.customer_warranty_duration_value ? formatWarranty(row.extra_info.customer_warranty_duration_value, row.extra_info.customer_warranty_duration_unit) : '-' }}</strong></span>
                        </div>
                    </button>
                </div>

                <!-- EMPTY STATE IMEI -->
                <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 py-8 text-center">
                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <QrCode class="h-5 w-5" />
                    </div>
                    <p class="text-xs font-bold text-slate-700">Chưa có mã IMEI / Serial nào</p>
                    <p class="mt-3 text-[11px] text-slate-400">Nhập IMEI / Serial hoặc bấm biểu tượng camera trong ô nhập bên dưới.</p>
                </div>
            </div>

            <!-- BẢNG BIẾN THỂ -->
            <div v-if="entryModal.mode === 'variant'" class="order-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5 sm:p-3">
                <div v-if="entryModal.item?.rows?.length" class="mb-2 flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold text-slate-800">Biến thể đã thêm</h3>
                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">{{ entryModal.item.rows.length }}</span>
                    </div>
                    <span class="text-[10px] text-slate-500">Bấm dòng để sao chép, bấm ✎ để sửa</span>
                </div>
                <div v-if="entryModal.item?.rows?.length" class="hidden max-h-60 overflow-y-auto overflow-x-hidden rounded-lg border border-slate-200 bg-white md:block">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 font-semibold text-slate-600 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2.5 text-left">Biến thể</th>
                                <th class="w-24 px-3 py-2.5 text-center">Số lượng</th>
                                <th class="w-32 px-3 py-2.5 text-right">Giá nhập</th>
                                <th class="w-32 px-3 py-2.5 text-right">Giá bán</th>
                                <th class="w-28 px-2 py-2.5 text-left">Bảo hành</th>
                                <th class="w-16 px-2 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in entryModal.item.rows" :key="`${index}-${row.variant_id}`" class="cursor-pointer hover:bg-blue-50/70" :class="editingEntryIndex === index ? 'bg-blue-50 ring-1 ring-inset ring-blue-300' : ''" @click="selectVariantEntry(index, true)">
                                <td class="px-3 py-2 font-medium text-slate-800">{{ variantLabel(row.variant) }}</td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="row.quantity"
                                        type="number"
                                        min="1"
                                        class="h-7 w-full rounded-lg border border-slate-200 text-center text-xs outline-none focus:border-blue-500"
                                        @click.stop
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        :value="formatPrice(row.cost_price)"
                                        type="text"
                                        inputmode="numeric"
                                        class="h-7 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        @input="updateRowMoney(row, 'cost_price', $event)"
                                        @click.stop
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        :value="formatPrice(row.sell_price)"
                                        type="text"
                                        inputmode="numeric"
                                        class="h-7 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        @input="updateRowMoney(row, 'sell_price', $event)"
                                        @click.stop
                                    />
                                </td>
                                <td class="px-2 py-2 text-slate-600">
                                    <template v-if="rowWarrantyLines(row).length">
                                        <div v-for="line in rowWarrantyLines(row)" :key="line">{{ line }}</div>
                                    </template>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-2 text-center">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <button type="button" class="p-1 text-slate-400 hover:text-blue-600" title="Sửa" @click.stop="selectVariantEntry(index)">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="p-1 text-slate-400 hover:text-red-500"
                                            title="Xóa"
                                            @click.stop="removeVariantRow(entryModal.item, index)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="entryModal.item?.rows?.length" class="space-y-2 md:hidden">
                    <button
                        v-for="(row, index) in entryModal.item.rows"
                        :key="`mobile-variant-${index}-${row.variant_id}`"
                        type="button"
                        class="w-full rounded-xl border p-3 text-left shadow-sm transition"
                        :class="editingEntryIndex === index ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-200' : 'border-slate-200 bg-white hover:border-blue-300'"
                        @click="selectVariantEntry(index, true)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Biến thể · {{ index + 1 }}</div>
                                <div class="mt-0.5 break-words text-sm font-bold text-slate-900">{{ variantLabel(row.variant) }}</div>
                            </div>
                            <div class="flex shrink-0 items-center">
                                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-600" aria-label="Sửa biến thể" @click.stop="selectVariantEntry(index)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Xóa biến thể" @click.stop="removeVariantRow(entryModal.item, index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div v-if="rowWarrantyLines(row).length" class="mt-2 rounded-lg bg-slate-50 px-2.5 py-1.5 text-[11px] font-medium text-slate-700">
                            <span v-for="line in rowWarrantyLines(row)" :key="line" class="mr-3 inline-block">{{ line }}</span>
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <label class="min-w-0 rounded-lg bg-slate-50 px-2 py-2 text-[10px] font-semibold text-slate-500" @click.stop>
                                Số lượng
                                <input v-model.number="row.quantity" type="number" min="1" class="mt-1 h-8 w-full rounded-md border border-slate-200 bg-white px-2 text-center text-xs font-medium text-slate-800 outline-none focus:border-blue-500" />
                            </label>
                            <label class="min-w-0 rounded-lg bg-slate-50 px-2 py-2 text-[10px] font-semibold text-slate-500" @click.stop>
                                Giá nhập
                                <input :value="formatPrice(row.cost_price)" type="text" inputmode="numeric" class="mt-1 h-8 w-full rounded-md border border-slate-200 bg-white px-1.5 text-right text-xs font-medium text-slate-800 outline-none focus:border-blue-500" @input="updateRowMoney(row, 'cost_price', $event)" />
                            </label>
                            <label class="min-w-0 rounded-lg bg-slate-50 px-2 py-2 text-[10px] font-semibold text-slate-500" @click.stop>
                                Giá bán
                                <input :value="formatPrice(row.sell_price)" type="text" inputmode="numeric" class="mt-1 h-8 w-full rounded-md border border-slate-200 bg-white px-1.5 text-right text-xs font-medium text-slate-800 outline-none focus:border-blue-500" @input="updateRowMoney(row, 'sell_price', $event)" />
                            </label>
                        </div>
                    </button>
                </div>

                <!-- EMPTY STATE VARIANT -->
                <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 py-8 text-center">
                    <p class="text-xs font-bold text-slate-700">Chưa chọn biến thể nào</p>
                    <p class="mt-1 text-[11px] text-slate-400">Chọn biến thể trong biểu mẫu bên dưới rồi bấm <strong>Thêm</strong>.</p>
                </div>
            </div>

        </div>

        <!-- FOOTER MODAL -->
        <template #footer>
            <div class="flex justify-end">
                <button
                    type="button"
                    class="min-h-9 rounded-lg bg-blue-600 px-6 py-1.5 text-sm font-bold text-white shadow-md shadow-blue-600/20 transition-all hover:bg-blue-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="imeiLookupLoading"
                    @click="complete"
                >
                    {{ imeiLookupLoading ? 'Đang kiểm tra...' : 'Lưu danh sách' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>
