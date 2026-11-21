import { ref } from 'vue'

// Hộp tìm kiếm toàn hệ thống dạng cửa sổ (Ctrl/Cmd + K hoặc từ nút nổi) cho các trang không có ô tìm kiếm riêng.
export const globalSearchDialogOpen = ref(false)
export const openGlobalSearchDialog = () => { globalSearchDialogOpen.value = true }
