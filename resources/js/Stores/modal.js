import { reactive } from 'vue'

let uid = 0

const state = reactive({
    modals: []
})

export function useModal() {
    return state
}

export function openModal(component, options = {}) {
    state.modals.push({
        id: ++uid, // 🔥 thêm id
        component,
        props: options.props || {},
        onUpdated: options.onUpdated || null
    })
}

export function closeModal(id = null) {
    if (id !== null) {
        state.modals = state.modals.filter(m => m.id !== id)
    } else {
        state.modals.pop()
    }
}