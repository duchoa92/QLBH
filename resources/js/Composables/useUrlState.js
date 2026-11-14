import { onBeforeUnmount, onMounted, watch } from 'vue'

/**
 * Đồng bộ trạng thái giao diện (từ khóa, tab, modal, bộ lọc...) lên query string của URL
 * để tải lại trang (F5), copy link hoặc nút Quay lại đều đưa người dùng về đúng chỗ đang xem.
 *
 * Chỉ dùng History API và giữ nguyên `history.state` của Inertia nên không gửi thêm request nào.
 */
const currentUrl = () => `${window.location.pathname}${window.location.search}${window.location.hash}`

/** Đọc toàn bộ query hiện tại thành object phẳng. */
export function readQuery() {
    return Object.fromEntries(new URL(window.location.href).searchParams.entries())
}

/**
 * Cập nhật các tham số trên URL. Giá trị null / undefined / '' sẽ xóa tham số đó.
 * push = true  -> thêm một mục lịch sử (nút Quay lại của trình duyệt quay về trạng thái trước).
 * push = false -> thay mục hiện tại (dùng cho tab, bộ lọc, modal...).
 */
export function patchQuery(patch, { push = false } = {}) {
    const url = new URL(window.location.href)

    Object.entries(patch).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') url.searchParams.delete(key)
        else url.searchParams.set(key, String(value))
    })

    const next = `${url.pathname}${url.search}${url.hash}`

    if (next === currentUrl()) return

    window.history[push ? 'pushState' : 'replaceState'](window.history.state, '', next)
}

/**
 * Gắn một mảnh trạng thái giao diện với một tham số URL.
 *   current():  trả về chuỗi mô tả trạng thái hiện tại (hoặc null khi ở mặc định -> bỏ tham số)
 *   restore(v): dựng lại trạng thái từ giá trị trên URL (null khi không có tham số)
 * Tự khôi phục khi tải trang và khi người dùng bấm Quay lại / Tiến của trình duyệt.
 */
export function useUrlView({ key = 'view', current, restore }) {
    const read = () => readQuery()[key] ?? null

    watch(current, value => patchQuery({ [key]: value }))

    const onPopState = () => restore(read())

    onMounted(() => {
        const value = read()

        if (value !== null) restore(value)
        window.addEventListener('popstate', onPopState)
    })
    onBeforeUnmount(() => window.removeEventListener('popstate', onPopState))
}
