import { onBeforeUnmount, ref, watch } from 'vue'
import api from '@/Services/api'

const decodeFaces = (header) => {
    if (!header) return []
    try {
        const bytes = Uint8Array.from(atob(header), char => char.charCodeAt(0))
        const list = JSON.parse(new TextDecoder().decode(bytes))
        return Array.isArray(list) ? list : []
    } catch {
        return []
    }
}

/**
 * Ảnh xem trước camera cửa: hỏi máy chủ khung hình mới nhất theo chu kỳ ngắn (không mở camera máy).
 * `src` là địa chỉ blob để gắn vào <img>; `ok` = đã có ảnh gần đây; `loading` = chưa có lần trả lời nào.
 * `faces` = khung bắt mặt worker gửi kèm ảnh: [{ id, x, y, w, h, kind, label }] toạ độ 0..1 (label là tên khách nếu đã nhận ra).
 */
export const useDoorPreview = (enabled, { interval = 300 } = {}) => {
    const src = ref('')
    const ok = ref(false)
    const loading = ref(true)
    const faces = ref([])
    let timer = null
    let busy = false
    let misses = 0

    const release = () => {
        if (src.value) URL.revokeObjectURL(src.value)
        src.value = ''
    }

    const tick = async () => {
        if (busy || document.hidden) return
        busy = true
        try {
            const response = await api.get('/api/customers/door-camera/frame', { responseType: 'blob' })
            if (response.status === 200 && response.data?.size) {
                const next = URL.createObjectURL(response.data)
                release()
                src.value = next
                faces.value = decodeFaces(response.headers?.['x-door-faces'])
                ok.value = true
                misses = 0
            } else if (++misses >= 3) {
                ok.value = false
                faces.value = []
            }
        } catch {
            if (++misses >= 3) { ok.value = false; faces.value = [] }
        } finally {
            loading.value = false
            busy = false
        }
    }

    const stop = () => {
        clearInterval(timer)
        timer = null
        release()
        ok.value = false
        faces.value = []
        loading.value = true
        misses = 0
    }
    const start = () => {
        stop()
        tick()
        timer = setInterval(tick, interval)
    }

    watch(enabled, value => { value ? start() : stop() }, { immediate: true })
    onBeforeUnmount(stop)

    return { src, ok, loading, faces }
}
