// Khi có modal / hộp thoại đang mở, vuốt (cảm ứng) hoặc lăn chuột KHÔNG được làm trang nền trượt.
//
// Trang nền của app không cuộn bằng window mà bằng các vùng cuộn bên trong (vd <main overflow-y-auto>
// của AdminLayout), nên khóa <body> là không đủ. Ở đây ta chặn cử chỉ ở mức sự kiện:
//   - Cử chỉ bắt đầu NGOÀI modal            -> chặn hoàn toàn.
//   - Cử chỉ bắt đầu TRONG modal            -> chỉ cho phép khi có vùng cuộn trong modal còn cuộn được
//                                              theo hướng đó; hết đà (đầu/cuối) hoặc không có vùng cuộn thì chặn
//                                              để không "chảy" ra trang nền.
// Không cần sửa từng modal: nhận diện mọi lớp phủ `fixed inset-0` đang hiển thị.

const OVERLAY_SELECTOR = '.fixed.inset-0'
// Thanh trượt (zoom camera...) và vùng tự khai báo thì để trình duyệt tự xử lý.
const FREE_SELECTOR = 'input[type="range"], [data-allow-touch-scroll]'

const isVisible = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden'

const openOverlays = () => Array.from(document.querySelectorAll(OVERLAY_SELECTOR)).filter((el) =>
    !el.classList.contains('pos-root')          // khung POS toàn màn hình, không phải modal
    && !el.classList.contains('lg:hidden')      // lớp mờ của menu trượt trên mobile
    && el.getAttribute('aria-hidden') !== 'true' // lớp mờ trang trí (FloatingActions)
    && isVisible(el),
)

// dx/dy: hướng NỘI DUNG sẽ di chuyển theo ngón tay (dy > 0 = kéo nội dung xuống = cần scrollTop > 0).
const canScroll = (el, dx, dy) => {
    const style = getComputedStyle(el)
    if (Math.abs(dy) >= Math.abs(dx)) {
        if (!/(auto|scroll)/.test(style.overflowY) || el.scrollHeight <= el.clientHeight + 1) return false
        return dy > 0 ? el.scrollTop > 0 : el.scrollTop + el.clientHeight < el.scrollHeight - 1
    }
    if (!/(auto|scroll)/.test(style.overflowX) || el.scrollWidth <= el.clientWidth + 1) return false
    return dx > 0 ? el.scrollLeft > 0 : el.scrollLeft + el.clientWidth < el.scrollWidth - 1
}

const shouldBlock = (target, dx, dy) => {
    if (!(target instanceof Element)) return false
    const overlays = openOverlays()
    if (!overlays.length) return false
    if (target.closest(FREE_SELECTOR)) return false

    const overlay = overlays.find((item) => item.contains(target))
    if (!overlay) return true // vuốt trên nền phía sau modal

    for (let el = target; el; el = el.parentElement) {
        if (canScroll(el, dx, dy)) return false
        if (el === overlay) break
    }
    return true
}

let installed = false

export function installModalScrollLock() {
    if (installed || typeof document === 'undefined') return
    installed = true

    let lastX = 0
    let lastY = 0

    document.addEventListener('touchstart', (event) => {
        if (event.touches.length !== 1) return
        lastX = event.touches[0].clientX
        lastY = event.touches[0].clientY
    }, { passive: true })

    document.addEventListener('touchmove', (event) => {
        if (event.touches.length !== 1) return // chụm 2 ngón để zoom: không can thiệp
        const touch = event.touches[0]
        const dx = touch.clientX - lastX
        const dy = touch.clientY - lastY
        lastX = touch.clientX
        lastY = touch.clientY
        if (!dx && !dy) return
        if (event.cancelable && shouldBlock(event.target, dx, dy)) event.preventDefault()
    }, { passive: false })

    document.addEventListener('wheel', (event) => {
        if (event.ctrlKey) return // zoom trình duyệt
        if (event.cancelable && shouldBlock(event.target, -event.deltaX, -event.deltaY)) event.preventDefault()
    }, { passive: false })
}
