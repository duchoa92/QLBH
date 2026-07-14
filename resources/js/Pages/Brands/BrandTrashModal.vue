<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'

const props = defineProps({
    show: Boolean
})

const emit = defineEmits(['close', 'updated'])

const items = ref([])
const loading = ref(false)
const confirmBox = useConfirm()

watch(() => props.show, (val) => {
    if (val) loadTrash()
})

const loadTrash = async () => {
    loading.value = true

    const res = await fetch('/brands/trash', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })

    const data = await res.json()
    items.value = data
    loading.value = false
}

/* restore */
const restore = (id) => {
    router.post(`/brands/${id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            items.value = items.value.filter(i => i.id !== id)
            emit('updated')
        }
    })
}

/* force delete */
const forceDelete = (id) => {
    confirmBox.show({
        title: 'Xóa vĩnh viễn?',
        message: 'Không thể khôi phục!',
        onConfirm: () => {
            router.delete(`/brands/${id}/force-delete`, {
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
<div v-if="show" class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <div class="absolute inset-0 flex items-center justify-center">
        <div class="bg-white w-[800px] h-[80vh] rounded-xl flex flex-col">

            <div class="p-4 border-b flex justify-between">
                <h2>Thùng rác</h2>
                <button @click="emit('close')">✕</button>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                <div v-if="loading">Loading...</div>

                <table v-else class="w-full">
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