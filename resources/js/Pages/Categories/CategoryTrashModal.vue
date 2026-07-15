<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'

const emit = defineEmits(['close', 'updated'])

const items = ref([])
const confirmBox = useConfirm()

const loadTrash = async () => {
    const res = await fetch('/categories/trash', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    items.value = await res.json()
}

onMounted(loadTrash)

const restore = (id) => {
    router.post(`/categories/${id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            items.value = items.value.filter(i => i.id !== id)
            emit('updated')
        }
    })
}

const forceDelete = (id) => {
    confirmBox.show({
        title: 'Xóa vĩnh viễn?',
        message: 'Không thể khôi phục!',
        onConfirm: () => {
            router.delete(`/categories/${id}/force-delete`, {
                preserveScroll: true,
                onSuccess: () => {
                    loadTrash()
                    emit('updated')
                }
            })
        }
    })
}
</script>

<template>
<div class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <div class="absolute inset-0 flex items-center justify-center">
        <div class="bg-white w-[800px] h-[80vh] rounded-xl flex flex-col">

            <div class="p-4 border-b flex justify-between">
                <h2>Thùng rác danh mục</h2>
                <button @click="emit('close')">✕</button>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                <table class="w-full">
                    <tr v-for="item in items" :key="item.id">
                        <td>{{ item.name }}</td>
                        <td>
                            <button @click="restore(item.id)">Khôi phục</button>
                            <button @click="forceDelete(item.id)">Xóa</button>
                        </td>
                    </tr>
                </table>
            </div>

        </div>
    </div>
</div>
</template>