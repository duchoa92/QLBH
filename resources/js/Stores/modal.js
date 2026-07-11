import { reactive, markRaw } from 'vue'

export const modalState = reactive({
    show: false,
    component: null,
    props: {},
    title: '',
    onUpdated: null
})

let scrollBarWidth = 0

const getScrollbarWidth = () => {
    return window.innerWidth - document.documentElement.clientWidth
}

export const openModal = (component, options = {}) => {
    modalState.show = true
    modalState.component = markRaw(component)
    modalState.props = options.props || {}
    modalState.title = options.title || ''
    modalState.onUpdated = options.onUpdated || null

    // fix co layout
    scrollBarWidth = getScrollbarWidth()
    document.body.style.overflow = 'hidden'
    document.body.style.paddingRight = scrollBarWidth + 'px'
}

export const closeModal = () => {
    modalState.show = false
    modalState.component = null
    modalState.props = {}
    modalState.title = ''
    modalState.onUpdated = null

    document.body.style.overflow = ''
    document.body.style.paddingRight = ''
}