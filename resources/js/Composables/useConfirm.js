import { reactive } from 'vue'

const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmText: 'OK',
    cancelText: 'Hủy',
    onConfirm: null,
    error: '',
    confirming: false,
})

export function useConfirm() {

    const show = (options) => {
        state.error = ''
        state.confirming = false
        state.show = true
        Object.assign(state, options)
    }

    const confirm = async () => {
        if (state.confirming) return

        state.confirming = true
        state.error = ''
        try {
            if (state.onConfirm) {
                await state.onConfirm()
            }
            state.show = false
        } catch (error) {
            console.error('Confirm action failed:', error)
            state.error = error?.message || 'Không thể thực hiện thao tác. Vui lòng thử lại.'
        } finally {
            state.confirming = false
        }
    }

    const cancel = () => {
        if (state.confirming) return
        state.show = false
    }

    return {
        state,
        show,
        confirm,
        cancel
    }
}
