import axios from 'axios'

export const paymentService = {

    async checkout(payload) {

        return axios.post(
            '/pos/checkout',
            payload
        )
    },
}