<script setup>
import {
    computed,
    ref,
    watch,
    nextTick,
    onMounted,
    onBeforeUnmount,
} from 'vue'

import { useForm, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { openModal } from '@/Stores/modal'

import api from '@/Services/api'
import { findProductByScannedCode } from '@/Modules/POS/Product/Services/productBarcodeLookup'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import { formatNumber, formatDate } from '@/utils/format'

import BaseModal from '@/Components/UI/BaseModal.vue'
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { imageUrl } from '@/utils/imageUrl'

import SupplierSection from './SupplierSection.vue'
import SupplierPaymentModal from './SupplierPaymentModal.vue'
import StockImportProductModal from './StockImportProductModal.vue'
import ProductForm from '@/Pages/Products/Form.vue'

import {
    X,
    Package,
    Plus,
    Pencil,
    Trash2,
} from 'lucide-vue-next'

const props = defineProps({
    initialPartyType: { type: String, default: 'supplier' },
    initialImport: { type: Object, default: null },
    initialReplenishment: { type: Object, default: null },
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
const isEditingImport = computed(() => Boolean(props.initialImport))

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
    paid_amount: 0,
    tendered_amount: 0,
    change_amount: 0,
    old_debt_paid: 0,
    old_debt_allocations: [],
    payment_method: 'cash',
    payment_reference: '',
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
const productSearchDropdownRef = ref(null)
const productDropdownStyle = ref({})
let productSearchRequest = 0

let searchTimeout = null

const searchProducts = async () => {
    const value = keyword.value.trim()
    if (!value) {
        productSearchRequest++
        searchResults.value = []
        searching.value = false
        showResults.value = false
        return
    }
    const requestId = ++productSearchRequest

    searching.value = true
    showResults.value = true

    try {
        const res = await api.get(
            '/products/list-import',
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

const updateProductDropdownPosition = () => {
    const rect = productSearchRef.value?.getBoundingClientRect()
    if (!rect) return
    const gap = 6
    const below = Math.max(0, window.innerHeight - rect.bottom - 12)
    const above = Math.max(0, rect.top - 12)
    const openAbove = below < 180 && above > below
    const maxHeight = Math.max(120, Math.min(320, openAbove ? above : below))
    const left = Math.max(8, rect.left)
    productDropdownStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        width: `${Math.min(rect.width, window.innerWidth - left - 8)}px`,
        maxHeight: `${maxHeight}px`,
        ...(openAbove
            ? { bottom: `${Math.max(8, window.innerHeight - rect.top + gap)}px` }
            : { top: `${rect.bottom + gap}px` }),
    }
}

watch([showResults, searchResults], async ([visible, results]) => {
    if (!visible || (!results.length && !searching.value && !keyword.value.trim())) return
    await nextTick()
    updateProductDropdownPosition()
}, { deep: false })

const openProductSearch = () => {
    if (!keyword.value.trim()) return
    showResults.value = true
    if (searchResults.value.length || searching.value) return
    clearTimeout(searchTimeout)
    searchProducts()
}

watch(keyword, value => {
    clearTimeout(searchTimeout)
    productSearchRequest++
    searching.value = false

    if (!value.trim()) {
        searchResults.value = []
        showResults.value = false
        return
    }

    searchTimeout = setTimeout(
        searchProducts,
        300
    )
})

const createProductFromSearch = () => {
    const initialName = keyword.value.trim()
    if (!initialName) return
    showResults.value = false

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
    if (showResults.value && !productSearchRef.value?.contains(event.target) && !productSearchDropdownRef.value?.contains(event.target)) {
        showResults.value = false
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeResultsOnOutsideClick)
    window.addEventListener('resize', updateProductDropdownPosition)
    window.addEventListener('scroll', updateProductDropdownPosition, true)

    if (!props.initialImport && props.initialReplenishment?.items?.length) {
        hydrateReplenishmentDraft(props.initialReplenishment)
    }

})

onBeforeUnmount(() => {
    clearTimeout(searchTimeout)
    document.removeEventListener('pointerdown', closeResultsOnOutsideClick)
    window.removeEventListener('resize', updateProductDropdownPosition)
    window.removeEventListener('scroll', updateProductDropdownPosition, true)
})

/*
|--------------------------------------------------------------------------
| SELECTED ITEMS
|--------------------------------------------------------------------------
*/

const selectedItems = ref([])

const hydrateReplenishmentDraft = async (draft) => {
    const requestedItems = Array.isArray(draft?.items) ? draft.items : []
    const ids = [...new Set(requestedItems.map(item => Number(item.id)).filter(Boolean))]
    if (!ids.length) return

    const supplier = props.suppliers.find(item => String(item.id) === String(draft.supplierId))
    if (supplier) selectSupplier(supplier)

    try {
        // Ưu tiên dữ liệu được chuyển trực tiếp từ danh sách lấy hàng: dữ liệu
        // này chứa đầy đủ biến thể đang được chọn và không bị độ trễ API.
        const productsById = new Map(
            requestedItems
                .filter(item => item.product?.id)
                .map(item => [Number(item.product.id), item.product])
        )
        const missingIds = ids.filter(id => !productsById.has(id))
        if (missingIds.length) {
            const { data: products } = await api.get('/products/list-import', { params: { ids: missingIds } })
            ;(products || []).forEach(product => productsById.set(Number(product.id), product))
        }
        let needsConfiguration = false

        requestedItems.forEach(source => {
            const product = productsById.get(Number(source.id))
            if (!product) return

            const target = selectProduct(product, { configure: false })
            const quantity = Math.max(1, Number(source.quantity) || 1)
            const costPrice = Math.max(0, Number(source.cost_price) || 0)

            target.cost_price = costPrice
            target.cost_price_input = costPrice

            if (target.type === 'simple') {
                target.quantity = quantity
            } else if (target.type === 'variant' && source.variant_id) {
                const variant = (target.available_variants || target.variants || [])
                    .find(option => Number(option.id) === Number(source.variant_id))
                if (!variant) return

                const existingRow = target.rows.find(row => Number(row.variant_id) === Number(variant.id))
                const row = {
                    variant_id: variant.id,
                    variant,
                    quantity,
                    cost_price: costPrice,
                    sell_price: Number(variant.sell_price ?? target.sell_price ?? 0),
                }
                if (existingRow) Object.assign(existingRow, row)
                else target.rows.push(row)
            } else {
                // The replenishment list has no variant/IMEI identity. Keep the
                // product in the receipt and require the user to choose that data.
                target.replenishment_quantity = quantity
                needsConfiguration = true
            }
        })

        if (!selectedItems.value.length) {
            toast.error('Không tìm thấy sản phẩm nào trong danh sách lấy hàng.')
            return
        }

        toast.success(needsConfiguration
            ? 'Đã áp dụng danh sách. Hãy chọn biến thể hoặc IMEI cho các sản phẩm cần quản lý riêng.'
            : 'Đã áp dụng danh sách lấy hàng vào phiếu nhập.')
    } catch (error) {
        console.error('Không thể áp dụng danh sách lấy hàng:', error)
        toast.error('Không thể tải sản phẩm từ danh sách lấy hàng. Vui lòng thử lại.')
    }
}

// Modal có thể được tạo trước khi Vue truyền xong prop từ danh sách lấy hàng.
// Watch ngay lập tức để không bỏ lỡ lần truyền đó.
let replenishmentDraftKey = ''
watch(() => props.initialReplenishment, draft => {
    if (props.initialImport || !draft?.items?.length) return
    const key = `${draft.supplierId || ''}:${draft.items.map(item => `${item.id}-${item.variant_id || ''}-${item.quantity}-${item.cost_price}`).join('|')}`
    if (key === replenishmentDraftKey) return
    replenishmentDraftKey = key
    hydrateReplenishmentDraft(draft)
}, { deep: true })

const hydrateInitialImport = (stockImport) => {
    if (!stockImport) return

    partyType.value = stockImport.party_type || 'supplier'
    form.party_type = partyType.value
    form.supplier_id = stockImport.supplier_id || null
    form.customer_id = stockImport.customer_id || null
    form.party_name = stockImport.party_name || stockImport.customer?.full_name || stockImport.supplier?.name || ''
    form.party_phone = stockImport.party_phone || stockImport.customer?.phone || stockImport.supplier?.phone || ''
    form.party_address = stockImport.party_address || stockImport.customer?.address || stockImport.supplier?.address || ''
    form.party_cccd = stockImport.party_cccd || stockImport.customer?.cccd || ''
    form.import_date = stockImport.import_date?.slice(0, 10) || form.import_date
    form.discount = Number(stockImport.discount || 0)
    form.extra_fee = Number(stockImport.extra_fee || 0)
    form.paid_amount = Number(stockImport.paid_amount || 0)
    form.tendered_amount = Number(stockImport.tendered_amount || 0)
    form.change_amount = Number(stockImport.change_amount || 0)
    form.old_debt_paid = Number(stockImport.old_debt_paid || 0)
    form.old_debt_allocations = stockImport.old_debt_allocations || []
    form.payment_method = stockImport.payment_method || 'cash'
    form.payment_reference = stockImport.payment_reference || ''
    form.note = stockImport.note || ''
    selectedSupplier.value = stockImport.supplier || null
    selectedCustomer.value = stockImport.customer || null
    customerDraft.value = {
        full_name: form.party_name || 'Khách lẻ', phone: form.party_phone,
        address: form.party_address, cccd: form.party_cccd,
    }

    // Decimal từ Laravel có thể là chuỗi "0.00" (truthy trong JS), nên không
    // dùng toán tử || để chọn giá dự phòng từ lịch sử IMEI/dòng nhập.
    const storedPrice = (...values) => {
        const positive = values.find(value => Number(value || 0) > 0)
        if (positive !== undefined) return Number(positive)
        const present = values.find(value => value !== null && value !== undefined && value !== '')
        return Number(present || 0)
    }

    // Giá bán đã lưu nằm ở lô tồn kho của dòng nhập (theo đơn vị cơ sở). Nạp lại
    // đúng giá đó (quy về đơn vị nhập) để sửa phiếu không vô tình ghi đè bằng giá
    // bán hiện tại của sản phẩm/biến thể; chỉ phiếu cũ không có lô mới dùng giá hiện tại.
    const importFactor = source => Math.max(1, Number(source.conversion_factor || 1))
    const savedSellPrice = source => {
        const batchPrice = Math.round(Number(source.stock_batch?.sell_price || 0) * importFactor(source))
        const currentPrice = Math.round(storedPrice(source.variant?.sell_price, source.product?.sell_price) * importFactor(source))
        return storedPrice(batchPrice, currentPrice)
    }

    const grouped = new Map()
    ;(stockImport.items || []).forEach(source => {
        const imeis = (source.import_imeis || []).map(history => ({
            imei: history.product_imei?.imei || history.imei,
            variant_id: history.product_imei?.variant_id || source.variant_id || null,
            variant: history.product_imei?.variant || source.variant || null,
            // Giá hiện tại của IMEI đi trước lịch sử nhập: nếu giá bán đã chỉnh ở trang
            // sản phẩm thì sửa phiếu không được đặt lại về giá cũ lúc nhập.
            cost_price: storedPrice(history.product_imei?.cost_price, history.cost_price, source.cost_price),
            sell_price: storedPrice(history.product_imei?.sell_price, history.sell_price, source.variant?.sell_price, source.product?.sell_price),
            extra_info: history.product_imei?.extra_info || history.meta?.extra_info || null,
            warranty_duration_value: null,
            warranty_duration_unit: 'months',
        }))
        const type = imeis.length ? (source.variant_id ? 'imei_variant' : 'imei') : (source.variant_id ? 'variant' : 'simple')
        const key = `${source.product_id}-${type}`
        let item = grouped.get(key)
        if (!item) {
            item = {
                type, product_id: source.product_id, name: source.product?.name || 'Sản phẩm',
                unit: source.unit_name || source.unit?.short_name || 'Cái', unit_id: source.unit_id,
                unit_name: source.unit_name, has_unit_conversion: Number(source.conversion_factor || 1) > 1,
                price_unit_basis: 'conversion', conversion_factor: Number(source.conversion_factor || 1),
                base_unit_name: source.product?.unit?.short_name || 'Cái', conversion_unit: source.product?.conversion_unit || null,
                quantity: 0,
                cost_price: storedPrice(source.cost_price),
                sell_price: savedSellPrice(source),
                has_vat: Boolean(source.has_vat), extra_info: source.extra_info || {},
                supplier_warranty_value: source.supplier_warranty_value || null,
                supplier_warranty_unit: source.supplier_warranty_unit || 'months',
                imeis: [], rows: [], available_variants: source.product?.variants || [], variants: source.product?.variants || [],
            }
            grouped.set(key, item)
        }
        if (type === 'simple') item.quantity += Number(source.quantity || 0)
        else if (type === 'variant') item.rows.push({
            variant_id: source.variant_id,
            variant: source.variant,
            quantity: Number(source.quantity || 0),
            cost_price: storedPrice(source.cost_price, source.variant?.cost_price),
            sell_price: savedSellPrice(source),
        })
        else item.imeis.push(...imeis)
    })
    selectedItems.value = [...grouped.values()]
}

if (props.initialImport) hydrateInitialImport(props.initialImport)

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
const defaultContractContent = 'Bên bán cam kết điện thoại ghi trong hợp đồng này thuộc quyền sở hữu hợp pháp của mình hoặc mình được chủ sở hữu ủy quyền hợp pháp để bán; điện thoại không do trộm cắp, cướp giật, lừa đảo, chiếm đoạt hoặc vi phạm pháp luật mà có. Bên bán chịu trách nhiệm trước pháp luật nếu cam kết này không đúng.'

const renderContractContent = (template, values) => {
    let content = escapeHtml(template)
    Object.entries(values).forEach(([key, value]) => {
        content = content.replaceAll(`{{${key}}}`, escapeHtml(value))
    })
    return content.replace(/\r?\n/g, '<br>')
}

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
    const settings = page.props.settings || {}
    const buyerName = settings.shop_name || page.props.auth?.user?.name || 'Cửa hàng'
    const contractCode = `IMP-${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`
    const contentTemplate = settings.purchase_contract_content ?? defaultContractContent
    const contractContent = renderContractContent(contentTemplate, {
        date: formatDate(new Date()),
        delivery_date: formatDate(form.import_date),
        import_code: contractCode,
        buyer_name: buyerName,
        seller_name: party.name,
        total: `${Math.max(0, totalAmount - Number(form.discount || 0) + Number(form.extra_fee || 0)).toLocaleString('vi-VN')} đ`,
        note: form.note || '',
    })
    const title = escapeHtml(settings.purchase_contract_title || 'Hợp đồng mua bán điện thoại')
    printPreviewHtml.value = `<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>${title}</title><style>@page{size:A4;margin:18mm}*{box-sizing:border-box}body{font:13px/1.55 Arial,sans-serif;color:#111;margin:0}h1{text-align:center;font-size:20px;margin:0 0 4px;text-transform:uppercase}h2{text-align:center;font-size:14px;margin:0 0 20px;font-weight:400}p{margin:5px 0}.parties{margin:18px 0}.party{margin:10px 0}table{width:100%;border-collapse:collapse;margin:14px 0}th,td{border:1px solid #555;padding:7px;vertical-align:top}th{background:#f3f4f6;text-align:center}.num{text-align:right;white-space:nowrap}.total{text-align:right;font-size:15px;font-weight:bold}.terms{margin-top:14px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:55px;text-align:center}.signatures strong{display:block}.signatures span{font-size:11px;font-style:italic}</style></head><body><h1>${title}</h1><h2>Số: ${escapeHtml(contractCode)}</h2><p>Hôm nay, ngày ${escapeHtml(formatDate(new Date()))}, các bên gồm:</p><div class="parties"><div class="party"><strong>BÊN MUA (CỬA HÀNG):</strong> ${escapeHtml(buyerName)}<br>Đại diện: ${escapeHtml(page.props.auth?.user?.name || '')}</div><div class="party"><strong>BÊN BÁN:</strong> ${escapeHtml(party.name)}<br>Số điện thoại: ${escapeHtml(party.phone || '—')}<br>CCCD: ${escapeHtml(party.cccd || '—')}<br>Địa chỉ: ${escapeHtml(party.address || '—')}</div></div><table><thead><tr><th>STT</th><th>Tên hàng / thông tin</th><th>ĐVT</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>${tableRows}</tbody></table><p class="total">Tổng tiền hàng: ${totalAmount.toLocaleString('vi-VN')} đ</p><p>Giảm giá: ${Number(form.discount || 0).toLocaleString('vi-VN')} đ</p><p>Phí khác: ${Number(form.extra_fee || 0).toLocaleString('vi-VN')} đ</p><p class="total">Tổng thanh toán: ${Math.max(0, totalAmount - Number(form.discount || 0) + Number(form.extra_fee || 0)).toLocaleString('vi-VN')} đ</p><div class="terms">${contractContent}</div><div class="signatures"><div><strong>BÊN MUA</strong><span>(Ký, ghi rõ họ tên)</span></div><div><strong>BÊN BÁN</strong><span>(Ký, ghi rõ họ tên)</span><br><br>${escapeHtml(party.name)}</div></div></body></html>`
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
    clearTimeout(searchTimeout)
    productSearchRequest++
    searchResults.value = []
    searching.value = false
    showResults.value = false
    keyword.value = ''

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
            has_vat: Boolean(page.props.settings?.stock_import_vat_default),
            extra_info: item.last_customer_warranty?.value > 0
                ? {
                    customer_warranty_duration_value: item.last_customer_warranty.value,
                    customer_warranty_duration_unit: item.last_customer_warranty.unit || 'months',
                }
                : {},

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

const variantCount = (item) => item.type === 'imei_variant'
    ? new Set((item.imeis || []).map(row => row?.variant_id).filter(value => value !== null && value !== undefined).map(String)).size
    : (item.rows || []).length
const paymentActionError = ref('')

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

const vatRate = computed(() => Math.min(100, Math.max(0, Number(page.props.settings?.stock_import_vat_rate ?? 10))))

const itemVatBase = (item) => {
    if (item.type === 'simple') {
        return Math.max(0, Number(item.quantity || 0)) * importPrice(item, item.sell_price)
    }

    const variantTotal = (item.rows || []).reduce(
        (sum, row) => sum + Math.max(0, Number(row.quantity || 0)) * importPrice(item, row.sell_price),
        0
    )
    const imeiTotal = (item.imeis || []).reduce(
        (sum, row) => sum + importPrice(item, row.sell_price),
        0
    )

    return variantTotal + imeiTotal
}

const itemVatSuggestion = (item) => item.has_vat
    ? Math.round(itemVatBase(item) * vatRate.value / 100)
    : 0

/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
*/

// API và phiếu nhập lưu giá theo đơn vị nhập; nếu người dùng nhập theo
// đơn vị cơ sở thì đổi sang giá mỗi đơn vị nhập trước khi gửi.
const importPrice = (item, value) => {
    const price = Number(value || 0)
    if (!item.has_unit_conversion || item.price_unit_basis !== 'base') return price
    return price * Math.max(1, Number(item.conversion_factor || 1))
}

const itemExtraInfo = (item) => {
    const source = item?.extra_info && typeof item.extra_info === 'object' ? item.extra_info : {}
    const warrantyValue = Number(source.customer_warranty_duration_value || 0)
    const extraInfo = {
        ...(partyType.value === 'customer' && item.is_used
            ? { is_used: true, account: source.account || null, password: source.password || null }
            : {}),
        customer_warranty_duration_value: warrantyValue > 0 ? warrantyValue : null,
        customer_warranty_duration_unit: warrantyValue > 0 ? (source.customer_warranty_duration_unit || 'months') : null,
        note: source.note || null,
    }
    const cleaned = Object.fromEntries(Object.entries(extraInfo).filter(([, value]) => value !== null && value !== ''))
    return Object.keys(cleaned).length ? cleaned : null
}

const handlePaymentAction = () => {
    paymentActionError.value = ''

    if (partyType.value === 'supplier' && !selectedSupplier.value) {
        paymentActionError.value = 'Vui lòng chọn nhà cung cấp trước khi tiếp tục.'
        return
    }
    if (partyType.value === 'customer' && !customerDraft.value.full_name?.trim()) {
        paymentActionError.value = 'Vui lòng nhập tên khách hàng trước khi tiếp tục.'
        return
    }
    if (!selectedItems.value.some(item => itemQuantity(item) > 0)) {
        paymentActionError.value = 'Vui lòng thêm ít nhất một sản phẩm có số lượng hợp lệ.'
        return
    }

    if (partyType.value === 'supplier') {
        openModal(SupplierPaymentModal, {
            props: {
                supplier: selectedSupplier.value,
                total: Number(total.value || 0),
                editMode: isEditingImport.value,
                initialPayment: isEditingImport.value ? {
                    payment_method: form.payment_method,
                    tendered_amount: form.tendered_amount,
                    paid_amount: form.paid_amount,
                    old_debt_paid: form.old_debt_paid,
                    payment_reference: form.payment_reference,
                } : {},
            },
            onUpdated: payment => confirm(payment),
        })
        return
    }

    confirm({ payment_method: 'cash', paid_amount: 0 })
}

const confirm = (payment = {}) => {

    form.party_type = partyType.value
    form.supplier_id = partyType.value === 'supplier' ? selectedSupplier.value.id : null
    form.customer_id = partyType.value === 'customer' ? selectedCustomer.value?.id ?? null : null
    form.party_name = partyType.value === 'customer' ? customerDraft.value.full_name.trim() : selectedSupplier.value.name
    form.party_phone = partyType.value === 'customer' ? customerDraft.value.phone : (selectedSupplier.value.phone || '')
    form.party_address = partyType.value === 'customer' ? customerDraft.value.address : (selectedSupplier.value.address || '')
    form.party_cccd = partyType.value === 'customer' ? customerDraft.value.cccd : ''
    form.paid_amount = partyType.value === 'supplier'
        ? Math.min(Math.max(0, Number(payment.paid_amount || 0)), Number(total.value || 0))
        : 0
    form.tendered_amount = partyType.value === 'supplier'
        ? Math.max(0, Number(payment.tendered_amount || 0))
        : 0
    form.change_amount = partyType.value === 'supplier'
        ? Math.max(0, Number(payment.change_amount || 0))
        : 0
    form.payment_method = payment.payment_method || 'cash'
    form.old_debt_paid = partyType.value === 'supplier'
        ? Math.max(0, Number(payment.old_debt_paid || 0))
        : 0
    form.old_debt_allocations = partyType.value === 'supplier'
        ? (payment.old_debt_allocations || [])
        : []
    form.payment_reference = partyType.value === 'supplier' ? (payment.payment_reference || '') : ''

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
                has_vat: Boolean(item.has_vat),
                supplier_warranty_value: item.supplier_warranty_value || null,
                supplier_warranty_unit: item.supplier_warranty_unit || 'months',
                extra_info: itemExtraInfo(item),
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
                    has_vat: Boolean(item.has_vat),
                    supplier_warranty_value: item.supplier_warranty_value || null,
                    supplier_warranty_unit: item.supplier_warranty_unit || 'months',
                    extra_info: itemExtraInfo(item),
                    imeis: [],
                }))
        }

        // Sản phẩm IMEI
        if (item.type === 'imei') {
            const groups = new Map()
            ;(item.imeis || []).forEach(imei => {
                const costPrice = importPrice(item, imei.cost_price ?? item.cost_price)
                const sellPrice = importPrice(item, imei.sell_price ?? item.sell_price)
                const key = `${costPrice}-${sellPrice}`
                if (!groups.has(key)) groups.set(key, { costPrice, sellPrice, imeis: [] })
                groups.get(key).imeis.push(imei)
            })
            return [...groups.values()].map(group => ({
                product_id: item.product_id,
                variant_id: null,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                quantity: group.imeis.length,
                cost_price: group.costPrice,
                sell_price: group.sellPrice,
                has_vat: Boolean(item.has_vat),
                supplier_warranty_value: item.supplier_warranty_value || null,
                supplier_warranty_unit: item.supplier_warranty_unit || 'months',
                extra_info: itemExtraInfo(item),
                imeis: group.imeis,
            }))
        }

        // Sản phẩm biến thể + IMEI
        if (item.type === 'imei_variant') {
            const groups = new Map()
            ;(item.imeis || []).forEach(imei => {
                const variantId = imei.variant_id ?? null
                const costPrice = importPrice(item, imei.cost_price ?? item.cost_price)
                const sellPrice = importPrice(item, imei.sell_price ?? item.sell_price)
                const key = `${variantId ?? 'none'}-${costPrice}-${sellPrice}`
                if (!groups.has(key)) groups.set(key, { variantId, costPrice, sellPrice, imeis: [] })
                groups.get(key).imeis.push(imei)
            })
            return [...groups.values()].map(group => ({
                product_id: item.product_id,
                variant_id: group.variantId,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                quantity: group.imeis.length,
                cost_price: group.costPrice,
                sell_price: group.sellPrice,
                has_vat: Boolean(item.has_vat),
                supplier_warranty_value: item.supplier_warranty_value || null,
                supplier_warranty_unit: item.supplier_warranty_unit || 'months',
                extra_info: itemExtraInfo(item),
                imeis: group.imeis,
            }))
        }

        return []
    })

    if (!form.items.length) {
        return
    }

    if (props.initialImport) {
        // PHP không parse multipart body của PUT/PATCH. Phiếu nhập có thể mang
        // ảnh trong thông tin IMEI, nên gửi POST + method spoofing để toàn bộ
        // party_type, ngày nhập và items luôn đến được backend.
        form.transform(data => ({ ...data, _method: 'put' })).post(`/stock-import/${props.initialImport.id}`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Đã cập nhật phiếu nhập thành công.')
                emit('created')
            },
            onError: (errors) => {
                paymentActionError.value = Object.values(errors || {}).flat().filter(Boolean).join(' ')
                    || 'Không cập nhật được phiếu nhập. Vui lòng kiểm tra lại thông tin.'
            },
        })
        return
    }

    form.post('/stock-import', {
        preserveScroll: true,
        forceFormData: true,

        onSuccess: () => {
            toast.success(Number(form.paid_amount || 0) + Number(form.old_debt_paid || 0) > 0
                ? 'Đã lưu phiếu nhập và ghi nhận thanh toán thành công.'
                : 'Đã lưu phiếu nhập, công nợ đã được ghi nhận.')
            emit('created')
        },

        onError: (errors) => {
            console.error('Lỗi validation phiếu nhập:', errors)
            paymentActionError.value = Object.values(errors || {}).flat().filter(Boolean).join(' ')
                || 'Không lưu được phiếu nhập. Vui lòng kiểm tra lại thông tin.'
        },
    })
}
</script>

<template>
    <BaseModal
        :title="isEditingImport ? 'Sửa phiếu nhập' : 'Nhập hàng'"
        size="xl"
        body-class="p-3 sm:p-4"
        @close="emit('close')"
    >
        <div class="flex min-h-0 flex-col gap-2.5">

            <div v-if="isEditingImport" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                <div><span class="font-bold">Đang sửa phiếu {{ initialImport.code }}</span><span class="ml-2 text-amber-700">· Thông tin thanh toán hiện có được giữ nguyên.</span></div>
                <span class="rounded-full border border-amber-300 bg-white px-2 py-0.5 font-bold text-amber-700">Chế độ chỉnh sửa</span>
            </div>

            <!-- NHÀ CUNG CẤP -->
            <SupplierSection
                :supplier="selectedSupplier"
                :party-type="partyType"
                :party-type-locked="isEditingImport"
                :customer="selectedCustomer"
                :can-print-contract="selectedItems.some(item => itemQuantity(item) > 0) && Boolean(customerDraft.full_name?.trim())"
                :date="form.import_date"
                :error="form.errors.supplier_id"
                @selected="selectSupplier"
                @customer-selected="selectCustomer"
                @customer-draft="updateCustomerDraft"
                @update:party-type="setPartyType"
                @update:date="form.import_date = $event"
                @print-contract="printPurchaseContract"
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
            <div v-if="paymentActionError" role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ paymentActionError }}</div>

            <!-- TÌM SẢN PHẨM -->
            <div :class="['relative transition-[z-index]', showResults ? 'z-50' : 'z-0']">
                <div class="relative z-10 flex items-start gap-2">
                    <div ref="productSearchRef" class="relative min-w-0 flex-1">
                        <FloatingInput
                            v-model="keyword"
                            type="text"
                            label="Tìm tên sản phẩm, SKU hoặc barcode"
                            input-class="pr-20"
                            @focus="openProductSearch"
                            @keydown.enter.prevent="scanProduct"
                        />

                        <div class="absolute right-11 top-1/2 flex -translate-y-1/2 items-center gap-1.5 text-slate-400">
                            <button v-if="keyword" type="button" class="p-1 hover:text-slate-600" aria-label="Xóa từ khóa tìm kiếm" @click="keyword = ''">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <BarcodeCameraButton class="absolute right-1 top-1/2 z-10 -translate-y-1/2 " @scanned="(code) => { keyword = code; scanProduct() }" />

                        <!-- Keep the results attached to the search field and above the import form. -->
                        <Teleport to="body">
                        <div
                            v-if="showResults"
                            ref="productSearchDropdownRef"
                            :style="productDropdownStyle"
                            class="z-[1200] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-2xl custom-scrollbar"
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

                            <div v-if="!searching && !searchResults.length" class="px-4 pt-5 text-center text-sm text-slate-400">
                                Chưa có sản phẩm phù hợp với “{{ keyword }}”.
                            </div>
                            <div v-if="keyword.trim()" class="flex justify-end border-t border-slate-100 bg-slate-50/70 p-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700"
                                    @click="createProductFromSearch"
                                >
                                    <Plus class="h-4 w-4" /> Thêm sản phẩm mới
                                </button>
                            </div>
                        </div>
                        </Teleport>
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
                                    <span v-if="item.type === 'variant' || item.type === 'imei_variant'" class="rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">{{ variantCount(item) }} biến thể</span>
                                    <span v-if="item.type === 'imei' || item.type === 'imei_variant'" class="rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700">{{ (item.imeis || []).length }} IMEI</span>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ itemUnit(item) }}</span>
                                    <label class="inline-flex min-h-6 items-center gap-1.5 rounded-md bg-emerald-50 px-2 text-[11px] font-medium text-emerald-800">
                                        <input v-model="item.has_vat" type="checkbox" class="h-3.5 w-3.5 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                                        VAT<span v-if="item.has_vat"> (Thuế dự kiến: {{ formatPrice(itemVatSuggestion(item)) }} {{ $currency() }})</span>
                                    </label>
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
                                                <label class="inline-flex min-h-6 items-center gap-1.5 rounded-md bg-emerald-50 px-2 text-[11px] font-medium text-emerald-800" @click.stop>
                                                    <input v-model="item.has_vat" type="checkbox" class="h-3.5 w-3.5 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                                                    VAT<span v-if="item.has_vat"> (Thuế dự kiến: {{ formatPrice(itemVatSuggestion(item)) }} {{ $currency() }})</span>
                                                </label>
                                                <span
                                                    v-if="
                                                        item.type ===
                                                            'variant' ||
                                                        item.type ===
                                                            'imei_variant'
                                                    "
                                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10"
                                                >
                                                    {{ variantCount(item) }}
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
                        type="button"
                        @click="
                            emit('close')
                        "
                        class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        {{ isEditingImport ? 'Hủy chỉnh sửa' : 'Hủy' }}
                    </button>

                    <button
                        type="button"
                        @click="handlePaymentAction"
                        :disabled="
                            form.processing
                        "
                        class="min-h-11 rounded-lg px-5 py-2 text-sm font-bold text-white shadow-sm disabled:cursor-wait disabled:opacity-60"
                        :class="isEditingImport ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700'"
                    >
                        {{ form.processing ? 'Đang cập nhật...' : (isEditingImport ? 'Lưu cập nhật phiếu' : 'Thanh toán') }}
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
            :vat-rate="vatRate"
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
