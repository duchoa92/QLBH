<script setup>
import { formatCurrency, formatDate as formatDateOnly, formatDateTime } from '@/utils/format'
import { imageUrl } from '@/utils/imageUrl'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowLeft,
    ArrowUpRight,
    Building2,
    Check,
    ChevronRight,
    Clock,
    Copy,
    History,
    Image as ImageIcon,
    Info,
    Package,
    PackageCheck,
    ReceiptText,
    Search,
    ShieldCheck,
    ShieldX,
    SlidersHorizontal,
    Smartphone,
    StickyNote,
    UserRound,
    Users,
    Wrench,
    X,
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
// Lọc: chọn một hoặc nhiều loại dữ liệu để tìm.
const scopeOptions = [
    { value: 'all', label: 'Toàn bộ', hint: 'Tất cả dữ liệu trong hệ thống', group: 'Tất cả' },
    { value: 'product', label: 'Sản phẩm', hint: 'Danh mục hàng hóa, linh kiện, màn hình... (kể cả không có IMEI)', group: 'Hàng hóa & thiết bị' },
    { value: 'sold', label: 'Thiết bị đã bán', hint: 'Chỉ các sản phẩm đã bán ra', group: 'Hàng hóa & thiết bị' },
    { value: 'in_stock', label: 'Sản phẩm trong kho', hint: 'Chỉ các sản phẩm còn tồn kho', group: 'Hàng hóa & thiết bị' },
    { value: 'repair', label: 'Sản phẩm sửa chữa', hint: 'Chỉ các máy đã và đang sửa', group: 'Hàng hóa & thiết bị' },
    { value: 'customer', label: 'Khách hàng', hint: 'Tên, số điện thoại, mã, CCCD, email', group: 'Đối tượng' },
    { value: 'user', label: 'Người dùng', hint: 'Nhân viên: tên, tên đăng nhập, số điện thoại', group: 'Đối tượng' },
    { value: 'supplier', label: 'Nhà cung cấp', hint: 'Tên, mã, số điện thoại, email', group: 'Đối tượng' },
    { value: 'sale', label: 'Hóa đơn bán', hint: 'Mã hóa đơn, khách mua', group: 'Chứng từ' },
    { value: 'import', label: 'Phiếu nhập', hint: 'Mã phiếu, nhà cung cấp', group: 'Chứng từ' },
]
const scopeGroups = computed(() => ['Tất cả', 'Hàng hóa & thiết bị', 'Đối tượng', 'Chứng từ']
    .map(title => ({ title, options: scopeOptions.filter(option => option.group === title) })))
const scopes = ref(['all'])
const advancedOpen = ref(false)
const advancedRef = ref(null)
const selectedScopeOptions = computed(() => scopeOptions.filter(option => scopes.value.includes(option.value)))
const scopeLabel = computed(() => selectedScopeOptions.value.map(option => option.label).join(', ') || 'Toàn bộ')
const isAdvanced = computed(() => !(scopes.value.length === 1 && scopes.value[0] === 'all'))
const searchPlaceholder = computed(() => {
    if (!isAdvanced.value) return 'Tìm tất cả: IMEI, sản phẩm, khách hàng, người dùng, nhà cung cấp, hóa đơn, phiếu nhập...'
    if (selectedScopeOptions.value.length === 1) return `Tìm trong ${selectedScopeOptions.value[0].label.toLowerCase()}: ${selectedScopeOptions.value[0].hint.toLowerCase()}`

    return `Tìm trong: ${scopeLabel.value}`
})
const toggleScope = (value) => {
    if (value === 'all') {
        scopes.value = ['all']
        return
    }

    const current = scopes.value.filter(item => item !== 'all')
    const next = current.includes(value) ? current.filter(item => item !== value) : [...current, value]

    scopes.value = next.length ? next : ['all']
}
const resetScopes = () => { scopes.value = ['all'] }
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
        return {
            icon: ShieldCheck,
            tile: 'bg-emerald-100 text-emerald-700',
            badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            bar: 'bg-emerald-500',
            remain: 'text-emerald-700',
        }
    }

    if (status === 'expired' || status === 'voided') {
        return {
            icon: ShieldX,
            tile: 'bg-rose-100 text-rose-700',
            badge: 'bg-rose-50 text-rose-700 ring-rose-200',
            bar: 'bg-rose-500',
            remain: 'text-rose-600',
        }
    }

    return {
        icon: Clock,
        tile: 'bg-slate-100 text-slate-500',
        badge: 'bg-slate-50 text-slate-600 ring-slate-200',
        bar: 'bg-slate-400',
        remain: 'text-slate-500',
    }
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

const dateOnly = (value) => (value ? formatDateOnly(value) || '-' : '-')

// Trạng thái hiện tại của thiết bị (hiển thị bằng tiếng Việt thay vì mã thô như in_stock / sold).
const deviceStatusMeta = computed(() => {
    const data = result.value

    if (!data?.imei) {
        return {
            label: data?.repair_only ? 'Chỉ có phiếu sửa' : 'Chưa có trong hệ thống',
            class: 'bg-amber-50 text-amber-700 ring-amber-200',
        }
    }

    if (data.imei.status === 'in_stock') return { label: 'Còn trong kho', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' }
    if (data.imei.status === 'sold') return { label: 'Đã bán', class: 'bg-blue-50 text-blue-700 ring-blue-200' }

    return { label: data.imei.status || 'Không rõ', class: 'bg-slate-50 text-slate-600 ring-slate-200' }
})

const deviceNotice = computed(() => {
    const data = result.value

    if (!data) return ''
    if (data.repair_only) return 'Chỉ có phiếu sửa chữa liên quan; thiết bị này không nhập/bán tại cửa hàng.'

    return data.message || ''
})

// Sao chép nhanh IMEI / Serial.
const copiedKey = ref('')
let copyTimer = null
const copyText = async (key, text) => {
    if (!text) return

    try {
        await navigator.clipboard.writeText(String(text))
    } catch {
        const field = document.createElement('textarea')

        field.value = String(text)
        document.body.appendChild(field)
        field.select()
        try { document.execCommand('copy') } catch { /* bỏ qua */ }
        field.remove()
    }

    copiedKey.value = key
    clearTimeout(copyTimer)
    copyTimer = setTimeout(() => { copiedKey.value = '' }, 1500)
}

// Phần trăm thời gian bảo hành đã trôi qua (chỉ tính khi biết cả ngày bắt đầu lẫn ngày hết hạn).
const progressFor = (info) => {
    if (!info?.start_at || !info?.expired_at) return null

    const start = new Date(info.start_at).getTime()
    const end = new Date(info.expired_at).getTime()

    if (!(end > start)) return null

    return Math.round(Math.min(1, Math.max(0, (Date.now() - start) / (end - start))) * 100)
}

// Gom 3 loại bảo hành vào một bảng duy nhất để so sánh cùng lúc.
const warrantyRows = computed(() => {
    const data = result.value

    if (!data) return []

    const build = (key, title, hint, info, meta, extra = {}) => {
        const period = info?.start_at && info?.expired_at
            ? `${dateOnly(info.start_at)} → ${dateOnly(info.expired_at)}`
            : (info?.expired_at ? `Hết hạn ${dateOnly(info.expired_at)}` : '')

        return {
            key,
            title,
            hint,
            meta,
            label: info?.label || '-',
            period,
            days: info?.days ? `${info.days} ngày` : '',
            remaining: remainingText(info),
            reason: info?.reason ? `Lý do hủy: ${info.reason}` : '',
            progress: progressFor(info),
            ...extra,
        }
    }

    return [
        build('supplier', 'Bảo hành nhà cung cấp', 'Tính từ ngày nhập kho', data.warranty, warrantyMeta.value),
        build('customer', 'Bảo hành cửa hàng', 'Tính từ ngày bán cho khách', data.customer_warranty, customerWarrantyMeta.value),
        build('repair', 'Bảo hành sau sửa chữa', 'Theo phiếu sửa gần nhất', data.repair_warranty, repairWarrantyMeta.value, {
            reference: data.repair_warranty?.code ? `Phiếu ${data.repair_warranty.code}` : '',
        }),
    ]
})

const customerSourceLabel = computed(() => (result.value?.customer?.source === 'sale' ? 'Mua tại hóa đơn' : 'Gửi sửa tại phiếu'))

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
    runLookup({ q, scope: scopes.value.join(',') })
}

// Gom kết quả theo nhóm: sản phẩm, thiết bị, phiếu sửa, khách hàng...
const groupTitles = {
    product: 'Sản phẩm',
    imei: 'Thiết bị (IMEI / Serial)',
    repair: 'Phiếu sửa chữa',
    customer: 'Khách hàng',
    user: 'Người dùng',
    supplier: 'Nhà cung cấp',
    sale: 'Hóa đơn bán',
    import: 'Phiếu nhập',
}
const groupOrder = Object.keys(groupTitles)

// Trả về các nhóm theo thứ tự cố định; mỗi mục giữ chỉ số phẳng để điều khiển bằng phím ↑ ↓.
const buildGroups = (items = []) => {
    const buckets = new Map(groupOrder.map(type => [type, []]))

    items.forEach(item => buckets.get(buckets.has(item.type) ? item.type : 'imei').push(item))

    let index = 0

    return groupOrder
        .filter(type => buckets.get(type).length)
        .map(type => ({
            key: type,
            title: groupTitles[type],
            entries: buckets.get(type).map(item => ({ item, index: index++ })),
        }))
}
const groupedSuggestions = computed(() => buildGroups(suggestions.value))
const flatSuggestions = computed(() => groupedSuggestions.value.flatMap(group => group.entries.map(entry => entry.item)))
const groupedCandidates = computed(() => buildGroups(result.value?.candidates || []))

const paramsFor = (item) => {
    if (item.type === 'repair') return { repair_id: item.repair_id }

    return { type: item.type, id: item.id, ...(item.filter_status ? { status: item.filter_status } : {}) }
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
        const response = await api.get('/api/imeis/lookup', { params: { suggest: 1, q, scope: scopes.value.join(',') } })

        if (requestId !== suggestRequest || keyword.value.trim() !== q) return

        suggestions.value = response.data?.suggestions || []
        suggestDone.value = true
        activeIndex.value = -1

        // Gõ/quét đúng IMEI hoặc Serial đầy đủ thì tra cứu luôn, không cần chọn gợi ý.
        const exact = scopes.value.some(item => ['all', 'sold', 'in_stock', 'repair'].includes(item)) && suggestions.value.find(item =>
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

watch([keyword, scopes], () => {
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
        const item = flatSuggestions.value[activeIndex.value]

        if (suggestOpen.value && item) selectSuggestion(item)
        else lookup()
    } else if (event.key === 'Escape') {
        suggestOpen.value = false
    }
}

const closeOnOutside = (event) => {
    if (searchBoxRef.value && !searchBoxRef.value.contains(event.target)) suggestOpen.value = false
    if (advancedRef.value && !advancedRef.value.contains(event.target)) advancedOpen.value = false
}

onMounted(() => window.addEventListener('pointerdown', closeOnOutside))
onBeforeUnmount(() => {
    clearTimeout(suggestTimer)
    clearTimeout(copyTimer)
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
        subtitle: '',
        happenedAt: item.happened_at || item.created_at,
        icon: ArrowDownLeft,
        color: 'emerald',
    }))
    const sales = (result.value?.sales || []).map(item => ({
        ...item,
        type: 'sale',
        title: item.code || 'Hóa đơn bán hàng',
        subtitle: '',
        happenedAt: item.sold_at,
        icon: ArrowUpRight,
        color: 'blue',
    }))
    const repairs = (result.value?.repairs || []).map(item => ({
        ...item,
        type: 'repair',
        title: item.code || 'Phiếu sửa chữa',
        subtitle: item.repair_request || item.note || '',
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
    if (item.party_type === 'customer') return item.customer_name || 'Khách lẻ'
    return item.supplier_name || (item.supplier_id ? `Nhà cung cấp #${item.supplier_id}` : '-')
}

const eventTileClass = type => ({
    import: 'bg-emerald-100 text-emerald-700',
    sale: 'bg-blue-100 text-blue-700',
    repair: 'bg-purple-100 text-purple-700',
}[type] || 'bg-slate-100 text-slate-600')

const eventPartnerLabel = item => (item.type === 'import' && item.party_type === 'customer' ? 'Khách bán' : null) || ({ import: 'Nhà cung cấp', sale: 'Khách mua', repair: 'Khách gửi sửa' }[item.type] || 'Đối tác')
const eventValueLabel = item => ({ import: 'Giá nhập', sale: 'Thành tiền', repair: 'Chi phí sửa' }[item.type] || 'Giá trị')

const eventStatusClass = (item) => {
    const status = String(item.status || '').toLowerCase()

    if (item.type === 'import') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
    if (['cancelled', 'canceled'].includes(status)) return 'bg-rose-50 text-rose-700 ring-rose-200'
    if (['completed', 'paid', 'done', 'returned'].includes(status)) return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
    if (['pending', 'repairing', 'warranty'].includes(status)) return 'bg-amber-50 text-amber-700 ring-amber-200'

    return 'bg-slate-50 text-slate-600 ring-slate-200'
}

const paymentLabel = (method) => ({
    cash: 'Tiền mặt', transfer: 'Chuyển khoản', bank: 'Chuyển khoản', bank_transfer: 'Chuyển khoản',
    card: 'Quẹt thẻ', debt: 'Ghi nợ', mixed: 'Nhiều hình thức', split: 'Nhiều hình thức',
}[String(method || '').toLowerCase()] || method || '-')

// Dữ liệu của cửa sổ xem chi tiết một lần nhập / bán / sửa: gom thành danh sách nhãn - giá trị, tách riêng số tiền chính.
const eventDetail = computed(() => {
    const item = selectedEvent.value

    if (!item) return null

    const rows = []
    const add = (label, value, show = true) => {
        if (show) rows.push({ label, value: value === null || value === undefined || value === '' ? '-' : value })
    }

    if (item.type === 'import') {
        add('Mã phiếu nhập', item.code)
        add(eventPartnerLabel(item), eventPartner(item))
        add('Số điện thoại', item.customer_phone, Boolean(item.customer_phone))
        add('Giá bán thiết lập', money(item.sell_price))

        return { amountLabel: 'Giá nhập', amount: money(item.cost_price), rows }
    }

    if (item.type === 'sale') {
        add('Mã hóa đơn', item.code)
        add('Khách hàng', item.customer_name || 'Khách lẻ')
        add('Số điện thoại', item.customer_phone)
        add('Đơn giá', money(item.unit_price))
        add('Giảm giá', money(item.discount_value))
        add('Hình thức thanh toán', paymentLabel(item.payment_method))

        return { amountLabel: 'Thành tiền', amount: money(item.subtotal), rows }
    }

    add('Mã phiếu sửa', item.code)
    add('Thiết bị', item.device_name)
    add('Khách gửi sửa', eventPartner(item))
    add('Yêu cầu sửa', item.repair_request)
    add('Kỹ thuật viên', item.technician_name)
    add('Chi phí dự kiến', money(item.estimated_cost))
    add('Hoàn tất', formatDate(item.completed_at), Boolean(item.completed_at))
    add('Đã trả máy', formatDate(item.returned_at), Boolean(item.returned_at))
    add('Bảo hành sau sửa', item.repair_warranty_days ? `${item.repair_warranty_days} ngày` : '', Boolean(item.repair_warranty_days))
    add('Hạn bảo hành', dateOnly(item.repair_warranty_expires_at), Boolean(item.repair_warranty_expires_at))
    add('Ghi chú', item.note, Boolean(item.note))

    return { amountLabel: 'Chi phí cuối', amount: money(item.final_cost ?? item.estimated_cost), rows }
})

// ---- Hồ sơ đối tượng (khách hàng, NCC, hóa đơn...): chia các danh sách liên quan thành tab.
const entityIcons = { customer: UserRound, user: Users, supplier: Building2, sale: ReceiptText, import: PackageCheck, product: Package }
const entityTiles = {
    customer: 'bg-blue-50 text-blue-600',
    user: 'bg-slate-100 text-slate-600',
    supplier: 'bg-amber-50 text-amber-600',
    sale: 'bg-emerald-50 text-emerald-600',
    import: 'bg-purple-50 text-purple-600',
    product: 'bg-indigo-50 text-indigo-600',
}
const entityIcon = computed(() => entityIcons[result.value?.entity_type] || Package)
const entityTile = computed(() => entityTiles[result.value?.entity_type] || 'bg-slate-100 text-slate-600')
const activeSection = ref(0)
const currentSection = computed(() => result.value?.sections?.[activeSection.value] || null)
const fieldClass = (field) => {
    const value = String(field.value ?? '')
    const isMoney = value.endsWith('đ')
    const isDebt = /công nợ/i.test(field.label) && value.replace(/\D/g, '').replace(/^0+/, '') !== ''

    return [isMoney ? 'tabular-nums' : '', isDebt ? 'text-rose-600' : 'text-slate-900']
}

watch(result, (data) => {
    const sections = data?.kind === 'entity' ? (data.sections || []) : []
    const firstFilled = sections.findIndex(section => section.items?.length)

    activeSection.value = firstFilled >= 0 ? firstFilled : 0
})



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
            title="Tra cứu"
            description="Tìm sản phẩm, IMEI / Serial, bảo hành, khách hàng, nhà cung cấp, hóa đơn, phiếu nhập... — hệ thống gợi ý ngay khi bạn gõ"
        />

        <DataPanel allow-overflow>
            <div class="border-b border-slate-200 p-4">
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
                                <div class="mt-0.5 text-xs text-slate-400">Trong “{{ scopeLabel }}” — thử từ khóa khác hoặc đổi điều kiện tìm kiếm</div>
                            </div>
                            <div v-else>
                                <div v-for="group in groupedSuggestions" :key="group.key">
                                    <div class="sticky top-0 z-10 flex items-center justify-between border-y border-slate-100 bg-slate-50 px-3.5 py-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 first:border-t-0">
                                        <span>{{ group.title }}</span>
                                        <span class="rounded-full bg-white px-1.5 py-0.5 text-[10px] text-slate-500">{{ group.entries.length }}</span>
                                    </div>
                                    <div class="divide-y divide-slate-100">
                                        <LookupSuggestionItem
                                            v-for="entry in group.entries"
                                            :key="`${entry.item.type}-${entry.item.id || entry.item.repair_id}`"
                                            :item="entry.item"
                                            :active="entry.index === activeIndex"
                                            @select="selectSuggestion"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lọc: gom mọi điều kiện, chọn được nhiều -->
                    <div ref="advancedRef" class="relative shrink-0">
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap md:w-auto rounded-xl border px-3.5 py-3 text-sm font-semibold shadow-sm transition"
                            :class="isAdvanced || advancedOpen ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50'"
                            @click="advancedOpen = !advancedOpen"
                        >
                            <SlidersHorizontal class="h-4 w-4" />
                            Lọc
                            <span v-if="isAdvanced" class="rounded-full bg-indigo-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ scopes.length }}</span>
                        </button>
                        <div
                            v-if="advancedOpen"
                            class="absolute right-0 top-full z-40 mt-1.5 w-[min(26rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                        >
                            <div class="max-h-[min(55vh,320px)] space-y-2.5 overflow-y-auto p-3">
                                <div v-for="group in scopeGroups" :key="group.title">
                                    <div class="mb-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ group.title }}</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="option in group.options"
                                            :key="option.value"
                                            type="button"
                                            :title="option.hint"
                                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-left text-xs font-semibold transition"
                                            :class="scopes.includes(option.value) ? 'border-indigo-300 bg-indigo-50 text-indigo-800' : 'border-slate-200 text-slate-700 hover:border-indigo-200 hover:bg-slate-50'"
                                            @click="toggleScope(option.value)"
                                        >
                                            <span
                                                class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border"
                                                :class="scopes.includes(option.value) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white'"
                                            >
                                                <Check v-if="scopes.includes(option.value)" class="h-2.5 w-2.5" />
                                            </span>
                                            {{ option.label }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-3 py-2">
                                <button type="button" class="text-xs font-semibold text-slate-500 transition hover:text-rose-600" @click="resetScopes">Đặt lại</button>
                                <button type="button" class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-bold text-white transition hover:bg-indigo-700" @click="advancedOpen = false">Xong</button>
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

                <!-- Thẻ điều kiện đang chọn -->
                <div class="mt-2.5 flex flex-wrap items-center gap-2">
                    <template v-if="isAdvanced">
                        <span
                            v-for="option in selectedScopeOptions"
                            :key="option.value"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-100 py-1 pl-3 pr-1.5 text-xs font-semibold text-indigo-800"
                        >
                            {{ option.label }}
                            <button type="button" class="rounded-full p-0.5 transition hover:bg-indigo-200" :aria-label="`Bỏ ${option.label}`" @click="toggleScope(option.value)">
                                <X class="h-3 w-3" />
                            </button>
                        </span>
                    </template>
                    <span v-else class="text-xs font-medium text-slate-400">Đang tìm trong toàn bộ dữ liệu</span>
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
                    Dùng “Lọc” để chọn một hoặc nhiều loại dữ liệu (sản phẩm, đã bán, trong kho, sửa chữa, khách hàng, người dùng...), gõ từ khóa đúng hoặc gần đúng rồi chọn một gợi ý để xem chi tiết.
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
                    Không có dữ liệu nào trong “{{ scopeLabel }}” khớp với “{{ keyword }}”. Hãy kiểm tra lại từ khóa hoặc mở “Lọc” để đổi điều kiện.
                </div>
            </div>

            <!-- Nhiều kết quả: chọn một -->
            <div v-else-if="result?.multiple" class="p-4">
                <div class="mb-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-800">
                    {{ result.message }}
                </div>
                <div class="space-y-3">
                    <div v-for="group in groupedCandidates" :key="group.key" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3.5 py-2 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <span>{{ group.title }}</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{{ group.entries.length }}</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <LookupSuggestionItem
                                v-for="entry in group.entries"
                                :key="`${entry.item.type}-${entry.item.id || entry.item.repair_id}`"
                                :item="entry.item"
                                @select="selectSuggestion"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hồ sơ đối tượng: khách hàng / người dùng / nhà cung cấp / hóa đơn / phiếu nhập / sản phẩm -->
            <div v-else-if="result?.kind === 'entity'" class="space-y-3 p-3 sm:p-4">
                <button v-if="backStack.length" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50" @click="goBack">
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Quay lại
                </button>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="flex items-center gap-3 px-4 py-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="entityTile">
                            <component :is="entityIcon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="inline-block rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide" :class="entityTile">{{ result.entity_label }}</span>
                                <span v-if="result.subtitle" class="truncate text-xs font-semibold text-slate-500">{{ result.subtitle }}</span>
                            </div>
                            <h2 class="mt-0.5 break-words text-base font-black leading-snug text-slate-900 sm:text-lg">{{ result.title }}</h2>
                        </div>
                    </div>

                    <dl v-if="result.fields?.length" class="grid grid-cols-2 gap-x-4 gap-y-2.5 border-t border-slate-100 bg-slate-50/60 px-4 py-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        <div v-for="field in result.fields" :key="field.label" class="min-w-0" :class="String(field.value ?? '').length > 26 ? 'col-span-2' : ''">
                            <dt class="truncate text-[11px] font-semibold text-slate-400">{{ field.label }}</dt>
                            <dd class="break-words text-[13px] font-bold leading-snug" :class="fieldClass(field)">{{ field.value }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="result.sections?.length" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="flex gap-1 overflow-x-auto border-b border-slate-100 bg-slate-50 p-2">
                        <button
                            v-for="(section, index) in result.sections"
                            :key="section.title"
                            type="button"
                            class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                            :class="activeSection === index ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-800'"
                            @click="activeSection = index"
                        >
                            {{ section.title }}
                            <span
                                class="rounded-full px-1.5 py-0.5 text-[11px] font-bold"
                                :class="activeSection === index ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-500'"
                            >{{ section.items.length }}</span>
                        </button>
                    </div>

                    <div v-if="currentSection?.items.length" class="divide-y divide-slate-100">
                        <LookupSuggestionItem
                            v-for="(item, index) in currentSection.items"
                            :key="`${currentSection.title}-${item.type}-${item.id || item.repair_id || index}`"
                            :item="item"
                            @select="selectSuggestion"
                        />
                    </div>
                    <div v-else class="px-4 py-10 text-center text-sm text-slate-400">Chưa có dữ liệu</div>
                </section>
            </div>

            <!-- Chi tiết thiết bị (IMEI / Serial) -->
            <div v-else-if="result" class="space-y-4 p-4">
                <button v-if="backStack.length" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50" @click="goBack">
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Quay lại
                </button>

                <!-- 1. Nhận diện thiết bị: tên, trạng thái, mã định danh, giá -->
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="flex flex-col gap-5 p-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <Smartphone class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="break-words text-xl font-black text-slate-900">{{ deviceTitle }}</h2>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 ring-inset" :class="deviceStatusMeta.class">{{ deviceStatusMeta.label }}</span>
                                </div>
                                <div class="mt-1 text-sm text-slate-500">
                                    <span v-if="result.imei?.product?.sku">SKU {{ result.imei.product.sku }}</span>
                                    <span v-if="result.imei?.product?.sku && variantText !== '-'"> · </span>
                                    <span v-if="variantText !== '-'">{{ variantText }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="group inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs transition hover:border-indigo-300 hover:bg-indigo-50"
                                        title="Bấm để sao chép"
                                        @click="copyText('imei', result.imei?.imei || keyword)"
                                    >
                                        <span class="font-semibold text-slate-400">IMEI</span>
                                        <span class="font-mono text-sm font-bold text-slate-900">{{ result.imei?.imei || keyword }}</span>
                                        <span v-if="result.imei?.is_used" class="rounded-md border border-amber-300 bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">SẢN PHẨM CŨ</span>
                                        <Check v-if="copiedKey === 'imei'" class="h-3.5 w-3.5 text-emerald-600" />
                                        <Copy v-else class="h-3.5 w-3.5 text-slate-400 group-hover:text-indigo-600" />
                                    </button>
                                    <button
                                        v-if="result.imei?.serial"
                                        type="button"
                                        class="group inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs transition hover:border-indigo-300 hover:bg-indigo-50"
                                        title="Bấm để sao chép"
                                        @click="copyText('serial', result.imei.serial)"
                                    >
                                        <span class="font-semibold text-slate-400">Serial</span>
                                        <span class="font-mono text-sm font-bold text-slate-900">{{ result.imei.serial }}</span>
                                        <Check v-if="copiedKey === 'serial'" class="h-3.5 w-3.5 text-emerald-600" />
                                        <Copy v-else class="h-3.5 w-3.5 text-slate-400 group-hover:text-indigo-600" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="result.imei" class="grid shrink-0 grid-cols-2 gap-3 lg:w-80">
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="text-xs font-semibold text-slate-400">Giá nhập</div>
                                <div class="mt-0.5 text-base font-black tabular-nums text-slate-900">{{ money(result.imei.cost_price) }}</div>
                            </div>
                            <div class="rounded-xl bg-indigo-50 px-4 py-3">
                                <div class="text-xs font-semibold text-indigo-400">Giá bán</div>
                                <div class="mt-0.5 text-base font-black tabular-nums text-indigo-700">{{ money(result.imei.sell_price) }}</div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="deviceNotice"
                        class="flex items-start gap-2 border-t px-5 py-2.5 text-sm"
                        :class="result.exists ? 'border-slate-100 bg-slate-50 text-slate-600' : 'border-amber-100 bg-amber-50 text-amber-800'"
                    >
                        <Info class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ deviceNotice }}</span>
                    </div>
                </section>

                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- 2. Bảo hành: gộp 3 loại vào một bảng -->
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white lg:col-span-2">
                        <header class="flex items-center gap-2 border-b border-slate-100 px-5 py-3">
                            <ShieldCheck class="h-5 w-5 text-indigo-600" />
                            <h3 class="font-bold text-slate-900">Bảo hành</h3>
                        </header>
                        <ul class="divide-y divide-slate-100">
                            <li v-for="row in warrantyRows" :key="row.key" class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="row.meta.tile">
                                        <component :is="row.meta.icon" class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                            <div>
                                                <div class="text-sm font-bold text-slate-900">{{ row.title }}</div>
                                                <div class="text-xs text-slate-400">{{ row.hint }}</div>
                                            </div>
                                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 ring-inset" :class="row.meta.badge">{{ row.label }}</span>
                                        </div>

                                        <div v-if="row.progress !== null" class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full transition-all" :class="row.meta.bar" :style="{ width: `${row.progress}%` }"></div>
                                        </div>

                                        <div v-if="row.period || row.remaining || row.days || row.reference || row.reason" class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                            <span v-if="row.period" class="tabular-nums">{{ row.period }}</span>
                                            <span v-if="row.days">Thời hạn {{ row.days }}</span>
                                            <span v-if="row.reference">{{ row.reference }}</span>
                                            <span v-if="row.remaining" class="font-bold" :class="row.meta.remain">{{ row.remaining }}</span>
                                            <span v-if="row.reason" class="font-medium text-rose-600">{{ row.reason }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- 3. Cột phụ: khách hàng, tóm tắt, ghi chú -->
                    <div class="space-y-4 lg:col-start-3 lg:row-span-2 lg:row-start-1">
                        <section class="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                                <UserRound class="h-5 w-5 text-indigo-600" />
                                Khách hàng gần nhất
                            </h3>
                            <template v-if="result.customer">
                                <div class="font-bold text-slate-900">{{ result.customer.name || 'Khách lẻ' }}</div>
                                <div class="mt-0.5 text-sm text-slate-600">{{ result.customer.phone || 'Chưa có số điện thoại' }}</div>
                                <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                    {{ customerSourceLabel }}
                                    <span class="font-bold text-slate-700">{{ result.customer.reference_code }}</span>
                                    <div class="mt-0.5">{{ formatDate(result.customer.happened_at) }}</div>
                                </div>
                            </template>
                            <div v-else class="text-sm text-slate-400">Chưa bán / chưa có thông tin khách</div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                                <History class="h-5 w-5 text-indigo-600" />
                                Tóm tắt
                            </h3>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-xl bg-emerald-50 py-2.5">
                                    <div class="text-xl font-black tabular-nums text-emerald-700">{{ result.summary?.import_count || 0 }}</div>
                                    <div class="text-[11px] font-semibold text-emerald-600">Lần nhập</div>
                                </div>
                                <div class="rounded-xl bg-blue-50 py-2.5">
                                    <div class="text-xl font-black tabular-nums text-blue-700">{{ result.summary?.sale_count || 0 }}</div>
                                    <div class="text-[11px] font-semibold text-blue-600">Lần bán</div>
                                </div>
                                <div class="rounded-xl bg-purple-50 py-2.5">
                                    <div class="text-xl font-black tabular-nums text-purple-700">{{ result.summary?.repair_count || 0 }}</div>
                                    <div class="text-[11px] font-semibold text-purple-600">Lần sửa</div>
                                </div>
                            </div>
                            <dl v-if="result.imei" class="mt-3 space-y-1.5 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Nhập kho</dt>
                                    <dd class="font-semibold tabular-nums text-slate-900">{{ dateOnly(result.imei.imported_at) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Bán gần nhất</dt>
                                    <dd class="font-semibold tabular-nums text-slate-900">{{ dateOnly(result.imei.sold_at) }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section v-if="hasExtraInfo" class="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                                <StickyNote class="h-5 w-5 text-indigo-600" />
                                Ghi chú thiết bị
                            </h3>
                            <p v-if="result.imei.extra_info?.note" class="whitespace-pre-line text-sm text-slate-700">{{ result.imei.extra_info.note }}</p>
                            <a
                                v-if="extraInfoImageUrl"
                                :href="extraInfoImageUrl"
                                target="_blank"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 transition hover:bg-blue-100"
                            >
                                <ImageIcon class="h-3.5 w-3.5" />
                                Xem ảnh thiết bị
                            </a>
                        </section>
                    </div>

                    <!-- 4. Lịch sử -->
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white lg:col-span-2 lg:col-start-1">
                        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-2">
                                <Clock class="h-5 w-5 text-indigo-600" />
                                <h3 class="font-bold text-slate-900">Lịch sử</h3>
                            </div>
                            <div class="flex max-w-full items-center gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1">
                                <button
                                    v-for="type in historyTypes"
                                    :key="type.value"
                                    type="button"
                                    class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                    :class="historyFilter === type.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                    @click="historyFilter = type.value"
                                >
                                    <span v-if="type.value !== 'all'" class="h-2 w-2 rounded-full" :class="eventDotClasses(type.value)"></span>
                                    {{ type.label }}
                                    <span class="text-slate-400">{{ historyCount(type.value) }}</span>
                                </button>
                            </div>
                        </header>

                        <ol v-if="filteredTimeline.length" class="relative space-y-3 p-5 before:absolute before:bottom-8 before:left-[38px] before:top-8 before:w-px before:bg-slate-200">
                            <li v-for="item in filteredTimeline" :key="`${item.type}-${item.id}`" class="relative flex items-start gap-3">
                                <span class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-4 ring-white" :class="eventTileClass(item.type)">
                                    <component :is="item.icon" class="h-4 w-4" />
                                </span>
                                <button
                                    type="button"
                                    class="group min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-indigo-300 hover:bg-slate-50"
                                    @click="selectedEvent = item"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-bold" :class="eventBadgeClasses(item)">{{ eventTypeLabel(item) }}</span>
                                            <span class="truncate text-sm font-bold text-slate-900">{{ item.title }}</span>
                                            <span v-if="item.is_used" class="shrink-0 rounded-md border border-amber-300 bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">CŨ</span>
                                        </div>
                                        <time class="text-xs tabular-nums text-slate-500">{{ formatDate(item.happenedAt) }}</time>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-end justify-between gap-x-4 gap-y-1.5">
                                        <div class="min-w-0 text-sm">
                                            <span class="text-slate-400">{{ eventPartnerLabel(item) }}: </span>
                                            <span class="font-medium text-slate-700">{{ eventPartner(item) }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-black tabular-nums text-slate-900">{{ money(eventValue(item)) }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset" :class="eventStatusClass(item)">{{ eventStatus(item) }}</span>
                                            <ChevronRight class="h-4 w-4 text-slate-300 transition group-hover:text-indigo-500" />
                                        </div>
                                    </div>
                                    <p v-if="item.subtitle" class="mt-1.5 truncate text-xs text-slate-500">{{ item.subtitle }}</p>
                                </button>
                            </li>
                        </ol>
                        <div v-else class="py-12 text-center text-sm font-medium text-slate-400">Không có dữ liệu thuộc bộ lọc này.</div>
                    </section>
                </div>
            </div>
        </DataPanel>

        <BaseModal v-if="selectedEvent && eventDetail" :title="`${eventTypeLabel(selectedEvent)} · ${selectedEvent.title}`" size="md" @close="selectedEvent = null">
            <div class="space-y-4 p-4 text-sm">
                <div class="flex items-center justify-between gap-3 rounded-xl border p-4" :class="eventColorClasses(selectedEvent)">
                    <div>
                        <div class="text-xs font-semibold opacity-70">{{ eventDetail.amountLabel }}</div>
                        <div class="mt-0.5 text-2xl font-black tabular-nums">{{ eventDetail.amount }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-semibold opacity-70">Thời gian</div>
                        <div class="mt-0.5 font-bold tabular-nums">{{ formatDate(selectedEvent.happenedAt) }}</div>
                        <span class="mt-1.5 inline-block rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-bold">{{ eventStatus(selectedEvent) }}</span>
                    </div>
                </div>

                <dl class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                    <div v-for="row in eventDetail.rows" :key="row.label" class="flex items-start justify-between gap-6 px-4 py-2.5">
                        <dt class="shrink-0 text-slate-500">{{ row.label }}</dt>
                        <dd class="min-w-0 break-words text-right font-semibold text-slate-900">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>
        </BaseModal>
    </div>
</template>
