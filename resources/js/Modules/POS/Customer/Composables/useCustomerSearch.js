import axios from 'axios'

import {
    ref,
    onMounted,
    onBeforeUnmount,
} from 'vue'

import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'
import { useEventBus } from '@/Composables/useEventBus'


export function useCustomerSearch(
    emit
) {

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const keyword = ref('')

    const customers = ref([])

    let timeout = null

    const {
        onEvent,
        offEvent,
    } = useEventBus()

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    const search = () => {

        clearTimeout(timeout)

        timeout = setTimeout(
            async () => {

                if (
                    !keyword.value
                ) {

                    customers.value = []

                    return
                }

                try {

                    const response =
                        await axios.get(
                            '/api/customers/search',
                            {
                                params: {
                                    search: keyword.value,
                                },
                            }
                        )

                    customers.value = response.data

                    reset()

                } catch (error) {

                    console.error(error)
                }
            },
            300
        )
    }

    /*
    |--------------------------------------------------------------------------
    | SELECT CUSTOMER
    |--------------------------------------------------------------------------
    */

    const selectCustomer = (
        customer
    ) => {

        keyword.value =
            customer.full_name

        customers.value = []

        emit(
            'selected',
            customer
        )
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CUSTOMER
    |--------------------------------------------------------------------------
    */

    const clearCustomer = () => {

        keyword.value = ''

        emit(
            'selected',
            null
        )

        reset()
    }


    // Reset ô Khách hàng
    function resetCustomerSearch() {

        keyword.value = ''

        customers.value = []

        activeIndex.value = -1

        reset()
    }

    /*
    |--------------------------------------------------------------------------
    | KEYBOARD
    |--------------------------------------------------------------------------
    */

    const {
        activeIndex,
        itemRefs,
        setItemRef,
        onKeyDown,
        setActive,
        reset,
    } = useAutocompleteKeyboard(

        customers,

        (customer) => {

            selectCustomer(customer)
        }
    )


    onMounted(() => {

        onEvent(
            'pos:reset',
            resetCustomerSearch
        )
    })


    onBeforeUnmount(() => {

        offEvent(
            'pos:reset',
            resetCustomerSearch
        )
    })

    return {

        keyword,

        customers,

        search,

        selectCustomer,

        clearCustomer,

        activeIndex,

        itemRefs,

        setItemRef,

        onKeyDown,

        setActive,
        
    }
}