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

// Áp khổ giấy/hướng in trong cài đặt vào iframe hóa đơn rồi mở hộp thoại in.
// `onAfterPrint` được gọi khi in xong hoặc người dùng đóng hộp thoại.
export function printReceiptFrame(event, settings = {}, onAfterPrint = () => {}) {
    const frame = event.target
    const frameWindow = frame?.contentWindow
    const frameDocument = frame?.contentDocument
    if (!frameWindow || !frameDocument) return

    const paper = settings.print_paper_size || 'a4'
    const landscape = settings.print_orientation === 'landscape'
    const thermal = paper === '80mm' || paper === '58mm'
    const width = paper === '58mm' ? '54mm'
        : paper === '80mm' ? '76mm'
            : paper === 'a5' ? (landscape ? '190mm' : '128mm')
                : (landscape ? '277mm' : '190mm')

    let style = frameDocument.getElementById('direct-print-settings')
    if (!style) {
        style = frameDocument.createElement('style')
        style.id = 'direct-print-settings'
        frameDocument.head.appendChild(style)
    }
    style.textContent = `.no-print,.print-bar,.btn-print{display:none!important} :root{--configured-receipt-width:${width}} @page{size:${PAPER_SIZES[paper] || 'A4'}${thermal ? '' : ` ${landscape ? 'landscape' : 'portrait'}`};margin:${thermal ? '0' : '10mm'}} .receipt-container{width:var(--configured-receipt-width)!important;margin-left:auto!important;margin-right:auto!important}`

    frameWindow.addEventListener('afterprint', onAfterPrint, { once: true })
    window.setTimeout(() => frameWindow.print(), 120)
}
