import { reactive } from 'vue'

// Cảnh báo hệ thống (lịch chạy nền, mạng...) đã được ẩn vào nút chức năng nổi (FloatingActions).
// Mỗi cảnh báo nhớ "chữ ký" của nội dung đã ẩn: nội dung đổi (nặng hơn / vấn đề khác) thì thanh cảnh báo tự hiện lại.
// Cảnh báo ổ đĩa có store riêng (diskAlert.js) từ trước.
const STORAGE_KEY = 'qlbh.alerts.collapsed'

const read = () => {
    try { return JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}') || {} } catch { return {} }
}
const save = () => {
    try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsedAlerts)) } catch { /* trình duyệt chặn lưu trữ: chỉ mất khả năng nhớ */ }
}

export const collapsedAlerts = reactive(typeof window === 'undefined' ? {} : read())

export const isAlertCollapsed = (id, signature) => collapsedAlerts[id] === signature
export const collapseAlert = (id, signature) => { collapsedAlerts[id] = signature; save() }
export const expandAlert = (id) => { delete collapsedAlerts[id]; save() }
