<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'
import { closeModal } from '@/Stores/modal'
import { Trash2 } from 'lucide-vue-next' // Nhớ import Trash2 nếu bị thiếu icon nhé

const props = defineProps({
    endpoint: String,
    modalId: [Number, String], // Chấp nhận cả Number hoặc String cho an toàn
    id: [Number, String]
})

const emit = defineEmits(['close', 'updated'])

const items = ref([])
const loading = ref(false)
const total = ref(0)

const confirmBox = useConfirm()


const loadTrash = async () => {
    loading.value = true
    try {
        const res = await fetch(`/${props.endpoint}/trash`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        const json = await res.json()
        items.value = json.data || json
        total.value = json.meta?.total || items.value.length
    } catch (e) {
        console.error(e)
        items.value = []
    }
    loading.value = false
}

onMounted(loadTrash)

const restore = (id) => {
    router.post(`/${props.endpoint}/${id}/restore`, {}, {
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
            router.delete(`/${props.endpoint}/${id}/force`, {
                onSuccess: () => {
                    loadTrash()
                    emit('updated')
                }
            })
        }
    })
}

const handleEsc = (e) => {
    if (e.key === 'Escape') {
        emit('close')
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
<div class="fixed inset-0 z-[9999] flex items-center justify-center">

    <div class="absolute inset-0 bg-black/40" @click="closeModal(props.modalId)"></div>

    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
        <div class="bg-white w-[900px] h-[80vh] rounded-xl shadow-xl flex flex-col overflow-hidden pointer-events-auto" @click.stop>

            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <Trash2 class="w-5 h-5 text-red-500" /> Thùng rác ({{ endpoint }})
                </h2>
                <button @click="closeModal(props.modalId)" class="text-gray-500 hover:text-black text-xl">✕</button>
            </div>

            <div class="p-4 flex-1 overflow-y-auto">
                <div v-if="loading" class="text-center py-10 text-gray-500">
                    Đang tải dữ liệu...
                </div>

                <div v-else-if="items.length === 0" class="text-center py-10 text-gray-400">
                    Không có dữ liệu trong thùng rác
                </div>

                <table v-else class="w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="p-3 border">ID</th>
                            <th class="p-3 border">Tên</th>
                            <th class="p-3 border text-center w-40">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
                            <td class="p-3 border">{{ item.id }}</td>
                            <td class="p-3 border font-medium">
                                {{ item.name || item.title || '---' }}
                            </td>
                            <td class="p-3 border text-center">
                                <div class="flex justify-center gap-2">
                                    <button
                                        @click="restore(item.id)"
                                        class="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600"
                                    >
                                        Khôi phục
                                    </button>
                                    <button
                                        @click="forceDelete(item.id)"
                                        class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600"
                                    >
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-t bg-gray-50 text-right">
                <button
                    @click="closeModal(props.modalId)"
                    class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
                >
                    Đóng
                </button>
            </div>

        </div>
    </div>
</div>
</template>