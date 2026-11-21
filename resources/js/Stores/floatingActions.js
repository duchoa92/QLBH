import { ref } from 'vue'

// Vùng chứa các nút chức năng của trang (do FloatingActions.vue tạo). ListToolbar/PageHeader teleport slot `actions` vào đây.
export const floatingActionsTarget = ref(null)
// Số khối nút chức năng của trang đang được teleport vào (để biết có hiện tiêu đề "Chức năng của trang" hay không).
export const floatingActionsCount = ref(0)
