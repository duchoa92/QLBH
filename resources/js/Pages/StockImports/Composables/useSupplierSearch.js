import axios from 'axios'

import {
    ref,
    onBeforeUnmount,
} from 'vue'

import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'

export function useSupplierSearch(emit) {

    const keyword = ref('')
    const suppliers = ref([])

    let timeout = null

    const search = () => {

        clearTimeout(timeout)

        timeout = setTimeout(
            async () => {

                const value =
                    keyword.value.trim()

                if (!value) {
                    suppliers.value = []
                    reset()
                    return
                }

                try {

                    const response =
                        await axios.get(
                            '/api/suppliers/search',
                            {
                                params: {
                                    search: value,
                                },
                            }
                        )

                    suppliers.value =
                        Array.isArray(response.data)
                            ? response.data
                            : []

                    reset()

                } catch (error) {

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


    const selectSupplier = (
        supplier
    ) => {

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

        keyword.value = ''

        suppliers.value = []

        emit(
            'selected',
            null
        )

        reset()
    }


    const resetSupplierSearch = () => {

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