<script setup>
import { closeModal } from '@/Stores/modal'
import { computed } from 'vue'
import { AlertTriangle, Trash2, Info } from 'lucide-vue-next'

const props = defineProps({
    title: String,
    message: String,
    type: {
        type: String,
        default: 'warning'
    },
    onConfirm: Function
})

const config = computed(() => {
    switch (props.type) {
        case 'danger':
            return { icon: Trash2, color: 'text-red-500', btn: 'bg-red-600' }
        case 'info':
            return { icon: Info, color: 'text-blue-500', btn: 'bg-blue-600' }
        default:
            return { icon: AlertTriangle, color: 'text-yellow-500', btn: 'bg-yellow-500 text-black' }
    }
})

const confirm = async () => {
    await props.onConfirm?.()
    closeModal()
}
</script>

<template>
<div class="p-6 text-center">

    <component :is="config.icon" class="w-12 h-12 mx-auto mb-4" :class="config.color" />

    <h2 class="text-lg font-bold mb-2">{{ title }}</h2>
    <p class="text-gray-600 mb-6">{{ message }}</p>

    <div class="flex justify-center gap-3">
        <button @click="closeModal" class="px-4 py-2 bg-gray-200 rounded">
            Hủy
        </button>

        <button @click="confirm" class="px-4 py-2 text-white rounded" :class="config.btn">
            Xác nhận
        </button>
    </div>

</div>
</template>