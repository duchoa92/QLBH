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

// Phải gọi trong sự kiện click của người dùng thì trình duyệt mới cho phép
export const requestPosFullscreen = () => {
    const el = document.documentElement

    if (!document.fullscreenElement && el.requestFullscreen) {
        el.requestFullscreen().catch(() => {})
    }
}

export const exitPosFullscreen = () => {
    if (document.fullscreenElement && document.exitFullscreen) {
        document.exitFullscreen().catch(() => {})
    }
}

// Gọi khi bấm menu "Mở POS"
export const prepareOpenPos = () => {
    rememberPosReturnUrl()
    requestPosFullscreen()
}
