/**
 * Hệ thống chỉ lưu VAT theo hóa đơn (invoice.tax), nên VAT từng dòng được chia theo tỷ lệ thành tiền của các dòng chịu VAT
 * (cùng cách với báo cáo khai thuế). Dòng cuối nhận phần dư làm tròn để tổng các dòng luôn bằng VAT của hóa đơn.
 * @returns {number[]} mảng cùng độ dài với items; dòng không chịu VAT = 0
 */
export function allocateSaleVat(items, tax) {
    const list = Array.isArray(items) ? items : []
    const result = list.map(() => 0)
    const total = Math.round(Number(tax || 0))
    const taxable = list.map((item, index) => ({ item, index })).filter(({ item }) => item?.vat_applied)
    const base = taxable.reduce((sum, { item }) => sum + Math.max(0, Number(item.subtotal || 0)), 0)
    if (total <= 0 || !taxable.length || base <= 0) return result

    let used = 0
    taxable.forEach(({ item, index }, position) => {
        const last = position === taxable.length - 1
        const amount = last ? total - used : Math.round(total * Math.max(0, Number(item.subtotal || 0)) / base)
        result[index] = Math.max(0, amount)
        used += amount
    })
    return result
}
