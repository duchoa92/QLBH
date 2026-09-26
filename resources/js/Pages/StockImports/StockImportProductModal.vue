<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue'

import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import api from '@/Services/api'
import { filterByKeywords, highlightText } from '@/utils/searchHelper'

import {
    Plus,
    Trash2,
    QrCode,
    Info,
    ChevronDown,
    ChevronUp,
    ShieldCheck,
    Image as ImageIcon,
} from 'lucide-vue-next'

/* PROPS / EMITS */
const props = defineProps({
    item: { type: Object, required: true },
})
const emit = defineEmits(['close', 'completed'])

/* REFS */
const imeiInput = ref(null)
const dropdownVariantRef = ref(null)
const dropdownImeiVariantRef = ref(null)

const showVariantDropdown = ref(false)
const variantSearch = ref('')
const imeiLookup = ref(null)
const imeiLookupLoading = ref(false)
let imeiLookupTimeout = null

// Toggle mở rộng phần thông tin nâng cao (Bảo hành, ghi chú, ảnh)
const showAdvancedInfo = ref(false)

/* MODAL STATE */
const entryModal = ref({
    mode: null,
    item: null,
    imei_input: '',
    variant_input: null,
    info_text_input: '',
    info_image_input: null,
    info_image_name: '',
    warranty_duration_value_input: null,
    warranty_duration_unit_input: 'months',
    quantity_input: 1,
    cost_price_input: 0,
    sell_price_input: 0,
})

/* HELPERS */
const formatPrice = (value) => Number(value || 0).toLocaleString('vi-VN')
const formatDate = (value) => value ? new Date(value).toLocaleString('vi-VN') : '-'

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

const buildExtraInfo = (modal) => {
    const extraInfo = { note: String(modal.info_text_input || '').trim() }
    const cleaned = Object.fromEntries(Object.entries(extraInfo).filter(([, value]) => value !== ''))
    return Object.keys(cleaned).length ? cleaned : null
}

const hasExtraInfo = (row) => {
    const extraInfo = row?.extra_info || {}
    return Boolean(extraInfo.note || extraInfo.image_path || row?.info_image)
}

const resetExtraInfoInputs = () => {
    entryModal.value.info_text_input = ''
    entryModal.value.info_image_input = null
    entryModal.value.info_image_name = ''
    entryModal.value.warranty_duration_value_input = null
    entryModal.value.warranty_duration_unit_input = 'months'
}

const hasAnyImeiExtraInfo = computed(() =>
    (entryModal.value.item?.imeis || []).some(row => typeof row !== 'string' && hasExtraInfo(row))
)

const handleInfoImageChange = (event) => {
    const file = event.target.files?.[0] || null
    entryModal.value.info_image_input = file
    entryModal.value.info_image_name = file?.name || ''
}

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

watch(() => entryModal.value.imei_input, (value) => {
    clearTimeout(imeiLookupTimeout)
    const imei = String(value || '').trim()
    if (!imei || entryModal.value.mode !== 'imei') {
        imeiLookup.value = null
        return
    }
    imeiLookupTimeout = setTimeout(() => { lookupImei(imei) }, 350)
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
    const cost = Number(variant.cost_price ?? variant.import_price ?? item.cost_price ?? 0)
    const sell = Number(variant.sell_price ?? variant.retail_price ?? variant.price ?? item.sell_price ?? 0)

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
    const defaultVariant = (mode === 'variant' || item.type === 'imei_variant') && variants.length ? variants[0] : null
    const defaultCost = defaultVariant ? Number(defaultVariant.cost_price ?? defaultVariant.import_price ?? item.cost_price ?? 0) : Number(item.cost_price ?? item.import_price ?? 0)
    const defaultSell = defaultVariant ? Number(defaultVariant.sell_price ?? defaultVariant.retail_price ?? defaultVariant.price ?? item.sell_price ?? 0) : Number(item.sell_price ?? item.price ?? 0)

    entryModal.value = {
        mode,
        item,
        imei_input: '',
        variant_input: defaultVariant?.id ?? null,
        info_text_input: '',
        info_image_input: null,
        info_image_name: '',
        warranty_duration_value_input: null,
        warranty_duration_unit_input: 'months',
        quantity_input: 1,
        cost_price_input: defaultCost,
        sell_price_input: defaultSell,
    }

    variantSearch.value = ''
    showVariantDropdown.value = false
    if (defaultVariant) nextTick(selectEntryVariant)
}

const resetEntryModal = () => {
    entryModal.value = {
        mode: null,
        item: null,
        imei_input: '',
        variant_input: null,
        info_text_input: '',
        info_image_input: null,
        info_image_name: '',
        warranty_duration_value_input: null,
        warranty_duration_unit_input: 'months',
        quantity_input: 1,
        cost_price_input: 0,
        sell_price_input: 0,
    }
    variantSearch.value = ''
    showVariantDropdown.value = false
    showAdvancedInfo.value = false
}

/* ADD / REMOVE ENTRY */
const addEntry = async () => {
    const modal = entryModal.value
    const item = modal.item
    if (!item) return

    if (modal.mode === 'variant') {
        const qty = Number(modal.quantity_input || 0)
        if (!qty) return
        const variants = item.available_variants || item.variants || []
        const variant = variants.find(v => Number(v.id) === Number(modal.variant_input))
        if (!variant) return

        if (!item.rows) item.rows = []
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
        return
    }

    if (modal.mode === 'imei') {
        const imei = String(modal.imei_input || '').trim()
        if (!imei) return
        if (!item.imeis) item.imeis = []

        const duplicate = item.imeis.some(row => {
            const value = typeof row === 'string' ? row : row.imei
            return String(value).toLowerCase() === imei.toLowerCase()
        })
        if (duplicate) return

        const lookup = imeiLookup.value?.imei?.imei === imei ? imeiLookup.value : await lookupImei(imei)
        if (lookup && !lookup.can_import) return

        item.imeis.push({
            imei,
            cost_price: Number(modal.cost_price_input),
            sell_price: Number(modal.sell_price_input),
            variant_id: modal.variant_input || null,
            extra_info: buildExtraInfo(modal),
            info_image: modal.info_image_input || null,
            warranty_duration_value: modal.warranty_duration_value_input || null,
            warranty_duration_unit: modal.warranty_duration_unit_input || 'months',
        })

        modal.imei_input = ''
        imeiLookup.value = null
        resetExtraInfoInputs()

        nextTick(() => { imeiInput.value?.focus?.() })
    }
}

const removeImei = (item, index) => { item.imeis.splice(index, 1) }
const removeVariantRow = (item, index) => { item.rows.splice(index, 1) }

const handleClickOutside = (event) => {
    if (
        (dropdownVariantRef.value && dropdownVariantRef.value.contains(event.target)) ||
        (dropdownImeiVariantRef.value && dropdownImeiVariantRef.value.contains(event.target))
    ) return
    showVariantDropdown.value = false
}

const complete = () => { emit('completed', props.item) }
const close = () => { resetEntryModal(); emit('close') }

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    openEntryModal(props.item)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
    clearTimeout(imeiLookupTimeout)
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
        size="lg"
        @close="close"
    >
        <div class="space-y-4">

            <!-- KHU VỰC 1: KHUNG DỰT FORM NHẬP TẬP TRUNG (CONTROL PANEL) -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12 items-end">

                    <!-- INPUT: SẢN PHẨM THƯỜNG -->
                    <div v-if="entryModal.mode === 'normal'" class="lg:col-span-4">
                        <FloatingInput
                            v-model.number="entryModal.item.quantity"
                            type="number"
                            min="1"
                            label="Số lượng nhập"
                        />
                    </div>

                    <!-- INPUT: IMEI -->
                    <div v-if="entryModal.mode === 'imei'" class="lg:col-span-5">
                        <FloatingInput
                            ref="imeiInput"
                            v-model="entryModal.imei_input"
                            type="text"
                            label="Quét hoặc nhập IMEI / Serial"
                            @keydown.enter.prevent="addEntry"
                        />
                    </div>

                    <!-- INPUT: BIẾN THỂ -->
                    <div v-if="entryModal.mode === 'variant'" class="lg:col-span-5">
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

                    <!-- INPUT: IMEI + VARIANT SELECT -->
                    <div
                        v-if="entryModal.mode === 'imei' && entryModal.item?.type === 'imei_variant'"
                        class="lg:col-span-4"
                    >
                        <div ref="dropdownImeiVariantRef" class="relative w-full">
                            <FloatingInput
                                v-model="variantSearch"
                                type="text"
                                label="Chọn biến thể cho IMEI"
                                @focus="showVariantDropdown = true"
                            />
                            <div
                                v-if="showVariantDropdown"
                                class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                            >
                                <div
                                    v-for="variant in filteredVariants"
                                    :key="variant.id"
                                    class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600"
                                    @click="selectVariant(variant)"
                                >
                                    <span v-html="highlightText(variantLabel(variant), variantSearch)" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SỐ LƯỢNG BIẾN THỂ -->
                    <div v-if="entryModal.mode === 'variant'" class="lg:col-span-2">
                        <FloatingInput
                            v-model.number="entryModal.quantity_input"
                            type="number"
                            min="1"
                            label="Số lượng"
                        />
                    </div>

                    <!-- GIÁ NHẬP -->
                    <div :class="entryModal.mode === 'normal' ? 'lg:col-span-4' : 'lg:col-span-2'">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            v-model.number="entryModal.cost_price_input"
                            type="number"
                            label="Giá nhập"
                        />
                        <FloatingInput
                            v-else
                            v-model.number="entryModal.item.cost_price"
                            type="number"
                            label="Giá nhập"
                        />
                    </div>

                    <!-- GIÁ BÁN -->
                    <div :class="entryModal.mode === 'normal' ? 'lg:col-span-4' : 'lg:col-span-2'">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            v-model.number="entryModal.sell_price_input"
                            type="number"
                            label="Giá bán"
                        />
                        <FloatingInput
                            v-else
                            v-model.number="entryModal.item.sell_price"
                            type="number"
                            label="Giá bán"
                        />
                    </div>

                    <!-- BUTTON THÊM -->
                    <div v-if="entryModal.mode !== 'normal'" class="lg:col-span-1 flex justify-end">
                        <button
                            type="button"
                            class="flex h-11 w-full items-center justify-center gap-1 rounded-xl bg-emerald-600 text-white font-semibold shadow-sm transition-all hover:bg-emerald-700 active:scale-95"
                            :disabled="entryModal.mode === 'imei' && imeiLookup && !imeiLookup.can_import"
                            :class="{ 'cursor-not-allowed opacity-50': entryModal.mode === 'imei' && imeiLookup && !imeiLookup.can_import }"
                            @click="addEntry"
                        >
                            <Plus class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- NÚT MỞ RỘNG THÔNG TIN NÂNG CAO CHO IMEI -->
                <div v-if="entryModal.mode === 'imei'" class="mt-3 pt-3 border-t border-slate-200/60">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700"
                        @click="showAdvancedInfo = !showAdvancedInfo"
                    >
                        <ShieldCheck class="h-4 w-4" />
                        <span>Thông tin thiết bị & BH NCC</span>
                        <ChevronDown v-if="!showAdvancedInfo" class="h-3.5 w-3.5" />
                        <ChevronUp v-else class="h-3.5 w-3.5" />
                    </button>

                    <!-- KHỐI MỞ RỘNG -->
                    <div v-if="showAdvancedInfo" class="mt-3 space-y-3 rounded-xl bg-white p-3 border border-slate-200">
                        <div class="grid gap-3 lg:grid-cols-3">
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 mb-1">
                                    Thông tin thiết bị
                                </label>
                                <textarea
                                    v-model="entryModal.info_text_input"
                                    rows="2"
                                    placeholder="Nhập QR, service code, tài khoản, ghi chú thiết bị..."
                                    class="w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                ></textarea>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-500">
                                    Ảnh thiết bị
                                </label>
                                <label class="flex h-16 w-full cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 hover:bg-slate-100">
                                    <ImageIcon class="h-4 w-4 text-slate-400" />
                                    <span class="mt-1 text-[11px] text-slate-500">
                                        {{ entryModal.info_image_name || 'Chọn ảnh...' }}
                                    </span>
                                    <input type="file" accept="image/*" class="hidden" @change="handleInfoImageChange" />
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3 items-center">
                            <div>
                                <label class="block text-xs font-bold text-slate-500">Hạn BH nhà cung cấp</label>
                                <input
                                    v-model.number="entryModal.warranty_duration_value_input"
                                    type="number"
                                    min="0"
                                    placeholder="Số lượng"
                                    class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500">Đơn vị tính</label>
                                <select
                                    v-model="entryModal.warranty_duration_unit_input"
                                    class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-3 text-xs outline-none focus:border-blue-500"
                                >
                                    <option value="months">Tháng</option>
                                    <option value="days">Ngày</option>
                                </select>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight self-end pb-1">
                                * Lưu thông tin bảo hành giữa Cửa hàng và Nhà cung cấp.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KẾT QUẢ TRA CỨU IMEI (LOOKUP ALERT) -->
            <div
                v-if="entryModal.mode === 'imei' && (imeiLookupLoading || imeiLookup)"
                class="rounded-xl border p-3 text-xs"
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
            <div v-if="entryModal.mode === 'imei'">
                <div v-if="entryModal.item?.imeis?.length" class="max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 font-semibold text-slate-600 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2.5 text-left">IMEI / Serial</th>
                                <th v-if="entryModal.item?.type === 'imei_variant'" class="px-3 py-2.5 text-left">Biến thể</th>
                                <th class="px-3 py-2.5 text-right">Giá nhập</th>
                                <th class="px-3 py-2.5 text-right">Giá bán</th>
                                <th class="px-3 py-2.5 text-left">Bảo hành</th>
                                <th v-if="hasAnyImeiExtraInfo" class="px-3 py-2.5 text-left">Thông tin thêm</th>
                                <th class="w-10 px-2 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in entryModal.item.imeis" :key="index" class="hover:bg-slate-50/80">
                                <td class="px-3 py-2 font-mono font-medium text-slate-800">
                                    {{ typeof row === 'string' ? row : row.imei }}
                                </td>
                                <td v-if="entryModal.item?.type === 'imei_variant'" class="px-3 py-2">
                                    <select
                                        v-model="row.variant_id"
                                        class="h-7 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none focus:border-blue-500"
                                    >
                                        <option
                                            v-for="variant in entryModal.item.available_variants || entryModal.item.variants"
                                            :key="variant.id"
                                            :value="variant.id"
                                        >
                                            {{ variantLabel(variant) }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input
                                        v-if="typeof row !== 'string'"
                                        v-model.number="row.cost_price"
                                        type="number"
                                        class="h-7 w-24 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />
                                    <span v-else class="text-slate-700">{{ formatPrice(entryModal.item.cost_price) }}</span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input
                                        v-if="typeof row !== 'string'"
                                        v-model.number="row.sell_price"
                                        type="number"
                                        class="h-7 w-24 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />
                                    <span v-else class="text-slate-700">{{ formatPrice(entryModal.item.sell_price) }}</span>
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
                                        <div v-if="row.info_image?.name" class="text-blue-500 truncate max-w-[150px]">Ảnh: {{ row.info_image.name }}</div>
                                    </div>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="px-2 text-center">
                                    <button
                                        type="button"
                                        class="p-1 text-slate-400 hover:text-red-500"
                                        @click="removeImei(entryModal.item, index)"
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
                    <p class="mt-1 text-[11px] text-slate-400">Quét mã vạch hoặc nhập IMEI rồi bấm nút <kbd class="rounded bg-white px-1 border">+</kbd> hoặc <kbd class="rounded bg-white px-1 border">Enter</kbd></p>
                </div>
            </div>

            <!-- BẢNG BIẾN THỂ -->
            <div v-if="entryModal.mode === 'variant'">
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
                            <tr v-for="(row, index) in entryModal.item.rows" :key="row.variant_id" class="hover:bg-slate-50/80">
                                <td class="px-3 py-2 font-medium text-slate-800">{{ variantLabel(row.variant) }}</td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="row.quantity"
                                        type="number"
                                        min="1"
                                        class="h-7 w-full rounded-lg border border-slate-200 text-center text-xs outline-none focus:border-blue-500"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="row.cost_price"
                                        type="number"
                                        class="h-7 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="row.sell_price"
                                        type="number"
                                        class="h-7 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />
                                </td>
                                <td class="px-2 text-center">
                                    <button
                                        type="button"
                                        class="p-1 text-slate-400 hover:text-red-500"
                                        @click="removeVariantRow(entryModal.item, index)"
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
                    <p class="mt-1 text-[11px] text-slate-400">Chọn biến thể ở danh sách phía trên và bấm nút <kbd class="rounded bg-white px-1 border">+</kbd></p>
                </div>
            </div>

        </div>

        <!-- FOOTER MODAL -->
        <template #footer>
            <div class="flex items-center justify-between">
                <div class="text-xs text-slate-500">
                    Đã cấu hình: <span class="font-bold text-emerald-600">{{ entryModal.item?.imeis?.length || entryModal.item?.rows?.length || entryModal.item?.quantity || 0 }}</span> mục
                </div>
                <button
                    type="button"
                    class="rounded-xl bg-slate-900 px-6 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-slate-800 active:scale-95"
                    @click="complete"
                >
                    Xác nhận & Hoàn tất
                </button>
            </div>
        </template>
    </BaseModal>
</template>
