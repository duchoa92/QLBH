import { onBeforeUnmount, onMounted } from 'vue'
import { useModal } from '@/Stores/modal'
import { fetchPartialProps, isCanceled } from '@/Services/partialFetch'

/**
 * Nạp ngầm dữ liệu mới nhất cho một trang không dùng useLiveList (ví dụ trang Thiết lập).
 * Dùng chung cơ chế AJAX với useLiveList (Services/partialFetch) nên không điều hướng, không dựng lại trang.
 *
 *   const { refresh } = useSilentRefresh({
 *       keys: ['settings'],
 *       apply: (next) => { liveSettings.value = next.settings },
 *       enabled: () => !form.isDirty,
 *   })
 *   refresh(true)   // nạp ngay; mặc định tự nạp khi quay lại tab (cách lần trước ≥ minInterval)
 */
export function useSilentRefresh({ keys, apply, enabled = () => true, minInterval = 15000, path } = {}) {
    const modals = useModal()
    let last = Date.now()
    let seq = 0
    let controller = null

    const refresh = async (force = false) => {
        if (document.hidden) return
        if (!force && Date.now() - last < minInterval) return
        if (modals.modals.length || !enabled()) return

        controller?.abort()
        controller = new AbortController()
        const id = ++seq
        try {
            const next = await fetchPartialProps({ keys, path, signal: controller.signal })
            if (id !== seq || !enabled()) return
            apply(next)
            last = Date.now()
        } catch (error) {
            if (isCanceled(error)) return
            if (error?.response?.status === 409) return // phiên bản mới: để lần tải trang kế tiếp xử lý
            console.error(error)
        }
    }

    const onVisible = () => { if (!document.hidden) refresh() }

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisible)
        window.addEventListener('focus', onVisible)
    })
    onBeforeUnmount(() => {
        controller?.abort()
        document.removeEventListener('visibilitychange', onVisible)
        window.removeEventListener('focus', onVisible)
    })

    return { refresh }
}
