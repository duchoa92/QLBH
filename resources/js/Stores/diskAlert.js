import { ref } from 'vue'

// Trạng thái "đã ẩn thông báo ổ đĩa", dùng chung giữa DiskHealthBanner và nút chức năng nổi (FloatingActions).
// Lưu mức cảnh báo đã ẩn: chuyển sang mức nặng hơn thì thông báo tự hiện lại.
const STORAGE_KEY = 'qlbh.diskHealth.collapsedLevel'

const read = () => {
    try { return window.localStorage.getItem(STORAGE_KEY) } catch { return null }
}

export const diskAlertCollapsedLevel = ref(typeof window === 'undefined' ? null : read())

export const setDiskAlertCollapsed = (level) => {
    diskAlertCollapsedLevel.value = level || null
    try {
        if (level) window.localStorage.setItem(STORAGE_KEY, level)
        else window.localStorage.removeItem(STORAGE_KEY)
    } catch { /* trình duyệt chặn lưu trữ: chỉ mất khả năng nhớ */ }
}
