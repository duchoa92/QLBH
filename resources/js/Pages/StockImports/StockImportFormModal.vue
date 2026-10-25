<script setup>
import {
    computed,
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
} from 'vue'

import { useForm, usePage } from '@inertiajs/vue3'
import { openModal } from '@/Stores/modal'

import api from '@/Services/api'
import { findProductByScannedCode } from '@/Modules/POS/Product/Services/productBarcodeLookup'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import { formatNumber } from '@/utils/format'

import BaseModal from '@/Components/UI/BaseModal.vue'
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { imageUrl } from '@/utils/imageUrl'

import SupplierSection from './SupplierSection.vue'
import StockImportProductModal from './StockImportProductModal.vue'
import ProductForm from '@/Pages/Products/Form.vue'

import {
    X,
    Package,
    Plus,
    Pencil,
    Trash2,
    LoaderCircle,
    Printer,
} from 'lucide-vue-next'

const props = defineProps({
    initialPartyType: { type: String, default: 'supplier' },
    suppliers: {
        type: Array,
        default: () => [],
    },

    categories: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'close',
    'created',
])
const page = usePage()

const form = useForm({
    party_type: props.initialPartyType,
    supplier_id: null,
    customer_id: null,
    party_name: '',
    party_phone: '',
    party_address: '',
    party_cccd: '',
    import_date: new Date().toISOString().slice(0, 10),

    items: [],

    discount: 0,
    extra_fee: 0,
    note: '',
})

/*
|--------------------------------------------------------------------------
| SUPPLIER
|--------------------------------------------------------------------------
*/

const selectedSupplier = ref(null)
const partyType = ref(props.initialPartyType)
const selectedCustomer = ref(null)
const customerDraft = ref({ full_name: 'Khách lẻ', phone: '', address: '', cccd: '' })

const selectSupplier = (supplier) => {
    selectedSupplier.value = supplier
    form.supplier_id = supplier?.id ?? null
}

const setPartyType = (value) => {
    if (!['supplier', 'customer'].includes(value)) return
    partyType.value = value
    form.party_type = value
}

const selectCustomer = (customer) => {
    selectedCustomer.value = customer
    form.customer_id = customer?.id ?? null
    if (customer) {
        customerDraft.value = {
            full_name: customer.full_name || '', phone: customer.phone || '',
            address: customer.address || '', cccd: customer.cccd || '',
        }
    }
}

const updateCustomerDraft = (draft) => {
    customerDraft.value = draft
    if (selectedCustomer.value && ['full_name', 'phone', 'address', 'cccd'].some(key => (draft[key] || '') !== (selectedCustomer.value[key] || ''))) {
        selectedCustomer.value = null
        form.customer_id = null
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT SEARCH
|--------------------------------------------------------------------------
*/

const keyword = ref('')
const searchResults = ref([])
const searching = ref(false)
const showResults = ref(false)
const productSearchRef = ref(null)
let productSearchRequest = 0

let searchTimeout = null

const searchProducts = async () => {
    const value = keyword.value.trim()
    const requestId = ++productSearchRequest

    if (!value) {
        searchResults.value = []
        showResults.value = false
        return
    }

    searching.value = true
    showResults.value = true

    try {
        const res = await api.get(
            '/api/products',
            {
                params: {
                    keyword: value,
                },
            }
        )

        if (requestId === productSearchRequest) searchResults.value = res.data || []
    } catch (error) {
        console.error(
            'Lỗi khi tải danh sách sản phẩm:',
            error
        )

        if (requestId === productSearchRequest) searchResults.value = []
    } finally {
        if (requestId === productSearchRequest) searching.value = false
    }
}

watch(keyword, () => {
    clearTimeout(searchTimeout)
    productSearchRequest++
    searching.value = false

    searchTimeout = setTimeout(
        searchProducts,
        300
    )
})

const createProductFromSearch = () => {
    const initialName = keyword.value.trim()
    if (!initialName) return

    openModal(ProductForm, {
        props: {
            title: 'Tạo sản phẩm để nhập lại',
            size: 'xl',
            categories: props.categories,
            initialName,
        },
        onUpdated: async (productId) => {
            try {
                let product = null
                if (productId) {
                    const { data } = await api.get(`/api/products/${productId}`)
                    product = data
                } else {
                    // Flash ID is not present in some Inertia responses; recover the
                    // just-created item from the same catalog search used by this form.
                    const { data } = await api.get('/api/products', { params: { keyword: initialName } })
                    const products = Array.isArray(data) ? data : (data?.data || [])
                    const normalizedName = initialName.trim().toLocaleLowerCase()
                    const exactMatches = products.filter(item => item.name?.trim().toLocaleLowerCase() === normalizedName)
                    product = exactMatches.length === 1 ? exactMatches[0] : null
                }

                if (!product?.id) {
                    window.alert('Sản phẩm đã tạo nhưng chưa xác định được bản ghi để thêm vào phiếu. Hãy tìm và chọn sản phẩm trong ô tìm kiếm.')
                    return
                }

                showResults.value = false
                selectProduct(product)
                keyword.value = ''
            } catch (error) {
                console.error('Không tải được sản phẩm vừa tạo:', error)
                window.alert('Đã tạo sản phẩm nhưng không thể mở thông tin nhập hàng. Hãy tìm lại sản phẩm trong ô tìm kiếm.')
            }
        },
    })
}

const closeResultsOnOutsideClick = (event) => {
    if (showResults.value && !productSearchRef.value?.contains(event.target)) {
        showResults.value = false
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeResultsOnOutsideClick)
})

onBeforeUnmount(() => {
    clearTimeout(searchTimeout)
    document.removeEventListener('pointerdown', closeResultsOnOutsideClick)
})

/*
|--------------------------------------------------------------------------
| SELECTED ITEMS
|--------------------------------------------------------------------------
*/

const selectedItems = ref([])

const unitLabel = (unit, fallback = 'Cái') => {
    if (typeof unit === 'string') return unit || fallback

    return unit?.short_name || unit?.name || fallback
}

const formErrorMessages = computed(() =>
    Object.values(form.errors || {})
        .flat()
        .filter(Boolean)
)

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
})[character])
const printPreviewHtml = ref('')

const printPurchaseContract = () => {
    const party = partyType.value === 'supplier'
        ? { name: selectedSupplier.value?.name || '', phone: selectedSupplier.value?.phone || '', address: selectedSupplier.value?.address || '', cccd: '' }
        : { name: customerDraft.value.full_name || '', phone: customerDraft.value.phone || '', address: customerDraft.value.address || '', cccd: customerDraft.value.cccd || '' }
    if (!party.name?.trim()) {
        window.alert('Vui lòng chọn nhà cung cấp hoặc nhập họ tên khách lẻ trước khi in hợp đồng.')
        return
    }
    const rows = selectedItems.value.flatMap(item => {
        if (item.type === 'simple') return [{ name: item.name, variant: '', quantity: item.quantity, unit: itemUnit(item), price: item.cost_price }]
        if (item.type === 'variant') return (item.rows || []).map(row => ({ name: item.name, variant: variantLabel(row.variant), quantity: row.quantity, unit: itemUnit(item), price: row.cost_price }))
        return (item.imeis || []).map(row => ({
            name: item.name,
            variant: item.type === 'imei_variant' ? variantLabel((item.available_variants || item.variants || []).find(variant => Number(variant.id) === Number(row.variant_id))) : '',
            quantity: 1, unit: itemUnit(item), price: row.cost_price || item.cost_price,
            imei: typeof row === 'string' ? row : row.imei,
        }))
    }).filter(row => Number(row.quantity || 0) > 0)
    const totalAmount = rows.reduce((sum, row) => sum + Number(row.quantity || 0) * Number(row.price || 0), 0)
    const tableRows = rows.map((row, index) => `<tr><td>${index + 1}</td><td>${escapeHtml(row.name)}${row.variant ? `<br><small>${escapeHtml(row.variant)}</small>` : ''}${row.imei ? `<br><small>IMEI/Serial: ${escapeHtml(row.imei)}</small>` : ''}</td><td>${escapeHtml(row.unit || '')}</td><td class="num">${Number(row.quantity || 0).toLocaleString('vi-VN')}</td><td class="num">${Number(row.price || 0).toLocaleString('vi-VN')} đ</td><td class="num">${(Number(row.price || 0) * Number(row.quantity || 0)).toLocaleString('vi-VN')} đ</td></tr>`).join('')
    printPreviewHtml.value = `<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Hợp đồng mua bán hàng hóa</title><style>@page{size:A4;margin:18mm}*{box-sizing:border-box}body{font:13px/1.55 Arial,sans-serif;color:#111;margin:0}h1{text-align:center;font-size:20px;margin:0 0 4px;text-transform:uppercase}h2{text-align:center;font-size:14px;margin:0 0 20px;font-weight:400}p{margin:5px 0}.parties{margin:18px 0}.party{margin:10px 0}table{width:100%;border-collapse:collapse;margin:14px 0}th,td{border:1px solid #555;padding:7px;vertical-align:top}th{background:#f3f4f6;text-align:center}.num{text-align:right;white-space:nowrap}.total{text-align:right;font-size:15px;font-weight:bold}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:55px;text-align:center}.signatures strong{display:block}.signatures span{font-size:11px;font-style:italic}</style></head><body><h1>Hợp đồng mua bán hàng hóa</h1><h2>Số: ${escapeHtml(`IMP-${new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14)}`)}</h2><p>Hôm nay, ngày ${escapeHtml(new Date().toLocaleDateString('vi-VN'))}, các bên gồm:</p><div class="parties"><div class="party"><strong>BÊN MUA (CỬA HÀNG):</strong> ${escapeHtml(page.props.auth?.user?.name || 'Cửa hàng')}<br>Đại diện: ${escapeHtml(page.props.auth?.user?.name || '')}</div><div class="party"><strong>BÊN BÁN:</strong> ${escapeHtml(party.name)}<br>Số điện thoại: ${escapeHtml(party.phone || '—')}<br>CCCD: ${escapeHtml(party.cccd || '—')}<br>Địa chỉ: ${escapeHtml(party.address || '—')}</div></div><p>Hai bên thống nhất việc mua bán hàng hóa với nội dung sau:</p><table><thead><tr><th>STT</th><th>Tên hàng / thông tin</th><th>ĐVT</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>${tableRows}</tbody></table><p class="total">Tổng tiền hàng: ${totalAmount.toLocaleString('vi-VN')} đ</p><p>Giảm giá: ${Number(form.discount || 0).toLocaleString('vi-VN')} đ</p><p>Phí khác: ${Number(form.extra_fee || 0).toLocaleString('vi-VN')} đ</p><p class="total">Tổng thanh toán: ${Math.max(0, totalAmount - Number(form.discount || 0) + Number(form.extra_fee || 0)).toLocaleString('vi-VN')} đ</p><p>Ngày giao nhận hàng: ${escapeHtml(form.import_date)}.</p><p>Hai bên xác nhận hàng hóa và giá trị nêu trên, cùng ký tên dưới đây.</p><div class="signatures"><div><strong>BÊN MUA</strong><span>(Ký, ghi rõ họ tên)</span></div><div><strong>BÊN BÁN</strong><span>(Ký, ghi rõ họ tên)</span><br><br>${escapeHtml(party.name)}</div></div></body></html>`
}

/*
|--------------------------------------------------------------------------
| PRODUCT CONFIG MODAL
|--------------------------------------------------------------------------
*/

const productModal = ref({
    open: false,
    item: null,
})

/*
|--------------------------------------------------------------------------
| SELECT PRODUCT
|--------------------------------------------------------------------------
*/

const selectProduct = (product, { configure = true } = {}) => {
    const item =
        JSON.parse(
            JSON.stringify(product)
        )

    let type = 'simple'

    const hasIMEI =
        item.manage_stock_by_serial ||
        item.has_imei

    const hasVariant =
        item.variants?.length ||
        item.has_variants

    if (
        hasIMEI &&
        hasVariant
    ) {
        type = 'imei_variant'
    } else if (hasIMEI) {
        type = 'imei'
    } else if (hasVariant) {
        type = 'variant'
    }

    let target =
        selectedItems.value.find(
            i =>
                Number(i.product_id) ===
                    Number(item.id) &&
                i.type === type
        )

    if (!target) {
        const conversionFactor = item.has_unit_conversion
            ? Math.max(1, Number(item.import_conversion_factor ?? item.conversion_factor ?? 1))
            : 1

        const costPrice =
            Number(
                item.cost_price ??
                item.import_price ??
                0
            ) * conversionFactor

        const sellPrice =
            Number(
                item.sell_price ??
                item.price ??
                0
            ) * conversionFactor

        target = {
            type,

            product_id: item.id,

            name: item.name,

            unit: item.has_unit_conversion
                ? (item.import_unit_name ?? item.conversion_unit?.short_name ?? item.conversion_unit?.name ?? 'Đơn vị nhập')
                : (item.unit_name ?? unitLabel(item.unit)),

            unit_id: item.has_unit_conversion
                ? (item.import_unit_id ?? item.conversion_unit?.id ?? null)
                : (item.unit_id ?? null),

            unit_name: item.has_unit_conversion
                ? (item.import_unit_name ?? item.conversion_unit?.short_name ?? item.conversion_unit?.name ?? 'Đơn vị nhập')
                : (item.unit_name ?? unitLabel(item.unit)),

            has_unit_conversion:
                Boolean(item.has_unit_conversion),

            price_unit_basis: item.has_unit_conversion ? 'conversion' : 'base',

            conversion_factor: Math.max(1, Number(item.import_conversion_factor ?? item.conversion_factor ?? 1)),

            conversion_unit: item.conversion_unit ?? null,

            base_unit_name: item.unit_name ?? unitLabel(item.unit),

            inventory_unit_name:
                item.inventory_unit_name ?? null,

            quantity:
                type === 'simple'
                    ? 1
                    : 0,

            cost_price:
                costPrice,

            sell_price:
                sellPrice,

            cost_price_input:
                costPrice,

            sell_price_input:
                sellPrice,

            is_used: false,
            extra_info: {},

            imeis: [],

            rows: [],

            available_variants:
                item.variants || [],

            variants:
                item.variants || [],
        }

        selectedItems.value.push(
            target
        )
    }

    keyword.value = ''
    searchResults.value = []
    showResults.value = false

    if (configure) {
        productModal.value = {
            open: true,
            item: target,
        }
    }

    return target
}

const scanProduct = async () => {
    const code = keyword.value.trim()
    if (!code) return

    clearTimeout(searchTimeout)
    try {
        const match = await findProductByScannedCode(code)
        if (match) {
            selectProduct(match.product)
            return
        }
        await searchProducts()
    } catch {
        await searchProducts()
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT MODAL
|--------------------------------------------------------------------------
*/

const closeProductModal = () => {
    productModal.value = {
        open: false,
        item: null,
    }
}

const handleProductCompleted = (
    item
) => {
    const currentItem = productModal.value.item
    const index = selectedItems.value.findIndex(
        selected => selected === currentItem
    )
    const matchingIndex = index !== -1
        ? index
        : selectedItems.value.findIndex(
            i =>
                Number(i.product_id) ===
                    Number(
                        item.product_id
                    ) &&
                i.type === item.type
        )

    if (matchingIndex !== -1) {
        selectedItems.value[matchingIndex] =
            item
    } else {
        selectedItems.value.push(item)
    }

    closeProductModal()
}

/*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/

const removeItem = (index) => {
    selectedItems.value.splice(
        index,
        1
    )
}

/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/

const formatPrice = formatNumber

const itemUnit = (item) =>
    item.unit_name ||
    unitLabel(item.unit)

const variantLabel = (variant) => {
    if (!variant) return ''
    const attributes = Object.values(variant.attributes || {})
        .map(value => typeof value === 'object' ? (value.value || value.label || value.name || '') : value)
        .filter(Boolean)
    return attributes.join(' / ') || variant.sku || ''
}

const itemConversionHint = (item) => {
    const factor = Math.max(1, Number(item.conversion_factor || 1))

    if (!item.has_unit_conversion || factor === 1) {
        return null
    }

    const inventoryUnit = item.base_unit_name
        || item.inventory_unit_name
        || 'đơn vị bán'

    return `1 ${itemUnit(item)} = ${factor} ${inventoryUnit}`
}

/*
|--------------------------------------------------------------------------
| QUANTITY
|--------------------------------------------------------------------------
*/

const itemQuantity = (item) => {
    if (
        item.type === 'simple'
    ) {
        return Math.max(0, Number(item.quantity || 0))
    }

    if (
        item.type === 'imei' ||
        item.type === 'imei_variant'
    ) {
        return (
            item.imeis || []
        ).length
    }

    if (
        item.type === 'variant'
    ) {
        return (
            item.rows || []
        ).reduce(
            (sum, row) =>
                sum +
                Math.max(0, Number(row.quantity || 0)),
            0
        )
    }

    return 0
}

/*
|--------------------------------------------------------------------------
| COST PRICE
|--------------------------------------------------------------------------
*/

const itemCostPrice = (
    item
) => {
    if (
        item.type === 'simple'
    ) {
        return Number(
            item.cost_price || 0
        )
    }

    const rows =
        item.type === 'variant'
            ? item.rows || []
            : item.imeis || []

    if (rows.length) {
        const prices =
            rows.map(
                r =>
                    Number(
                        r.cost_price ||
                        0
                    )
            )

        return prices.every(
            p =>
                p ===
                prices[0]
        )
            ? prices[0]
            : null
    }

    return Number(
        item.cost_price || 0
    )
}

/*
|--------------------------------------------------------------------------
| SELL PRICE
|--------------------------------------------------------------------------
*/

const itemSellPrice = (
    item
) => {
    if (
        item.type === 'simple'
    ) {
        return Number(
            item.sell_price || 0
        )
    }

    const rows =
        item.type === 'variant'
            ? item.rows || []
            : item.imeis || []

    if (rows.length) {
        const prices =
            rows.map(
                r =>
                    Number(
                        r.sell_price ||
                        0
                    )
            )

        return prices.every(
            p =>
                p ===
                prices[0]
        )
            ? prices[0]
            : null
    }

    return Number(
        item.sell_price || 0
    )
}

/*
|--------------------------------------------------------------------------
| TOTAL QUANTITY
|--------------------------------------------------------------------------
*/

const totalQuantity =
    computed(() =>
        selectedItems.value.reduce(
            (total, item) =>
                total +
                itemQuantity(item),
            0
        )
    )

const itemTypeCounts = computed(() => ({
    simple: selectedItems.value.filter(item => item.type === 'simple').length,
    variant: selectedItems.value.filter(item => item.type === 'variant').length,
    imei: selectedItems.value.filter(item => item.type === 'imei').length,
    imeiVariant: selectedItems.value.filter(item => item.type === 'imei_variant').length,
}))

/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

const total = computed(() =>
    selectedItems.value.reduce(
        (total, item) => {
            if (
                item.type ===
                'simple'
            ) {
                return (
                    total +
                    Number(
                        Math.max(0, Number(item.quantity || 0))
                    ) *
                    Number(
                        importPrice(item, item.cost_price)
                    )
                )
            }

            const rows =
                item.rows || []

            const imeis =
                item.imeis || []

            const variantTotal =
                rows.reduce(
                    (
                        sum,
                        row
                    ) =>
                        sum +
                        Math.max(0, Number(row.quantity || 0)) *
                        importPrice(item, row.cost_price),
                    0
                )

            const imeiTotal =
                imeis.reduce(
                    (
                        sum,
                        row
                    ) =>
                        sum +
                        importPrice(item, row.cost_price),
                    0
                )

            return (
                total +
                variantTotal +
                imeiTotal
            )
        },
        0
    )
)

/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
*/

const canConfirm = computed(
    () =>
        (partyType.value === 'supplier' ? selectedSupplier.value : customerDraft.value.full_name?.trim()) &&
        selectedItems.value.some(item => itemQuantity(item) > 0)
)

// API và phiếu nhập lưu giá theo đơn vị nhập; nếu người dùng nhập theo
// đơn vị cơ sở thì đổi sang giá mỗi đơn vị nhập trước khi gửi.
const importPrice = (item, value) => {
    const price = Number(value || 0)
    if (!item.has_unit_conversion || item.price_unit_basis !== 'base') return price
    return price * Math.max(1, Number(item.conversion_factor || 1))
}

const confirm = () => {
    if (partyType.value === 'supplier' && !selectedSupplier.value) {
        return
    }
    if (partyType.value === 'customer' && !customerDraft.value.full_name?.trim()) {
        return
    }

    if (!selectedItems.value.some(item => itemQuantity(item) > 0)) {
        return
    }

    form.party_type = partyType.value
    form.supplier_id = partyType.value === 'supplier' ? selectedSupplier.value.id : null
    form.customer_id = partyType.value === 'customer' ? selectedCustomer.value?.id ?? null : null
    form.party_name = partyType.value === 'customer' ? customerDraft.value.full_name.trim() : selectedSupplier.value.name
    form.party_phone = partyType.value === 'customer' ? customerDraft.value.phone : (selectedSupplier.value.phone || '')
    form.party_address = partyType.value === 'customer' ? customerDraft.value.address : (selectedSupplier.value.address || '')
    form.party_cccd = partyType.value === 'customer' ? customerDraft.value.cccd : ''

    form.items = selectedItems.value.flatMap(item => {
        // Sản phẩm thường
        if (item.type === 'simple') {
            return [{
                product_id: item.product_id,
                variant_id: null,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                quantity: Math.max(0, Math.round(Number(item.quantity || 0))),
                cost_price: importPrice(item, item.cost_price),
                sell_price: importPrice(item, item.sell_price),
                supplier_warranty_value: item.supplier_warranty_value || null,
                supplier_warranty_unit: item.supplier_warranty_unit || 'months',
                extra_info: partyType.value === 'customer' && item.is_used
                    ? { is_used: true, account: item.extra_info?.account || null, password: item.extra_info?.password || null }
                    : null,
                imeis: [],
            }]
        }

        // Sản phẩm có biến thể
        if (item.type === 'variant') {
            return (item.rows || [])
                .filter(row => Number(row.quantity || 0) > 0)
                .map(row => ({
                    product_id: item.product_id,
                    variant_id: row.variant_id ?? null,
                    unit_id: item.unit_id ?? null,
                    unit_name: item.unit_name ?? itemUnit(item),
                    quantity: Math.max(0, Math.round(Number(row.quantity || 0))),
                    cost_price: importPrice(item, row.cost_price),
                    sell_price: importPrice(item, row.sell_price),
                    supplier_warranty_value: item.supplier_warranty_value || null,
                    supplier_warranty_unit: item.supplier_warranty_unit || 'months',
                    extra_info: partyType.value === 'customer' && item.is_used
                        ? { is_used: true, account: item.extra_info?.account || null, password: item.extra_info?.password || null }
                        : null,
                    imeis: [],
                }))
        }

        // Sản phẩm IMEI
        if (item.type === 'imei') {
            return (item.imeis || []).map(imei => ({
                product_id: item.product_id,
                variant_id: null,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                quantity: 1,
                cost_price: importPrice(item, imei.cost_price ?? item.cost_price),
                sell_price: importPrice(item, imei.sell_price ?? item.sell_price),
                imeis: [imei],
            }))
        }

        // Sản phẩm biến thể + IMEI
        if (item.type === 'imei_variant') {
            return (item.imeis || []).map(imei => ({
                product_id: item.product_id,
                variant_id: imei.variant_id ?? null,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                quantity: 1,
                cost_price: importPrice(item, imei.cost_price ?? item.cost_price),
                sell_price: importPrice(item, imei.sell_price ?? item.sell_price),
                imeis: [imei],
            }))
        }

        return []
    })

    if (!form.items.length) {
        return
    }

    form.post('/stock-import', {
        preserveScroll: true,
        forceFormData: true,

        onSuccess: () => {
            emit('created')
        },

        onError: (errors) => {
            console.error('Lỗi validation phiếu nhập:', errors)
        },
    })
}
</script>

<template>
    <BaseModal
        title="Nhập hàng"
        size="2xl"
        body-class="p-3 sm:p-4"
        @close="emit('close')"
    >
        <div class="flex min-h-0 flex-col gap-2.5">

            <!-- NHÀ CUNG CẤP -->
            <SupplierSection
                :supplier="selectedSupplier"
                :party-type="partyType"
                :party-type-locked="false"
                :customer="selectedCustomer"
                :date="form.import_date"
                :error="form.errors.supplier_id"
                @selected="selectSupplier"
                @customer-selected="selectCustomer"
                @customer-draft="updateCustomerDraft"
                @update:party-type="setPartyType"
                @update:date="form.import_date = $event"
            />

            <div
                v-if="formErrorMessages.length"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <div
                    v-for="message in formErrorMessages"
                    :key="message"
                    class="leading-6"
                >
                    {{ message }}
                </div>
            </div>

            <!-- TÌM SẢN PHẨM -->
            <div :class="['relative transition-[z-index]', showResults ? 'z-50' : 'z-0']">
                <div class="relative z-10 flex items-start gap-2">
                    <div ref="productSearchRef" class="relative min-w-0 flex-1">
                        <FloatingInput
                            v-model="keyword"
                            type="text"
                            label="Tìm tên sản phẩm, SKU hoặc barcode"
                            input-class="pr-20"
                            @keydown.enter.prevent="scanProduct"
                        />

                        <div class="absolute right-11 top-1/2 flex -translate-y-1/2 items-center gap-1.5 text-slate-400">
                            <button v-if="keyword" type="button" class="p-1 hover:text-slate-600" aria-label="Xóa từ khóa tìm kiếm" @click="keyword = ''">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <BarcodeCameraButton class="absolute right-1 top-1/2 z-10 -translate-y-1/2 !h-8 !w-8" @scanned="(code) => { keyword = code; scanProduct() }" />

                        <!-- Keep the results attached to the search field and above the import form. -->
                        <div
                            v-if="showResults"
                            class="absolute left-0 right-0 top-full z-[100] mt-1 max-h-80 overflow-auto rounded-xl border border-slate-200 bg-white shadow-2xl"
                        >
                            <div v-if="searching" class="px-4 py-4 text-center text-sm text-slate-500">
                                Đang tìm sản phẩm...
                            </div>

                            <button
                                v-for="product in searchResults"
                                :key="product.id"
                                type="button"
                                class="flex w-full items-center gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
                                @click="selectProduct(product)"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                    <img v-if="imageUrl(product.image_url ?? product.image)" :src="imageUrl(product.image_url ?? product.image)" class="h-full w-full object-cover" />
                                    <Package v-else class="h-5 w-5 text-slate-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</div>
                                    <div class="mt-0.5 flex gap-3 text-xs text-slate-500">
                                        <span>SKU: {{ product.sku || '—' }}</span>
                                        <span>Tồn: {{ product.stock ?? 0 }}</span>
                                    </div>
                                </div>
                                <div class="text-sm font-bold text-slate-900">
                                    {{ formatPrice(product.cost_price ?? product.price ?? 0) }} {{ $currency() }}
                                </div>
                            </button>

                            <div v-if="!searching && !searchResults.length" class="px-4 py-6 text-center text-sm text-slate-400">
                                <p>Chưa có sản phẩm phù hợp với “{{ keyword }}”.</p>
                                <button
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700"
                                    @click="createProductFromSearch"
                                >
                                    <Plus class="h-4 w-4" /> Tạo sản phẩm mới
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- DANH SÁCH -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-200 bg-white px-3 py-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 font-semibold text-slate-900">
                            <Package class="h-4 w-4 text-blue-600" /> Danh sách hàng nhập
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ selectedItems.length }} mặt hàng</span>
                        </div>
                        <div class="text-sm font-semibold text-blue-700">{{ totalQuantity }} sản phẩm</div>
                    </div>
                    <div v-if="selectedItems.length" class="mt-1.5 flex flex-wrap gap-1.5">
                        <span v-if="itemTypeCounts.simple" class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">Hàng thường {{ itemTypeCounts.simple }}</span>
                        <span v-if="itemTypeCounts.variant" class="rounded-md bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700">Có biến thể {{ itemTypeCounts.variant }}</span>
                        <span v-if="itemTypeCounts.imei" class="rounded-md bg-orange-50 px-2 py-1 text-[11px] font-medium text-orange-700">Có IMEI {{ itemTypeCounts.imei }}</span>
                    </div>
                </div>

                <div
                    v-if="
                        !selectedItems.length
                    "
                    class="flex flex-col items-center py-12 text-center text-sm text-slate-400"
                >
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><Package class="h-6 w-6" /></div>
                    <span class="font-semibold text-slate-600">Chưa có sản phẩm trong phiếu</span>
                    <span class="mt-1 text-xs">Tìm sản phẩm đã có trong kho hoặc tạo mới nếu đây là mẫu máy chưa quản lý.</span>
                </div>

                <template v-else>
                <div class="divide-y divide-slate-100 sm:hidden">
                    <article
                        v-for="(item, itemIndex) in selectedItems"
                        :key="`mobile-${item.type}-${item.product_id}`"
                        class="p-3"
                    >
                        <div class="flex items-start gap-2.5">
                            <Package class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
                            <div class="min-w-0 flex-1">
                                <div class="break-words text-sm font-semibold leading-5 text-slate-900">{{ item.name }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <span v-if="item.type === 'variant' || item.type === 'imei_variant'" class="rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">{{ (item.rows || []).length }} biến thể</span>
                                    <span v-if="item.type === 'imei' || item.type === 'imei_variant'" class="rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700">{{ (item.imeis || []).length }} IMEI</span>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ itemUnit(item) }}</span>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50" title="Chỉnh sửa cấu hình" aria-label="Chỉnh sửa cấu hình" @click="productModal = { open: true, item }">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-lg text-red-500 hover:bg-red-50" title="Xóa sản phẩm" aria-label="Xóa sản phẩm" @click="removeItem(itemIndex)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                            <div>
                                <div class="text-slate-500">Số lượng</div>
                                <div class="mt-0.5 font-semibold text-slate-900">{{ itemQuantity(item) }} {{ itemUnit(item) }}</div>
                                <div v-if="itemConversionHint(item)" class="mt-0.5 text-[11px] font-medium text-indigo-600">{{ itemConversionHint(item) }}</div>
                            </div>
                            <div>
                                <div class="text-slate-500">Giá nhập</div>
                                <div class="mt-0.5 font-semibold text-slate-900">{{ itemCostPrice(item) !== null ? formatPrice(itemCostPrice(item)) : 'Nhiều giá' }}</div>
                            </div>
                            <div class="col-span-2 border-t border-slate-200 pt-2">
                                <div class="text-slate-500">Giá bán</div>
                                <div class="mt-0.5 font-semibold text-slate-900">{{ itemSellPrice(item) !== null ? formatPrice(itemSellPrice(item)) : 'Nhiều giá' }}</div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto sm:block">
                    <table
                        class="w-full min-w-[750px] text-sm"
                    >
                        <thead>
                            <tr
                                class="border-b bg-slate-50/70 text-xs font-semibold text-slate-500"
                            >
                                <th
                                    class="px-4 py-3 text-left"
                                >
                                    Sản phẩm
                                </th>

                                <th
                                    class="w-24 px-3 py-3 text-center"
                                >
                                    Đơn vị
                                </th>

                                <th
                                    class="w-28 px-3 py-3 text-center"
                                >
                                    Số lượng
                                </th>

                                <th
                                    class="w-36 px-3 py-3 text-right"
                                >
                                    Giá nhập
                                </th>

                                <th
                                    class="w-36 px-3 py-3 text-right"
                                >
                                    Giá bán
                                </th>

                                <th
                                    class="w-24 px-3 py-3 text-center"
                                >
                                    Thao tác
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-slate-100"
                        >
                            <tr
                                v-for="(
                                    item,
                                    itemIndex
                                ) in selectedItems"
                                :key="`${item.type}-${item.product_id}`"
                                class="hover:bg-slate-50/50"
                            >
                                <td
                                    class="px-4 py-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2.5"
                                    >
                                        <Package
                                            class="h-5 w-5 shrink-0 text-blue-600"
                                        />

                                        <div
                                            class="min-w-0 flex-1"
                                        >
                                            <div
                                                class="truncate font-semibold text-slate-900"
                                            >
                                                {{
                                                    item.name
                                                }}
                                            </div>

                                            <div
                                                class="mt-1 flex flex-wrap items-center gap-1.5"
                                            >
                                                <span
                                                    v-if="
                                                        item.type ===
                                                            'variant' ||
                                                        item.type ===
                                                            'imei_variant'
                                                    "
                                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10"
                                                >
                                                    {{
                                                        (
                                                            item.rows ||
                                                            []
                                                        ).length
                                                    }}
                                                    biến thể
                                                </span>

                                                <span
                                                    v-if="
                                                        item.type ===
                                                            'imei' ||
                                                        item.type ===
                                                            'imei_variant'
                                                    "
                                                    class="inline-flex items-center rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700 ring-1 ring-inset ring-orange-700/10"
                                                >
                                                    {{
                                                        (
                                                            item.imeis ||
                                                            []
                                                        ).length
                                                    }}
                                                    IMEI
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    class="px-3 py-3 text-center text-slate-600"
                                >
                                <div class="font-medium">
                                        {{
                                            itemUnit(
                                                item
                                            )
                                        }}
                                </div>

                                <div
                                    v-if="itemConversionHint(item)"
                                    class="mt-1 text-[11px] font-medium text-indigo-600"
                                >
                                    {{ itemConversionHint(item) }}
                                </div>

                                </td>

                                <td
                                    class="px-3 py-3 text-center font-semibold text-slate-900"
                                >
                                    {{
                                        itemQuantity(
                                            item
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-3 py-3 text-right"
                                >
                                    <span
                                        v-if="
                                            itemCostPrice(
                                                item
                                            ) !==
                                            null
                                        "
                                        class="font-medium text-slate-700"
                                    >
                                        {{
                                            formatPrice(
                                                itemCostPrice(
                                                    item
                                                )
                                            )
                                        }}
                                    </span>

                                    <span
                                        v-else
                                        class="text-xs text-slate-400"
                                    >
                                        Nhiều giá
                                    </span>
                                </td>

                                <td
                                    class="px-3 py-3 text-right"
                                >
                                    <span
                                        v-if="
                                            itemSellPrice(
                                                item
                                            ) !==
                                            null
                                        "
                                        class="font-medium text-slate-700"
                                    >
                                        {{
                                            formatPrice(
                                                itemSellPrice(
                                                    item
                                                )
                                            )
                                        }}
                                    </span>

                                    <span
                                        v-else
                                        class="text-xs text-slate-400"
                                    >
                                        Nhiều giá
                                    </span>
                                </td>

                                <td
                                    class="px-3 py-3 text-center"
                                >
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                productModal = {
                                                    open: true,
                                                    item,
                                                }
                                            "
                                            title="Chỉnh sửa cấu hình"
                                            class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 transition-colors"
                                        >
                                            <Pencil
                                                class="h-4 w-4"
                                            />
                                        </button>

                                        <button
                                            type="button"
                                            @click="
                                                removeItem(
                                                    itemIndex
                                                )
                                            "
                                            title="Xóa sản phẩm"
                                            class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 transition-colors"
                                        >
                                            <Trash2
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </template>

                <!-- TOTAL -->
                <div
                    v-if="
                        selectedItems.length
                    "
                    class="flex items-center justify-between gap-3 border-t bg-slate-50/50 px-3 py-3 sm:px-4"
                >
                    <span
                        class="font-semibold text-slate-700"
                    >
                        Tổng tiền
                    </span>

                    <span
                        class="text-right text-base font-bold text-green-600 sm:text-lg"
                    >
                        {{
                            formatPrice(
                                total
                            )
                        }}
                    </span>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <template #footer>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
            >
                <div
                    class="text-center text-xs text-slate-500 sm:text-left sm:text-sm"
                >
                    <span
                        class="font-semibold text-slate-700"
                    >
                        {{
                            selectedItems.length
                        }}
                    </span>
                    dòng /

                    <span
                        class="font-semibold text-slate-700"
                    >
                        {{
                            totalQuantity
                        }}
                    </span>
                    sản phẩm
                </div>

                <div
                    class="grid grid-cols-2 gap-2 sm:ml-auto sm:flex"
                >
                    <button
                        v-if="partyType === 'customer'"
                        type="button"
                        @click="printPurchaseContract"
                        :disabled="!selectedItems.some(item => itemQuantity(item) > 0) || (partyType === 'customer' ? !customerDraft.full_name?.trim() : !selectedSupplier)"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Printer class="h-4 w-4" /> In hợp đồng
                    </button>
                    <button
                        type="button"
                        @click="
                            emit('close')
                        "
                        class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Hủy
                    </button>

                    <button
                        type="button"
                        @click="confirm"
                        :disabled="
                            !canConfirm
                        "
                        class="min-h-11 rounded-lg bg-slate-800 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Tạo phiếu nhập
                    </button>
                </div>
            </div>
        </template>

        <!-- MODAL CẤU HÌNH SẢN PHẨM -->
        <StockImportProductModal
            v-if="
                productModal.open &&
                productModal.item
            "
            :item="
                productModal.item
            "
            :customer-import="partyType === 'customer'"
            @close="
                closeProductModal
            "
            @completed="
                handleProductCompleted
            "
        />
        <PrintPreviewModal :show="Boolean(printPreviewHtml)" title="Xem trước hợp đồng mua bán" :html="printPreviewHtml" @close="printPreviewHtml = ''" />
    </BaseModal>
</template>
