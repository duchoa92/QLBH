import { onBeforeUnmount, onMounted } from 'vue'
import { onLiveChange } from '@/Services/liveSync'

/**
 * Cho modal/khối chi tiết tự tải lại dữ liệu của mình khi các nhóm dữ liệu liên quan đổi.
 *
 *   useLiveReload(['customers', 'debts'], () => load({ silent: true }))
 *
 * `reload` nên là bản tải NGẦM (không bật trạng thái loading, không báo lỗi). channels = null: mọi nhóm.
 */
export function useLiveReload(channels, reload, { debounce = 500 } = {}) {
    let timer = null
    let off = null

    onMounted(() => {
        off = onLiveChange(channels, () => {
            clearTimeout(timer)
            timer = setTimeout(() => { if (!document.hidden) reload() }, debounce)
        })
    })
    onBeforeUnmount(() => {
        off?.()
        clearTimeout(timer)
    })
}
