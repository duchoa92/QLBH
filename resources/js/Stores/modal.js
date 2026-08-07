import { reactive, markRaw } from 'vue'

let uid = 0

const state = reactive({
    modals: []
})

export function useModal() {
    return state
}

export function openModal(component, options = {}) {
    state.modals.push({
        id: ++uid,
        component,
        component: markRaw(component),
        props: options.props || {},
        onUpdated: options.onUpdated || null
    })
}

export function closeModal(id = null) {
    if (id !== null) {
        const index = state.modals.findIndex(m => m.id === id)
        if (index !== -1) state.modals.splice(index, 1)
    } else {
        state.modals.pop()
    }
}