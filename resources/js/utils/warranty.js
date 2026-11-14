// Đơn vị thời hạn bảo hành dùng chung: ngày · tuần · tháng · năm (mặc định: tháng).
// Quy ước quy đổi sang ngày: 1 tuần = 7 ngày, 1 tháng = 30 ngày, 1 năm = 365 ngày.
export const WARRANTY_UNIT_DAYS = { day: 1, week: 7, month: 30, year: 365 }
export const WARRANTY_UNIT_LABELS = { day: 'ngày', week: 'tuần', month: 'tháng', year: 'năm' }
export const DEFAULT_WARRANTY_UNIT = 'month'
export const MAX_WARRANTY_DAYS = 3650

const capitalize = text => text.charAt(0).toUpperCase() + text.slice(1)

// Dạng số ít (day/week/month/year) — dùng ở sản phẩm, giỏ hàng POS.
export const WARRANTY_UNIT_OPTIONS = Object.keys(WARRANTY_UNIT_DAYS).map(value => ({ value, label: capitalize(WARRANTY_UNIT_LABELS[value]) }))
// Dạng số nhiều (days/weeks/months/years) — dùng ở phiếu nhập kho (đã lưu như vậy trong CSDL).
export const WARRANTY_UNIT_OPTIONS_PLURAL = WARRANTY_UNIT_OPTIONS.map(option => ({ ...option, value: `${option.value}s` }))

// 'months' | 'month' | 'Month' -> 'month'; giá trị lạ -> mặc định
export const normalizeWarrantyUnit = (unit) => {
    const key = String(unit || '').toLowerCase().replace(/s$/, '')
    return WARRANTY_UNIT_DAYS[key] ? key : DEFAULT_WARRANTY_UNIT
}
export const warrantyUnitDays = unit => WARRANTY_UNIT_DAYS[normalizeWarrantyUnit(unit)]
export const warrantyUnitLabel = unit => WARRANTY_UNIT_LABELS[normalizeWarrantyUnit(unit)]
export const warrantyMaxValue = unit => Math.floor(MAX_WARRANTY_DAYS / warrantyUnitDays(unit))
export const formatWarranty = (value, unit) => `${value} ${warrantyUnitLabel(unit)}`

// Số ngày -> { value, unit } dùng đơn vị lớn nhất chia hết (365 → 1 năm, 90 → 3 tháng, 14 → 2 tuần). 0 -> trống, đơn vị tháng.
export const splitWarrantyDays = (days) => {
    const total = Math.round(Number(days) || 0)
    if (total <= 0) return { value: '', unit: DEFAULT_WARRANTY_UNIT }
    for (const unit of ['year', 'month', 'week']) {
        if (total % WARRANTY_UNIT_DAYS[unit] === 0) return { value: total / WARRANTY_UNIT_DAYS[unit], unit }
    }
    return { value: total, unit: 'day' }
}
