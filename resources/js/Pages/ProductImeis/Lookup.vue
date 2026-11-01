<script setup>
import { formatCurrency, formatDateTime } from '@/utils/format'
import { imageUrl } from '@/utils/imageUrl'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
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
    Wrench,
} from 'lucide-vue-next'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import api from '@/Services/api'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'

defineOptions({
    layout: AdminLayout,
})

const keyword = ref('')
const loading = ref(false)
const result = ref(null)
const errorMessage = ref('')
const historyFilter = ref('all')
const selectedEvent = ref(null)
let lookupTimer = null
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

const warrantyMeta = computed(() => {
    const status = result.value?.warranty?.status

    if (status === 'active') {
        return {
            icon: ShieldCheck,
            class: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        }
    }

    if (status === 'expired') {
        return {
            icon: ShieldX,
            class: 'border-rose-200 bg-rose-50 text-rose-700',
        }
    }

    return {
        icon: Clock,
        class: 'border-slate-200 bg-slate-50 text-slate-600',
    }
})

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

const lookup = async (expectedKeyword = keyword.value.trim()) => {
    const imei = keyword.value.trim()

    if (!imei || imei !== expectedKeyword) return

    const requestId = ++lookupRequest

    loading.value = true
    errorMessage.value = ''
    result.value = null

    try {
        const response = await api.get('/api/imeis/lookup', {
            params: {
                imei,
            },
        })

        if (requestId === lookupRequest && keyword.value.trim() === imei) {
            result.value = response.data
        }
    } catch (error) {
        if (requestId === lookupRequest) {
            errorMessage.value =
                error.response?.data?.message ||
                'Không tra cứu được IMEI. Vui lòng thử lại.'
        }
    } finally {
        if (requestId === lookupRequest) loading.value = false
    }
}

const lookupScannedCode = (code) => {
    keyword.value = code
}

watch(keyword, value => {
    clearTimeout(lookupTimer)
    lookupRequest += 1
    loading.value = false
    result.value = null
    errorMessage.value = ''
    selectedEvent.value = null
    const imei = value.trim()

    if (!imei) {
        return
    }

    lookupTimer = setTimeout(() => lookup(imei), 400)
})

onBeforeUnmount(() => clearTimeout(lookupTimer))

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
    if (item.type === 'repair') return item.contact_phone || 'Khách gửi sửa chữa'
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
            title="Tra cứu IMEI"
            description="Kiểm tra thông tin máy, lịch sử nhập bán, sửa chữa và bảo hành"
        />

        <DataPanel>
            <div class="border-b border-slate-200 p-4">
                <div class="flex flex-col gap-3 md:flex-row">
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                      <div class="relative min-w-0 flex-1">
                        <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="keyword"
                            type="text"
                            placeholder="Nhập hoặc quét IMEI / Serial..."
                            class="w-full rounded-xl border border-slate-200 py-3 pl-10 pr-12 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            autocomplete="off"
                        >
                        <BarcodeCameraButton class="absolute right-1 top-1/2 z-10 -translate-y-1/2 !h-9 !w-9" @scanned="lookupScannedCode" />
                      </div>
                    </div>

                    <div class="flex min-w-[130px] items-center justify-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-500">
                        <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600"></span>
                        {{ loading ? 'Đang tra cứu...' : 'Tự động tra cứu' }}
                    </div>
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
                    Nhập IMEI để bắt đầu tra cứu
                </div>
                <div class="mt-1 max-w-lg text-sm text-slate-500">
                    Trang này dùng để xem nhanh lịch sử mua bán, sửa chữa, thông tin thiết bị và tình trạng bảo hành.
                </div>
            </div>

            <div
                v-if="result"
                class="space-y-5 p-4"
            >
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
                                {{ result.exists ? 'Đã tìm thấy dữ liệu IMEI trong hệ thống.' : 'Chưa có dữ liệu IMEI trong hệ thống.' }}
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
                            {{ result.imei?.product?.name || '-' }}
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
