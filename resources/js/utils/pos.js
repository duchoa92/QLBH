// Tiện ích dùng chung cho việc mở / đóng màn hình POS toàn màn hình

export const POS_RETURN_KEY = 'pos_return_url'

// Ghi nhớ trang hiện tại để quay lại khi đóng POS
export const rememberPosReturnUrl = () => {
    try {
        const current = window.location.pathname + window.location.search

        if (!current.startsWith('/pos')) {
            sessionStorage.setItem(POS_RETURN_KEY, current)
        }
    } catch {
        // bỏ qua (trình duyệt chặn storage)
    }
}

export const getPosReturnUrl = () => {
    try {
        return sessionStorage.getItem(POS_RETURN_KEY)
    } catch {
        return null
    }
}

const fullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement || null

// Đã chạy như ứng dụng (thêm vào Màn hình chính) hoặc đang toàn màn hình → không còn thanh địa chỉ.
export const isStandaloneDisplay = () => {
    try {
        return Boolean(
            window.navigator.standalone
            || window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
        )
    } catch {
        return false
    }
}

// Phải gọi trong sự kiện click của người dùng thì trình duyệt mới cho phép.
// iPhone (Safari) KHÔNG hỗ trợ Fullscreen API cho trang web: ở đó chỉ toàn màn hình thật khi
// thêm vào Màn hình chính, nên hàm này im lặng bỏ qua thay vì báo lỗi.
export const requestPosFullscreen = () => {
    const el = document.documentElement

    if (fullscreenElement()) return

    try {
        const request = el.requestFullscreen || el.webkitRequestFullscreen
        if (!request) return
        const result = request.call(el, { navigationUI: 'hide' })
        if (result?.catch) result.catch(() => {})
    } catch {
        // trình duyệt từ chối: bỏ qua
    }
}

export const exitPosFullscreen = () => {
    if (!fullscreenElement()) return

    try {
        const exit = document.exitFullscreen || document.webkitExitFullscreen
        const result = exit?.call(document)
        if (result?.catch) result.catch(() => {})
    } catch {
        // bỏ qua
    }
}

// Vào/ra chế độ POS "nhập vai": khóa cuộn trang nền (hết nảy/kéo làm lộ thanh trình duyệt),
// ẩn thanh cảnh báo http, và (nếu có thể) vào toàn màn hình.
export const enterPosImmersive = () => {
    document.documentElement.classList.add('pos-fullscreen')
    document.getElementById('insecure-http-notice')?.remove()
    window.scrollTo(0, 1)
}

export const leavePosImmersive = () => {
    document.documentElement.classList.remove('pos-fullscreen')
}

// Gọi khi bấm menu "Mở POS"
export const prepareOpenPos = () => {
    rememberPosReturnUrl()
    requestPosFullscreen()
}
