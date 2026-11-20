// Bàn phím ảo (iOS Safari / Chrome điện thoại) chỉ thu nhỏ "vùng nhìn thấy" (visualViewport), còn khung cố định
// (modal, hộp thoại) vẫn tính theo chiều cao cũ nên bị bàn phím che hoặc ép còn một dải nhỏ.
// Hàm này đo vùng nhìn thấy thật và gắn vào :root để CSS (app.css) co các hộp thoại cho vừa phần còn lại.

export function installViewportFix() {
    const viewport = typeof window !== 'undefined' ? window.visualViewport : null
    if (!viewport) return

    const root = document.documentElement
    let frame = 0
    let widestViewport = viewport.width
    let fullViewportHeight = Math.max(viewport.height, window.innerHeight, document.documentElement.clientHeight)

    const revealFocusedField = () => {
        const field = document.activeElement
        if (!(field instanceof HTMLElement) || !field.matches('input:not([type="checkbox"]):not([type="radio"]), textarea, select, [contenteditable="true"]')) return

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
        const keyboardOpen = !zoomed && coveredHeight > 100

        if (keyboardOpen) {
            root.style.setProperty('--vv-h', `${Math.round(viewport.height)}px`)
            root.style.setProperty('--vv-top', `${Math.round(viewport.offsetTop)}px`)
        } else {
            root.style.removeProperty('--vv-h')
            root.style.removeProperty('--vv-top')
        }

        root.classList.toggle('kb-open', keyboardOpen)

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

    schedule()
}
