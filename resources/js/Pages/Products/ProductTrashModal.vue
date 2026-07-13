<script setup>
import { ref, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useConfirm } from '@/Composables/useConfirm'

const props = defineProps({
    show: Boolean
})

const emit = defineEmits(['close', 'updated'])

const items = ref([])
const loading = ref(false)
const confirmState = useConfirm()

watch(() => props.show, (val) => {
    if (val) loadTrash()
})

const loadTrash = async () => {
    loading.value = true

    const res = await fetch('/products/trash', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })

    const data = await res.json()
    items.value = data.data
    loading.value = false
}

onMounted(() => {
    if (props.show) loadTrash()
})

/* restore */
const restore = (id) => {
    router.post(`/products/${id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            items.value = items.value.filter(i => i.id !== id)
            emit('updated')
        }
    })
}

/* force delete */


const forceDelete = (id) => {
    confirmState.show({
        title: 'Xóa vĩnh viễn?',
        message: 'Không thể khôi phục dữ liệu!',
        confirmText: 'Xóa luôn',
        cancelText: 'Hủy',

        onConfirm: () => {
            router.delete(route('products.forceDelete', id), {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    loadTrash()
                    emit('updated')
                }
            })
        }
    })
}
const deletingId = ref(null)

</script>

<template>
<div v-if="show" class="fixed inset-0 z-50">

    <!-- overlay -->
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <!-- modal -->
    <div class="absolute inset-0 flex items-center justify-center">

        <div class="bg-white w-[900px] h-[80vh] rounded-xl shadow-lg overflow-hidden flex flex-col">

            <!-- header -->
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="font-bold text-lg">Thùng rác sản phẩm</h2>
                <button @click="emit('close')" class="text-red-500 font-bold">✕</button>
            </div>

            <!-- body -->
            <div class="flex-1 overflow-y-auto p-4">

                <div v-if="loading">Đang tải...</div>

                <div v-else-if="items.length === 0">
                    Thùng rác trống
                </div>

                <table v-else class="w-full text-sm border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 w-16">ID</th>
                            <th class="p-2">Tên</th>
                            <th class="p-2 text-center w-40">Hành động</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-t">
                            <td class="p-2 text-center">{{ item.id }}</td>
                            <td class="p-2">{{ item.name }}</td>

                            <td class="p-2 text-center">
                                <div class="flex justify-center gap-2">
                                    <button @click="restore(item.id)"
                                        class="px-2 py-1 bg-green-600 text-white rounded text-sm">
                                        Khôi phục
                                    </button>

                                    <button
                                        @click="forceDelete(item.id)"
                                        class="px-2 py-1 bg-red-600 text-white text-sm rounded"
                                        :disabled="deletingId === item.id"
                                    >
                                        <span v-if="deletingId === item.id">...</span>
                                        <span v-else>Xóa</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>

        </div>

    </div>
</div>
</template>