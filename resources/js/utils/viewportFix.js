// Bàn phím ảo (iOS Safari / Chrome điện thoại) chỉ thu nhỏ "vùng nhìn thấy" (visualViewport), còn khung cố định
// (modal, hộp thoại) vẫn tính theo chiều cao cũ nên bị bàn phím che hoặc ép còn một dải nhỏ.
// Hàm này đo vùng nhìn thấy thật và gắn vào :root để CSS (app.css) co các hộp thoại cho vừa phần còn lại.

export function installViewportFix() {
    const viewport = typeof window !== 'undefined' ? window.visualViewport : null
    if (!viewport) return

    const root = document.documentElement
    let frame = 0

    const update = () => {
        frame = 0
        const zoomed = viewport.scale > 1.01
        const keyboardOpen = !zoomed && window.innerHeight - viewport.height > 120

        if (keyboardOpen) {
            root.style.setProperty('--vv-h', `${Math.round(viewport.height)}px`)
            root.style.setProperty('--vv-top', `${Math.round(viewport.offsetTop)}px`)
        } else {
            root.style.removeProperty('--vv-h')
            root.style.removeProperty('--vv-top')
        }

        root.classList.toggle('kb-open', keyboardOpen)
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

        window.setTimeout(() => {
            if (root.classList.contains('kb-open') && document.activeElement === field) {
                field.scrollIntoView({ block: 'center', behavior: 'smooth' })
            }
        }, 350)
    })

    schedule()
}
