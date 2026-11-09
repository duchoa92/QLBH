import api from '@/Services/api'

export const customerService = {

    // tạo khách hàng
    async create(data) {

        const response = await api.post(
            '/api/customers',
            data
        )

        return response.data.data
    },
}