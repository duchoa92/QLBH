<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'

import ProductFilter from './Components/ProductFilter.vue'
import ProductTable from './Components/ProductTable.vue'
import ProductBulkBar from './Components/ProductBulkBar.vue'
import ProductModal from './Components/ProductModal.vue'
import ProductCreateModal from './Components/ProductCreateModal.vue'

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
| SEARCH (ANTI SPAM - CHUẨN)
|--------------------------------------------------------------------------
*/
let timeout = null

watch(
    filters,
    () => {
        clearTimeout(timeout)

        timeout = setTimeout(() => {
            router.get(route('products.index'), filters.value, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['products', 'filters', 'brands', 'categories'],
                onStart: () => loading.value = true,
                onFinish: () => loading.value = false
            })
        }, 400)
    },
    { deep: true }
)

/*
|--------------------------------------------------------------------------
| SELECT
|--------------------------------------------------------------------------
*/
const selectedIds = ref([])

const toggleOne = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(i => i !== id)
    } else {
        selectedIds.value.push(id)
    }
}

const toggleAll = () => {
    const ids = props.products.data.map(p => p.id)

    if (selectedIds.value.length === ids.length) {
        selectedIds.value = []
    } else {
        selectedIds.value = ids
    }
}

/*
|--------------------------------------------------------------------------
| BULK ACTION
|--------------------------------------------------------------------------
*/
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

const printImei = () => {
    if (!selectedIds.value.length) return

    router.post(route('products.printImei'), {
        ids: selectedIds.value
    })
}

/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/
const showModal = ref(false)
const selectedProduct = ref(null)
const saving = ref(false)

const openModal = (product) => {
    selectedProduct.value = JSON.parse(JSON.stringify(product))
    showModal.value = true
}

const updateProduct = () => {
    saving.value = true

    router.put(
        route('products.update', selectedProduct.value.id),
        selectedProduct.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
                router.reload({ only: ['products'] })
            },
            onFinish: () => saving.value = false
        }
    )
}

const showCreate = ref(false)

</script>

<template>
<div class="space-y-4">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Hàng hóa</h1>
            <p class="text-sm text-gray-500">Quản lý hàng hóa</p>
        </div>

        <div class="flex gap-2">
            <button @click="showCreate" class="btn-green">
                + Thêm
            </button>
            <Link :href="route('products.trash')" class="btn-green">Thùng rác</Link>
        </div>
    </div>

    <!-- FILTER -->
    <ProductFilter
        :filters="filters"
        :categories="categories"
        :brands="brands"
        @update:filters="v => {
            if (!v || typeof v !== 'object') return
            Object.assign(filters, v)
        }"
    />

    <!-- BULK -->
    <ProductBulkBar
        :selectedIds="selectedIds"
        @delete="bulkDelete"
        @print="printImei"
    />

    <!-- TABLE -->
    <ProductTable
        :products="products"
        :selectedIds="selectedIds"
        :filters="filters"
        @toggleOne="toggleOne"
        @toggleAll="toggleAll"
        @open="openModal"
    />

</div>

<!-- MODAL -->
<ProductModal
    :show="showModal"
    :product="selectedProduct"
    @close="showModal = false"
    @save="updateProduct"
/>

<ProductCreateModal
    :show="showCreate"
    :categories="categories"
    :brands="brands"
    @close="() => {
        showCreate = false
        router.reload({ only: ['products'] })
    }"
/>

</template>

<style scoped>
.btn-green {
    @apply px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700;
}
</style>