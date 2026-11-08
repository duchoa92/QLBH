import { onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import api from '@/Services/api'

const isBlank = value => value === '' || value === null || value === undefined

/**
 * Tìm kiếm / lọc / sắp xếp danh sách KHÔNG tải lại trang — cùng cách làm với trang
 * Đơn hàng, Phiếu sửa, Nhập hàng: gõ chữ → đợi ngắn → gọi AJAX (hủy yêu cầu cũ) →
 * thay dữ liệu tại chỗ và đồng bộ URL bằng history.replaceState.
 *
 * Không cần sửa controller: yêu cầu được gửi kèm header Inertia (partial reload) nên
 * server trả về đúng các props cần dùng dạng JSON, không render lại cả trang.
 *
 *   const list = useLiveList({
 *       props, dataKey: 'products', extraKeys: ['summary'],
 *       params: () => ({ search: search.value, status: status.value }),
 *   })
 *   // list.data / list.filters / list.loading là ref; list.schedule() khi gõ, list.request() khi bấm lọc.
 */
export function useLiveList({ props, dataKey, filtersKey = 'filters', extraKeys = [], params, path, delay = 180 }) {
    const page = usePage()
    const data = ref(props[dataKey])
    const filters = ref(props[filtersKey] || {})
    const extras = {}
    extraKeys.forEach(key => { extras[key] = ref(props[key]) })
    const loading = ref(false)

    // Khi server đẩy props mới (phân trang, router.reload sau khi thêm/sửa/xóa) thì cập nhật theo.
    watch(() => props[dataKey], value => { data.value = value })
    watch(() => props[filtersKey], value => { filters.value = value || {} })
    extraKeys.forEach(key => watch(() => props[key], value => { extras[key].value = value }))

    let timer = null
    let controller = null
    let seq = 0

    const syncUrl = (query) => {
        const url = new URL(window.location.href)
        const keys = new Set([...Object.keys(params()), 'sort_by', 'sort_order', 'per_page'])
        keys.forEach(key => {
            if (isBlank(query[key])) url.searchParams.delete(key)
            else url.searchParams.set(key, String(query[key]))
        })
        url.searchParams.delete('page')
        window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
    }

    const request = async (overrides = {}) => {
        clearTimeout(timer)
        controller?.abort()
        const id = ++seq
        controller = new AbortController()
        loading.value = true

        const merged = {
            sort_by: filters.value?.sort_by,
            sort_order: filters.value?.sort_order,
            per_page: filters.value?.per_page,
            ...params(),
            ...overrides,
        }
        const query = {}
        Object.entries(merged).forEach(([key, value]) => { if (!isBlank(value)) query[key] = value })

        try {
            const response = await api.get(path || `${window.location.origin}${window.location.pathname}`, {
                params: query,
                signal: controller.signal,
                headers: {
                    Accept: 'text/html, application/xhtml+xml',
                    'X-Inertia': 'true',
                    'X-Inertia-Version': page.version,
                    'X-Inertia-Partial-Component': page.component,
                    'X-Inertia-Partial-Data': [dataKey, filtersKey, ...extraKeys].join(','),
                },
            })
            if (id !== seq) return
            const next = response.data?.props || {}
            if (dataKey in next) data.value = next[dataKey]
            if (filtersKey in next) filters.value = next[filtersKey] || {}
            extraKeys.forEach(key => { if (key in next) extras[key].value = next[key] })
            syncUrl(query)
        } catch (error) {
            if (error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED') return
            if (error?.response?.status === 409) { window.location.reload(); return }
            if (id === seq) console.error(error)
        } finally {
            if (id === seq) loading.value = false
        }
    }

    const schedule = () => {
        clearTimeout(timer)
        timer = setTimeout(() => request(), delay)
    }

    const cancel = () => {
        clearTimeout(timer)
        controller?.abort()
    }

    onBeforeUnmount(cancel)

    return { data, filters, extras, loading, request, schedule, cancel }
}
