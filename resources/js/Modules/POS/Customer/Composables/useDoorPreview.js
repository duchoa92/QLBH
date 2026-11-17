import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
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
export const useDoorPreview = (enabled, { interval = 100 } = {}) => {
    const src = ref('')
    const ok = ref(false)
    const loading = ref(true)
    const faces = ref([])
    let timer = null
    let busy = false
    let misses = 0
    let lastFrameAt = ''
    let generation = 0

    const release = () => {
        if (src.value) URL.revokeObjectURL(src.value)
        src.value = ''
    }

    const decodeFrame = url => new Promise((resolve, reject) => {
        const image = new Image()
        image.onload = resolve
        image.onerror = () => reject(new Error('Không giải mã được ảnh camera'))
        image.src = url
    })

    const tick = async () => {
        if (busy || document.hidden) return
        busy = true
        const requestGeneration = generation
        try {
            const path = lastFrameAt
                ? `/api/customers/door-camera/frame?after=${encodeURIComponent(lastFrameAt)}`
                : '/api/customers/door-camera/frame'
            const response = await api.get(path, { responseType: 'blob' })
            if (response.status === 200 && response.data?.size) {
                const next = URL.createObjectURL(response.data)
                try {
                    await decodeFrame(next)
                } catch (error) {
                    URL.revokeObjectURL(next)
                    throw error
                }
                if (requestGeneration !== generation) {
                    URL.revokeObjectURL(next)
                    return
                }
                const previous = src.value
                src.value = next
                faces.value = decodeFaces(response.headers?.['x-door-faces'])
                ok.value = true
                misses = 0
                lastFrameAt = response.headers?.['x-door-frame-at'] || ''
                await nextTick()
                if (previous) URL.revokeObjectURL(previous)
            } else if (response.headers?.['x-door-frame-unchanged'] === '1') {
                faces.value = decodeFaces(response.headers?.['x-door-faces'])
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
        generation += 1
        clearInterval(timer)
        timer = null
        release()
        ok.value = false
        faces.value = []
        loading.value = true
        misses = 0
        lastFrameAt = ''
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
