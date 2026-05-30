import api from '@/Services/api'

export const productService = {

    // Tìm kiếm sản phẩm theo từ khóa và danh mục
    async search(
        keyword = '',
        categoryId = ''
    ) {

        const response =
            await api.get(
                '/api/products',
                {
                    params: {

                        keyword,

                        category_id:
                            categoryId,
                    },
                }
            )

        return response.data
    },

    // Tìm kiếm sản phẩm theo mã vạch
    async findByBarcode(
        barcode
    ) {

        const response =
            await api.get(
                '/api/products/barcode',
                {
                    params: {
                        barcode,
                    },
                }
            )

        return response.data
    }
}