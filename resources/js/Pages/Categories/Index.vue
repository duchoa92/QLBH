<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { openModal } from '@/Stores/modal'
import CategoryForm from './Form.vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'
import TrashModal from '@/Components/TrashModal.vue'


defineOptions({ layout: AdminLayout })

const props = defineProps({
    categories: Object,
    filters: Object
})

/* FILTER */
const search = ref(props.filters?.search || '')

const confirmBox = useConfirm()

let timeout = null
onBeforeUnmount(() => {
    if (timeout) {
        clearTimeout(timeout)
    }
})

watch(search, (s) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/categories', { search: s }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})


/* RELOAD */
const loadData = () => {
    router.reload({ only: ['categories'] })
    loadTrashCount()
}

/* DELETE */
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển vào thùng rác?',
        onConfirm: () => {
            router.delete(`/categories/${id}`, {
                preserveScroll: true,
                onSuccess: loadData
            })
        }
    })
}

const openCreate = () => {
    openModal(CategoryForm, {
        title: 'Thêm danh mục',
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(CategoryForm, {
        title: 'Sửa danh mục',
        props: {
            category: item
        },
        onUpdated: loadData
    })
}



const openTrash = () => {
    openModal(TrashModal, {
        props: {
            endpoint: 'categories'
        },
        onUpdated: loadData
    })
}


/* TRASH COUNT */
onMounted(() => {
    loadTrashCount()
})


const trashCount = ref(0)
const loadTrashCount = async () => {
    try {
        const res = await fetch('/categories/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        if (!res.ok) throw new Error('API lỗi')

        const data = await res.json()
        trashCount.value = data.length
    } catch (e) {
        console.error('Load trash count lỗi', e)
        trashCount.value = 0
    }
}

/* STATUS */
const loadingStatus = ref(null)

const toggleStatus = (id) => {
    if (loadingStatus.value) return

    loadingStatus.value = id

    router.patch(`/categories/${id}/toggle-status`, {}, {
        onFinish: () => loadingStatus.value = null
    })
}
</script>

<template>
<div>
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Danh mục</h1>
            <p class="text-gray-500">Quản lý danh mục sản phẩm</p>
        </div>
        <div class="flex gap-2">
            <button @click="openCreate" class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700">
                <Plus /> Thêm mới
            </button>
            <button @click="openTrash" class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500  hover:text-white">
                <Trash2 /> ({{ trashCount }})
            </button>
        </div>
    </div>

    <div class="flex gap-3 mb-5">
        <FloatingInput name="search" v-model="search" label="Tìm kiếm..." class="w-64" />
    </div>
    <div class="bg-white rounded-xl shadow border overflow-hidden">
        <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
            <thead >
                <tr class="bg-gray-100 uppercase text-left">
                    <th class="border p-2 w-16 text-center">ID</th>
                    <th class="border p-2">Tên danh mục</th>
                    <th class="border p-2 w-32 text-center">Trạng thái</th>
                    <th class="border p-2 w-40 text-center">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in categories.data" :key="item.id" class="hover:bg-gray-50">
                    <td class="border p-2 text-center">{{ item.id }}</td>
                    <td class="border p-2 font-medium">{{ item.name }}</td>
                    <td class="border p-2 text-center">
                        <div class="flex items-center gap-2 justify-center">
                            <button
                                @click="toggleStatus(item.id)"
                                :disabled="loadingStatus === item.id"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="item.is_active ? 'bg-green-500' : 'bg-gray-300'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                    :class="item.is_active ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </button>
                        </div>
                    </td>
                    <td class="border p-2 text-center">
                        <div class="flex gap-2 justify-center">
                            <button @click.stop="openEdit(item)" class="mx-1 px-2 py-2 bg-red-500 text-white rounded text-sm">Sửa</button>
                            <button @click.stop="destroy(item.id)" class="mx-1 px-2 py-2 bg-red-500 text-white rounded text-sm"><Trash2 /></button>
                        </div>
                    </td>
                </tr>
                <tr v-if="categories.data.length === 0">
                    <td colspan="4" class="text-center p-10 text-gray-500 bg-gray-50">Không tìm thấy danh mục nào phù hợp.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

</template>