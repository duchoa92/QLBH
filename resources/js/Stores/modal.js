import { reactive } from 'vue'

const state = reactive({
    modals: []
})

export function useModal() {
    return state
}

export function openModal(component, options = {}) {
    state.modals.push({
        id: Date.now(), // ✅ thêm dòng này
        component,
        props: options.props || {},
        onUpdated: options.onUpdated || null
    })
}

export function closeModal(index = null) {
    if (index !== null) {
        state.modals.splice(index, 1)
    } else {
        state.modals.pop()
    }
}