import axios from 'axios'

export const saleService = {

    async getAll()
    {
        const response =
            await axios.get('/api/sales')

        return response.data
    },

    async show(id)
    {
        const response =
            await axios.get(`/api/sales/${id}`)

        return response.data
    },

}