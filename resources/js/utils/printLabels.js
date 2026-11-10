// In tem IMEI/Serial ngay trong trang hiện tại bằng iframe ẩn (không mở tab/điều hướng).
// Dùng chung cho danh sách sản phẩm và cửa sổ chi tiết sản phẩm.
import JsBarcode from 'jsbarcode'

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
})[c])

const normalizeStatus = (status) => ({ 0: 'in_stock', 1: 'sold', 2: 'repairing', 3: 'returned' }[status] ?? status)

const labelCode = (item) => String(item?.imei || item?.serial || '').trim()

const isInStock = (item) => String(normalizeStatus(item?.status) || '').toLowerCase() === 'in_stock'

const variantText = (product, variantId) => {
    const variant = (product?.variants || []).find(v => Number(v.id) === Number(variantId))
    if (!variant) return ''
    const values = Object.values(variant.attributes || {})
        .map(value => typeof value === 'object' ? (value?.value || value?.label || value?.name || '') : value)
        .filter(Boolean)
    return values.join(' / ') || variant.sku || ''
}

/** Các IMEI/Serial còn trong kho và có mã để in của một sản phẩm. */
export const printableCodes = (product) =>
    (product?.imeis || []).filter(item => isInStock(item) && labelCode(item))

export const canPrintLabels = (product) => printableCodes(product).length > 0

/** Danh sách tem có thể in (mỗi IMEI/Serial một tem) để người dùng chọn. */
export const labelRowsFor = (products) => products.filter(Boolean).flatMap(product =>
    printableCodes(product).map(item => ({
        key: `${product.id}-${item.id ?? labelCode(item)}`,
        productId: product.id,
        productName: product.name,
        variant: variantText(product, item.variant_id),
        code: labelCode(item),
        kind: item.imei ? 'IMEI' : 'SN',
        price: item.sell_price ?? product.sell_price,
    })))

const money = (value) => `${new Intl.NumberFormat('vi-VN').format(Number(value) || 0)} ₫`

/** Mặc định trùng với App\Support\LabelTemplate::DEFAULTS. */
export const DEFAULT_LABEL_TEMPLATE = {
    layout: 'sheet', width_mm: 50, height_mm: 30, gap_mm: 2, margin_mm: 5,
    border: 'none', align: 'left',
    show_shop: false, show_name: true, show_variant: true, show_code: true, show_price: true, show_barcode: true,
    name_size: 8, code_size: 7, price_size: 9, barcode_height_mm: 9, footer_text: '',
}

export const mergeLabelTemplate = (template) => ({ ...DEFAULT_LABEL_TEMPLATE, ...(template || {}) })

const MM_TO_PX = 3.78

const barcodeSvg = (code, template) => {
    try {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
        JsBarcode(svg, code, { format: 'CODE128', width: 1, height: Math.round(template.barcode_height_mm * MM_TO_PX), displayValue: false, margin: 0 })
        svg.setAttribute('preserveAspectRatio', 'none')
        svg.removeAttribute('width')
        svg.removeAttribute('height')
        svg.setAttribute('style', `display:block;width:100%;height:${template.barcode_height_mm}mm;margin-top:0.6mm`)
        return svg.outerHTML
    } catch {
        return '' // Mã chứa ký tự CODE128 không hỗ trợ: vẫn in chữ, bỏ vạch.
    }
}

/** CSS của tem theo mẫu; dùng chung cho bản in và bản xem trước trong Cài đặt. */
export const labelCss = (template) => {
    const t = mergeLabelTemplate(template)
    const border = t.border === 'none' ? 'none' : `0.2mm ${t.border} #000`
    return `.lbl-wrap{display:flex;flex-wrap:wrap;gap:${t.gap_mm}mm}
.lbl{width:${t.width_mm}mm;height:${t.height_mm}mm;border:${border};padding:1mm 1.2mm;overflow:hidden;box-sizing:border-box;font-family:Arial,sans-serif;color:#000;text-align:${t.align};page-break-inside:avoid;break-inside:avoid;display:flex;flex-direction:column;justify-content:center;line-height:1.2}
.lbl .shop{font-size:${Math.max(5, t.name_size - 1)}pt;font-weight:bold;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lbl .name{font-size:${t.name_size}pt;font-weight:bold;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lbl .variant{font-size:${t.code_size}pt;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lbl .code{font-size:${t.code_size}pt;font-family:monospace;white-space:nowrap;overflow:hidden}
.lbl .price{font-size:${t.price_size}pt;font-weight:bold}
.lbl .foot{font-size:${Math.max(5, t.code_size - 1)}pt;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}`
}

/** HTML một tem. */
export const labelHtml = (row, template, shopName = '') => {
    const t = mergeLabelTemplate(template)
    const parts = []
    if (t.show_shop && shopName) parts.push(`<div class="shop">${escapeHtml(shopName)}</div>`)
    if (t.show_name) parts.push(`<div class="name">${escapeHtml(row.productName)}</div>`)
    if (t.show_variant && row.variant) parts.push(`<div class="variant">${escapeHtml(row.variant)}</div>`)
    if (t.show_barcode) parts.push(barcodeSvg(row.code, t))
    if (t.show_code) parts.push(`<div class="code">${row.kind}: ${escapeHtml(row.code)}</div>`)
    if (t.show_price) parts.push(`<div class="price">${money(row.price)}</div>`)
    if (t.footer_text) parts.push(`<div class="foot">${escapeHtml(t.footer_text)}</div>`)
    return `<div class="lbl">${parts.join('')}</div>`
}

const buildHtml = (rows, template, shopName) => {
    const t = mergeLabelTemplate(template)
    const roll = t.layout === 'roll'
    const page = roll
        ? `@page{size:${t.width_mm}mm ${t.height_mm}mm;margin:0}.lbl-wrap{display:block}.lbl{page-break-after:always;break-after:page}.lbl:last-child{page-break-after:auto;break-after:auto}`
        : `@page{margin:${t.margin_mm}mm}`
    return `<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>In tem</title><style>
*{box-sizing:border-box}body{margin:0}${page}
${labelCss(t)}
</style></head><body><div class="lbl-wrap">${rows.map(row => labelHtml(row, t, shopName)).join('')}</div></body></html>`
}

/**
 * In đúng các tem đã chọn bằng iframe ẩn trong trang hiện tại.
 * Iframe phải có kích thước thật (đặt ngoài màn hình) vì iframe 0x0 thường in ra trang trống.
 */
export function printLabelRows(rows, template = null, shopName = '') {
    if (!rows.length) return 0

    const frame = document.createElement('iframe')
    frame.setAttribute('aria-hidden', 'true')
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:900px;height:700px;border:0'

    let cleaned = false
    const cleanup = () => {
        if (cleaned) return
        cleaned = true
        frame.remove()
    }

    frame.onload = () => {
        const frameWindow = frame.contentWindow
        if (!frameWindow) return cleanup()
        frameWindow.addEventListener('afterprint', cleanup, { once: true })
        window.setTimeout(() => {
            frameWindow.focus()
            frameWindow.print()
            window.setTimeout(cleanup, 60000)
        }, 250)
    }
    frame.srcdoc = buildHtml(rows, template, shopName)
    document.body.appendChild(frame)

    return rows.length
}
