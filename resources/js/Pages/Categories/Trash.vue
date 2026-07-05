<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const items = ref([])
const loading = ref(true)

const emit = defineEmits(['updated'])

const load = async () => {
    const res = await fetch('/categories/trash', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })

    items.value = await res.json()
    loading.value = false
}

onMounted(load)

const restore = (id) => {
    router.post(`/categories/${id}/restore`, {}, {
        onSuccess: () => {
            items.value = items.value.filter(i => i.id !== id)
            emit('updated') // 🔥 báo ra ngoài
        }
    })
}

const forceDelete = (id) => {
    router.delete(`/categories/${id}/force`, {
        onSuccess: () => {
            items.value = items.value.filter(i => i.id !== id)
            emit('updated') // 🔥 báo ra ngoài
        }
    })
}
</script>

<template>
<div>
    <div v-if="loading" class="p-5 text-center">Đang tải...</div>

    <table v-else class="w-full border">
        <thead class="bg-gray-100">
            <tr>
                <th class="p-2 w-16">ID</th>
                <th class="p-2">Tên</th>
                <th class="p-2 w-40 text-center">Hành động</th>
            </tr>
        </thead>

        <tbody>
            <tr v-for="item in items" :key="item.id">
                <td class="p-2 text-center">{{ item.id }}</td>
                <td class="p-2">{{ item.name }}</td>

                <td class="p-2 text-center">
                    <button @click="restore(item.id)" class="bg-green-600 text-white px-2 py-1 text-xs rounded">
                        Khôi phục
                    </button>

                    <button @click="forceDelete(item.id)" class="bg-red-600 text-white px-2 py-1 text-xs rounded ml-2">
                        Xóa
                    </button>
                </td>
            </tr>

            <tr v-if="items.length === 0">
                <td colspan="3" class="text-center p-5 text-gray-400">
                    Thùng rác trống
                </td>
            </tr>
        </tbody>
    </table>
</div>
</template>