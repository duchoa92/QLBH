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
| STATE
|--------------------------------------------------------------------------
*/
const filters = ref({
    search: props.filters?.search || '',
    category_id: props.filters?.category_id || '',
    brand_id: props.filters?.brand_id || '',
    stock: props.filters?.stock || '',
    status: props.filters?.status || '',
    sort_by: props.filters?.sort_by || 'id',
    sort_order: props.filters?.sort_order || 'desc'
})

const selected = ref([]) // checkbox hàng loạt
const loading = ref(false)

/*
|--------------------------------------------------------------------------
| SEARCH REALTIME
|--------------------------------------------------------------------------
*/
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

watch(filters, searchServer, { deep: true })

/*
|--------------------------------------------------------------------------
| SORT
|--------------------------------------------------------------------------
*/
const sort = (field) => {
    if (filters.value.sort_by === field) {
        filters.value.sort_order =
            filters.value.sort_order === 'asc' ? 'desc' : 'asc'
    } else {
        filters.value.sort_by = field
        filters.value.sort_order = 'asc'
    }
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
const money = (value) => Number(value || 0).toLocaleString('vi-VN')

const destroy = (id) => {
    if (!confirm('Xóa sản phẩm?')) return
    router.delete(route('products.destroy', id))
}

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

const matchedImeis = (product) => {
    const kw = filters.value.search?.trim().toLowerCase()
    if (!kw) return []

    return (product.imeis || []).filter(i =>
        i.imei?.toLowerCase().includes(kw)
    )
}

/*
|--------------------------------------------------------------------------
| SELECT ALL
|--------------------------------------------------------------------------
*/
const toggleAll = (e) => {
    if (e.target.checked) {
        selected.value = props.products.data.map(p => p.id)
    } else {
        selected.value = []
    }
}


</script>

<template>
<div class="space-y-4">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Hàng hóa</h1>
            <p class="text-sm text-gray-500">Quản lý hàng hóa của bạn</p>
        </div>

        <div class="flex gap-2">
            <Link :href="route('products.create')" class="btn-green">+ Thêm</Link>
            <button class="btn-green">Nhập Excel</button>
            <button class="btn-green">Xuất Excel</button>
            <button class="btn-green">In tem</button>
            <Link :href="route('products.trash')" class="btn-green">Thùng rác</Link>
        </div>
    </div>

    <!-- SEARCH -->
    <div class="bg-white p-4 rounded shadow">
        <FloatingInput v-model="filters.search" label="Tìm sản phẩm, SKU, IMEI..." />

        <div v-if="loading" class="mt-2 text-xs text-blue-500">Đang tìm...</div>

        <!-- FILTER -->
        <div class="flex gap-2 mt-3">
            <select v-model="filters.category_id" class="input">
                <option value="">Danh mục</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>

            <select v-model="filters.brand_id" class="input">
                <option value="">Thương hiệu</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>

            <select v-model="filters.stock" class="input">
                <option value="">Tồn kho</option>
                <option value="in_stock">Còn hàng</option>
                <option value="out_stock">Hết hàng</option>
            </select>
        </div>
    </div>

    <!-- BULK ACTION -->
    <div v-if="selected.length" class="bg-yellow-100 p-2 rounded text-sm">
        Đã chọn {{ selected.length }} sản phẩm
        <button class="ml-3 text-red-600">Xóa hàng loạt</button>
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded shadow overflow-hidden">
        <table class="w-full text-sm">

            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2">
                        <input type="checkbox" @change="toggleAll">
                    </th>

                    <th @click="sort('id')" class="cursor-pointer">ID</th>
                    <th @click="sort('name')" class="cursor-pointer text-left">Tên hàng hóa</th>
                    <th @click="sort('sell_price')" class="cursor-pointer">Giá bán</th>
                    <th>Tồn kho</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="p in products.data" :key="p.id" class="border-t hover:bg-gray-50">

                    <td class="p-2">
                        <input type="checkbox" v-model="selected" :value="p.id">
                    </td>

                    <td>{{ p.id }}</td>

                    <td class="p-2">
                        <div v-html="highlight(p.name)"></div>

                        <div class="text-xs text-gray-500">
                            SKU: <span v-html="highlight(p.sku)"></span>
                        </div>

                        <!-- IMEI MATCH ONLY -->
                        <div v-if="filters.search && matchedImeis(p).length">
                            <div
                                v-for="imei in matchedImeis(p)"
                                :key="imei.id"
                                class="text-xs text-gray-500"
                            >
                                IMEI:
                                <span v-html="highlight(imei.imei)"></span>
                            </div>
                        </div>
                    </td>

                    <td>{{ money(p.sell_price) }}</td>

                    <td>
                        <span class="px-2 py-1 text-xs rounded"
                              :class="p.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                            {{ p.stock }}
                        </span>
                    </td>

                    <td>
                        <div class="flex gap-2">
                            <Link :href="route('products.show', p.id)">
                                <Eye size="16"/>
                            </Link>
                            <Link :href="route('products.edit', p.id)">
                                <Pencil size="16"/>
                            </Link>
                            <button @click="destroy(p.id)">
                                <Trash size="16"/>
                            </button>
                        </div>
                    </td>

                </tr>

                <tr v-if="!products.data.length">
                    <td colspan="6" class="text-center p-4 text-gray-500">
                        Không có dữ liệu
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
</template>

<style scoped>
.btn-green {
    @apply px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700;
}

.input {
    @apply border px-2 py-1 rounded text-sm;
}
</style>