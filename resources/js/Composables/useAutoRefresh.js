import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useModal } from '@/Stores/modal'
import { fetchPartialProps, isCanceled } from '@/Services/partialFetch'
import { onLiveChange } from '@/Services/liveSync'

const currentQuery = () => {
    const query = {}
    new URLSearchParams(window.location.search).forEach((value, key) => { query[key] = value })
    return query
}

/**
 * Tự làm mới dữ liệu của một trang khi các nhóm dữ liệu liên quan thay đổi (ai đổi cũng được).
 *
 *   useAutoRefresh({
 *       channels: ['sales', 'customers'],   // nhóm dữ liệu trang hiển thị (xem App\Support\DataVersion)
 *       keys: ['sales', 'stats'],           // props Inertia cần lấy lại
 *       apply: (next) => { ... },           // áp dữ liệu mới vào state cục bộ
 *       enabled: () => !loading.value,      // tuỳ chọn: hoãn khi trang đang bận
 *   })
 *
 * - Có `apply`: tải ngầm bằng AJAX theo đúng bộ lọc/trang trên URL, không điều hướng, không dựng lại trang.
 * - Không có `apply`: dùng router.reload({ only: keys }) giữ cuộn và trạng thái.
 * - Hoãn khi tab ẩn, đang mở modal hoặc enabled() = false; xong sẽ tự làm mới lại.
 */
export function useAutoRefresh({ channels, keys, apply, enabled = () => true, params, path, debounce = 400, minGap = 1500 } = {}) {
    const modals = useModal()
    let timer = null
    let retry = null
    let controller = null
    let seq = 0
    let dirty = false
    let lastRun = 0
    let off = null

    const blocked = () => document.hidden || modals.modals.length > 0 || !enabled()

    const scheduleRetry = () => {
        if (retry || document.hidden) return
        retry = setTimeout(() => { retry = null; if (dirty) run() }, 1500)
    }

    const run = async () => {
        timer = null
        if (blocked()) { dirty = true; scheduleRetry(); return }
        const wait = minGap - (Date.now() - lastRun)
        if (wait > 0) { timer = setTimeout(run, wait); return }
        dirty = false
        lastRun = Date.now()

        if (typeof apply !== 'function') {
            router.reload({ only: keys, preserveScroll: true, preserveState: true })
            return
        }

        controller?.abort()
        controller = new AbortController()
        const id = ++seq
        try {
            const next = await fetchPartialProps({
                keys,
                params: typeof params === 'function' ? params() : (params ?? currentQuery()),
                path,
                signal: controller.signal,
            })
            if (id !== seq) return
            if (blocked()) { dirty = true; scheduleRetry(); return }
            apply(next)
        } catch (error) {
            if (isCanceled(error)) return
            if (error?.response?.status === 409) return
            if (id === seq) console.error(error)
        }
    }

    const onChanged = () => {
        dirty = true
        clearTimeout(timer)
        timer = setTimeout(run, debounce)
    }

    const onVisible = () => {
        if (!document.hidden && dirty) { clearTimeout(timer); timer = setTimeout(run, 200) }
    }

    onMounted(() => {
        off = onLiveChange(channels, onChanged)
        document.addEventListener('visibilitychange', onVisible)
    })
    onBeforeUnmount(() => {
        off?.()
        document.removeEventListener('visibilitychange', onVisible)
        clearTimeout(timer)
        clearTimeout(retry)
        controller?.abort()
    })

    return { refresh: onChanged }
}
