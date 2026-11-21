import { computed, onBeforeUnmount, ref, watch } from 'vue'
import api from '@/Services/api'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'
import CustomerDetailModal from '@/Pages/Customers/CustomerDetailModal.vue'
import SupplierDetailModal from '@/Pages/Suppliers/SupplierDetailModal.vue'
import UserDetailModal from '@/Pages/Users/UserDetailModal.vue'
import SaleSearchModal from '@/Components/Search/SaleSearchModal.vue'
import RepairSearchModal from '@/Components/Search/RepairSearchModal.vue'
import StockImportDetailModal from '@/Pages/StockImports/StockImportDetailModal.vue'
import ProductDetailModal from '@/Pages/Products/ProductDetailModal.vue'
import ProductImeisModal from '@/Pages/Products/ProductImeisModal.vue'
import { openModal } from '@/Stores/modal'

// Mở đúng modal chi tiết mà trang đích dùng, ngay tại trang hiện tại (không điều hướng)
const modalFor = (record, keyword) => {
    switch (record.type) {
        case 'sale': return [SaleSearchModal, { saleId: record.id }]
        case 'repair': return [RepairSearchModal, { repairId: record.id }]
        case 'stock-import': return [StockImportDetailModal, { importId: record.id }]
        case 'product': return [ProductDetailModal, { productId: record.id, productName: record.name || '' }]
        case 'imei': return [ProductImeisModal, { productId: record.product_id, productName: record.name || '' }]
        case 'customer': return [CustomerDetailModal, { recordId: record.id, keyword }]
        case 'supplier': return [SupplierDetailModal, { recordId: record.id, keyword }]
        case 'user': return [UserDetailModal, { recordId: record.id, keyword }]
        default: return [RecordDetailModal, { type: record.type, recordId: record.id, keyword }]
    }
}

/**
 * Logic tìm kiếm toàn hệ thống dùng chung (ô tìm của từng trang + cửa sổ Ctrl+K).
 * Chỉ gọi API khi `enabled` = true, nên gõ tìm trong trang không phát sinh request toàn hệ thống.
 */
export function useGlobalSearch(query, { enabled = ref(true), onSelected = null } = {}) {
    const results = ref([])
    const loading = ref(false)
    const activeIndex = ref(-1)
    let timer = null
    let controller = null
    let requestId = 0

    const reset = () => {
        clearTimeout(timer)
        controller?.abort()
        requestId += 1
        results.value = []
        loading.value = false
        activeIndex.value = -1
    }

    watch([query, enabled], ([value, isEnabled]) => {
        reset()
        const keyword = String(value ?? '').trim()
        if (!isEnabled || keyword.length < 2) return

        const id = requestId
        loading.value = true
        timer = setTimeout(async () => {
            controller = new AbortController()
            try {
                const { data } = await api.get('/api/global-search', { params: { q: keyword }, signal: controller.signal })
                if (id === requestId) results.value = data.results || []
            } catch (error) {
                if (error.name !== 'CanceledError' && id === requestId) results.value = []
            } finally {
                if (id === requestId) loading.value = false
            }
        }, 180)
    })

    onBeforeUnmount(reset)

    // Gom kết quả theo nhóm (giữ thứ tự xuất hiện); index là vị trí trong danh sách phẳng để điều hướng bằng phím
    const groups = computed(() => {
        const map = new Map()
        results.value.forEach((result, index) => {
            if (!map.has(result.type)) map.set(result.type, { type: result.type, items: [] })
            map.get(result.type).items.push({ result, index })
        })
        return [...map.values()]
    })

    const select = (result) => {
        if (!result?.record) return
        const keyword = String(query.value ?? '').trim()
        onSelected?.()
        const [component, props] = modalFor(result.record, keyword)
        openModal(component, { props })
    }

    // Trả về true nếu phím đã được xử lý (mũi tên / Enter trên kết quả đang chọn).
    const handleKey = (event) => {
        const total = results.value.length
        if (!total) return false
        if (event.key === 'ArrowDown') {
            event.preventDefault()
            activeIndex.value = (activeIndex.value + 1) % total
            return true
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault()
            activeIndex.value = activeIndex.value <= 0 ? total - 1 : activeIndex.value - 1
            return true
        }
        if (event.key === 'Enter' && activeIndex.value >= 0) {
            event.preventDefault()
            select(results.value[activeIndex.value])
            return true
        }
        return false
    }

    return { results, loading, activeIndex, groups, select, handleKey, reset }
}
