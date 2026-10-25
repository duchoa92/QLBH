<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import {
    ArrowLeft,
    AlertTriangle,
    Check,
    ChevronLeft,
    ChevronRight,
    FileSpreadsheet,
    ImageDown,
    LoaderCircle,
    Save,
    ShoppingBasket,
    X,
} from 'lucide-vue-next'

import api from '@/Services/api'
import { formatCurrency } from '@/utils/format'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import JSZip from 'jszip'

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const products = ref([])
const categories = ref([])
const selectedItems = ref([])
const loading = ref(true)
const loadError = ref('')
const search = ref('')
const categoryId = ref('')
const stockFilter = ref('all')
const supplierId = ref('')
const savedOrders = ref([])
const screen = ref('builder')
const previewOrder = ref(null)
const previewBackScreen = ref('builder')
const currentPage = ref(1)
const pageSize = 10
const savedOrdersStorageKey = 'stock-import-replenishment-orders-v1'

const selectedSupplier = computed(() =>
    props.suppliers.find(supplier => String(supplier.id) === String(supplierId.value)) || null
)

const stockStatus = product => {
    const stock = Number(product.stock || 0)
    if (stock <= 0) return 'out'
    return stock <= Number(product.alert_stock || 0) ? 'low' : 'available'
}

const filteredProducts = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase()
    return products.value.filter(product => {
        const matchesSearch = !keyword || [product.name, product.sku, product.barcode]
            .some(value => String(value || '').toLocaleLowerCase().includes(keyword))
        const matchesCategory = !categoryId.value || String(product.category_id || '') === String(categoryId.value)
        const matchesFilter = stockFilter.value === 'all' || stockStatus(product) === stockFilter.value
        return matchesSearch && matchesCategory && matchesFilter
    })
})

const pageCount = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / pageSize)))
const paginatedProducts = computed(() => {
    const start = (currentPage.value - 1) * pageSize
    return filteredProducts.value.slice(start, start + pageSize)
})
const firstVisibleProduct = computed(() => filteredProducts.value.length ? (currentPage.value - 1) * pageSize + 1 : 0)
const lastVisibleProduct = computed(() => Math.min(currentPage.value * pageSize, filteredProducts.value.length))

const outOfStockCount = computed(() => products.value.filter(product => Number(product.stock) <= 0).length)
const lowStockCount = computed(() => products.value.filter(product => stockStatus(product) === 'low').length)
const availableCount = computed(() => products.value.filter(product => stockStatus(product) === 'available').length)
const selectedIds = computed(() => new Set(selectedItems.value.map(item => Number(item.id))))
const totalEstimate = computed(() => selectedItems.value.reduce(
    (total, item) => total + Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.cost_price) || 0),
    0,
))

const loadProducts = async () => {
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await api.get('/stock-import/replenishment-products')
        products.value = Array.isArray(data.products) ? data.products : []
        categories.value = Array.isArray(data.categories) ? data.categories : []
    } catch (error) {
        console.error('Không thể tải sản phẩm cần nhập:', error)
        loadError.value = 'Không tải được danh sách tồn kho. Hãy thử tải lại.'
    } finally {
        loading.value = false
    }
}

const suggestedQuantity = product => Math.max(1, Math.ceil(Number(product.alert_stock || 0) - Number(product.stock || 0)))

const selectProduct = (product, checked) => {
    const existingIndex = selectedItems.value.findIndex(item => Number(item.id) === Number(product.id))
    if (!checked) {
        if (existingIndex !== -1) selectedItems.value.splice(existingIndex, 1)
        return
    }
    if (existingIndex !== -1) return
    selectedItems.value.push({
        id: product.id,
        name: product.name,
        sku: product.sku,
        unit_name: product.unit_name,
        quantity: suggestedQuantity(product),
        cost_price: Number(product.cost_price || 0),
    })
}

const selectAllVisible = () => {
    const visibleIds = new Set(paginatedProducts.value.map(product => Number(product.id)))
    const allSelected = paginatedProducts.value.length > 0
        && paginatedProducts.value.every(product => selectedIds.value.has(Number(product.id)))
    if (allSelected) {
        selectedItems.value = selectedItems.value.filter(item => !visibleIds.has(Number(item.id)))
        return
    }
    paginatedProducts.value.forEach(product => selectProduct(product, true))
}

const removeItem = id => {
    selectedItems.value = selectedItems.value.filter(item => Number(item.id) !== Number(id))
}

const persistSavedOrders = () => {
    localStorage.setItem(savedOrdersStorageKey, JSON.stringify(savedOrders.value))
}

const buildOrder = () => ({
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    code: `DH-${new Date().toLocaleDateString('vi-VN').replaceAll('/', '')}-${String(Date.now()).slice(-4)}`,
    savedAt: new Date().toISOString(),
    supplierName: selectedSupplier.value?.name || '',
    items: selectedItems.value.map(item => ({ ...item })),
})

const saveOrder = () => {
    if (!selectedItems.value.length) {
        toast.error('Hãy chọn ít nhất một sản phẩm để lưu đơn')
        return
    }

    try {
        const order = buildOrder()
        savedOrders.value.unshift(order)
        persistSavedOrders()
        toast.success(`Đã lưu ${order.code}`)
    } catch (error) {
        console.error('Không thể lưu đơn lấy hàng:', error)
        toast.error('Không thể lưu đơn trên thiết bị này')
    }
}

const showOrder = (order, fromScreen = 'builder') => {
    previewOrder.value = order
    previewBackScreen.value = fromScreen
    screen.value = 'preview'
}

const showCurrentOrder = () => {
    if (!selectedItems.value.length) return
    showOrder(buildOrder())
}

const safeFileName = value => String(value || 'don-lay-hang')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'don-lay-hang'

const downloadFile = (blob, fileName) => {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = fileName
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

const exportOrderImage = () => {
    if (!previewOrder.value) return

    const rows = previewOrder.value.items
    const width = 900
    const padding = 24
    const headerHeight = 52
    const rowHeights = []
    const canvas = document.createElement('canvas')
    const context = canvas.getContext('2d')
    if (!context) {
        toast.error('Trình duyệt không hỗ trợ xuất ảnh')
        return
    }

    context.font = '16px Arial, sans-serif'
    const wrapName = name => {
        const words = String(name || '').split(/\s+/)
        const lines = []
        let line = ''
        words.forEach(word => {
            const nextLine = line ? `${line} ${word}` : word
            if (line && context.measureText(nextLine).width > width - 250) {
                lines.push(line)
                line = word
            } else {
                line = nextLine
            }
        })
        if (line) lines.push(line)
        return lines.length ? lines : ['']
    }
    const wrappedNames = rows.map(item => wrapName(item.name))
    wrappedNames.forEach(lines => rowHeights.push(Math.max(48, lines.length * 22 + 20)))

    const height = padding * 2 + headerHeight + rowHeights.reduce((sum, rowHeight) => sum + rowHeight, 0)
    const scale = 2
    canvas.width = width * scale
    canvas.height = height * scale
    context.scale(scale, scale)
    context.fillStyle = '#ffffff'
    context.fillRect(0, 0, width, height)

    const columns = [
        { title: 'STT', x: padding, width: 70, align: 'center' },
        { title: 'Tên sản phẩm', x: padding + 70, width: 640, align: 'left' },
        { title: 'SL', x: padding + 710, width: 142, align: 'right' },
    ]
    let y = padding
    context.fillStyle = '#f1f5f9'
    context.fillRect(padding, y, width - padding * 2, headerHeight)
    context.font = 'bold 16px Arial, sans-serif'
    context.fillStyle = '#334155'
    columns.forEach(column => {
        context.textAlign = column.align
        const textX = column.align === 'right' ? column.x + column.width - 12
            : column.align === 'center' ? column.x + column.width / 2 : column.x + 12
        context.fillText(column.title, textX, y + 32)
    })
    y += headerHeight

    rows.forEach((item, index) => {
        const rowHeight = rowHeights[index]
        context.fillStyle = '#ffffff'
        context.fillRect(padding, y, width - padding * 2, rowHeight)
        context.strokeStyle = '#e2e8f0'
        context.lineWidth = 1
        context.strokeRect(padding, y, width - padding * 2, rowHeight)
        context.font = '16px Arial, sans-serif'
        context.fillStyle = '#64748b'
        context.textAlign = 'center'
        context.fillText(String(index + 1), columns[0].x + columns[0].width / 2, y + rowHeight / 2 + 5)
        context.fillStyle = '#0f172a'
        context.textAlign = 'left'
        wrappedNames[index].forEach((line, lineIndex) => {
            context.fillText(line, columns[1].x + 12, y + (rowHeight - wrappedNames[index].length * 22) / 2 + 17 + lineIndex * 22)
        })
        context.fillStyle = '#0f172a'
        context.textAlign = 'right'
        context.fillText(String(item.quantity ?? 0), columns[2].x + columns[2].width - 12, y + rowHeight / 2 + 5)
        y += rowHeight
    })

    canvas.toBlob(blob => {
        if (!blob) {
            toast.error('Không thể tạo ảnh đơn lấy hàng')
            return
        }
        downloadFile(blob, `${safeFileName(previewOrder.value.code)}.png`)
        toast.success('Đã xuất ảnh đơn lấy hàng')
    }, 'image/png')
}

const exportOrderExcel = async () => {
    if (!previewOrder.value) return

    try {
        const escapeXml = value => String(value ?? '').replace(/[<>&"']/g, character => ({
            '<': '&lt;',
            '>': '&gt;',
            '&': '&amp;',
            '"': '&quot;',
            "'": '&apos;',
        })[character])
        const stringCell = (reference, value) => `<c r="${reference}" t="inlineStr"><is><t xml:space="preserve">${escapeXml(value)}</t></is></c>`
        const numberCell = (reference, value) => `<c r="${reference}"><v>${Number.isFinite(Number(value)) ? Number(value) : 0}</v></c>`
        const rows = [
            ['STT', 'Tên sản phẩm', 'SL'].map((value, index) => stringCell(`${String.fromCharCode(65 + index)}1`, value)).join(''),
            ...previewOrder.value.items.map((item, index) => {
                const rowNumber = index + 2
                return [
                    numberCell(`A${rowNumber}`, index + 1),
                    stringCell(`B${rowNumber}`, item.name),
                    numberCell(`C${rowNumber}`, item.quantity),
                ].join('')
            }),
        ]
        const zip = new JSZip()
        zip.file('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>')
        zip.folder('_rels').file('.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>')
        zip.folder('xl').file('workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Danh sach lay hang" sheetId="1" r:id="rId1"/></sheets></workbook>')
        zip.folder('xl').folder('_rels').file('workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>')
        zip.folder('xl').folder('worksheets').file('sheet1.xml', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>${rows.map((row, index) => `<row r="${index + 1}">${row}</row>`).join('')}</sheetData></worksheet>`)

        const blob = await zip.generateAsync({
            type: 'blob',
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        })
        downloadFile(blob, `${safeFileName(previewOrder.value.code)}.xlsx`)
        toast.success('Đã xuất file Excel đơn lấy hàng')
    } catch (error) {
        console.error('Không thể xuất Excel đơn lấy hàng:', error)
        toast.error('Không thể xuất file Excel')
    }
}

const deleteSavedOrder = id => {
    try {
        savedOrders.value = savedOrders.value.filter(order => order.id !== id)
        persistSavedOrders()
        toast.success('Đã xóa đơn đã lưu')
    } catch (error) {
        console.error('Không thể xóa đơn lấy hàng:', error)
        toast.error('Không thể cập nhật danh sách đơn đã lưu')
    }
}

watch([search, categoryId, stockFilter], () => { currentPage.value = 1 })

onMounted(() => {
    try {
        const orders = JSON.parse(localStorage.getItem(savedOrdersStorageKey) || '[]')
        if (Array.isArray(orders)) savedOrders.value = orders

        const legacyDraft = JSON.parse(localStorage.getItem('stock-import-replenishment-draft-v1') || 'null')
        if (!savedOrders.value.length && Array.isArray(legacyDraft?.selectedItems) && legacyDraft.selectedItems.length) {
            savedOrders.value = [{
                ...buildOrder(),
                id: `legacy-${Date.now()}`,
                code: 'Đơn đã lưu trước đây',
                savedAt: legacyDraft.savedAt || new Date().toISOString(),
                supplierName: props.suppliers.find(supplier => String(supplier.id) === String(legacyDraft.supplierId))?.name || '',
                items: legacyDraft.selectedItems,
            }]
            persistSavedOrders()
            localStorage.removeItem('stock-import-replenishment-draft-v1')
        }
    } catch (error) {
        console.warn('Danh sách đơn lấy hàng đã lưu không đọc được:', error)
    }

    loadProducts()
})
</script>

<template>
    <BaseModal :title="screen === 'preview' ? previewOrder?.code : screen === 'saved' ? 'Đơn lấy hàng đã lưu' : 'Lập danh sách cần lấy hàng'" :size="screen === 'builder' ? '2xl' : 'lg'" body-class="p-0" @close="emit('close')">
        <div v-if="screen === 'preview' && previewOrder" class="mx-auto flex w-full max-w-6xl flex-col p-3 sm:p-6">
            <div class="mb-3 grid grid-cols-2 gap-2 sm:flex sm:items-center sm:justify-end">
                <button type="button" class="col-span-2 inline-flex min-h-9 items-center gap-1 justify-self-start rounded-lg px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 sm:col-span-1 sm:mr-auto sm:text-sm" @click="screen = previewBackScreen; previewOrder = null"><ArrowLeft class="h-4 w-4" /> Quay lại</button>
                <button type="button" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:gap-2 sm:px-3 sm:text-sm" title="Xuất bảng thành ảnh PNG" @click="exportOrderImage"><ImageDown class="h-4 w-4 shrink-0" /> Xuất ảnh</button>
                <button type="button" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-2 text-xs font-semibold text-white hover:bg-emerald-700 sm:gap-2 sm:px-3 sm:text-sm" title="Xuất bảng thành file Excel" @click="exportOrderExcel"><FileSpreadsheet class="h-4 w-4 shrink-0" /> Xuất Excel</button>
            </div>
            <div class="max-h-[calc(100dvh-14rem)] overflow-auto rounded-lg border border-slate-200">
                <table class="w-full table-fixed border-collapse text-xs sm:text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold text-slate-600">
                        <tr>
                            <th class="w-[12%] px-2 py-2 text-center sm:px-3 sm:py-3">STT</th>
                            <th class="w-[68%] px-2 py-2 sm:px-3 sm:py-3">Tên sản phẩm</th>
                            <th class="w-[20%] px-2 py-2 text-right sm:px-3 sm:py-3">SL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(item, index) in previewOrder.items" :key="`${item.id}-${index}`">
                            <td class="px-2 py-2 text-center text-slate-500 sm:px-3 sm:py-3">{{ index + 1 }}</td>
                            <td class="break-words px-2 py-2 font-medium text-slate-900 sm:px-3 sm:py-3">{{ item.name }}</td>
                            <td class="px-2 py-2 text-right font-semibold tabular-nums sm:px-3 sm:py-3">{{ item.quantity }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else-if="screen === 'saved'" class="p-4 sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-slate-500">{{ savedOrders.length }} đơn đã lưu trên thiết bị này</p>
                <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="screen = 'builder'">Quay lại lập đơn</button>
            </div>
            <div v-if="!savedOrders.length" class="flex min-h-48 flex-col items-center justify-center text-center text-slate-400">
                <ShoppingBasket class="h-8 w-8" />
                <p class="mt-3 text-sm">Chưa có đơn nào được lưu.</p>
            </div>
            <div v-else class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                <div v-for="order in savedOrders" :key="order.id" class="flex flex-wrap items-center gap-3 p-3 sm:px-4">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-slate-900">{{ order.code }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ order.supplierName || 'Chưa chọn nhà cung cấp' }} · {{ order.items.length }} mặt hàng · {{ new Date(order.savedAt).toLocaleString('vi-VN') }}</div>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">{{ formatCurrency(order.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.cost_price || 0), 0)) }}</div>
                    <button type="button" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="showOrder(order, 'saved')">Xem đơn</button>
                    <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Xóa ${order.code}`" @click="deleteSavedOrder(order.id)"><X class="h-4 w-4" /></button>
                </div>
            </div>
        </div>

        <div v-else class="grid min-h-0 grid-cols-1 lg:grid-cols-[minmax(0,1.05fr)_minmax(440px,1fr)]">
            <section class="min-w-0 border-b border-slate-200 lg:border-b-0 lg:border-r">
                <div class="space-y-2.5 border-b border-slate-100 bg-slate-50/70 p-2.5 sm:p-3">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button type="button" class="rounded-lg border px-2.5 py-1.5 text-left transition" :class="stockFilter === 'all' ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="stockFilter = 'all'">
                            <span class="block text-base font-bold leading-4">{{ products.length }}</span><span class="mt-0.5 block text-[10px]">Tất cả</span>
                        </button>
                        <button type="button" class="rounded-lg border px-2.5 py-1.5 text-left transition" :class="stockFilter === 'out' ? 'border-red-600 bg-red-600 text-white' : 'border-red-100 bg-white text-red-700 hover:bg-red-50'" @click="stockFilter = 'out'">
                            <span class="block text-base font-bold leading-4">{{ outOfStockCount }}</span><span class="mt-0.5 block text-[10px]">Hết hàng</span>
                        </button>
                        <button type="button" class="rounded-lg border px-2.5 py-1.5 text-left transition" :class="stockFilter === 'low' ? 'border-amber-500 bg-amber-500 text-white' : 'border-amber-100 bg-white text-amber-700 hover:bg-amber-50'" @click="stockFilter = 'low'">
                            <span class="block text-base font-bold leading-4">{{ lowStockCount }}</span><span class="mt-0.5 block text-[10px]">Gần hết hàng</span>
                        </button>
                        <button type="button" class="col-span-2 rounded-lg border px-2.5 py-1.5 text-left transition sm:col-span-1" :class="stockFilter === 'available' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-emerald-100 bg-white text-emerald-700 hover:bg-emerald-50'" @click="stockFilter = 'available'">
                            <span class="block text-base font-bold leading-4">{{ availableCount }}</span><span class="mt-0.5 block text-[10px]">Còn hàng</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_200px]">
                        <FloatingInput
                            v-model="search"
                            name="product_search"
                            label="Tìm kiếm"
                            placeholder-text="Tên hàng hoặc mã SKU..."
                            type="search"
                            input-class="h-10"
                        />
                        <FloatingSelect
                            v-model="categoryId"
                            name="category_filter"
                            label="Danh mục"
                            :show-placeholder="false"
                            :options="[
                                { value: '', label: 'Tất cả danh mục' },
                                ...categories.map(category => ({ value: category.id, label: category.name }))
                            ]"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2 sm:px-4">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                        <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" :checked="paginatedProducts.length > 0 && paginatedProducts.every(product => selectedIds.has(Number(product.id)))" @change="selectAllVisible" />
                        Chọn trang này
                    </label>
                    <span class="text-xs text-slate-400">{{ firstVisibleProduct }}–{{ lastVisibleProduct }} / {{ filteredProducts.length }} mặt hàng</span>
                </div>

                <div class="max-h-[52dvh] overflow-y-auto lg:max-h-[calc(100dvh-18rem)]">
                    <div v-if="loading" class="flex min-h-48 items-center justify-center gap-2 text-sm text-slate-500">
                        <LoaderCircle class="h-4 w-4 animate-spin" /> Đang tải tồn kho...
                    </div>
                    <div v-else-if="loadError" class="p-8 text-center">
                        <AlertTriangle class="mx-auto h-8 w-8 text-amber-500" />
                        <p class="mt-2 text-sm text-slate-600">{{ loadError }}</p>
                        <button type="button" class="mt-3 rounded-lg border px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="loadProducts">Tải lại</button>
                    </div>
                    <div v-else-if="!filteredProducts.length" class="flex min-h-48 flex-col items-center justify-center px-6 text-center">
                        <Check class="h-8 w-8 rounded-full bg-emerald-50 p-1.5 text-emerald-600" />
                        <p class="mt-3 text-sm font-semibold text-slate-800">{{ products.length ? 'Không tìm thấy mặt hàng phù hợp' : 'Kho chưa có mặt hàng cần bổ sung' }}</p>
                        <p class="mt-1 text-xs text-slate-500">Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc tồn kho.</p>
                    </div>
                    <div v-else class="divide-y divide-slate-100">
                        <label v-for="product in paginatedProducts" :key="product.id" class="flex cursor-pointer items-start gap-3 px-3 py-2 transition hover:bg-blue-50/50 sm:px-4">
                            <input type="checkbox" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" :checked="selectedIds.has(Number(product.id))" @change="selectProduct(product, $event.target.checked)" />
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="stockStatus(product) === 'out' ? 'bg-red-50 text-red-700' : stockStatus(product) === 'low' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">{{ stockStatus(product) === 'out' ? 'Hết hàng' : stockStatus(product) === 'low' ? 'Gần hết' : 'Còn hàng' }}</span>
                                </span>
                                <span class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                    <span>SKU: {{ product.sku || '—' }}</span>
                                    <span>Tồn: <b :class="stockStatus(product) === 'out' ? 'text-red-600' : stockStatus(product) === 'low' ? 'text-amber-700' : 'text-emerald-700'">{{ product.stock }} {{ product.unit_name }}</b></span>
                                    <span>Mức báo: {{ product.alert_stock }}</span>
                                </span>
                            </span>
                            <span class="hidden shrink-0 text-right text-xs text-slate-500 sm:block">Giá nhập gần nhất<br /><b class="mt-0.5 inline-block text-slate-800">{{ formatCurrency(product.cost_price) }}</b></span>
                        </label>
                    </div>
                </div>
                <div v-if="pageCount > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-2 sm:px-5">
                    <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage <= 1" @click="currentPage--"><ChevronLeft class="h-4 w-4" /> Trước</button>
                    <span class="text-xs text-slate-500">Trang {{ currentPage }} / {{ pageCount }}</span>
                    <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage >= pageCount" @click="currentPage++">Sau <ChevronRight class="h-4 w-4" /></button>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col bg-white">
                <div class="space-y-2 border-b border-slate-100 p-3 sm:p-4">
                    <FloatingSelect
                        v-model="supplierId"
                        name="replenishment_supplier"
                        label="Nhà cung cấp (không bắt buộc)"
                        placeholder-text="Chưa chọn nhà cung cấp"
                        :options="suppliers.map(supplier => ({ value: supplier.id, label: supplier.name }))"
                    />
                </div>

                <div class="max-h-[50dvh] min-h-32 flex-1 overflow-y-auto p-3 sm:p-4 lg:max-h-[calc(100dvh-18rem)]">
                    <div v-if="!selectedItems.length" class="flex h-full min-h-28 flex-col items-center justify-center text-center text-slate-400">
                        <ShoppingBasket class="h-8 w-8" />
                        <p class="mt-2 text-xs">Chọn sản phẩm bên trái để lên danh sách.</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[500px] table-fixed border-collapse text-xs">
                            <thead class="border-b border-slate-200 bg-slate-50 text-left font-semibold text-slate-600">
                                <tr>
                                    <th class="w-[30%] px-2 py-2">Sản phẩm</th>
                                    <th class="w-[15%] px-1 py-2 text-center">Số lượng</th>
                                    <th class="w-[22%] px-1 py-2 text-center">Giá nhập</th>
                                    <th class="w-[25%] px-2 py-2 text-right">Tạm tính</th>
                                    <th class="w-10 px-1 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in selectedItems" :key="item.id" class="hover:bg-slate-50/70">
                                    <td class="break-words px-2 py-2 font-semibold leading-4 text-slate-900">{{ item.name }}</td>
                                    <td class="px-1 py-2">
                                        <FloatingInput
                                            v-model.number="item.quantity"
                                            :name="`quantity_${item.id}`"
                                            label="SL"
                                            type="number"
                                            min="1"
                                            step="1"
                                            input-class="h-8 px-1 text-center text-xs font-semibold"
                                        />
                                    </td>
                                    <td class="px-1 py-2">
                                        <FloatingInput
                                            v-model.number="item.cost_price"
                                            :name="`cost_price_${item.id}`"
                                            label="Giá"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            :format-thousands="true"
                                            input-class="h-8 px-1 text-right text-xs font-semibold"
                                        />
                                    </td>
                                    <td class="px-2 py-2 text-right font-semibold tabular-nums text-slate-700">{{ formatCurrency(Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.cost_price) || 0)) }}</td>
                                    <td class="px-1 py-2 text-center">
                                        <button type="button" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Bỏ ${item.name} khỏi danh sách`" @click="removeItem(item.id)"><X class="h-4 w-4" /></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border-t border-slate-100 bg-slate-50/70 p-3 sm:p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <div class="min-w-0 text-sm text-slate-600">
                            <span>Tổng dự kiến</span>
                            <strong class="ml-2 whitespace-nowrap text-base text-slate-900">{{ formatCurrency(totalEstimate) }}</strong>
                        </div>
                        <button type="button" class="shrink-0 rounded px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-white" @click="screen = 'saved'">Đơn đã lưu ({{ savedOrders.length }})</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:opacity-50" :disabled="!selectedItems.length" @click="saveOrder">
                            <Save class="h-4 w-4" /> Lưu đơn
                        </button>
                        <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedItems.length || selectedItems.some(item => Number(item.quantity) < 1)" @click="showCurrentOrder">
                            <Check class="h-4 w-4" /> Xem đơn
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </BaseModal>
</template>
