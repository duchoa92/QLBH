import { ref, nextTick, watch } from 'vue'

export function useAutocompleteKeyboard(optionsRef, onSelect) {
    const activeIndex = ref(-1)
    const itemRefs = ref([])

    /*
    |--------------------------------------------------
    | RESET khi options thay đổi
    |--------------------------------------------------
    */
    watch(
        () => optionsRef.value,
        () => {
            activeIndex.value = -1
        }
    )

    const setItemRef = (el, index) => {
        if (el) {
            itemRefs.value[index] = el
        }
    }

    const scrollToItem = async (index) => {
        await nextTick()

        const el = itemRefs.value[index]

        if (el) {
            el.scrollIntoView({
                block: 'nearest',
                behavior: 'smooth',
            })
        }
    }

    const reset = () => {
        activeIndex.value = -1
    }

    const onKeyDown = (e) => {
        const max = optionsRef.value.length - 1

        if (max < 0) return

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault()

                if (activeIndex.value < 0) {
                    activeIndex.value = 0
                } else {
                    activeIndex.value =
                        activeIndex.value < max ? activeIndex.value + 1 : 0
                }

                scrollToItem(activeIndex.value)
                break

            case 'ArrowUp':
                e.preventDefault()

                if (activeIndex.value < 0) {
                    activeIndex.value = max
                } else {
                    activeIndex.value =
                        activeIndex.value > 0 ? activeIndex.value - 1 : max
                }

                scrollToItem(activeIndex.value)
                break

            case 'Enter':
                e.preventDefault()

                const item = optionsRef.value?.[activeIndex.value]

                if (item) {
                    onSelect(item)
                    reset()
                }
                break

            case 'Escape':
                reset()
                break
        }
    }

    const setActive = (index) => {
        activeIndex.value = index
    }

    return {
        activeIndex,
        itemRefs,
        setItemRef,
        onKeyDown,
        setActive,
        reset,
    }
}