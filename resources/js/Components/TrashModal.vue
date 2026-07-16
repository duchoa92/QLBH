<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'
import { closeModal } from '@/Stores/modal'

const props = defineProps({
    endpoint: {
        type: String,
        required: true
    }
})

const emit = defineEmits(['updated'])

const items = ref([])
const loading = ref(false)
const confirmBox = useConfirm()

const loadTrash = async () => {
    loading.value = true

    const res = await fetch(`/${props.endpoint}/trash`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })

    items.value = await res.json()
    loading.value = false
}

onMounted(loadTrash)

/* restore */
const restore = (id) => {
    router.post(`/${props.endpoint}/${id}/restore`, {}, {
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
            router.delete(`/${props.endpoint}/${id}/force`, {
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

    <!-- overlay -->
    <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>

    <!-- modal -->
    <div class="absolute inset-0 flex items-center justify-center">
        <div class="bg-white w-[900px] h-[80vh] rounded-xl shadow-xl flex flex-col overflow-hidden">

            <!-- header -->
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h2 class="text-lg font-semibold">
                    🗑 Thùng rác ({{ endpoint }})
                </h2>
                <button @click="closeModal()" class="text-gray-500 hover:text-black text-xl">✕</button>
            </div>

            <!-- content -->
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

            <!-- footer -->
            <div class="p-3 border-t bg-gray-50 text-right">
                <button
                    @click="closeModal()"
                    class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
                >
                    Đóng
                </button>
            </div>

        </div>
    </div>
</div>
</template>