import {
    ref,
    onBeforeUnmount,
    watch,
} from 'vue'

import api from '@/Services/api'
import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'

export function useSupplierSearch(emit) {

    const keyword = ref('')
    const suppliers = ref([])

    let timeout = null
    let requestId = 0
    let skipKeywordWatch = false

    const search = () => {

        if (skipKeywordWatch) {
            skipKeywordWatch = false
            return
        }

        clearTimeout(timeout)
        const currentRequest = ++requestId
        const value = keyword.value.trim()

        if (!value) {
            suppliers.value = []
            reset()
            return
        }

        timeout = setTimeout(
            async () => {
                try {

                    const response =
                        await api.get(
                            '/api/suppliers/search',
                            {
                                params: {
                                    search: value,
                                },
                            }
                        )

                    if (currentRequest !== requestId) return

                    const rows = Array.isArray(response.data)
                        ? response.data
                        : (response.data?.data || response.data?.suppliers || [])
                    suppliers.value = Array.isArray(rows) ? rows : []

                    reset()

                } catch (error) {

                    if (currentRequest !== requestId) return

                    console.error(
                        'Lỗi tìm kiếm nhà cung cấp:',
                        error
                    )

                    suppliers.value = []
                }

            },
            300
        )
    }

    watch(keyword, search)


    const selectSupplier = (
        supplier
    ) => {

        clearTimeout(timeout)
        requestId++
        skipKeywordWatch = true

        keyword.value =
            supplier.name

        suppliers.value = []

        emit(
            'selected',
            supplier
        )

        reset()
    }


    const clearSupplier = () => {

        clearTimeout(timeout)
        requestId++

        keyword.value = ''

        suppliers.value = []

        emit(
            'selected',
            null
        )

        reset()
    }


    const resetSupplierSearch = () => {

        clearTimeout(timeout)
        requestId++

        keyword.value = ''

        suppliers.value = []

        activeIndex.value = -1

        reset()
    }


    const {
        activeIndex,
        itemRefs,
        setItemRef,
        onKeyDown,
        setActive,
        reset,
    } = useAutocompleteKeyboard(
        suppliers,
        supplier => {
            selectSupplier(supplier)
        }
    )


    onBeforeUnmount(() => {

        clearTimeout(timeout)
        requestId++

    })


    return {

        keyword,

        suppliers,

        search,

        selectSupplier,

        clearSupplier,

        resetSupplierSearch,

        activeIndex,

        itemRefs,

        setItemRef,

        onKeyDown,

        setActive,

    }
}
