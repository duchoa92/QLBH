import axios from 'axios'

import {
    ref,
} from 'vue'

import { useAutocompleteKeyboard }
    from '@/Composables/useAutocompleteKeyboard'

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

    const selectedCustomer =
        ref(null)

    let timeout = null

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
                                    q: keyword.value,
                                },
                            }
                        )

                    customers.value =
                        response.data

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

        selectedCustomer.value =
            customer

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

        selectedCustomer.value =
            null

        keyword.value = ''

        emit(
            'selected',
            null
        )

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

    return {

        keyword,

        customers,

        selectedCustomer,

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