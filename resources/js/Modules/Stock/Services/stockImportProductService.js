import api from '@/Services/api'

export const stockImportProductService = {

    async search(keyword = '') {

        const response = await api.get(
            '/products/list-import',
            {
                params: {
                    keyword,
                },
            }
        )

        return response.data
    },

}