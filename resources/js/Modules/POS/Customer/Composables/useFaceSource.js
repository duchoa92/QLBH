import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useDoorQueue } from './useDoorQueue'
import { useFaceCheckIn } from './useFaceCheckIn'
import { useDoorPreview } from './useDoorPreview'

/**
 * Nguồn nhận diện khách cho POS.
 *  - Chưa bật "camera cửa" trong Cài đặt: dùng camera máy như trước đây.
 *  - Đã bật "camera cửa": LUÔN ưu tiên camera cửa (worker trên máy chủ). Chỉ khi worker không chạy hoặc camera cửa
 *    không lấy được ảnh (đã xác nhận qua vài lần hỏi liên tiếp) mới mở camera máy; camera cửa ổn lại thì đóng camera máy.
 *
 * Trả về cùng dạng với useFaceCheckIn / useDoorQueue, thêm `usingLocal` (đang dùng camera máy) để giao diện biết có hiện khung xem trước.
 */
export const useFaceSource = (options = {}) => {
    const page = usePage()
    const doorConfigured = !!page.props.settings?.face_door_camera_enabled // đọc lúc tải trang, giống trước đây

    if (!doorConfigured) {
        return { ...useFaceCheckIn(options), usingLocal: computed(() => true), fallbackActive: computed(() => false), doorMode: computed(() => false), doorPreviewSrc: ref(''), doorPreviewOk: ref(false) }
    }

    const door = useDoorQueue(options)
    const usingLocal = computed(() => door.active.value && door.unavailable.value)
    const local = useFaceCheckIn({ ...options, activeWhen: () => usingLocal.value })

    // Camera cửa đang là nguồn chính: hiện ảnh xem trước lấy từ máy chủ (camera máy dự phòng thì dùng khung video như cũ).
    const preview = useDoorPreview(computed(() => door.active.value && !usingLocal.value))

    const source = () => (usingLocal.value ? local : door)
    const pick = key => computed(() => source()[key].value)
    // Xử lý theo nguồn gốc của mục (camera cửa đánh dấu source = 'door'), không theo nguồn đang dùng: mục cũ vẫn có thể còn trên màn hình lúc chuyển nguồn.
    const owner = item => (item?.source === 'door' ? door : local)

    const statusText = computed(() => {
        if (!usingLocal.value) return door.statusText.value
        const own = local.statusText.value
        const notice = `${door.unavailableReason.value}, đang dùng camera máy`
        return !own || own === 'Đang chờ khách…' ? notice : `${notice} · ${own}`
    })

    return {
        featureOn: door.featureOn,
        enabled: door.enabled,
        active: door.active,
        failed: pick('failed'),
        facing: local.facing,
        switching: local.switching,
        tone: pick('tone'),
        suggestions: pick('suggestions'),
        queue: pick('queue'),
        liveFaces: pick('liveFaces'),
        statusText,
        usingLocal,
        doorMode: computed(() => door.active.value && !usingLocal.value),
        doorPreviewSrc: preview.src,
        doorPreviewOk: preview.ok,
        fallbackActive: usingLocal,
        attachPreview: local.attachPreview,
        toggle: door.toggle, // một công tắc chung: tắt là tắt cả camera cửa lẫn camera máy dự phòng
        switchCamera: local.switchCamera,
        confirm: item => owner(item).confirm(item),
        dismiss: item => owner(item).dismiss(item),
        dismissAll: () => { door.dismissAll(); local.dismissAll() },
        markServed: (item, ...rest) => owner(item).markServed(item, ...rest),
    }
}
