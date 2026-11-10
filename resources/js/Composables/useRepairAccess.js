import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { useConfirm } from '@/Composables/useConfirm'

/**
 * Quyền, nhãn nút và thao tác dùng chung của phiếu sửa chữa.
 * Dùng cho trang Sửa chữa và modal phiếu mở từ ô tìm nhanh để hai nơi luôn cùng quy tắc.
 */
export function useRepairAccess() {
    const page = usePage()
    const confirmBox = useConfirm()
    const permissions = computed(() => page.props.auth?.permissions || [])
    const can = (permission) => permissions.value.includes(permission)
    const isSuperAdmin = computed(() => (page.props.auth?.roles || []).includes('Super Admin'))
    const canViewCredentials = computed(() => can('repairs.create') || can('repairs.edit') || can('repairs.complete'))

    // Người dùng có được tiến hành bước tiếp theo của phiếu (sửa / hoàn tất / trả máy) hay không.
    const canProgress = (repair) => {
        if (repair.status === 'done') return can('repairs.return')
        if (repair.status === 'repairing') return can('repairs.complete') || can('repairs.edit')
        return can('repairs.edit')
    }

    // Nhãn nút tiến độ trong chi tiết phiếu; chuỗi rỗng = ẩn nút.
    const progressLabel = (repair) => {
        if (!repair || !['pending', 'repairing', 'done'].includes(repair.status) || !canProgress(repair)) return ''
        if (repair.status === 'done') return 'Thanh toán / trả máy'
        return repair.status === 'pending' ? 'Chuyển sang bước sửa' : 'Cập nhật sửa chữa'
    }

    // Xóa phiếu (chỉ Super Admin). `onDeleted` chạy sau khi xóa thành công, có thể trả Promise.
    const removeRepair = (repair, onDeleted = null) => {
        if (!isSuperAdmin.value || !repair?.id) return
        const notes = []
        if (repair.parts?.length) notes.push('linh kiện đã dùng sẽ được hoàn lại kho')
        if (['done', 'returned'].includes(repair.status)) notes.push('công nợ phát sinh từ phiếu (nếu có) sẽ được đảo lại')
        confirmBox.show({
            title: 'Xóa phiếu sửa chữa',
            message: `Xóa phiếu ${repair.code} (${repair.device_name})?` + (notes.length ? ` Khi xóa, ${notes.join(' và ')}.` : '') + ' Thao tác này không thể hoàn tác trên giao diện.',
            confirmText: 'Xóa phiếu',
            cancelText: 'Hủy',
            onConfirm: () => new Promise((resolve, reject) => {
                router.delete(route('repairs.destroy', repair.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Đã xóa phiếu sửa chữa')
                        Promise.resolve(onDeleted?.()).then(resolve).catch(reject)
                    },
                    onError: (errors) => reject(new Error(Object.values(errors || {}).flat()[0] || 'Không thể xóa phiếu sửa chữa.')),
                })
            }),
        })
    }

    return { can, isSuperAdmin, canViewCredentials, canProgress, progressLabel, removeRepair }
}

// Tải thông tin bảo mật (mật khẩu máy...) của một phiếu. Trả về null khi lỗi.
// `isCurrent` giúp bỏ qua thông báo lỗi khi người dùng đã chuyển sang phiếu khác.
export async function fetchRepairCredentials(repairId, isCurrent = () => true) {
    try {
        const { data } = await axios.get(route('repairs.credentials', repairId))
        return data
    } catch {
        if (isCurrent()) toast.error('Không tải được thông tin bảo mật của phiếu.')
        return null
    }
}
