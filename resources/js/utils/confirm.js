import { openModal } from '@/Stores/modal'
import ConfirmModal from '@/Components/ConfirmModal.vue'

export const confirmDelete = (message, onConfirm) => {
    openModal(ConfirmModal, {
        title: 'Xác nhận',
        props: {
            message,
            onConfirm
        }
    })
}