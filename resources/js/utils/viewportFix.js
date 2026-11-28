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

    // Chống tự phóng to khi chạm vào ô nhập và khi thu bàn phím (iOS Safari/Chrome iOS vẫn giữ trang bị zoom dù ô nhập đã 16px,
    // ví dụ khi ô nằm trong khung cố định hoặc bị bọc transform). Khi một ô nhập đang được focus, tạm đặt maximum-scale=1 để
    // trình duyệt không zoom và kéo trang về mức 1x; rời ô nhập thì trả lại meta viewport gốc nên chụm hai ngón vẫn zoom được.
    const viewportMeta = document.querySelector('meta[name="viewport"]')
    if (viewportMeta) {
        const baseViewportContent = viewportMeta.getAttribute('content') || 'width=device-width, initial-scale=1'
        const lockedViewportContent = `${baseViewportContent.replace(/,?\s*(maximum-scale|user-scalable)\s*=\s*[^,]*/gi, '')}, maximum-scale=1`
        const focusableField = 'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, select, [contenteditable="true"]'
        let restoreTimer = 0

        // Ô nhập từ 16px trở lên thì iOS không tự phóng to, nên KHÔNG đổi meta viewport: mỗi lần đổi meta Safari tính lại bố cục
        // và trang bị giật trong lúc bàn phím đang mở.
        const needsZoomLock = (field) => parseFloat(window.getComputedStyle(field).fontSize) < 16

        document.addEventListener('focusin', (event) => {
            if (!(event.target instanceof HTMLElement) || !event.target.matches(focusableField) || !needsZoomLock(event.target)) return
            window.clearTimeout(restoreTimer)
            if (viewportMeta.getAttribute('content') !== lockedViewportContent) viewportMeta.setAttribute('content', lockedViewportContent)
        })

        document.addEventListener('focusout', () => {
            window.clearTimeout(restoreTimer)
            // Chuyển giữa hai ô nhập sẽ có focusin ngay sau đó và hủy bước trả lại này.
            restoreTimer = window.setTimeout(() => {
                if (!(document.activeElement instanceof HTMLElement) || !document.activeElement.matches(focusableField)) {
                    viewportMeta.setAttribute('content', baseViewportContent)
                }
            }, 400)
        })
    }

    const isEditable = el => el instanceof HTMLElement
        && el.matches('input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, select, [contenteditable="true"]')
    let frame = 0
    let lastH = -1
    let lastTop = -1
    let widestViewport = viewport.width
    let fullViewportHeight = Math.max(viewport.height, window.innerHeight, document.documentElement.clientHeight)

    // Đưa ô đang gõ vào vùng nhìn thấy: chỉ làm MỘT lần sau khi bàn phím mở xong (đợi viewport hết thay đổi), cuộn tức thì (không smooth),
    // và chỉ khi ô thật sự bị che. Trước đây hàm này chạy ở mỗi khung hình resize/scroll với scrollIntoView smooth nên trang giật hàng trăm ms.
    let revealTimer = 0
    let revealedField = null
    const isFieldVisible = (field) => {
        const rect = field.getBoundingClientRect()
        return rect.top >= viewport.offsetTop + 8 && rect.bottom <= viewport.offsetTop + viewport.height - 8
    }
    const revealFocusedField = () => {
        window.clearTimeout(revealTimer)
        revealTimer = window.setTimeout(() => {
            const field = document.activeElement
            if (!(field instanceof HTMLElement) || !field.matches('input:not([type="checkbox"]):not([type="radio"]), textarea, select, [contenteditable="true"]')) return
            if (!root.classList.contains('kb-open') || field === revealedField) return
            // Trang POS toàn màn hình là khung cố định: không cuộn trang để "đưa ô nhập ra giữa" (làm vỡ bố cục).
            if (root.classList.contains('pos-fullscreen') && !field.closest('[role="dialog"], .base-modal-sheet')) return
            revealedField = field
            if (!isFieldVisible(field)) field.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' })
        }, 250)
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

        // Chỉ ghi lại khi giá trị đổi (>= 2px): ghi liên tục mỗi khung hình khiến modal tính lại bố cục và rung.
        if (keyboardOpen) {
            const h = Math.round(viewport.height)
            const top = Math.round(viewport.offsetTop)
            if (Math.abs(h - lastH) >= 2) { root.style.setProperty('--vv-h', `${h}px`); lastH = h }
            if (Math.abs(top - lastTop) >= 2) { root.style.setProperty('--vv-top', `${top}px`); lastTop = top }
        } else {
            if (lastH !== -1) root.style.removeProperty('--vv-h')
            if (lastTop !== -1) root.style.removeProperty('--vv-top')
            lastH = -1
            lastTop = -1
            revealedField = null
        }

        if (root.classList.contains('kb-open') !== keyboardOpen) root.classList.toggle('kb-open', keyboardOpen)

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

        revealFocusedField()
    })

    // Rời khỏi ô nhập: gỡ trạng thái kb-open ngay (tránh bị kẹt khi sự kiện resize không bắn).
    document.addEventListener('focusout', () => window.setTimeout(schedule, 120))
    window.addEventListener('orientationchange', () => {
        widestViewport = 0
        window.setTimeout(schedule, 300)
    })

    schedule()
}