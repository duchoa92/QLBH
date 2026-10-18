let settings = {
    currency_symbol: '₫',
    currency_format: 'vi-VN',
    app_locale: 'vi',
    app_timezone: 'Asia/Ho_Chi_Minh',
    date_format: 'd/m/Y',
}

export function setFormatSettings(nextSettings = {}) {
    settings = { ...settings, ...nextSettings }
}

export function formatNumber(value) {
    return Number(value || 0).toLocaleString(settings.currency_format || 'vi-VN')
}

export function formatThousandsText(value) {
    if (value === '' || value === null || value === undefined) return ''
    return Number(value).toLocaleString(settings.currency_format || 'vi-VN')
}

export function parseThousandsText(value) {
    const locale = settings.currency_format || 'vi-VN'
    const parts = new Intl.NumberFormat(locale).formatToParts(12345.6)
    const group = parts.find(part => part.type === 'group')?.value || ','
    const decimal = parts.find(part => part.type === 'decimal')?.value || '.'
    let normalized = String(value ?? '').trim()
    if (!normalized) return ''

    normalized = normalized
        .split(group).join('')
        .replace(/\s/g, '')
        .replace(decimal, '.')
        .replace(/[^\d.-]/g, '')

    const number = Number(normalized)
    return Number.isFinite(number) ? number : ''
}

export function formatThousandsInput(event) {
    const input = event.target
    const oldValue = input.value
    const cursor = input.selectionStart ?? oldValue.length
    const digitsBeforeCursor = oldValue.slice(0, cursor).replace(/\D/g, '').length
    const value = parseThousandsText(oldValue)
    const formatted = formatThousandsText(value)
    input.value = formatted

    let nextCursor = 0
    let digitCount = 0
    while (nextCursor < formatted.length && digitCount < digitsBeforeCursor) {
        if (/\d/.test(formatted[nextCursor])) digitCount += 1
        nextCursor += 1
    }
    input.setSelectionRange?.(nextCursor, nextCursor)
    return value
}

export function formatCurrency(value) {
    const symbol = getCurrencySymbol()
    return `${formatNumber(value)} ${symbol}`
}

export function getCurrencySymbol() {
    return settings.currency_symbol || '₫'
}

export function formatMoney(value) {
    return formatCurrency(value)
}

const dateLocale = () => settings.app_locale === 'en' ? 'en-US' : 'vi-VN'
const parseDate = (value) => {
    if (!value) return null
    if (value instanceof Date) return value
    const text = String(value)
    return new Date(/^\d{4}-\d{2}-\d{2}$/.test(text) ? `${text}T12:00:00` : text)
}

export function formatDate(date) {
    const parsed = parseDate(date)
    if (!parsed || Number.isNaN(parsed.getTime())) return ''
    const parts = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        timeZone: settings.app_timezone || 'Asia/Ho_Chi_Minh',
    }).formatToParts(parsed).reduce((result, part) => {
        if (part.type !== 'literal') result[part.type] = part.value
        return result
    }, {})

    if (settings.date_format === 'Y-m-d') return `${parts.year}-${parts.month}-${parts.day}`
    if (settings.date_format === 'm/d/Y') return `${parts.month}/${parts.day}/${parts.year}`
    return `${parts.day}/${parts.month}/${parts.year}`
}

export function formatDateTime(date) {
    const parsed = parseDate(date)
    if (!parsed || Number.isNaN(parsed.getTime())) return ''
    const formattedDate = formatDate(parsed)
    const formattedTime = new Intl.DateTimeFormat(dateLocale(), {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: settings.app_timezone || 'Asia/Ho_Chi_Minh',
    }).format(parsed)
    return `${formattedDate} ${formattedTime}`
}
