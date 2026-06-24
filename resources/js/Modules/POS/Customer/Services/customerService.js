import api from '@/Services/api'

export const customerService = {

    // tìm kiếm khách hàng
    async search(keyword) {

        const response = await api.get(
            '/api/customers/search',
            {
                params: {
                    q: keyword,
                },
            }
        )
        return response.data
    },

    // lấy danh sách nợ của khách hàng
    async getDebts(customerId) {

        const response =
            await api.get(
                `/customers/${customerId}/debts`
            )

        return response.data
    },

    // tạo khách hàng
    async create(data) {

        const response = await api.post(
            '/api/customers',
            data
        )

        return response.data.data
    },
}