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
    },

    // Quét barcode hoặc IMEI
    async scan(code) {

        const response =
            await api.post(
                '/api/pos/scan',
                {
                    code,
                }
            )

        return response.data
    },

    // Lấy danh sách IMEI còn trong kho của 1 sản phẩm (lọc theo biến thể nếu có)
    async imeis(productId, variantId = null) {

        const response =
            await api.get(
                `/api/products/${productId}/imeis`,
                {
                    params: variantId
                        ? { variant_id: variantId }
                        : {},
                }
            )

        return response.data
    },
}