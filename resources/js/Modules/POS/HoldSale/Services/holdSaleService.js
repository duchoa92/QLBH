import api from '@/Services/api'

export const holdSaleService = {

    async getAll() {
        const response = await api.get(
            '/api/hold-sales'
        )
        return response.data    
    },

    async getById(id) {
        const response = await api.get(
            `/api/hold-sales/${id}`
        )
        return response.data
    },

    async create(payload) {

        const response = await api.post(
            '/api/hold-sales',
            payload
        )
        return response.data
    },

    async remove(id) {

        const response = await api.delete(
            `/api/hold-sales/${id}`
        )
        return response.data
    },
}