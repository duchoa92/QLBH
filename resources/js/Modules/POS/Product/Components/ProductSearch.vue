<script setup>
import { computed } from 'vue'
import { useProductSearch } from '@/Modules/POS/Product/Composables/useProductSearch'
import { toast } from 'vue-sonner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { RotateCw, Search, PackageX, Loader2 } from 'lucide-vue-next'



// Nhận giỏ hàng từ POS truyền vào
const props = defineProps({
    cart: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'selected',
    'product-scanned',
])

const {
    keyword,
    categoryId,
    products,
    categories,
    loading,
    loadProducts,
} = useProductSearch()

const selectProduct = (product) => {
    // Kiểm tra tồn kho khả dụng trước khi chọn
    const currentStock = getDisplayStock(product)
    if (currentStock <= 0) {
        toast.error('Sản phẩm/Biến thể này đã hết hàng hoặc đã được chọn hết vào giỏ!')
        return
    }
    emit('selected', product)
}

const scanImei = async () => {
    const value = keyword.value.trim()
    if (!value) return

    try {
        const result = await productService.scan(value)
        
        // Kiểm tra xem mã IMEI / Serial này đã có trong giỏ hàng chưa
        const isImeiInCart = props.cart.some(item => 
            (item.imei && item.imei === result.data.imei) || 
            (item.serial && item.serial === result.data.serial) ||
            (item.imei_id && item.imei_id === result.data.imei_id)
        )

        if (isImeiInCart) {
            toast.error(`Mã IMEI/Serial (${value}) đã có trong giỏ hàng!`)
            keyword.value = ''
            return
        }

        const product = {
            id: result.data.id,
            product_id: result.data.product_id || result.data.id,
            name: result.data.name,
            sell_price: result.data.sell_price ?? result.data.price,
            image_url: result.data.image_url,
            product_type: 'imei',
            variant_id: result.data.variant_id || result.data.variant?.id,
            variant: result.data.variant ?? null,
            imei_id: result.data.imei_id,
            imei: result.data.imei,
            serial: result.data.serial ?? null,
            color: result.data.color ?? null,
            storage: result.data.storage ?? null,
        }

        emit('selected', product)
        keyword.value = ''
    } catch (error) {
        console.error(error)
        const message = error.response?.data?.message ?? 'Không tìm thấy sản phẩm hoặc IMEI không hợp lệ'
        toast.error(message)
        keyword.value = ''
    }
}

const formatPrice = (value) => {
    return Number(value || 0).toLocaleString('vi-VN')
}

const priceLabel = (product) => {
    if (product.price_label) {
        return product.price_label
    }

    if (
        Number(product.price_min || 0) > 0
        && Number(product.price_max || 0) > 0
        && Number(product.price_min) !== Number(product.price_max)
    ) {
        return `${formatPrice(product.price_min)} - ${formatPrice(product.price_max)}`
    }

    return formatPrice(product.price)
}

/**
 * TÍNH TỒN KHO KHẢ DỤNG CHUẨN 100% CHO MỌI LOẠI SẢN PHẨM
 */
const getDisplayStock = (product) => {
    const productId = Number(product.product_id ?? product.id)
    const variantId = product.variant_id ? Number(product.variant_id) : null

    // Danh sách IMEI nếu backend có trả về mảng chi tiết
    const imeiList = product.imeis || product.serials || product.product_imeis || []
    
    // Kiểm tra xem sản phẩm này có phải loại quản lý theo IMEI/Serial không
    const isImeiProduct = product.product_type === 'imei' || 
                          product.manage_stock_by_serial || 
                          product.has_imei || 
                          imeiList.length > 0

    // =========================================================================
    // TRƯỜNG HỢP 1: SẢN PHẨM QUẢN LÝ THEO IMEI (DÙ CÓ BIẾN THỂ HAY KHÔNG)
    // =========================================================================
    if (isImeiProduct) {
        let rawStock = 0

        // 1a. Nếu Backend có trả về mảng IMEI chi tiết -> Lọc theo status & giỏ hàng
        if (Array.isArray(imeiList) && imeiList.length > 0) {
            const availableImeis = imeiList.filter(item => {
                const isAvailable = item.status === 'available' || 
                                    item.status === 1 || 
                                    item.status === 'in_stock' || 
                                    item.is_sold === 0 || 
                                    item.is_sold === false || 
                                    !item.status

                const isInCart = props.cart.some(cartItem => 
                    (cartItem.imei && cartItem.imei === item.imei) ||
                    (cartItem.imei_id && Number(cartItem.imei_id) === Number(item.id))
                )

                return isAvailable && !isInCart
            })

            return availableImeis.length
        }

        // 1b. Nếu Backend KHÔNG trả về mảng IMEI (chỉ trả product.stock do Server đếm sẵn)
        rawStock = Number(product.stock ?? product.stock_quantity ?? 0)

        // Trừ đi số lượng món IMEI của sản phẩm này đã cho vào giỏ hàng
        const inCartQty = props.cart.reduce((sum, item) => {
            const itemProductId = Number(item.product_id ?? item.id)
            if (itemProductId === productId) {
                return sum + Number(item.quantity ?? 1)
            }
            return sum
        }, 0)

        return Math.max(0, rawStock - inCartQty)
    }

    // =========================================================================
    // TRƯỜNG HỢP 2: ĐANG CHỌN MỘT BIẾN THỂ CỤ THỂ
    // =========================================================================
    if (variantId) {
        const variant = product.variants?.find(v => Number(v.id) === variantId)
        const rawStock = Number(variant?.stock_quantity ?? variant?.stock ?? variant?.quantity ?? 0)
        
        const inCartQty = props.cart.reduce((sum, item) => {
            const itemProductId = Number(item.product_id ?? item.id)
            const itemVariantId = item.variant_id ? Number(item.variant_id) : null
            if (itemProductId === productId && itemVariantId === variantId) {
                return sum + Number(item.quantity ?? 1)
            }
            return sum
        }, 0)

        return Math.max(0, rawStock - inCartQty)
    }

    // =========================================================================
    // TRƯỜNG HỢP 3: SẢN PHẨM CHỈ CÓ BIẾN THỂ (KHÔNG PHẢI IMEI)
    // =========================================================================
    if (Array.isArray(product.variants) && product.variants.length > 0) {
        const totalAvailable = product.variants.reduce((total, variant) => {
            const vStock = Number(variant.stock_quantity ?? variant.stock ?? variant.quantity ?? 0)
            
            const inCartQty = props.cart.reduce((sum, cartItem) => {
                const itemProductId = Number(cartItem.product_id ?? cartItem.id)
                const itemVariantId = cartItem.variant_id ? Number(cartItem.variant_id) : null
                
                if (itemProductId === productId && itemVariantId === Number(variant.id)) {
                    return sum + Number(cartItem.quantity ?? 1)
                }
                return sum
            }, 0)

            return total + Math.max(0, vStock - inCartQty)
        }, 0)

        return totalAvailable
    }

    // =========================================================================
    // TRƯỜNG HỢP 4: SẢN PHẨM THƯỜNG (KHÔNG BIẾN THỂ, KHÔNG IMEI)
    // =========================================================================
    const rawStock = Number(product.stock ?? product.stock_quantity ?? 0)
    const inCartQty = props.cart.reduce((sum, item) => {
        const itemProductId = Number(item.product_id ?? item.id)
        if (itemProductId === productId) {
            return sum + Number(item.quantity ?? 1)
        }
        return sum
    }, 0)

    return Math.max(0, rawStock - inCartQty)
}

const refreshProducts = () => {
    loadProducts()
}

const categoryOptions = computed(() => [
    {
        value: '',
        label: 'Tất cả danh mục',
    },
    ...categories.value.map(category => ({
        value: category.id,
        label: category.name,
    })),
])
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col font-sans antialiased">
        
        <!-- THANH LỌC & TÌM KIẾM SẢN PHẨM -->
        <div class="border-b border-slate-200/80 bg-white p-3.5 rounded-xl shadow-sm mb-3">
            <div class="grid gap-3 md:grid-cols-[1fr_260px] lg:grid-cols-[1fr_320px] items-center">
                
                <!-- Ô TÌM KIẾM -->
                <div class="relative">
                    <FloatingInput
                        v-model="keyword"
                        @keyup.enter="scanImei"
                        type="text"
                        label="Nhập tên sản phẩm, SKU, barcode hoặc IMEI (Ấn Enter để quét)"
                        class="w-full"
                    />
                </div>

                <!-- LỌC DANH MỤC & NÚT REFRESH -->
                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <FloatingSelect
                            v-model="categoryId"
                            label="Danh mục"
                            :options="categoryOptions"
                        />
                    </div>

                    <button
                        type="button"
                        @click="refreshProducts"
                        title="Tải lại danh sách"
                        class="h-10 w-10 shrink-0 inline-flex items-center justify-center rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900 active:scale-95 transition-all"
                    >
                        <RotateCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
                    </button>
                </div>

            </div>
        </div>

        <!-- DANH SÁCH SẢN PHẨM -->
        <div class="min-h-0 flex-1 overflow-auto pr-1 custom-scrollbar">
            
            <!-- LOADING -->
            <div
                v-if="loading"
                class="flex h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white/50 text-slate-400 gap-3"
            >
                <Loader2 class="w-8 h-8 animate-spin text-indigo-500" />
                <span class="text-xs font-medium">Đang tải dữ liệu sản phẩm...</span>
            </div>

            <!-- CARD SẢN PHẨM -->
            <div
                v-else-if="products.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
            >
                <button
                    v-for="product in products"
                    :key="product.id"
                    type="button"
                    :disabled="getDisplayStock(product) <= 0"
                    class="group relative text-left overflow-hidden rounded-2xl border transition-all duration-200 flex flex-col justify-between"
                    :class="[
                        getDisplayStock(product) <= 0
                            ? 'opacity-60 border-slate-200 bg-slate-100/50 cursor-not-allowed'
                            : 'border-slate-200/80 bg-white shadow-sm hover:border-indigo-400 hover:shadow-md active:scale-[0.98]'
                    ]"
                    @click="selectProduct(product)"
                >
                    <!-- KHU VỰC ẢNH -->
                    <div class="relative h-36 w-full bg-slate-50 overflow-hidden shrink-0">
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="product.name"
                            class="h-full w-full object-cover transition-transform duration-300"
                            :class="{ 'group-hover:scale-105': getDisplayStock(product) > 0 }"
                        />

                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-slate-300 bg-slate-100/60"
                        >
                            <Search class="w-8 h-8 stroke-1" />
                        </div>

                        <!-- BADGE HẾT HÀNG NẾU TỒN KHO = 0 -->
                        <div
                            v-if="getDisplayStock(product) <= 0"
                            class="absolute inset-0 bg-slate-900/40 backdrop-blur-[1px] flex items-center justify-center"
                        >
                            <span class="bg-rose-600 text-white font-extrabold text-[11px] uppercase tracking-wider px-2.5 py-1 rounded-lg shadow">
                                Hết hàng
                            </span>
                        </div>

                        <!-- BADGE GIÁ -->
                        <div
                            v-else
                            class="absolute bottom-2 left-2 rounded-lg bg-rose-600/90 backdrop-blur-sm px-2.5 py-1 text-xs font-bold text-white shadow-sm"
                        >
                            {{ priceLabel(product) }}<span v-if="!product.price_label">đ</span>
                        </div>

                        <!-- BADGE LOẠI SẢN PHẨM -->
                        <div
                            v-if="product.product_type === 'imei' || product.manage_stock_by_serial"
                            class="absolute top-2 right-2 rounded-md bg-indigo-600 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white shadow-sm"
                        >
                            IMEI
                        </div>

                        <div
                            v-else-if="product.variants && product.variants.length"
                            class="absolute top-2 right-2 rounded-md bg-amber-500 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white shadow-sm"
                        >
                            {{ product.variants.length }} bản
                        </div>
                    </div>

                    <!-- THÔNG TIN TÊN & TỒN KHO -->
                    <div class="p-3 flex flex-col justify-between flex-1 bg-white">
                        <div
                            class="line-clamp-2 min-h-[36px] text-xs font-semibold leading-snug transition-colors"
                            :class="getDisplayStock(product) > 0 ? 'text-slate-800 group-hover:text-indigo-600' : 'text-slate-400'"
                            :title="product.name"
                        >
                            {{ product.name }}
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] font-medium">
                            <span
                                class="font-bold px-1.5 py-0.5 rounded"
                                :class="getDisplayStock(product) > 0 ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'"
                            >
                                Tồn: {{ getDisplayStock(product) }}
                            </span>

                            <span class="text-slate-400">
                                Đã bán: {{ product.sold_count || 0 }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>

            <!-- KHÔNG CÓ KẾT QUẢ -->
            <div
                v-else
                class="flex h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white/50 text-slate-400 gap-2"
            >
                <PackageX class="w-10 h-10 stroke-1 text-slate-300" />
                <span class="text-sm font-semibold text-slate-600">Không tìm thấy sản phẩm nào</span>
                <span class="text-xs text-slate-400">Vui lòng thử từ khóa khác hoặc thay đổi danh mục</span>
            </div>

        </div>

    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>