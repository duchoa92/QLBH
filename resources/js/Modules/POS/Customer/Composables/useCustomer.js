import { ref } from 'vue'

export function useCustomer() {

    const selectedCustomer =
        ref(null)

    const setCustomer = (
        customer
    ) => {

        selectedCustomer.value =
            customer
    }

    const clearCustomer = () => {

        selectedCustomer.value =
            null
    }

    return {

        selectedCustomer,

        setCustomer,

        clearCustomer,
    }
}