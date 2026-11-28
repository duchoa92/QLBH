import { appBaseUrl } from '@/utils/appPaths'

/**
 * Lấy lại cookie XSRF-TOKEN (và phiên) mới nhất từ máy chủ.
 *
 * Trang đăng nhập có thể được mở từ trước (tab cũ, phiên đã hết hạn, phiên bị đổi bởi một request nền...) nên
 * token trong cookie không còn khớp với phiên hiện tại và lần gửi đầu tiên bị 419 "Page expired". Gọi hàm này
 * ngay trước khi gửi form đăng nhập, hoặc sau khi gặp 419, để lần gửi tiếp theo luôn dùng token mới.
 * Không bao giờ ném lỗi: nếu mạng lỗi thì cứ để form gửi như bình thường.
 */
export async function refreshCsrfCookie() {
    try {
        await fetch(new URL('sanctum/csrf-cookie', appBaseUrl).href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        return true
    } catch {
        return false
    }
}
