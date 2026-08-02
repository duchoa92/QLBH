export function formatMoney(value) {
    return Number(value || 0).toLocaleString('vi-VN')
}

export function formatDate(date) {
    if (!date) return ''

    return new Date(date).toLocaleDateString('vi-VN')
}

export function formatDateTime(date) {
    if (!date) return ''

    return new Date(date).toLocaleString('vi-VN')
}