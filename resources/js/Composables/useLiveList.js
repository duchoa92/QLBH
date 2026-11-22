import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { fetchPartialProps, isCanceled } from '@/Services/partialFetch'
import { useModal } from '@/Stores/modal'
import { onLiveChange } from '@/Services/liveSync'

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
 *
 * Truyền `channels: ['products', 'brands']` để danh sách TỰ làm mới khi các nhóm dữ liệu đó đổi (do bất kỳ ai),
 * vẫn giữ bộ lọc, sắp xếp và trang đang xem.
 */
export function useLiveList({ props, dataKey, filtersKey = 'filters', extraKeys = [], params, path, delay = 180, refreshOnFocus = true, focusInterval = 15000, channels = null }) {
    const page = usePage()
    const modals = useModal()
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
    let lastLoaded = Date.now()

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

    // Làm mới ngầm phải giữ trang đang xem (?page=N), nếu không danh sách sẽ nhảy về trang 1.
    const silentOverrides = () => {
        const current = new URL(window.location.href).searchParams.get('page')
        return current && Number(current) > 1 ? { page: current } : {}
    }

    // silent = true: làm mới ngầm (không bật loading, không đổi URL) khi người dùng quay lại tab.
    const request = async (overrides = {}, { silent = false } = {}) => {
        clearTimeout(timer)
        timer = null
        controller?.abort()
        const id = ++seq
        controller = new AbortController()
        if (!silent) loading.value = true

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
            const next = await fetchPartialProps({
                keys: [dataKey, filtersKey, ...extraKeys],
                params: query,
                path,
                signal: controller.signal,
            })
            if (id !== seq) return
            if (dataKey in next) data.value = next[dataKey]
            if (filtersKey in next) filters.value = next[filtersKey] || {}
            extraKeys.forEach(key => { if (key in next) extras[key].value = next[key] })
            if (!silent) syncUrl(query)
            lastLoaded = Date.now()
        } catch (error) {
            if (isCanceled(error)) return
            if (error?.response?.status === 409) { window.location.reload(); return }
            if (id === seq) console.error(error)
        } finally {
            // Luôn tắt trạng thái tải của lượt mới nhất (kể cả lượt ngầm đã hủy lượt có loading trước đó, tránh bị kẹt).
            if (id === seq) loading.value = false
        }
    }

    const schedule = () => {
        clearTimeout(timer)
        timer = setTimeout(() => { timer = null; request() }, delay)
    }

    const cancel = () => {
        clearTimeout(timer)
        timer = null
        controller?.abort()
    }

    // Quay lại tab/cửa sổ: làm mới ngầm danh sách theo đúng bộ lọc hiện tại.
    // Bỏ qua khi tab ẩn, đang tải/đang gõ tìm kiếm hoặc đang mở modal để không làm gián đoạn người dùng.
    const refreshOnReturn = () => {
        if (document.hidden || loading.value || timer || modals.modals.length) return
        if (liveDirty) { runLive(); return }
        if (Date.now() - lastLoaded < focusInterval) return
        request(silentOverrides(), { silent: true })
    }

    // --- Tự làm mới khi dữ liệu thay đổi (theo nhóm dữ liệu) ---
    let liveDirty = false
    let liveTimer = null
    let liveRetry = null
    let offLive = null

    // Không chặn khi đang mở modal: danh sách phía sau được thay dữ liệu mới, modal giữ bản riêng nên không bị ảnh hưởng.
    const liveBlocked = () => document.hidden || loading.value || timer

    const scheduleLiveRetry = () => {
        if (liveRetry || document.hidden) return
        liveRetry = setTimeout(() => { liveRetry = null; if (liveDirty) runLive() }, 1500)
    }

    function runLive() {
        liveTimer = null
        if (liveBlocked()) { liveDirty = true; scheduleLiveRetry(); return }
        liveDirty = false
        request(silentOverrides(), { silent: true })
    }

    const onLiveChanged = () => {
        liveDirty = true
        clearTimeout(liveTimer)
        liveTimer = setTimeout(runLive, 400)
    }

    onMounted(() => {
        if (channels?.length) offLive = onLiveChange(channels, onLiveChanged)
        if (!refreshOnFocus) return
        document.addEventListener('visibilitychange', refreshOnReturn)
        window.addEventListener('focus', refreshOnReturn)
    })
    onBeforeUnmount(() => {
        offLive?.()
        clearTimeout(liveTimer)
        clearTimeout(liveRetry)
        document.removeEventListener('visibilitychange', refreshOnReturn)
        window.removeEventListener('focus', refreshOnReturn)
        cancel()
    })

    return { data, filters, extras, loading, request, schedule, cancel }
}
