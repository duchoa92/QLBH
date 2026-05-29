import axios from 'axios'

export const holdSaleService = {

    async getAll() {

        return axios.get(
            '/api/hold-sales'
        )
    },

    async getById(id) {

        return axios.get(
            `/api/hold-sales/${id}`
        )
    },

    async create(payload) {

        return axios.post(
            '/api/hold-sales',
            payload
        )
    },

    async remove(id) {

        return axios.delete(
            `/api/hold-sales/${id}`
        )
    },
}