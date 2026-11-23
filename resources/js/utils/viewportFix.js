// Bàn phím ảo (iOS Safari / Chrome điện thoại) chỉ thu nhỏ "vùng nhìn thấy" (visualViewport), còn khung cố định
// (modal, hộp thoại) vẫn tính theo chiều cao cũ nên bị bàn phím che hoặc ép còn một dải nhỏ.
// Hàm này đo vùng nhìn thấy thật và gắn vào :root để CSS (app.css) co các hộp thoại cho vừa phần còn lại.

export function installViewportFix() {
    const viewport = typeof window !== 'undefined' ? window.visualViewport : null
    if (!viewport) return

    // Chỉ thiết bị cảm ứng (điện thoại/máy tính bảng) mới có bàn phím ảo. Trên máy tính, việc đổi cỡ cửa sổ,
    // zoom trình duyệt, mở DevTools hay thanh công cụ làm visualViewport nhỏ đi sẽ bị nhầm là "bàn phím mở"
    // và làm modal dính sát khung + chừa khoảng trống bên dưới.
    const coarse = window.matchMedia ? window.matchMedia('(hover: none) and (pointer: coarse)') : null
    if (!coarse || !coarse.matches) return

    const root = document.documentElement
    const isEditable = el => el instanceof HTMLElement
        && el.matches('input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, select, [contenteditable="true"]')
    let frame = 0
    let widestViewport = viewport.width
    let fullViewportHeight = Math.max(viewport.height, window.innerHeight, document.documentElement.clientHeight)

    const revealFocusedField = () => {
        const field = document.activeElement
        if (!(field instanceof HTMLElement) || !field.matches('input:not([type="checkbox"]):not([type="radio"]), textarea, select, [contenteditable="true"]')) return

        // Trang POS toàn màn hình là khung cố định: không cuộn trang để "đưa ô nhập ra giữa" (làm vỡ bố cục).
        if (root.classList.contains('pos-fullscreen') && !field.closest('[role="dialog"], .base-modal-sheet')) return

        // Chờ Safari/Chrome hoàn tất animation mở bàn phím rồi cuộn ngay vùng chứa ô nhập.
        window.setTimeout(() => {
            if (root.classList.contains('kb-open') && document.activeElement === field) {
                field.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' })
            }
        }, 80)
    }

    const update = () => {
        frame = 0
        const zoomed = viewport.scale > 1.01
        const widthChanged = Math.abs(viewport.width - widestViewport) > 80

        // Sau xoay màn hình phải lấy lại chiều cao nền; còn khi bàn phím mở thì giữ chiều cao
        // lớn nhất đã biết để iOS (nơi innerHeight đôi khi cũng bị co) vẫn nhận diện chính xác.
        if (widthChanged) {
            widestViewport = viewport.width
            fullViewportHeight = Math.max(viewport.height, window.innerHeight, document.documentElement.clientHeight)
        } else if (!zoomed && viewport.height >= fullViewportHeight - 24) {
            fullViewportHeight = Math.max(viewport.height, window.innerHeight, document.documentElement.clientHeight)
        }

        const coveredHeight = Math.max(0, fullViewportHeight - viewport.height - viewport.offsetTop)
        // Bàn phím chỉ thật sự mở khi có một ô nhập đang được focus.
        const keyboardOpen = !zoomed && coveredHeight > 100 && isEditable(document.activeElement)

        if (keyboardOpen) {
            root.style.setProperty('--vv-h', `${Math.round(viewport.height)}px`)
            root.style.setProperty('--vv-top', `${Math.round(viewport.offsetTop)}px`)
        } else {
            root.style.removeProperty('--vv-h')
            root.style.removeProperty('--vv-top')
        }

        root.classList.toggle('kb-open', keyboardOpen)

        // iOS tự trượt cả trang lên khi bật bàn phím: POS là khung cố định nên đưa về đầu trang.
        if (keyboardOpen && root.classList.contains('pos-fullscreen') && (window.scrollY || window.scrollX)) {
            window.scrollTo(0, 0)
        }

        if (keyboardOpen) revealFocusedField()
    }
    const schedule = () => {
        if (!frame) frame = window.requestAnimationFrame(update)
    }

    viewport.addEventListener('resize', schedule)
    viewport.addEventListener('scroll', schedule)

    // Ô đang gõ không được nằm dưới bàn phím hay dưới thanh tiêu đề dính của modal: cuộn nó ra giữa vùng nhìn thấy.
    document.addEventListener('focusin', (event) => {
        const field = event.target
        if (!(field instanceof HTMLElement) || !field.matches('input:not([type="checkbox"]):not([type="radio"]), textarea, select, [contenteditable="true"]')) return

        window.setTimeout(revealFocusedField, 350)
    })

    // Rời khỏi ô nhập: gỡ trạng thái kb-open ngay (tránh bị kẹt khi sự kiện resize không bắn).
    document.addEventListener('focusout', () => window.setTimeout(schedule, 120))
    window.addEventListener('orientationchange', () => {
        widestViewport = 0
        window.setTimeout(schedule, 300)
    })

    schedule()
}
