import api from '@/Services/api'

export const categoryService = {

    async getAll() {

        const response =
            await api.get(
                '/api/categories'
            )

        return response.data
    },
}