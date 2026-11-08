<script setup>
import { formatCurrency, formatDateTime } from '@/utils/format'
import { imageUrl } from '@/utils/imageUrl'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    ExternalLink,
    History,
    Package,
    Search,
    ShieldCheck,
    ShieldX,
    UserRound,
    Wrench,
} from 'lucide-vue-next'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import api from '@/Services/api'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import LookupSuggestionItem from './LookupSuggestionItem.vue'

defineOptions({
    layout: AdminLayout,
})

const keyword = ref('')
const loading = ref(false)
const result = ref(null)
const errorMessage = ref('')
const historyFilter = ref('all')
const selectedEvent = ref(null)
// Phạm vi tìm kiếm: chọn loại dữ liệu muốn tra cứu.
const scopeOptions = [
    { value: 'all', label: 'Toàn bộ', placeholder: 'Tìm tất cả: IMEI, khách hàng, máy, người dùng, nhà cung cấp, hóa đơn, phiếu nhập...' },
    { value: 'sold', label: 'Thiết bị đã bán', placeholder: 'Tìm trong các thiết bị đã bán: IMEI, tên máy, khách mua, mã hóa đơn...' },
    { value: 'in_stock', label: 'Sản phẩm trong kho', placeholder: 'Tìm trong các thiết bị còn trong kho: IMEI, tên máy, biến thể...' },
    { value: 'repair', label: 'Sản phẩm sửa chữa', placeholder: 'Tìm trong các máy đã/đang sửa: mã phiếu, tên máy, IMEI, khách, SĐT...' },
    { value: 'customer', label: 'Khách hàng', placeholder: 'Tìm khách hàng theo tên, số điện thoại, mã, CCCD...' },
    { value: 'user', label: 'Người dùng', placeholder: 'Tìm nhân viên/người dùng theo tên, tên đăng nhập, số điện thoại...' },
    { value: 'supplier', label: 'Nhà cung cấp', placeholder: 'Tìm nhà cung cấp theo tên, mã, số điện thoại, email...' },
    { value: 'sale', label: 'Hóa đơn bán', placeholder: 'Tìm hóa đơn theo mã hóa đơn, tên hoặc SĐT khách...' },
    { value: 'import', label: 'Phiếu nhập', placeholder: 'Tìm phiếu nhập theo mã phiếu, nhà cung cấp, số điện thoại...' },
]
const scope = ref('all')
const scopeLabel = computed(() => scopeOptions.find(option => option.value === scope.value)?.label || '')
const searchPlaceholder = computed(() => scopeOptions.find(option => option.value === scope.value)?.placeholder || '')
const backStack = ref([])

const suggestions = ref([])
const suggestOpen = ref(false)
const suggestLoading = ref(false)
const suggestDone = ref(false)
const activeIndex = ref(-1)
const searchBoxRef = ref(null)
let suggestTimer = null
let suggestRequest = 0
let lookupRequest = 0

const money = formatCurrency

const formatDate = (value) => {
    if (!value) return '-'

    return formatDateTime(value)
}

const variantText = computed(() => {
    const attributes = result.value?.imei?.variant?.attributes

    if (!attributes || typeof attributes !== 'object') {
        return result.value?.imei?.variant?.sku || '-'
    }

    return Object.values(attributes)
        .map(value => {
            if (!value || typeof value !== 'object') return value

            return value.value || value.name || value.label || ''
        })
        .filter(Boolean)
        .join(' / ') || result.value?.imei?.variant?.sku || '-'
})

const metaFor = (status) => {
    if (status === 'active') {
        return { icon: ShieldCheck, class: 'border-emerald-200 bg-emerald-50 text-emerald-700' }
    }

    if (status === 'expired' || status === 'voided') {
        return { icon: ShieldX, class: 'border-rose-200 bg-rose-50 text-rose-700' }
    }

    return { icon: Clock, class: 'border-slate-200 bg-slate-50 text-slate-600' }
}

const warrantyMeta = computed(() => metaFor(result.value?.warranty?.status))
const customerWarrantyMeta = computed(() => metaFor(result.value?.customer_warranty?.status))
const repairWarrantyMeta = computed(() => metaFor(result.value?.repair_warranty?.status))

// Máy không có trong kho (chỉ có phiếu sửa) thì lấy tên máy từ phiếu sửa.
const deviceTitle = computed(() =>
    result.value?.imei?.product?.name || result.value?.repairs?.[0]?.device_name || '-')

const remainingText = (info) => {
    if (info?.remaining_days === null || info?.remaining_days === undefined) return ''

    return info.remaining_days >= 0
        ? `Còn ${info.remaining_days} ngày`
        : `Quá hạn ${Math.abs(info.remaining_days)} ngày`
}

const hasExtraInfo = computed(() => {
    const extraInfo = result.value?.imei?.extra_info || {}

    return Boolean(
        extraInfo.note ||
        extraInfo.image_path
    )
})

const extraInfoImageUrl = computed(() => {
    const imagePath = result.value?.imei?.extra_info?.image_path

    return imageUrl(imagePath)
})

const runLookup = async (params) => {
    const requestId = ++lookupRequest

    loading.value = true
    errorMessage.value = ''
    result.value = null
    selectedEvent.value = null
    suggestOpen.value = false

    try {
        const response = await api.get('/api/imeis/lookup', { params })

        if (requestId === lookupRequest) result.value = response.data
    } catch (error) {
        if (requestId === lookupRequest) {
            errorMessage.value =
                error.response?.data?.message ||
                'Không tra cứu được. Vui lòng thử lại.'
        }
    } finally {
        if (requestId === lookupRequest) loading.value = false
    }
}

// Tra cứu theo từ khóa tự do (Enter / nút Tra cứu / quét mã).
const lookup = () => {
    const q = keyword.value.trim()

    if (!q) return

    clearTimeout(suggestTimer)
    suggestRequest += 1
    suggestLoading.value = false
    runLookup({ q, scope: scope.value })
}

const paramsFor = (item) => {
    if (item.type === 'repair') return { repair_id: item.repair_id }

    return { type: item.type, id: item.id }
}

const selectSuggestion = (item) => {
    // Chọn từ bên trong một kết quả (danh sách / hồ sơ) thì nhớ để có nút Quay lại.
    if (result.value && !result.value.no_match) backStack.value.push(result.value)

    runLookup(paramsFor(item))
}

const goBack = () => {
    const previous = backStack.value.pop()

    if (previous) {
        result.value = previous
        selectedEvent.value = null
    }
}

const fetchSuggestions = async (q) => {
    const requestId = ++suggestRequest

    suggestLoading.value = true

    try {
        const response = await api.get('/api/imeis/lookup', { params: { suggest: 1, q, scope: scope.value } })

        if (requestId !== suggestRequest || keyword.value.trim() !== q) return

        suggestions.value = response.data?.suggestions || []
        suggestDone.value = true
        activeIndex.value = -1

        // Gõ/quét đúng IMEI hoặc Serial đầy đủ thì tra cứu luôn, không cần chọn gợi ý.
        const exact = ['all', 'sold', 'in_stock', 'repair'].includes(scope.value) && suggestions.value.find(item =>
            item.type === 'imei' && [item.imei, item.serial].some(code => code && code === q))

        if (exact) selectSuggestion(exact)
    } catch {
        if (requestId === suggestRequest) {
            suggestions.value = []
            suggestDone.value = true
        }
    } finally {
        if (requestId === suggestRequest) suggestLoading.value = false
    }
}

const lookupScannedCode = async (code) => {
    keyword.value = code
    await nextTick()
    lookup()
}

watch([keyword, scope], () => {
    const value = keyword.value

    backStack.value = []
    clearTimeout(suggestTimer)
    suggestRequest += 1
    lookupRequest += 1
    loading.value = false
    suggestLoading.value = false
    suggestDone.value = false
    activeIndex.value = -1
    result.value = null
    errorMessage.value = ''
    selectedEvent.value = null

    const q = value.trim()

    if (q.length < 2) {
        suggestions.value = []
        suggestOpen.value = false
        return
    }

    suggestOpen.value = true
    suggestLoading.value = true
    suggestTimer = setTimeout(() => fetchSuggestions(q), 250)
})

const onKeydown = (event) => {
    const count = suggestions.value.length

    if (event.key === 'ArrowDown' && count) {
        event.preventDefault()
        suggestOpen.value = true
        activeIndex.value = (activeIndex.value + 1) % count
    } else if (event.key === 'ArrowUp' && count) {
        event.preventDefault()
        suggestOpen.value = true
        activeIndex.value = activeIndex.value <= 0 ? count - 1 : activeIndex.value - 1
    } else if (event.key === 'Enter') {
        event.preventDefault()
        const item = suggestions.value[activeIndex.value]

        if (suggestOpen.value && item) selectSuggestion(item)
        else lookup()
    } else if (event.key === 'Escape') {
        suggestOpen.value = false
    }
}

const closeOnOutside = (event) => {
    if (searchBoxRef.value && !searchBoxRef.value.contains(event.target)) suggestOpen.value = false
}

onMounted(() => window.addEventListener('pointerdown', closeOnOutside))
onBeforeUnmount(() => {
    clearTimeout(suggestTimer)
    window.removeEventListener('pointerdown', closeOnOutside)
})

const historyTypes = [
    { value: 'all', label: 'Tất cả' },
    { value: 'import', label: 'Nhập hàng' },
    { value: 'sale', label: 'Bán hàng' },
    { value: 'repair', label: 'Sửa chữa' },
]

const timeline = computed(() => {
    const imports = (result.value?.imports || []).map(item => ({
        ...item,
        type: 'import',
        title: item.code || 'Phiếu nhập',
        subtitle: `Giá nhập ${money(item.cost_price)}`,
        happenedAt: item.happened_at || item.created_at,
        icon: ArrowDownLeft,
        color: 'emerald',
    }))
    const sales = (result.value?.sales || []).map(item => ({
        ...item,
        type: 'sale',
        title: item.code || 'Hóa đơn bán hàng',
        subtitle: item.customer_name ? `Khách: ${item.customer_name}` : 'Khách lẻ',
        happenedAt: item.sold_at,
        icon: ArrowUpRight,
        color: 'blue',
    }))
    const repairs = (result.value?.repairs || []).map(item => ({
        ...item,
        type: 'repair',
        title: item.code || 'Phiếu sửa chữa',
        subtitle: item.repair_request || item.note || 'Tiếp nhận sửa chữa',
        happenedAt: item.received_at,
        icon: Wrench,
        color: 'purple',
    }))

    return [...imports, ...sales, ...repairs]
        .sort((a, b) => new Date(b.happenedAt || 0) - new Date(a.happenedAt || 0))
})

const filteredTimeline = computed(() => historyFilter.value === 'all'
    ? timeline.value
    : timeline.value.filter(item => item.type === historyFilter.value))

const eventTypeLabel = item => ({ import: 'Nhập hàng', sale: 'Bán hàng', repair: 'Sửa chữa' }[item?.type] || 'Nghiệp vụ')
const eventColorClasses = item => ({
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
    purple: 'border-purple-200 bg-purple-50 text-purple-700',
}[item?.color] || 'border-slate-200 bg-slate-50 text-slate-700')

const eventBadgeClasses = item => ({
    import: 'bg-emerald-100 text-emerald-800',
    sale: 'bg-blue-100 text-blue-800',
    repair: 'bg-purple-100 text-purple-800',
}[item?.type] || 'bg-slate-100 text-slate-700')

const eventDotClasses = type => ({
    import: 'bg-emerald-400',
    sale: 'bg-blue-400',
    repair: 'bg-purple-400',
}[type] || 'bg-slate-400')

const eventPartner = item => {
    if (item.type === 'sale') return item.customer_name || 'Khách lẻ'
    if (item.type === 'repair') return item.customer_name || item.customer_phone || item.contact_phone || 'Khách gửi sửa chữa'
    return item.supplier_id ? `Nhà cung cấp #${item.supplier_id}` : '-'
}

const eventValue = item => {
    if (item.type === 'sale') return item.subtotal ?? item.unit_price
    if (item.type === 'repair') return item.final_cost ?? item.estimated_cost
    return item.cost_price
}

const eventStatus = item => {
    const status = String(item.status || '').toLowerCase()
    const labels = {
        completed: 'Hoàn tất', paid: 'Đã thanh toán', cancelled: 'Đã hủy',
        pending: 'Đang chờ', repairing: 'Đang sửa', done: 'Đã sửa xong',
        returned: 'Đã trả khách', warranty: 'Bảo hành',
    }
    if (item.type === 'import') return 'Đã nhập kho'
    return labels[status] || item.status || 'Đã ghi nhận'
}

const historyCount = type => type === 'all'
    ? timeline.value.length
    : timeline.value.filter(item => item.type === type).length
</script>

<template>
    <div class="space-y-3">
        <PageHeader
            title="Tra cứu IMEI, bảo hành & dữ liệu"
            description="Chọn phạm vi tìm kiếm rồi gõ từ khóa đúng hoặc gần đúng — hệ thống gợi ý ngay khi bạn gõ"
        />

        <DataPanel>
            <div class="border-b border-slate-200 p-4">
                <div class="mb-3 flex items-center gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1">
                    <button
                        v-for="option in scopeOptions"
                        :key="option.value"
                        type="button"
                        class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="scope === option.value ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                        @click="scope = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="flex flex-col gap-3 md:flex-row">
                    <div ref="searchBoxRef" class="relative min-w-0 flex-1">
                        <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="keyword"
                            type="text"
                            :placeholder="searchPlaceholder"
                            class="w-full rounded-xl border border-slate-200 py-3 pl-10 pr-12 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            autocomplete="off"
                            @focus="suggestOpen = keyword.trim().length >= 2"
                            @keydown="onKeydown"
                        >
                        <BarcodeCameraButton class="absolute right-1 top-1/2 z-10 -translate-y-1/2" @scanned="lookupScannedCode" />

                        <!-- Danh sách gợi ý khi gõ -->
                        <div
                            v-if="suggestOpen"
                            class="absolute left-0 right-0 top-full z-30 mt-1.5 max-h-[420px] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl"
                        >
                            <div v-if="suggestLoading && !suggestions.length" class="flex items-center gap-2 px-4 py-3 text-sm text-slate-500">
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600"></span>
                                Đang tìm gợi ý...
                            </div>
                            <div v-else-if="suggestDone && !suggestions.length" class="px-4 py-5 text-center">
                                <div class="text-sm font-bold text-slate-700">Không có kết quả phù hợp</div>
                                <div class="mt-0.5 text-xs text-slate-400">Trong phạm vi “{{ scopeLabel }}” — thử từ khóa khác hoặc đổi phạm vi</div>
                            </div>
                            <div v-else class="divide-y divide-slate-100">
                                <LookupSuggestionItem
                                    v-for="(item, index) in suggestions"
                                    :key="`${item.type}-${item.id || item.repair_id}`"
                                    :item="item"
                                    :active="index === activeIndex"
                                    @select="selectSuggestion"
                                />
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="flex min-w-[130px] items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loading || !keyword.trim()"
                        @click="lookup"
                    >
                        <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        {{ loading ? 'Đang tra cứu...' : 'Tra cứu' }}
                    </button>
                </div>

                <p
                    v-if="errorMessage"
                    class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
                >
                    {{ errorMessage }}
                </p>
            </div>

            <div
                v-if="!result && !loading"
                class="flex flex-col items-center justify-center px-6 py-16 text-center"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <Search class="h-7 w-7" />
                </div>
                <div class="mt-4 text-base font-bold text-slate-900">
                    Nhập thông tin để bắt đầu tra cứu
                </div>
                <div class="mt-1 max-w-lg text-sm text-slate-500">
                    Chọn phạm vi ở phía trên (toàn bộ, thiết bị đã bán, trong kho, sửa chữa, khách hàng, người dùng...), gõ từ khóa đúng hoặc gần đúng rồi chọn một gợi ý để xem chi tiết.
                </div>
            </div>

            <!-- Không có kết quả -->
            <div
                v-if="result?.no_match"
                class="flex flex-col items-center justify-center px-6 py-16 text-center"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                    <AlertTriangle class="h-7 w-7" />
                </div>
                <div class="mt-4 text-base font-bold text-slate-900">Không có kết quả phù hợp</div>
                <div class="mt-1 max-w-lg text-sm text-slate-500">
                    Không có dữ liệu nào trong phạm vi “{{ scopeLabel }}” khớp với “{{ keyword }}”. Hãy kiểm tra lại từ khóa hoặc thử phạm vi khác.
                </div>
            </div>

            <!-- Nhiều kết quả: chọn một -->
            <div v-else-if="result?.multiple" class="p-4">
                <div class="mb-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-800">
                    {{ result.message }}
                </div>
                <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <LookupSuggestionItem
                        v-for="item in result.candidates"
                        :key="`${item.type}-${item.id || item.repair_id}`"
                        :item="item"
                        @select="selectSuggestion"
                    />
                </div>
            </div>

            <!-- Hồ sơ khách hàng / người dùng / nhà cung cấp / hóa đơn / phiếu nhập -->
            <div v-else-if="result?.kind === 'entity'" class="space-y-5 p-4">
                <button v-if="backStack.length" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50" @click="goBack">
                    ← Quay lại
                </button>

                <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                    <div class="text-xs font-bold uppercase text-indigo-500">{{ result.entity_label }}</div>
                    <div class="mt-1 text-xl font-black text-slate-900">{{ result.title }}</div>
                    <div v-if="result.subtitle" class="mt-0.5 text-sm font-medium text-slate-600">{{ result.subtitle }}</div>
                </div>

                <div v-if="result.fields?.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="field in result.fields" :key="field.label" class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-bold uppercase text-slate-400">{{ field.label }}</div>
                        <div class="mt-1.5 break-words font-bold text-slate-900">{{ field.value }}</div>
                    </div>
                </div>

                <div v-for="section in result.sections" :key="section.title" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-2.5">
                        <span class="text-sm font-bold text-slate-800">{{ section.title }}</span>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-slate-500">{{ section.items.length }}</span>
                    </div>
                    <div v-if="section.items.length" class="divide-y divide-slate-100">
                        <LookupSuggestionItem
                            v-for="(item, index) in section.items"
                            :key="`${section.title}-${item.type}-${item.id || item.repair_id || index}`"
                            :item="item"
                            @select="selectSuggestion"
                        />
                    </div>
                    <div v-else class="px-4 py-6 text-center text-sm text-slate-400">Chưa có dữ liệu</div>
                </div>
            </div>

            <div
                v-else-if="result"
                class="space-y-5 p-4"
            >
                <button v-if="backStack.length" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50" @click="goBack">
                    ← Quay lại
                </button>

                <div
                    class="rounded-2xl border p-4"
                    :class="result.exists ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'"
                >
                    <div class="flex items-start gap-3">
                        <CheckCircle2
                            v-if="result.exists"
                            class="mt-0.5 h-5 w-5 shrink-0"
                        />
                        <AlertTriangle
                            v-else
                            class="mt-0.5 h-5 w-5 shrink-0"
                        />
                        <div>
                            <div class="font-bold">
                                {{ result.message }}
                            </div>
                            <div class="mt-1 text-sm">
                                {{ result.exists ? 'Đã tìm thấy dữ liệu IMEI trong hệ thống.' : (result.repair_only ? 'Chỉ có phiếu sửa chữa liên quan; thiết bị này không nhập/bán tại cửa hàng.' : 'Chưa có dữ liệu IMEI trong hệ thống.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-400">
                            <Package class="h-4 w-4" />
                            Sản phẩm
                        </div>
                        <div class="mt-2 font-bold text-slate-900">
                            {{ deviceTitle }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            SKU: {{ result.imei?.product?.sku || '-' }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-bold uppercase text-slate-400">
                            IMEI / Serial
                        </div>
                        <div class="mt-2 font-mono text-sm font-bold text-slate-900">
                            {{ result.imei?.imei || keyword }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ result.imei?.serial || '-' }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-bold uppercase text-slate-400">
                            Biến thể
                        </div>
                        <div class="mt-2 font-semibold text-slate-900">
                            {{ variantText }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            Trạng thái: {{ result.imei?.status || '-' }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border p-4"
                        :class="warrantyMeta.class"
                    >
                        <div class="flex items-center gap-2 text-xs font-bold uppercase">
                            <component :is="warrantyMeta.icon" class="h-4 w-4" />
                            BH NCC
                        </div>
                        <div class="mt-2 font-black">
                            {{ result.warranty?.label || '-' }}
                        </div>
                        <div
                            v-if="result.warranty?.remaining_days !== null"
                            class="mt-1 text-xs font-semibold"
                        >
                            {{ result.warranty.remaining_days >= 0 ? `Còn ${result.warranty.remaining_days} ngày` : `Quá hạn ${Math.abs(result.warranty.remaining_days)} ngày` }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-400">
                            <UserRound class="h-4 w-4" />
                            Khách hàng
                        </div>
                        <template v-if="result.customer">
                            <div class="mt-2 font-bold text-slate-900">{{ result.customer.name || 'Khách lẻ' }}</div>
                            <div class="mt-1 text-xs font-semibold text-slate-600">{{ result.customer.phone || 'Chưa có số điện thoại' }}</div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ result.customer.source === 'sale' ? 'Mua tại hóa đơn' : 'Gửi sửa tại phiếu' }}
                                {{ result.customer.reference_code }} · {{ formatDate(result.customer.happened_at) }}
                            </div>
                        </template>
                        <div v-else class="mt-2 text-sm font-semibold text-slate-500">Chưa bán / chưa có thông tin khách</div>
                    </div>

                    <div class="rounded-2xl border p-4" :class="customerWarrantyMeta.class">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase">
                            <component :is="customerWarrantyMeta.icon" class="h-4 w-4" />
                            Bảo hành cửa hàng (khách)
                        </div>
                        <div class="mt-2 font-black">{{ result.customer_warranty?.label || '-' }}</div>
                        <div v-if="result.customer_warranty?.expired_at" class="mt-1 text-xs font-semibold">
                            Hạn: {{ formatDate(result.customer_warranty.expired_at) }}
                            <span v-if="remainingText(result.customer_warranty)"> · {{ remainingText(result.customer_warranty) }}</span>
                        </div>
                        <div v-if="result.customer_warranty?.start_at" class="mt-0.5 text-xs opacity-80">
                            Tính từ ngày bán: {{ formatDate(result.customer_warranty.start_at) }}
                        </div>
                        <div v-if="result.customer_warranty?.reason" class="mt-0.5 text-xs">Lý do hủy: {{ result.customer_warranty.reason }}</div>
                    </div>

                    <div class="rounded-2xl border p-4" :class="repairWarrantyMeta.class">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase">
                            <component :is="repairWarrantyMeta.icon" class="h-4 w-4" />
                            Bảo hành sửa chữa
                        </div>
                        <div class="mt-2 font-black">{{ result.repair_warranty?.label || '-' }}</div>
                        <div v-if="result.repair_warranty?.code" class="mt-1 text-xs font-semibold">Phiếu {{ result.repair_warranty.code }}</div>
                        <div v-if="result.repair_warranty?.expired_at" class="mt-0.5 text-xs font-semibold">
                            Hạn: {{ formatDate(result.repair_warranty.expired_at) }}
                            <span v-if="remainingText(result.repair_warranty)"> · {{ remainingText(result.repair_warranty) }}</span>
                        </div>
                        <div v-if="result.repair_warranty?.reason" class="mt-0.5 text-xs">Lý do hủy: {{ result.repair_warranty.reason }}</div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex items-center gap-2 font-bold text-slate-900">
                            <ShieldCheck class="h-5 w-5 text-indigo-600" />
                            Thông tin BH nhà cung cấp
                        </div>
                        <div class="space-y-2 text-sm text-slate-600">
                            <div class="flex justify-between gap-4">
                                <span>Ngày bắt đầu</span>
                                <span class="font-semibold text-slate-900">{{ formatDate(result.warranty?.start_at) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>Hạn BH NCC</span>
                                <span class="font-semibold text-slate-900">{{ formatDate(result.warranty?.expired_at) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>Giá nhập hiện tại</span>
                                <span class="font-semibold text-slate-900">{{ money(result.imei?.cost_price) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>Giá bán hiện tại</span>
                                <span class="font-semibold text-slate-900">{{ money(result.imei?.sell_price) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 lg:col-span-2">
                        <div class="mb-3 flex items-center gap-2 font-bold text-slate-900">
                            <History class="h-5 w-5 text-indigo-600" />
                            Tổng quan nghiệp vụ
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-xs font-bold uppercase text-slate-400">Số lần nhập</div>
                                <div class="mt-1 text-2xl font-black text-slate-900">{{ result.summary?.import_count || 0 }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-xs font-bold uppercase text-slate-400">Số lần bán</div>
                                <div class="mt-1 text-2xl font-black text-slate-900">{{ result.summary?.sale_count || 0 }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-xs font-bold uppercase text-slate-400">Số lần sửa</div>
                                <div class="mt-1 text-2xl font-black text-slate-900">{{ result.summary?.repair_count || 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasExtraInfo"
                    class="rounded-2xl border border-slate-200 bg-white p-4"
                >
                    <div class="mb-3 font-bold text-slate-900">
                        Thông tin thiết bị lưu kèm
                    </div>
                    <div class="grid gap-3 text-sm lg:grid-cols-[1fr_auto]">
                        <div v-if="result.imei.extra_info?.note">
                            <div class="text-xs font-bold uppercase text-slate-400">Thông tin</div>
                            <div class="mt-1 whitespace-pre-line font-semibold text-slate-900">{{ result.imei.extra_info.note }}</div>
                        </div>
                        <a
                            v-if="extraInfoImageUrl"
                            :href="extraInfoImageUrl"
                            target="_blank"
                            class="inline-flex items-center justify-center rounded-xl border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100"
                        >
                            Xem ảnh thiết bị
                        </a>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 border-b border-slate-100 pb-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-lg font-bold text-slate-900">
                                <Clock class="h-5 w-5 text-blue-600" />
                                Lịch sử vòng đời sản phẩm
                            </div>
                            <div class="mt-0.5 text-xs text-slate-500">Hiển thị toàn bộ tiến trình nhập kho, bán hàng và bảo hành sửa chữa theo thứ tự thời gian.</div>
                        </div>
                        <div class="flex max-w-full items-center gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1">
                            <button
                                v-for="type in historyTypes"
                                :key="type.value"
                                type="button"
                                class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                                :class="historyFilter === type.value
                                    ? type.value === 'import' ? 'bg-emerald-600 text-white shadow-sm'
                                        : type.value === 'sale' ? 'bg-blue-600 text-white shadow-sm'
                                            : type.value === 'repair' ? 'bg-purple-600 text-white shadow-sm'
                                                : 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'"
                                @click="historyFilter = type.value"
                            >
                                <span v-if="type.value !== 'all'" class="h-2 w-2 rounded-full" :class="eventDotClasses(type.value)"></span>
                                {{ type.label }}<span v-if="type.value === 'all'">({{ historyCount(type.value) }})</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredTimeline.length" class="relative px-2 pt-6 sm:px-6">
                        <div class="absolute bottom-6 left-[29px] top-10 w-0.5 bg-slate-200 sm:left-[45px]"></div>
                        <div class="space-y-6">
                        <div v-for="item in filteredTimeline" :key="`${item.type}-${item.id}`" class="group relative flex items-start gap-4 sm:gap-6">
                            <button type="button" class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border-2 bg-white shadow-sm transition-transform duration-200 group-hover:scale-110 sm:h-11 sm:w-11" :class="eventColorClasses(item)" @click="selectedEvent = item">
                                <component :is="item.icon" class="h-4 w-4" />
                            </button>
                            <button type="button" class="min-w-0 flex-1 rounded-xl border border-slate-200/80 bg-slate-50 p-4 text-left transition-all hover:border-slate-300 hover:bg-white hover:shadow-md sm:p-5" @click="selectedEvent = item">
                                <div class="mb-2 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md px-2.5 py-0.5 text-xs font-bold" :class="eventBadgeClasses(item)">{{ eventTypeLabel(item) }}</span>
                                        <span class="font-bold text-slate-900 transition-colors group-hover:text-blue-600">{{ item.title }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                                        <Clock class="h-3.5 w-3.5 text-slate-400" />
                                        {{ formatDate(item.happenedAt) }}
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-200/60 pt-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <span class="block text-xs text-slate-400">Đối tác / Khách hàng</span>
                                        <span class="font-medium text-slate-700">{{ eventPartner(item) }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-slate-400">{{ item.type === 'repair' ? 'Chi phí sửa chữa' : 'Giá trị giao dịch' }}</span>
                                        <span class="font-bold text-slate-900">{{ money(eventValue(item)) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 sm:justify-start">
                                        <div>
                                            <span class="block text-xs text-slate-400">Trạng thái</span>
                                            <span class="mt-0.5 inline-block rounded bg-slate-200/60 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ eventStatus(item) }}</span>
                                        </div>
                                        <span class="ml-auto flex items-center gap-1 text-xs font-semibold text-blue-600 group-hover:underline">
                                            Chi tiết <ExternalLink class="h-3 w-3" />
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </div>
                        </div>
                    </div>
                    <div v-else class="py-12 text-center text-sm font-medium text-slate-400">Không có dữ liệu tiến trình thuộc bộ lọc này.</div>
                </div>
            </div>
        </DataPanel>

        <BaseModal v-if="selectedEvent" :title="`${eventTypeLabel(selectedEvent)} · ${selectedEvent.title}`" size="md" @close="selectedEvent = null">
            <div class="space-y-4 p-4 text-sm">
                <div class="rounded-xl border p-3" :class="eventColorClasses(selectedEvent)">
                    <div class="text-xs font-bold uppercase opacity-70">Thời gian phát sinh</div>
                    <div class="mt-1 font-bold">{{ formatDate(selectedEvent.happenedAt) }}</div>
                </div>
                <dl v-if="selectedEvent.type === 'import'" class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <dt class="text-slate-500">Mã phiếu nhập</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.code || '-' }}</dd>
                    <dt class="text-slate-500">Giá nhập</dt><dd class="text-right font-semibold text-slate-900">{{ money(selectedEvent.cost_price) }}</dd>
                    <dt class="text-slate-500">Giá bán thiết lập</dt><dd class="text-right font-semibold text-slate-900">{{ money(selectedEvent.sell_price) }}</dd>
                </dl>
                <dl v-else-if="selectedEvent.type === 'sale'" class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <dt class="text-slate-500">Khách hàng</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.customer_name || 'Khách lẻ' }}</dd>
                    <dt class="text-slate-500">Số điện thoại</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.customer_phone || '-' }}</dd>
                    <dt class="text-slate-500">Đơn giá</dt><dd class="text-right font-semibold text-slate-900">{{ money(selectedEvent.unit_price) }}</dd>
                    <dt class="text-slate-500">Giảm giá</dt><dd class="text-right font-semibold text-slate-900">{{ money(selectedEvent.discount_value) }}</dd>
                    <dt class="text-slate-500">Thành tiền</dt><dd class="text-right font-bold text-blue-700">{{ money(selectedEvent.subtotal) }}</dd>
                    <dt class="text-slate-500">Thanh toán</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.payment_method || '-' }}</dd>
                    <dt class="text-slate-500">Trạng thái</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.status || '-' }}</dd>
                </dl>
                <dl v-else class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <dt class="text-slate-500">Thiết bị</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.device_name || '-' }}</dd>
                    <dt class="text-slate-500">Yêu cầu sửa</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.repair_request || '-' }}</dd>
                    <dt class="text-slate-500">Kỹ thuật viên</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.technician_name || '-' }}</dd>
                    <dt class="text-slate-500">Chi phí dự kiến</dt><dd class="text-right font-semibold text-slate-900">{{ money(selectedEvent.estimated_cost) }}</dd>
                    <dt class="text-slate-500">Chi phí cuối</dt><dd class="text-right font-bold text-amber-700">{{ money(selectedEvent.final_cost) }}</dd>
                    <dt class="text-slate-500">Trạng thái</dt><dd class="text-right font-semibold text-slate-900">{{ selectedEvent.status || '-' }}</dd>
                    <dt v-if="selectedEvent.completed_at" class="text-slate-500">Hoàn tất</dt><dd v-if="selectedEvent.completed_at" class="text-right font-semibold text-slate-900">{{ formatDate(selectedEvent.completed_at) }}</dd>
                    <dt v-if="selectedEvent.returned_at" class="text-slate-500">Đã trả máy</dt><dd v-if="selectedEvent.returned_at" class="text-right font-semibold text-slate-900">{{ formatDate(selectedEvent.returned_at) }}</dd>
                    <dt v-if="selectedEvent.note" class="text-slate-500">Ghi chú</dt><dd v-if="selectedEvent.note" class="text-right font-semibold text-slate-900">{{ selectedEvent.note }}</dd>
                </dl>
            </div>
        </BaseModal>
    </div>
</template>
