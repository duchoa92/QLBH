// Tự tải lại trang (do service worker mới hoặc phiên bản tài nguyên đổi) KHÔNG được xảy ra khi người dùng đang nhập liệu:
// reload sẽ làm mất những gì vừa gõ. Hàm dưới đây chỉ tải lại khi trang thật sự rảnh, còn bận thì đợi.

const FIELD = 'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, select, [contenteditable="true"]'
const RECENT_INPUT_MS = 5 * 60 * 1000
const CHECK_MS = 5000

let lastInputAt = 0
if (typeof window !== 'undefined') {
    ;['input', 'change', 'keydown', 'paste'].forEach((name) =>
        window.addEventListener(name, () => { lastInputAt = Date.now() }, { capture: true, passive: true }))
}

export const isUserBusy = () => {
    if (typeof document === 'undefined') return false
    const active = document.activeElement
    // Đang đặt con trỏ trong một ô nhập.
    if (active instanceof HTMLElement && active.matches(FIELD)) return true
    // Đang mở hộp thoại / form dạng modal.
    if (document.querySelector('[role="dialog"], .base-modal-sheet, dialog[open]')) return true
    // Vừa gõ trong vài phút gần đây (kể cả khi con trỏ đã rời ô nhập, ví dụ bấm nút khác trong form).
    return Date.now() - lastInputAt < RECENT_INPUT_MS
}

let waiting = false

/** Tải lại trang ngay nếu rảnh; nếu đang bận thì kiểm tra lại mỗi vài giây và chỉ tải lại khi trang rảnh. */
export const reloadWhenIdle = () => {
    if (waiting || typeof window === 'undefined') return
    if (!isUserBusy()) {
        window.location.reload()
        return
    }
    waiting = true
    const timer = window.setInterval(() => {
        // Tab đang ẩn thì thôi chờ: tải lại ngầm không ảnh hưởng ai, nhưng cũng không cần gấp.
        if (isUserBusy()) return
        window.clearInterval(timer)
        waiting = false
        window.location.reload()
    }, CHECK_MS)
}
