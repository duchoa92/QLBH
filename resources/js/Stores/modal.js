import { reactive } from 'vue'

export const modalState = reactive({
    show: false,
    title: '',
    component: null,
    props: {},
    onUpdated: null,
})

export const openModal = (component, options = {}) => {
    modalState.show = true
    modalState.component = component
    modalState.title = options.title || ''
    modalState.props = options.props || {}
    modalState.onUpdated = options.onUpdated || null 
}

export const closeModal = () => {
    modalState.show = false
    modalState.component = null
    modalState.props = {}
    modalState.onUpdated = null
}