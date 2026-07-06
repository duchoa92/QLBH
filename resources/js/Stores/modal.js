import { reactive, markRaw } from 'vue'

export const modalState = reactive({
    show: false,
    title: '',
    component: null,
    props: {},
    onUpdated: null,
})

export const openModal = (component, options = {}) => {
    console.log('OPEN MODAL', component) // 👈 debug

    modalState.show = true
    modalState.component = markRaw(component)
    modalState.title = options.title || ''
    modalState.props = options.props ?? options
    modalState.onUpdated = options.onUpdated || null
}

export const closeModal = () => {
    modalState.show = false
    modalState.component = null
    modalState.props = {}
    modalState.onUpdated = null
}