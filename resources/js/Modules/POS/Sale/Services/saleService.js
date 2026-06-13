import axios from 'axios'

export const saleService = {

    async getSales(params = {})
    {
        const response =
            await axios.get(
                '/api/sales',
                {
                    params,
                }
            )

        return response
    },

    async getSale(id)
    {
        const response =
            await axios.get(`/api/sales/${id}`)
 
        return response
    },

}