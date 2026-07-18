import { reactive } from 'vue'

const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmText: 'OK',
    cancelText: 'Hủy',
    onConfirm: null
})

export function useConfirm() {

    const show = (options) => {
        state.show = true
        Object.assign(state, options)
    }

    const confirm = async () => {
        if (state.onConfirm) {
            await state.onConfirm()
        }

        state.show = false
    }

    const cancel = () => {
        state.show = false
    }

    return {
        state,
        show,
        confirm,
        cancel
    }
}