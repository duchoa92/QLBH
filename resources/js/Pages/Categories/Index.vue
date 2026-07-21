<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { openModal } from '@/Stores/modal'
import CategoryForm from './Form.vue'
import TrashModal from '@/Components/TrashModal.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { SquarePen, Plus, Trash2, FilePlus } from 'lucide-vue-next'
import BaseTable from '@/Components/UI/BaseTable.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { size } from 'lodash'


defineOptions({ layout: AdminLayout })

const props = defineProps({
    categories: Object,
    filters: Object
})

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },

    { key: 'name', label: 'Tên danh mục', sortable: true },

    { 
        key: 'is_active', 
        label: 'Trạng thái', 
        sortable: true,
        width: '120px',
        class: 'text-center'
    }
]



const confirmBox = useConfirm()

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

watch([search, status], () => {
    router.get('/categories', {
        search: search.value,
        status: status.value,
        sort_by: props.filters?.sort_by,
        sort_order: props.filters?.sort_order
    }, {
        preserveState: true,
        replace: true
    })
})

let timeout = null

watch([search, status], () => {
    clearTimeout(timeout)

    timeout = setTimeout(() => {
        router.get('/categories', {
            search: search.value,
            status: status.value,
            sort_by: props.filters?.sort_by,
            sort_order: props.filters?.sort_order
        }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})

onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout)
})

/* ================= RELOAD ================= */
const loadData = () => {
    router.reload({ only: ['categories'] })
    loadTrashCount()
}

/* ================= MODAL ================= */
const openCreate = () => {
    openModal(CategoryForm, {
        props: {
            title: 'Thêm danh mục',
            size: 'sm'
        },
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(CategoryForm, {
        props: {
            category: item,
            title: 'Sửa danh mục',
            size: 'sm',
        },
        onUpdated: loadData
    })
}

/* ================= DELETE ================= */
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển vào thùng rác?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(`/categories/${id}`, {
                preserveScroll: true,
                onSuccess: loadData
            })
        }
    })
}

/* ================= TRASH ================= */
const trashCount = ref(0)

const loadTrashCount = async () => {
    try {
        const res = await fetch('/categories/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        const data = await res.json()
        trashCount.value = data.meta?.total || 0
    } catch {
        trashCount.value = 0
    }
}

const openTrash = () => {
    openModal(TrashModal, {
        props: { endpoint: 'categories' },
        onUpdated: loadData
    })
}

onMounted(loadTrashCount)

/* ================= STATUS ================= */
const loadingStatus = ref(null)

const toggleStatus = (id) => {
    if (loadingStatus.value) return

    loadingStatus.value = id

    // 👉 update UI trước
    const item = props.categories.data.find(i => i.id === id)
    if (item) item.is_active = !item.is_active

    router.patch(`/categories/${id}/toggle-status`, {}, {
        onFinish: () => loadingStatus.value = null
    })
}

const sort = ({ field, order }) => {
    router.get('/categories', {
        search: search.value,
        status: status.value,
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

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Danh mục</h1>
            <p class="text-sm text-gray-500">Quản lý các danh mục</p>
        </div>

        <div class="flex gap-2">
            <button @click="openCreate"
                class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700">
                <FilePlus /> Thêm
            </button>

            <button @click="openTrash"
                class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500 hover:text-white">
                <Trash2 /> ({{ trashCount }})
            </button>
        </div>
    </div>

    <!-- FILTER -->
    <div class="flex gap-3 my-5">
        <FloatingInput v-model="search" label="Tìm kiếm..." class="w-64" />

        <FloatingSelect
            v-model="status"
            label="Trạng thái"
            :options="[
                { value: '', label: 'Tất cả' },
                { value: 1, label: 'Hoạt động' },
                { value: 0, label: 'Ngừng' }
            ]"
            class="w-64"
        />
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded-xl shadow border overflow-hidden">
        <BaseTable
            :data="categories"
            :columns="columns"
            :filters="filters"
            @sort="sort"
        >
            <template #row="{ row }">

                <td class="border-r p-2 text-center">{{ row.id }}</td>

                <td class="border-r p-2 font-medium">
                    {{ row.name }}
                </td>

                <td class="border-r p-2 text-center">
                    <button
                        @click="toggleStatus(row.id)"
                        :disabled="loadingStatus === row.id"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :class="row.is_active ? 'bg-green-500' : 'bg-gray-300'"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                            :class="row.is_active ? 'translate-x-6' : 'translate-x-1'"
                        />
                    </button>
                </td>

                <td class="text-center p-2">
                    <div class="flex justify-center gap-1">
                        <Tooltip text="Sửa">
                            <button @click="openEdit(row)" title="Sửa" class="p-1 hover:bg-gray-200 rounded">
                                <SquarePen size="17" class="text-blue-500" />
                            </button>
                        </Tooltip>

                        <Tooltip text="Chuyển vào thùng rác" position="top">
                            <button @click="destroy(row.id)"  class="p-1 hover:bg-gray-200 rounded">
                                <Trash2 size="17" class="text-red-500" />
                            </button>
                        </Tooltip>

                    </div>
                </td>

            </template>
        </BaseTable>
    </div>

</div>
</template>