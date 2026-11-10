// Nhãn và màu trạng thái phiếu sửa chữa, dùng chung cho trang Sửa chữa và ô tìm nhanh.
export const REPAIR_STATUS_CLASSES = {
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    repairing: 'bg-purple-50 text-purple-700 border-purple-200',
    done: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    returned: 'bg-slate-100 text-slate-600 border-slate-200',
    cancelled: 'bg-rose-50 text-rose-700 border-rose-200',
}

export const REPAIR_STATUS_LABELS = {
    pending: 'Tiếp nhận',
    repairing: 'Đang sửa',
    done: 'Hoàn tất sửa',
    returned: 'Đã trả khách',
    cancelled: 'Hủy',
}
