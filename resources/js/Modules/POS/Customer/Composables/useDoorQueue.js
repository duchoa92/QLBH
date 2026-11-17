import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import api from '@/Services/api'

/**
 * Chế độ "camera cửa": POS KHÔNG mở camera. Worker camera cửa (chạy nền trên máy chủ) lo nhận diện và ghi lượt khách;
 * POS chỉ hỏi máy chủ định kỳ "ai đang đứng ở cửa" rồi hiện gợi ý / hàng chờ.
 *
 * Trả về cùng dạng với useFaceCheckIn để CustomerSection dùng chung giao diện.
 *  - Khách quen đang ở cửa -> gợi ý (khi POS đang rảnh); bấm "Đúng" thì chọn khách và đánh dấu đã phục vụ.
 *  - Khách lạ (chưa có dữ liệu) -> nằm trong hàng chờ; bấm để nhập thông tin, lượt được gán cho khách vừa tạo.
 *  - Đã phục vụ ở quầy nào thì các quầy khác không gợi ý lại.
 */
const POLL_MS = 2500
const DISMISS_COOLDOWN = 60000
const DEFAULT_KEY = 'pos.doorQueue'
const UNAVAILABLE_AFTER = 2 // số lần hỏi liên tiếp báo worker/camera lỗi (~5 giây) thì coi camera cửa không dùng được
const RECOVER_AFTER = 2 // số lần liên tiếp báo ổn lại thì quay về camera cửa

const readEnabled = (key) => { try { return localStorage.getItem(key) !== '0' } catch { return true } }

export const useDoorQueue = ({ paused = () => false, onConfirm = () => {}, currentCustomerId = () => null, storageKey = DEFAULT_KEY } = {}) => {
    const page = usePage()
    const featureOn = computed(() => !!page.props.settings?.face_recognition_enabled && !!page.props.settings?.face_door_camera_enabled)
    const enabled = ref(readEnabled(storageKey))
    const active = computed(() => featureOn.value && enabled.value)

    const present = ref([])
    const workerOnline = ref(true)
    const cameraOk = ref(true)
    const requestFailed = ref(false)
    const unavailable = ref(false) // camera cửa không dùng được (worker không chạy / camera lỗi), có độ trễ để khỏi nhấp nháy
    let badStreak = 0
    let goodStreak = 0
    const tick = ref(0) // tăng mỗi lần dữ liệu/trạng thái đổi để các computed tính lại (cooldown theo thời gian)
    const dismissed = new Map() // visitId -> thời điểm bỏ qua
    const served = new Set() // visitId đã phục vụ ở quầy này

    let timer = null
    let busy = false

    const toItem = visit => ({
        source: 'door', // useFaceSource dựa vào đây để biết mục thuộc camera cửa hay camera máy
        trackId: `v${visit.id}`,
        visitId: visit.id,
        kind: visit.kind,
        customer: visit.customer,
        confidence: 1,
        thumb: visit.thumb,
        descriptor: null, // vector nằm ở máy chủ; POS không giữ
    })

    const items = computed(() => {
        void tick.value
        return present.value.filter(visit => !served.has(visit.id)).map(toItem)
    })

    const suggestions = computed(() => {
        void tick.value
        if (!active.value || paused()) return []
        const now = Date.now()
        return items.value.filter((item) => {
            if (item.kind !== 'known' || !item.customer) return false
            if (item.customer.id === currentCustomerId()) return false
            const skippedAt = dismissed.get(item.visitId)
            return !(skippedAt && now - skippedAt < DISMISS_COOLDOWN)
        })
    })

    const queue = computed(() => {
        if (!active.value) return []
        const inSuggestion = new Set(suggestions.value.map(item => item.visitId))
        return items.value.filter(item => !inSuggestion.has(item.visitId))
    })

    const failed = computed(() => active.value && (requestFailed.value || !workerOnline.value || !cameraOk.value))
    const tone = computed(() => {
        if (failed.value) return 'error'
        return suggestions.value.length ? 'found' : 'idle'
    })
    const statusText = computed(() => {
        if (!active.value) return ''
        if (requestFailed.value) return 'Không kết nối được máy chủ'
        if (!workerOnline.value) return 'Camera cửa chưa chạy'
        if (!cameraOk.value) return 'Không lấy được ảnh camera cửa'
        if (paused()) return 'Đã có khách'
        if (suggestions.value.length) return suggestions.value.length > 1 ? `Nhận ra ${suggestions.value.length} khách` : 'Đã nhận ra khách'
        if (queue.value.length) return `${queue.value.length} khách ở cửa`
        return 'Đang chờ khách…'
    })

    const poll = async () => {
        if (busy || document.hidden) return
        busy = true
        try {
            const { data } = await api.get('/api/customers/visits/present')
            present.value = Array.isArray(data?.visits) ? data.visits : []
            workerOnline.value = !!data?.worker_online
            cameraOk.value = data?.camera_ok !== false
            requestFailed.value = false
            // Chỉ tính khi máy chủ trả lời được: mất kết nối tới máy chủ thì camera máy cũng không nhận diện được.
            if (!workerOnline.value || !cameraOk.value) {
                goodStreak = 0
                if (++badStreak >= UNAVAILABLE_AFTER) unavailable.value = true
            } else {
                badStreak = 0
                if (++goodStreak >= RECOVER_AFTER) unavailable.value = false
            }
        } catch {
            requestFailed.value = true
        } finally {
            busy = false
            tick.value += 1
        }
    }

    const start = () => {
        stop()
        poll()
        timer = setInterval(poll, POLL_MS)
    }
    const stop = () => {
        if (timer) clearInterval(timer)
        timer = null
        present.value = []
        unavailable.value = false
        badStreak = 0
        goodStreak = 0
    }
    const onVisible = () => { if (!document.hidden && active.value) poll() }

    const markServedOnServer = (visitId) => {
        if (visitId) api.post(`/api/customers/visits/${visitId}/served`).catch(() => {})
    }

    /* ---------------- thao tác của nhân viên ---------------- */
    const confirm = (suggestion) => {
        if (!suggestion) return
        served.add(suggestion.visitId)
        markServedOnServer(suggestion.visitId)
        onConfirm(suggestion.customer)
        tick.value += 1
    }

    const dismiss = (suggestion) => {
        if (!suggestion) return
        dismissed.set(suggestion.visitId, Date.now())
        tick.value += 1
    }

    const dismissAll = () => { [...suggestions.value].forEach(dismiss) }

    // Gọi sau khi gán khách lạ cho khách vừa tạo (CustomerSection đã gọi /link): đánh dấu đã phục vụ.
    const markServed = (item) => {
        if (!item?.visitId) return
        served.add(item.visitId)
        markServedOnServer(item.visitId)
        tick.value += 1
    }

    const toggle = () => {
        enabled.value = !enabled.value
        try { localStorage.setItem(storageKey, enabled.value ? '1' : '0') } catch { /* bỏ qua */ }
    }

    watch(active, (value) => { value ? start() : stop() }, { immediate: true })
    document.addEventListener('visibilitychange', onVisible)
    onBeforeUnmount(() => {
        stop()
        document.removeEventListener('visibilitychange', onVisible)
    })

    return {
        featureOn,
        enabled,
        active,
        failed,
        unavailable,
        unavailableReason: computed(() => (!workerOnline.value ? 'Camera cửa chưa chạy' : 'Không lấy được ảnh camera cửa')),
        facing: ref('user'),
        switching: ref(false),
        tone,
        suggestions,
        queue,
        liveFaces: ref([]),
        statusText,
        attachPreview: () => {},
        toggle,
        switchCamera: () => {},
        confirm,
        dismiss,
        dismissAll,
        markServed,
    }
}
