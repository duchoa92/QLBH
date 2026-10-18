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
import { BrowserMultiFormatReader } from '@zxing/browser'
import { BarcodeFormat, DecodeHintType } from '@zxing/library'
import api from '@/Services/api'
import { filterByKeywords, highlightText } from '@/utils/searchHelper'
import { formatNumber, formatDateTime } from '@/utils/format'

import {
    Plus,
    Trash2,
    QrCode,
    ChevronDown,
    ChevronUp,
    ShieldCheck,
    Camera,
    CameraOff,
} from 'lucide-vue-next'

/* PROPS / EMITS */
const props = defineProps({
    item: { type: Object, required: true },
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

// Toggle mở rộng phần thông tin thiết bị và bảo hành nhà cung cấp.
const showAdvancedInfo = ref(false)
const editingEntryIndex = ref(null)

/* MODAL STATE */
const entryModal = ref({
    mode: null,
    item: null,
    imei_input: '',
    variant_input: null,
    info_text_input: '',
    warranty_duration_value_input: null,
    warranty_duration_unit_input: 'months',
    quantity_input: 1,
    cost_price_input: 0,
    sell_price_input: 0,
})
const entryError = ref('')

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
    const extraInfo = { note: String(modal.info_text_input || '').trim() }
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
    if (!value) {
        imeiLookup.value = null
        return null
    }
    imeiLookupLoading.value = true
    try {
        const response = await api.get('/api/imeis/lookup', { params: { imei: value } })
        imeiLookup.value = response.data
        return response.data
    } catch (error) {
        console.error('Lỗi tra cứu IMEI:', error)
        imeiLookup.value = null
        return null
    } finally {
        imeiLookupLoading.value = false
    }
}

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
    const rawCost = variant.cost_price ?? variant.import_price
    const rawSell = variant.sell_price ?? variant.retail_price ?? variant.price
    const cost = rawCost === null || rawCost === undefined
        ? Number(item.cost_price || 0)
        : Number(rawCost) * factor
    const sell = rawSell === null || rawSell === undefined
        ? Number(item.sell_price || 0)
        : Number(rawSell) * factor

    entryModal.value.cost_price_input = cost
    entryModal.value.sell_price_input = sell
}

const selectVariant = (variant) => {
    if (!variant) return
    entryModal.value.variant_input = variant.id
    variantSearch.value = variantLabel(variant)
    showVariantDropdown.value = false
    nextTick(() => { selectEntryVariant() })
}

watch(variantSearch, () => { if (entryModal.value.item) selectEntryVariant() })
watch(() => entryModal.value.variant_input, () => { if (entryModal.value.item) selectEntryVariant() })

/* OPEN / RESET MODAL */
const openEntryModal = (item) => {
    let mode = 'normal'
    if (item.type === 'imei' || item.type === 'imei_variant') mode = 'imei'
    else if (item.type === 'variant') mode = 'variant'

    const variants = item.available_variants || item.variants || []
    const defaultVariant = mode === 'variant' && variants.length ? variants[0] : null
    const factor = item.has_unit_conversion ? Math.max(1, Number(item.conversion_factor || 1)) : 1
    const rawVariantCost = defaultVariant?.cost_price ?? defaultVariant?.import_price
    const rawVariantSell = defaultVariant?.sell_price ?? defaultVariant?.retail_price ?? defaultVariant?.price
    const defaultCost = defaultVariant
        ? (rawVariantCost == null ? Number(item.cost_price || 0) : Number(rawVariantCost) * factor)
        : Number(item.cost_price ?? item.import_price ?? 0)
    const defaultSell = defaultVariant
        ? (rawVariantSell == null ? Number(item.sell_price || 0) : Number(rawVariantSell) * factor)
        : Number(item.sell_price ?? item.price ?? 0)

    entryModal.value = {
        mode,
        item,
        imei_input: '',
        variant_input: defaultVariant?.id ?? null,
        info_text_input: '',
        warranty_duration_value_input: item.supplier_warranty_value ?? null,
        warranty_duration_unit_input: item.supplier_warranty_unit || 'months',
        quantity_input: 1,
        cost_price_input: defaultCost,
        sell_price_input: defaultSell,
    }

    variantSearch.value = ''
    showVariantDropdown.value = false
    showAdvancedInfo.value = false
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
        warranty_duration_value_input: null,
        warranty_duration_unit_input: 'months',
        quantity_input: 1,
        cost_price_input: 0,
        sell_price_input: 0,
    }
    variantSearch.value = ''
    showVariantDropdown.value = false
    showAdvancedInfo.value = false
    editingEntryIndex.value = null
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
    modal.warranty_duration_value_input = item.supplier_warranty_value ?? null
    modal.warranty_duration_unit_input = item.supplier_warranty_unit || 'months'
    editingEntryIndex.value = null
    imeiLookup.value = null
    variantSearch.value = selectedVariant
    showVariantDropdown.value = false
    showAdvancedInfo.value = false

    modal.cost_price_input = keepPrices ? costPrice : Number(item.cost_price ?? item.import_price ?? 0)
    modal.sell_price_input = keepPrices ? sellPrice : Number(item.sell_price ?? item.price ?? 0)
    entryError.value = ''

}

const selectImeiEntry = (index) => {
    const row = entryModal.value.item?.imeis?.[index]
    if (!row) return
    editingEntryIndex.value = index
    entryModal.value.imei_input = typeof row === 'string' ? row : (row.imei || '')
    entryModal.value.variant_input = typeof row === 'string' ? null : (row.variant_id || null)
    entryModal.value.info_text_input = row.extra_info?.note || ''
    entryModal.value.warranty_duration_value_input = row.warranty_duration_value || null
    entryModal.value.warranty_duration_unit_input = row.warranty_duration_unit || 'months'
    showAdvancedInfo.value = Boolean(row.warranty_duration_value || row.extra_info?.note)
    imeiLookup.value = null
    const variant = (entryModal.value.item.available_variants || entryModal.value.item.variants || []).find((option) => Number(option.id) === Number(row.variant_id))
    variantSearch.value = variant ? variantLabel(variant) : ''
    nextTick(() => {
        entryModal.value.cost_price_input = Number(row.cost_price ?? entryModal.value.item.cost_price ?? 0)
        entryModal.value.sell_price_input = Number(row.sell_price ?? entryModal.value.item.sell_price ?? 0)
        entryFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    })
}

const selectVariantEntry = (index) => {
    const row = entryModal.value.item?.rows?.[index]
    if (!row) return
    editingEntryIndex.value = index
    entryModal.value.variant_input = row.variant_id
    entryModal.value.quantity_input = Number(row.quantity || 1)
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
            }
            resetEntryInputs()
            return true
        }

        const exist = item.rows.find(row => Number(row.variant_id) === Number(variant.id))

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
            const lookup = await lookupImei(imei)
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
            warranty_duration_value: modal.warranty_duration_value_input || null,
            warranty_duration_unit: modal.warranty_duration_unit_input || 'months',
        }
        if (editingEntryIndex.value !== null) item.imeis.splice(editingEntryIndex.value, 1, nextRow)
        else item.imeis.push(nextRow)
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
        props.item.supplier_warranty_value = entryModal.value.warranty_duration_value_input || null
        props.item.supplier_warranty_unit = entryModal.value.warranty_duration_unit_input || 'months'
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
    if (!item?.has_unit_conversion || Number(item.conversion_factor || 1) <= 1) {
        return 'Giá bán / đơn vị bán'
    }
    const importUnit = item.unit || 'đơn vị nhập'
    const baseUnit = item.base_unit_name || 'đơn vị bán'
    return `Giá bán / ${importUnit} (quy đổi về ${baseUnit})`
}

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
        @close="close"
    >
        <div class="flex flex-col gap-3">
            <!-- KHU VỰC 1: KHUNG DỰT FORM NHẬP TẬP TRUNG (CONTROL PANEL) -->
            <div ref="entryFormRef" class="order-1 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div v-if="editingEntryIndex !== null" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                    <span>Đang nạp dòng đã chọn vào biểu mẫu.</span>
                    <button type="button" class="font-semibold underline" @click="resetEntryInputs">Thoát sửa</button>
                </div>
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
                <div class="grid items-end gap-2.5 [grid-template-columns:repeat(auto-fit,minmax(min(100%,145px),1fr))]">

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
                    <div v-if="entryModal.mode === 'imei'" class="min-w-0">
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
                                class="absolute right-1 top-1 flex h-8 w-8 items-center justify-center rounded-md text-sky-700 transition hover:bg-sky-50"
                                :aria-label="scanning ? 'Tắt camera' : 'Bật camera quét IMEI'"
                                :title="scanning ? 'Tắt camera' : 'Bật camera quét IMEI'"
                                @click.stop="scanning ? stopCamera() : startCamera()"
                            >
                                <CameraOff v-if="scanning" class="h-4 w-4" />
                                <Camera v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- INPUT: BIẾN THỂ -->
                    <div v-if="entryModal.mode === 'variant' || entryModal.item?.type === 'imei_variant'" class="min-w-0">
                        <div ref="dropdownVariantRef" class="relative w-full">
                            <FloatingInput
                                v-model="variantSearch"
                                type="text"
                                label="Gõ để tìm biến thể..."
                                @focus="showVariantDropdown = true"
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
                    <div class="min-w-0">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            :model-value="formatPrice(entryModal.cost_price_input)"
                            type="text"
                            inputmode="numeric"
                            label="Giá nhập"
                            @update:model-value="entryModal.cost_price_input = parseMoneyInput($event)"
                        />
                        <FloatingInput
                            v-else
                            :model-value="formatPrice(entryModal.item.cost_price)"
                            type="text"
                            inputmode="numeric"
                            label="Giá nhập"
                            @update:model-value="entryModal.item.cost_price = parseMoneyInput($event)"
                        />
                    </div>

                    <!-- GIÁ BÁN -->
                    <div class="min-w-0">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            :model-value="formatPrice(entryModal.sell_price_input)"
                            type="text"
                            inputmode="numeric"
                            :label="sellPriceLabel(entryModal.item)"
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

                </div>

                <p v-if="scanError" class="mt-2 text-xs font-medium text-rose-600">{{ scanError }}</p>

                <!-- BẢO HÀNH NCC DÙNG ĐƯỢC CHO MỌI LOẠI SẢN PHẨM -->
                <div class="mt-3 border-t border-slate-200/60 pt-2.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700"
                        @click="showAdvancedInfo = !showAdvancedInfo"
                    >
                        <ShieldCheck class="h-4 w-4" />
                        <span>{{ entryModal.mode === 'imei' ? 'Thông tin phụ' : 'Bảo hành nhà cung cấp' }}</span>
                        <ChevronDown v-if="!showAdvancedInfo" class="h-3.5 w-3.5" />
                        <ChevronUp v-else class="h-3.5 w-3.5" />
                    </button>

                    <!-- KHỐI MỞ RỘNG -->
                    <div v-if="showAdvancedInfo" class="mt-2 grid min-w-0 grid-cols-1 items-start gap-2 rounded-lg border border-slate-200 bg-slate-50/50 p-2 sm:grid-cols-[minmax(0,1.5fr)_minmax(230px,1fr)]">
                        <div v-if="entryModal.mode === 'imei'" class="min-w-0">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-600">Thông tin thiết bị</label>
                            <textarea
                                v-model="entryModal.info_text_input"
                                rows="2"
                                placeholder="QR, service code, ghi chú..."
                                class="w-full resize-y rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            ></textarea>
                        </div>

                        <div class="min-w-0">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-600">BH nhà cung cấp</label>
                            <div class="flex h-9 min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100">
                                <input
                                    v-model.number="entryModal.warranty_duration_value_input"
                                    type="number"
                                    min="0"
                                    placeholder="Thời hạn"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-2.5 text-xs outline-none focus:ring-0"
                                >
                                <select
                                    v-model="entryModal.warranty_duration_unit_input"
                                    class="w-[88px] shrink-0 border-0 border-l border-slate-200 bg-slate-50 px-2 text-xs outline-none focus:ring-0"
                                >
                                    <option value="months">Tháng</option>
                                    <option value="days">Ngày</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="entryModal.mode !== 'normal'" class="mt-3 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex h-10 min-w-28 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="imeiLookupLoading"
                        @click="addEntry"
                    >
                        <Plus class="h-4 w-4" />
                        {{ editingEntryIndex !== null ? 'Cập nhật' : 'Thêm' }}
                    </button>
                </div>
            </div>

            <div v-if="entryError" class="order-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                {{ entryError }}
            </div>

            <!-- KẾT QUẢ TRA CỨU IMEI (LOOKUP ALERT) -->
            <div
                v-if="entryModal.mode === 'imei' && (imeiLookupLoading || imeiLookup)"
                class="order-3 rounded-xl border p-3 text-xs"
                :class="
                    imeiLookup?.can_import === false
                        ? 'border-rose-200 bg-rose-50 text-rose-800'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                "
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-bold text-sm">
                            {{ imeiLookupLoading ? 'Đang tra cứu IMEI...' : imeiLookup.message }}
                        </div>
                        <div v-if="imeiLookup?.imei" class="mt-1">
                            Sản phẩm hiện lưu: <span class="font-semibold">{{ imeiLookup.imei.product?.name || '-' }}</span>
                            <span v-if="imeiLookup.imei.status" class="ml-2">
                                Trạng thái: {{ imeiLookup.imei.status === 'in_stock' ? 'Còn trong kho' : 'Đã bán/rời kho' }}
                            </span>
                        </div>
                    </div>
                    <div v-if="imeiLookup?.summary" class="text-right font-semibold shrink-0">
                        <div>{{ imeiLookup.summary.import_count }} lần nhập | {{ imeiLookup.summary.sale_count }} lần bán</div>
                    </div>
                </div>
            </div>

            <!-- KHU VỰC 2: DANH SÁCH BẢNG HIỂN THỊ (LIST ZONE) -->

            <!-- BẢNG IMEI -->
            <div v-if="entryModal.mode === 'imei'" class="order-2">
                <div v-if="entryModal.item?.imeis?.length" class="mb-1.5 flex items-center justify-between gap-2 px-1">
                    <h3 class="text-xs font-bold text-slate-800">IMEI đã thêm</h3>
                    <span class="text-[10px] text-slate-500">Bấm vào dòng để nạp lại biểu mẫu bên dưới</span>
                </div>
                <div v-if="entryModal.item?.imeis?.length" class="max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full min-w-[720px] table-fixed text-xs">
                        <thead class="bg-slate-50 font-semibold text-slate-600 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2.5 text-left">IMEI / Serial</th>
                                <th v-if="entryModal.item?.type === 'imei_variant'" class="w-48 px-3 py-2.5 text-left">Biến thể</th>
                                <th class="px-3 py-2.5 text-right">Giá nhập</th>
                                <th class="px-3 py-2.5 text-right">Giá bán</th>
                                <th class="px-3 py-2.5 text-left">Bảo hành</th>
                                <th v-if="hasAnyImeiExtraInfo" class="px-3 py-2.5 text-left">Thông tin thêm</th>
                                <th class="w-10 px-2 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in entryModal.item.imeis" :key="index" class="cursor-pointer hover:bg-blue-50/70" :class="editingEntryIndex === index ? 'bg-blue-50 ring-1 ring-inset ring-blue-300' : ''" @click="selectImeiEntry(index)">
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
                                        class="h-7 w-24 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
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
                                        class="h-7 w-24 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        @input="updateRowMoney(row, 'sell_price', $event)"
                                        @click.stop
                                    />
                                    <span v-else class="text-slate-700">{{ formatPrice(entryModal.item.sell_price) }} {{ $currency() }}</span>
                                </td>
                                <td class="px-3 py-2 text-slate-600">
                                    {{
                                        typeof row !== 'string' && row.warranty_expired_at
                                            ? formatDate(row.warranty_expired_at)
                                            : (
                                                typeof row !== 'string' && row.warranty_duration_value
                                                    ? `${row.warranty_duration_value} ${row.warranty_duration_unit === 'days' ? 'ngày' : 'tháng'}`
                                                    : '-'
                                            )
                                    }}
                                </td>
                                <td v-if="hasAnyImeiExtraInfo" class="px-3 py-2 text-slate-600">
                                    <div v-if="typeof row !== 'string' && hasExtraInfo(row)" class="space-y-0.5">
                                        <div v-if="row.extra_info?.note" class="text-slate-500 truncate max-w-[150px]">{{ row.extra_info.note }}</div>
                                    </div>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-2 text-center">
                                    <button
                                        type="button"
                                        class="p-1 text-slate-400 hover:text-red-500"
                                        @click.stop="removeImei(entryModal.item, index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- EMPTY STATE IMEI -->
                <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 py-8 text-center">
                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <QrCode class="h-5 w-5" />
                    </div>
                    <p class="text-xs font-bold text-slate-700">Chưa có mã IMEI / Serial nào</p>
                    <button
                        type="button"
                        class="mt-3 inline-flex h-9 items-center gap-2 rounded-lg border border-sky-200 bg-white px-3 text-xs font-semibold text-sky-700 transition hover:bg-sky-50"
                        @click="startCamera"
                    >
                        <Camera class="h-4 w-4" /> Quét bằng camera
                    </button>
                    <p class="mt-2 text-[11px] text-slate-400">Hoặc nhập IMEI / Serial ở biểu mẫu bên dưới.</p>
                </div>
            </div>

            <!-- BẢNG BIẾN THỂ -->
            <div v-if="entryModal.mode === 'variant'" class="order-2">
                <div v-if="entryModal.item?.rows?.length" class="mb-1.5 flex items-center justify-between gap-2 px-1">
                    <h3 class="text-xs font-bold text-slate-800">Biến thể đã thêm</h3>
                    <span class="text-[10px] text-slate-500">Bấm vào dòng để nạp lại biểu mẫu bên dưới</span>
                </div>
                <div v-if="entryModal.item?.rows?.length" class="max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 font-semibold text-slate-600 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2.5 text-left">Biến thể</th>
                                <th class="w-24 px-3 py-2.5 text-center">Số lượng</th>
                                <th class="w-32 px-3 py-2.5 text-right">Giá nhập</th>
                                <th class="w-32 px-3 py-2.5 text-right">Giá bán</th>
                                <th class="w-10 px-2 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in entryModal.item.rows" :key="row.variant_id" class="cursor-pointer hover:bg-blue-50/70" :class="editingEntryIndex === index ? 'bg-blue-50 ring-1 ring-inset ring-blue-300' : ''" @click="selectVariantEntry(index)">
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
                                <td class="px-2 text-center">
                                    <button
                                        type="button"
                                        class="p-1 text-slate-400 hover:text-red-500"
                                        @click.stop="removeVariantRow(entryModal.item, index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                    class="rounded-xl bg-slate-900 px-6 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-slate-800 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="imeiLookupLoading"
                    @click="complete"
                >
                    {{ imeiLookupLoading ? 'Đang kiểm tra...' : 'Xác nhận & Hoàn tất' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>
