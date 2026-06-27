<script setup>
import { ref, watch, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import SortHeader from '@/Components/UI/SortHeader.vue'
import { Trash, Eye, Pencil } from 'lucide-vue-next'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'


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
watch(() => props.products.data, (newData) => {
    const validIds = newData.map(p => p.id)
    selectedIds.value = selectedIds.value.filter(id => validIds.includes(id))
})


// Check trạng thái sort


const handleSort = (data) => {
    filters.value.sort_by = data.sort_by
    filters.value.sort_order = data.sort_order
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
const money = (value) => Number(value || 0).toLocaleString('vi-VN')

const destroy = (id) => {
    if (!confirm('Chuyển sản phẩm vào thùng rác?')) return

    router.delete(route('products.destroy', id), {
        preserveScroll: true,
        only: ['products', 'flash'],
        onSuccess: () => {
            // ❗ XÓA ID khỏi selectedIds
            selectedIds.value = selectedIds.value.filter(i => i !== id)
        }
    })
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
| SELECT
|--------------------------------------------------------------------------
*/
const selectedIds = ref([])

// tất cả id của page hiện tại
const pageIds = computed(() => props.products.data.map(p => p.id))

// đã chọn hết chưa
const isAllSelected = computed(() =>
    pageIds.value.length > 0 &&
    pageIds.value.every(id => selectedIds.value.includes(id))
)

// chọn 1 phần
const allSelected = computed(() => {
    return props.products.data.length &&
        props.products.data.every(p => selectedIds.value.includes(p.id))
})

const isIndeterminate = computed(() => {
    return selectedIds.value.length > 0 && !allSelected.value
})

// chọn tất cả
const toggleAll = () => {
    if (allSelected.value) {
        // bỏ chọn tất cả trong page
        selectedIds.value = selectedIds.value.filter(
            id => !props.products.data.some(p => p.id === id)
        )
    } else {
        // thêm tất cả trong page
        const ids = props.products.data.map(p => p.id)
        selectedIds.value = [...new Set([...selectedIds.value, ...ids])]
    }
}

// chọn từng dòng
const toggleOne = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(i => i !== id)
    } else {
        selectedIds.value = [...selectedIds.value, id]
    }
}

// Xóa
const bulkDelete = () => {
    if (!selectedIds.value.length) return

    if (!confirm(`Chuyển ${selectedIds.value.length} sản phẩm vào thùng rác?`)) return

    router.post(route('products.bulkDelete'), {
        ids: selectedIds.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = []
        }
    })
}

// In Tem
const printImei = () => {
    if (!selectedIds.value.length) return

    router.post(route('products.printImei'), {
        ids: selectedIds.value
    })
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


        <!-- FILTER -->
        <div class="flex gap-2 mt-3">
            <FloatingSelect
                v-model="filters.category_id"
                label="Danh mục"
                :options="categories"
                option-label="name"
                option-value="id"
            />
            <FloatingSelect
                v-model="filters.brand_id"
                label="Thương hiệu"
                :options="brand"
                option-label="name"
                option-value="id"
            />
            <FloatingSelect
                v-model="filters.stock"
                label="Tồn kho"
                :options="categories"
                option-label="name"
                option-value="id"
            >
                <option value="in_stock">Còn hàng</option>
                <option value="out_stock">Hết hàng</option>
                </FloatingSelect>

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
        <div class="py-4">
            <div v-if="loading" class="text-xs text-blue-500">Đang tìm...</div>
        </div>
    </div>

    <!-- BULK ACTION -->
   <div v-if="selectedIds.length" class="bg-blue-50 border p-2 flex justify-between items-center">
        <div class="text-sm">
            Đã chọn <b>{{ selectedIds.length }}</b> sản phẩm
        </div>

        <div class="flex gap-2">
            <button @click="bulkDelete" class="px-3 py-1 bg-red-500 text-white rounded">
                Chuyển vào thùng rác
            </button>

            <button
                @click="printImei"
                class="px-3 py-1 bg-purple-600 text-white rounded"
            >
                In tem IMEI
            </button>
        </div>
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded shadow overflow-hidden">
        <table class="w-full text-sm">

            <thead class="bg-gray-100 text-sm">
            <tr>
                <th class="p-2">
                    <input
                        type="checkbox"
                        :checked="allSelected"
                        :indeterminate="isIndeterminate"
                        @change="toggleAll"
                    />
                </th>

                <SortHeader
                    label="ID"
                    field="id"
                    :currentSort="filters.sort_by"
                    :currentOrder="filters.sort_order"
                    @sort="handleSort"
                />

                <SortHeader
                    label="Tên hàng"
                    field="name"
                    :currentSort="filters.sort_by"
                    :currentOrder="filters.sort_order"
                    @sort="handleSort"
                />

                <SortHeader
                    label="Giá"
                    field="sell_price"
                    :currentSort="filters.sort_by"
                    :currentOrder="filters.sort_order"
                    @sort="handleSort"
                />

                <SortHeader
                    label="Tồn kho"
                    field="stock"
                    :currentSort="filters.sort_by"
                    :currentOrder="filters.sort_order"
                    @sort="handleSort" />
                <th></th>
            </tr>

            </thead>

            <tbody>
                <tr v-for="p in products.data" @click="toggleOne(p.id)" :key="p.id"  :class="['border-t hover:bg-gray-50', selectedIds.includes(p.id) ? 'bg-blue-50' : '']">
                   <td class="p-2">
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(p.id)"
                            @click.stop
                            @change="toggleOne(p.id)"
                        >
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