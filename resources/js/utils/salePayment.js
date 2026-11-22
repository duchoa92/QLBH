/**
 * Đơn bán còn nợ hoàn toàn (khách chưa trả đồng nào cho đơn này): chưa xác định được tiền mặt hay chuyển khoản,
 * nên hình thức thanh toán hiển thị là "Nợ". Tiền khách đưa dư để trả nợ cũ (old_debt_paid) không tính là đã trả đơn này.
 */
export const isDebtSale = (sale) => {
    if (!sale || sale.status === 'cancelled') return false
    const total = Number(sale.grand_total ?? sale.total_amount ?? 0)
    if (total <= 0) return false
    const paid = Math.max(0, Number(sale.paid_amount || 0) - Number(sale.old_debt_paid || 0))
    return paid <= 0.009
}

/** Mã hình thức dùng để hiển thị: 'debt' nếu đơn nợ hoàn toàn, ngược lại giữ payment_method gốc. */
export const effectivePaymentMethod = (sale) => (isDebtSale(sale) ? 'debt' : sale?.payment_method)
