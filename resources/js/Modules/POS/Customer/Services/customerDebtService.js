import axios from 'axios'

export const customerDebtService = {

    async getDebts(
        customerId
    ) {

        return axios.get(
            `/api/customers/${customerId}/debts`
        )
    },
}