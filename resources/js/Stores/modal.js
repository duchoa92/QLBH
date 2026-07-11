import { reactive, markRaw } from 'vue'

export const modalState = reactive({
    show: false,
    title: '',
    component: null,
    props: {},
    onUpdated: null,
})

export const openModal = (component, options = {}) => {

    // ✅ FIX GIẬT LAYOUT
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth

    document.body.style.overflow = 'hidden'
    document.body.style.paddingRight = scrollbarWidth + 'px'

    modalState.show = true
    modalState.component = markRaw(component)
    modalState.title = options.title || ''
    modalState.props = options.props ?? options
    modalState.onUpdated = options.onUpdated || null

    // 👉 FIX CO LAYOUT
    scrollBarWidth = getScrollbarWidth()
    document.body.style.overflow = 'hidden'
    document.body.style.paddingRight = scrollBarWidth + 'px'
}

export const closeModal = () => {

    // ✅ RESET LẠI SCROLL
    document.body.style.overflow = ''
    document.body.style.paddingRight = ''

    modalState.show = false
    modalState.component = null
    modalState.props = {}
    modalState.onUpdated = null

    // 👉 RESET
    document.body.style.overflow = ''
    document.body.style.paddingRight = ''
}