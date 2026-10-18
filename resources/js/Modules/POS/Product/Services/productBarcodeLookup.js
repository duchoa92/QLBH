import api from '@/Services/api'

const normalizeCode = (value) => String(value ?? '').trim().toLocaleLowerCase()

/** Find one product or variant from a scanner's exact code. */
export const findProductByScannedCode = async (rawCode) => {
    const code = String(rawCode ?? '').trim()
    if (!code) return null

    const { data } = await api.get('/api/products', { params: { keyword: code } })
    const products = Array.isArray(data) ? data : []
    const normalizedCode = normalizeCode(code)

    const variantMatches = products.flatMap(product => (product.variants || [])
        .filter(variant => [variant.barcode, variant.sku].some(value => normalizeCode(value) === normalizedCode))
        .map(variant => ({ product, variant })))

    if (variantMatches.length === 1) return variantMatches[0]
    if (variantMatches.length > 1) return null

    const productMatches = products.filter(product =>
        [product.barcode, product.sku].some(value => normalizeCode(value) === normalizedCode)
    )

    return productMatches.length === 1
        ? { product: productMatches[0], variant: null }
        : null
}
