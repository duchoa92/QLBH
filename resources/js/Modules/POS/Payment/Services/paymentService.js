import api from '@/Services/api'

export const paymentService = {

    async checkout(payload) {

        const response = await api.post(
            '/pos/checkout',
            payload
        )
        return response.data
    },
}