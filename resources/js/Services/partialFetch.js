import { usePage } from '@inertiajs/vue3'
import api from '@/Services/api'

/**
 * Cơ chế dùng chung để lấy lại một phần dữ liệu trang (Inertia partial reload) bằng AJAX.
 *
 * Khác với router.reload / router.visit: KHÔNG tạo một lượt điều hướng Inertia nên không đổi page.url,
 * không làm AdminLayout dựng lại trang (slot key = page.url), không đóng modal đang mở, không mất state.
 * Hàm chỉ trả về props mới; nơi gọi tự quyết định áp dụng vào đâu (useLiveList, useSilentRefresh...).
 */
export async function fetchPartialProps({ keys, params = {}, path, signal } = {}) {
    const page = usePage()
    const response = await api.get(path || `${window.location.origin}${window.location.pathname}`, {
        params,
        signal,
        headers: {
            Accept: 'text/html, application/xhtml+xml',
            'X-Inertia': 'true',
            'X-Inertia-Version': page.version,
            'X-Inertia-Partial-Component': page.component,
            'X-Inertia-Partial-Data': keys.join(','),
        },
    })
    return response.data?.props || {}
}

export const isCanceled = error => error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED'
