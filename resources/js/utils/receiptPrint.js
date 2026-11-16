// Dùng chung cho các nơi in hóa đơn bằng iframe ẩn (trang Hóa đơn, ô tìm nhanh).

export const DEFAULT_CANCEL_REASONS = [
    'Khách đổi ý không mua nữa',
    'Đổi sang sản phẩm khác',
    'Nhầm sản phẩm / số lượng',
    'Nhập sai giá / giảm giá',
    'Chưa nhận được tiền',
    'Trùng hóa đơn',
]

const PAPER_SIZES = { a4: 'A4', a5: 'A5', '80mm': '80mm 200mm', '58mm': '58mm 150mm' }

// Tạo CSS khổ giấy / hướng in theo cài đặt.
export function receiptPrintCss(settings = {}) {
    const paper = settings.print_paper_size || 'a4'
    const landscape = settings.print_orientation === 'landscape'
    const thermal = paper === '80mm' || paper === '58mm'
    const width = paper === '58mm' ? '54mm'
        : paper === '80mm' ? '76mm'
            : paper === 'a5' ? (landscape ? '190mm' : '128mm')
                : (landscape ? '277mm' : '190mm')

    return `.no-print,.print-bar,.btn-print{display:none!important} :root{--configured-receipt-width:${width}} @page{size:${PAPER_SIZES[paper] || 'A4'}${thermal ? '' : ` ${landscape ? 'landscape' : 'portrait'}`};margin:${thermal ? '0' : '10mm'}} .receipt-container{width:var(--configured-receipt-width)!important;margin-left:auto!important;margin-right:auto!important}`
}

export function applyReceiptPrintStyle(doc, settings = {}) {
    let style = doc.getElementById('direct-print-settings')
    if (!style) {
        style = doc.createElement('style')
        style.id = 'direct-print-settings'
        doc.head.appendChild(style)
    }
    style.textContent = receiptPrintCss(settings)
}

// Điện thoại / máy tính bảng: in từ iframe ẩn 1px sẽ ra trang trắng, nên mở hóa đơn ở tab riêng và tự in ở đó.
// Trả về true nếu đã xử lý (gọi trực tiếp trong sự kiện bấm để không bị chặn popup).
export function openReceiptInTab(url) {
    const touch = window.matchMedia?.('(pointer: coarse)').matches || window.innerWidth < 768
    if (!touch) return false

    const target = new URL(url, window.location.origin)
    target.searchParams.set('print', '1')

    const opened = window.open(target.toString(), '_blank')
    if (!opened) window.location.assign(target.toString()) // bị chặn popup: mở ngay trong tab hiện tại

    return true
}

// Áp khổ giấy/hướng in trong cài đặt vào iframe hóa đơn rồi mở hộp thoại in.
// `onAfterPrint` được gọi khi in xong hoặc người dùng đóng hộp thoại.
export function printReceiptFrame(event, settings = {}, onAfterPrint = () => {}) {
    const frame = event.target
    const frameWindow = frame?.contentWindow
    const frameDocument = frame?.contentDocument
    if (!frameWindow || !frameDocument) return

    applyReceiptPrintStyle(frameDocument, settings)

    frameWindow.addEventListener('afterprint', onAfterPrint, { once: true })
    window.setTimeout(() => frameWindow.print(), 120)
}
