import { appBaseUrl } from '@/utils/appPaths'

const DISMISS_KEY = 'insecure-http-notice-dismissed'
const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]']

// Truy cập bằng http:// (không phải máy chủ nội bộ) thì hiện thanh báo dẫn tới trang cài chứng chỉ để dùng https://.
export function installInsecureNotice() {
    if (typeof document === 'undefined') return
    if (location.protocol !== 'http:' || LOCAL_HOSTS.includes(location.hostname)) return

    try {
        if (sessionStorage.getItem(DISMISS_KEY)) return
    } catch (e) { /* bỏ qua */ }

    const guideUrl = new URL('huong-dan.html', appBaseUrl).href
    const httpsUrl = `https://${location.host}${location.pathname}${location.search}`

    const show = () => {
        if (document.getElementById('insecure-http-notice')) return

        const bar = document.createElement('div')
        bar.id = 'insecure-http-notice'
        bar.setAttribute('role', 'alert')
        bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:2000;display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;padding:10px 16px;background:#fef3c7;color:#92400e;border-top:1px solid #fcd34d;font-size:14px;line-height:1.4'

        const text = document.createElement('span')
        text.style.flex = '1 1 240px'
        text.textContent = 'Bạn đang dùng http:// (chưa bảo mật), camera và quét mã có thể không hoạt động.'

        const mkLink = (label, href, primary) => {
            const a = document.createElement('a')
            a.textContent = label
            a.href = href
            a.style.cssText = primary
                ? 'background:#2563eb;color:#fff;padding:6px 12px;border-radius:8px;font-weight:600;text-decoration:none'
                : 'color:#92400e;font-weight:600;text-decoration:underline'
            return a
        }

        const close = document.createElement('button')
        close.type = 'button'
        close.textContent = '✕'
        close.setAttribute('aria-label', 'Đóng')
        close.style.cssText = 'border:0;background:transparent;color:#92400e;font-size:16px;cursor:pointer;padding:4px 8px'
        close.addEventListener('click', () => {
            bar.remove()
            try { sessionStorage.setItem(DISMISS_KEY, '1') } catch (e) { /* bỏ qua */ }
        })

        bar.append(text, mkLink('Cài chứng chỉ để dùng https', guideUrl, true), mkLink('Mở bản https', httpsUrl, false), close)
        document.body.appendChild(bar)
    }

    if (document.body) show()
    else document.addEventListener('DOMContentLoaded', show)
}
