<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { openModal } from '@/Stores/modal'
import Form from './Form.vue'
import ProductFilter from './Components/ProductFilter.vue'
import ProductTable from './Components/ProductTable.vue'
import ProductBulkBar from './Components/ProductBulkBar.vue'
import { Plus } from 'lucide-vue-next'


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
            })
        }, 400)
    },
    { deep: true }
)


const loadData=()=>{
    router.reload({
        only:['products']
    })
}




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
// mở modal sửa sản phẩm
const openEdit = (product) => {

    openModal(Form, {

        title: 'Sửa sản phẩm',

        props: {

            product,

            categories: props.categories,

            brands: props.brands

        },

        onUpdated: loadData

    })

}
// mở modal tạo sản phẩm
const openCreate = () => {

    openModal(Form, {

        title: 'Thêm sản phẩm',

        props: {

            categories: props.categories,

            brands: props.brands

        },

        onUpdated: loadData

    })

}

const openTrash = () => {

    openModal(TrashModal, {

        title: 'Thùng rác',

        props: {
            endpoint: 'products'
        },

        onUpdated: loadData

    })

}

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
            <button @click="openCreate" class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700">
                <Plus /> Thêm
            </button>
            <button
                @click="openTrash"
                class="btn-gray"
            >
                Thùng rác
            </button>
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
        @open="openEdit"
    />

</div>

</template>
