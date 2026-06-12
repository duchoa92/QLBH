import api from '@/Services/api'


export const saleService = {


    async getAll()
    {

        const response =
            await api.get(
                '/api/sales'
            )


        return response.data

    },


    async show(id)
    {

        const response =
            await api.get(
                `/api/sales/${id}`
            )


        return response.data

    }

}