<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import BrandForm from './Form.vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import TrashModal from '@/Components/TrashModal.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal } from '@/Stores/modal'
import BaseTable from '@/Components/UI/BaseTable.vue'


defineOptions({ layout: AdminLayout })

const props = defineProps({
    brands: Object,
    filters: Object,
    categories: Array
})

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },

    { key: 'name', label: 'Tên', sortable: true },

    { 
        key: 'category_name', 
        label: 'Danh mục', 
        sortable: true,
        width: '150px'
    },

    { 
        key: 'is_active', 
        label: 'Trạng thái', 
        sortable: true,
        width: '120px',
        class: 'text-center'
    }
]

const selectedIds = ref([])

const toggleOne = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(i => i !== id)
    } else {
        selectedIds.value.push(id)
    }
}

const toggleAll = () => {
    if (selectedIds.value.length === props.brands.data.length) {
        selectedIds.value = []
    } else {
        selectedIds.value = props.brands.data.map(i => i.id)
    }
}

const confirmBox = useConfirm()

/* FILTER */
const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id ?? '') // Đồng bộ với value trống '' của option Tất cả

let timeout = null
onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout)
})

// HÀM TRIGGER CHUNG ĐỂ GET DỮ LIỆU
const handleFilter = () => {
    router.get('/brands', {
        search: search.value,
        category_id: category_id.value,
        sort_by: props.filters?.sort_by,
        sort_order: props.filters?.sort_order
    })
}

// Chỉ debounce với ô tìm kiếm văn bản (Search)
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(handleFilter, 300)
})

// Lập tức trigger filter khi người dùng thay đổi select Category
watch(category_id, handleFilter)

/* RELOAD */
const loadData = () => {
    router.reload({ only: ['brands'] })
    loadTrashCount()
}

/* DELETE */
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển thương hiệu vào thùng rác?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(`/brands/${id}`, {
                preserveScroll: true,
                onSuccess: loadData
            })
        }
    })
}

const openCreate = () => {
    openModal(BrandForm, {
        props: {
            categories: props.categories,
            title: 'Thêm thương hiệu',
        },
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(BrandForm, {
        props: {
            brand: item,
            categories: props.categories,
            title: 'Sửa thương hiệu',
        },
        onUpdated: loadData
    })
}

/* TRASH COUNT */
const trashCount = ref(0)

const loadTrashCount = async () => {
    try {
        const res = await fetch('/brands/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        const data = await res.json()
        trashCount.value = data.meta?.total || 0
    } catch {
        trashCount.value = 0
    }
}

onMounted(loadTrashCount)

const openTrash = () => {
    openModal(TrashModal, {
        props: { endpoint: 'brands' },
        onUpdated: loadData
    })
}

/* STATUS */
const loadingStatus = ref(null)
const toggleStatus = (id) => {
    if (loadingStatus.value) return
    loadingStatus.value = id
    router.patch(`/brands/${id}/toggle-status`, {}, {
        preserveScroll: true,
        onFinish: () => loadingStatus.value = null
    })
}


// Sort

const sort = ({ field, order }) => {
    router.get('/brands', {
        search: search.value,
        category_id: category_id.value,
        sort_by: field,
        sort_order: order
    }, {
        preserveState: true,
        replace: true
    })
}

</script>

<template>
<div>
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Thương hiệu</h1>
            <p class="text-gray-500">Quản lý các thương hiệu sản phẩm</p>
        </div>
        <div class="flex gap-2">
            <button @click="openCreate" 
                class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                <Plus class="w-4 h-4" /> Thêm mới
            </button>
            <button @click="openTrash"
                class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500 hover:text-white transition">
                <Trash2 class="w-4 h-4" />({{ trashCount }})
            </button>
        </div>
    </div>

    <div class="flex gap-3 mb-5">
        <FloatingInput name="search" v-model="search" label="Tìm kiếm..." class="w-64" />
        <FloatingSelect 
            name="category_id"
            v-model="category_id" 
            label="Danh mục"
            :options="[
                { value: '', label: 'Tất cả' },
                ...categories.map(c => ({ value: c.id, label: c.name }))
            ]" class="w-64" />
    </div>

    <div class="bg-white rounded-xl shadow border overflow-hidden">
      <BaseTable
            :data="brands"
            :columns="columns"
            :filters="filters"
            @sort="sort"

            :selectedIds="selectedIds"
            selectable
            @toggleOne="toggleOne"
            @toggleAll="toggleAll"
        >
            <template #row="{ row }">

                <td class="border-r p-2 text-center">{{ row.id }}</td>

                <td class="border-r p-2 font-medium">
                    {{ row.name }}
                </td>

                <td class="border-r p-2">
                    {{ row.category?.name || '---' }}
                </td>

                <td class="border-r p-2 text-center">
                    <span
                        class="px-2 py-1 rounded text-xs"
                        :class="row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200'"
                    >
                        {{ row.is_active ? 'Hoạt động' : 'Tắt' }}
                    </span>
                </td>

                <td class="text-center p-2">
                    <button @click="openEdit(row)" class="mx-1 px-2 py-2 bg-blue-500 text-white rounded">
                        Sửa
                    </button>
                    <button @click="destroy(row.id)" class="mx-1 px-2 py-2 bg-red-500 text-white rounded">
                        Xóa
                    </button>
                </td>

            </template>
        </BaseTable>
    </div>
</div>

</template>