import api from '@/Services/api'

export const customerService = {

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
}