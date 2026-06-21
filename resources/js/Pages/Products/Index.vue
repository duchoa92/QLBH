<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { Trash, ScanSearch, Eye, Pencil } from 'lucide-vue-next'

const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    brands: Array
})

/*
|--------------------------------------------------------------------------
| FILTER STATE (CHỈ DÙNG 1 OBJECT)
|--------------------------------------------------------------------------
*/
const filters = ref({
    search: props.filters?.search || '',
    category_id: props.filters?.category_id || '',
    brand_id: props.filters?.brand_id || '',
    stock: props.filters?.stock || '',
    status: props.filters?.status || ''
})

/*
|--------------------------------------------------------------------------
| SEARCH SERVER (REALTIME KHÔNG RELOAD)
|--------------------------------------------------------------------------
*/
const loading = ref(false)

const searchServer = debounce(() => {
    router.get(route('products.index'), filters.value, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        only: ['products', 'filters'],
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false
    })
}, 300)

// 👇 WATCH TOÀN BỘ FILTER
watch(filters, () => {
    searchServer()
}, { deep: true })

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
const money = (value) => {
    return Number(value || 0).toLocaleString('vi-VN')
}

const destroy = (id) => {
    if (!confirm('Xóa sản phẩm này?')) return
    router.delete(route('products.destroy', id))
}

// highlight nhiều từ
const highlight = (text) => {
    if (!text || !filters.value.search) return text || ''

    const words = filters.value.search.trim().split(' ').filter(Boolean)

    let result = text

    words.forEach(word => {
        const escaped = word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        result = result.replace(
            new RegExp(escaped, 'gi'),
            m => `<span class="bg-yellow-200">${m}</span>`
        )
    })

    return result
}

/*
|--------------------------------------------------------------------------
| CHỈ HIỂN THỊ IMEI MATCH (QUAN TRỌNG)
|--------------------------------------------------------------------------
*/
const matchedImeis = (product) => {
    const kw = filters.value.search?.trim().toLowerCase()
    if (!kw) return []

    return (product.imeis || []).filter(i =>
        i.imei?.toLowerCase().includes(kw)
    )
}
</script>

<template>
<div class="space-y-4">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Sản phẩm</h1>

        <div class="flex gap-2">
            <Link :href="route('products.trash')" class="px-3 py-2 bg-red-100 text-red-700 rounded">
                Thùng rác
            </Link>
            <Link :href="route('products.create')" class="px-3 py-2 bg-blue-600 text-white rounded">
                Thêm
            </Link>
        </div>
    </div>

    <!-- SEARCH -->
    <div class="relative">
        <FloatingInput
            v-model="filters.search"
            label="Tìm sản phẩm, SKU, IMEI..."
        />

        <div v-if="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
            <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>

    <!-- FILTER -->
    <div class="flex gap-2">
        <select v-model="filters.category_id">
            <option value="">Danh mục</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">
                {{ c.name }}
            </option>
        </select>

        <select v-model="filters.brand_id">
            <option value="">Thương hiệu</option>
            <option v-for="b in brands" :key="b.id" :value="b.id">
                {{ b.name }}
            </option>
        </select>

        <select v-model="filters.stock">
            <option value="">Tồn kho</option>
            <option value="in_stock">Còn hàng</option>
            <option value="out_stock">Hết hàng</option>
        </select>

        <select v-model="filters.status">
            <option value="">Trạng thái</option>
            <option value="active">Đang bán</option>
            <option value="inactive">Ngưng</option>
        </select>
    </div>

    <!-- RESULT INFO -->
    <div v-if="filters.search" class="text-sm text-gray-500 flex items-center gap-2">
        <ScanSearch />
        <span>Kết quả:</span>
        <b>{{ filters.search }}</b>
        <span>({{ products.total }})</span>
    </div>

    <!-- TABLE -->
    <table class="w-full text-sm border">
        <thead class="bg-gray-100">
            <tr>
                <th class="p-2 text-left">Sản phẩm</th>
                <th>Danh mục</th>
                <th>Brand</th>
                <th>Giá</th>
                <th>Tồn</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <tr v-for="product in products.data" :key="product.id">

                <td class="p-2">
                    <div v-html="highlight(product.name)"></div>

                    <div class="text-xs text-gray-500">
                        SKU: <span v-html="highlight(product.sku)"></span>
                    </div>

                    <!-- ✅ CHỈ HIỆN IMEI MATCH -->
                    <div v-if="matchedImeis(product).length">
                        <div v-for="imei in matchedImeis(product)" :key="imei.id" class="text-xs text-gray-500">
                            IMEI:
                            <span v-html="highlight(imei.imei)"></span>
                        </div>
                    </div>
                </td>

                <td v-html="highlight(product.category?.name)"></td>
                <td v-html="highlight(product.brand?.name)"></td>

                <td>{{ money(product.sell_price) }}</td>

                <td>{{ product.stock }}</td>

                <td>
                    <div class="flex gap-2">
                        <Link :href="route('products.show', product.id)">
                            <Eye size="16"/>
                        </Link>
                        <Link :href="route('products.edit', product.id)">
                            <Pencil size="16"/>
                        </Link>
                        <button @click="destroy(product.id)">
                            <Trash size="16"/>
                        </button>
                    </div>
                </td>

            </tr>
        </tbody>
    </table>

</div>
</template>