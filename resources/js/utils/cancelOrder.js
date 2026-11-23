import axios from 'axios'

// Dùng chung cho modal "Hủy" của hóa đơn bán (Danh sách hóa đơn, POS, tìm nhanh) và phiếu nhập hàng.

const variantText = (variant) => {
    const attributes = Object.values(variant?.attributes || {})
    return attributes.join(' / ') || variant?.name || variant?.sku || ''
}

/** Dòng hiển thị trong modal hủy từ một hóa đơn bán (cần sale.items). */
export const saleCancelItems = (sale) => (sale?.items || []).map((item) => {
    const imei = item.product_imei?.imei || item.imei || ''
    const quantity = Math.max(1, Number(item.quantity || 1))
    return {
        id: item.id,
        name: item.product?.name || item.product_name || 'Sản phẩm',
        subtitle: [variantText(item.variant), imei ? `IMEI: ${imei}` : ''].filter(Boolean).join(' · '),
        unit: item.unit_name || '',
        price: Number(item.unit_price || 0),
        max: quantity,
        editableQty: !imei && quantity > 1,
    }
})

/** Dòng hiển thị trong modal hủy từ một phiếu nhập (cần stockImport.items), mỗi dòng nhập một hàng. */
export const importCancelItems = (stockImport) => (stockImport?.items || []).map((item) => {
    const quantity = Math.max(1, Number(item.quantity || 1))
    const imeis = item.import_imeis || item.imeis || []
    const isSerial = Boolean(item.product?.manage_stock_by_serial || imeis.length)
    return {
        id: item.id,
        name: item.product?.name || 'Sản phẩm',
        subtitle: [variantText(item.variant), imeis.length ? `${imeis.length} IMEI` : ''].filter(Boolean).join(' · '),
        unit: item.unit_name || item.unit?.short_name || '',
        price: Number(item.cost_price || 0),
        max: quantity,
        editableQty: !isSerial && quantity > 1,
    }
})

/** Request hủy hóa đơn: hủy cả đơn hoặc chỉ các sản phẩm/số lượng đã chọn. Lý do có thể để trống. */
export const saleCancelRequest = (saleId, payload) => payload.whole
    ? { url: route('sales.cancel', saleId), data: { reason: payload.reason, cancel_reason: payload.reason } }
    : { url: route('sales.items.cancel', saleId), data: { reason: payload.reason, items: payload.items } }

/** Request hủy phiếu nhập: hủy cả phiếu hoặc chỉ các dòng/số lượng đã chọn. */
export const importCancelRequest = (importId, payload) => payload.whole
    ? { url: `/stock-import/${importId}/cancel`, data: { reason: payload.reason } }
    : { url: `/stock-import/${importId}/items/cancel`, data: { reason: payload.reason, items: payload.items } }

/** Hóa đơn ở danh sách có thể chưa kèm sản phẩm: tải chi tiết (API POS) để chọn sản phẩm cần hủy. */
export const loadSaleForCancel = async (saleId) => {
    try {
        const response = await axios.get(`/api/sales/${saleId}`)
        return response.data?.data || response.data
    } catch {
        return null
    }
}
