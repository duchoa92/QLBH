<script setup>
import { computed } from 'vue'
import { closeModal } from '@/Stores/modal'
import {
    AlertTriangle,
    Trash2,
    CheckCircle,
    Info
} from 'lucide-vue-next'

const props = defineProps({
    title: String,
    message: String,
    type: {
        type: String,
        default: 'warning' // warning | danger | success | info
    },
    onConfirm: Function
})

/*
|--------------------------------------------------------------------------
| CONFIG THEO TYPE
|--------------------------------------------------------------------------
*/
const config = computed(() => {
    switch (props.type) {
        case 'danger':
            return {
                icon: Trash2,
                color: 'red',
                btn: 'bg-red-600 hover:bg-red-700'
            }
        case 'success':
            return {
                icon: CheckCircle,
                color: 'green',
                btn: 'bg-green-600 hover:bg-green-700'
            }
        case 'info':
            return {
                icon: Info,
                color: 'blue',
                btn: 'bg-blue-600 hover:bg-blue-700'
            }
        default:
            return {
                icon: AlertTriangle,
                color: 'yellow',
                btn: 'bg-red-500 hover:bg-red-600 text-white'
            }
    }
})

const confirm = () => {
    props.onConfirm?.()
    closeModal()
}
</script>

<template>
<div class="p-6 text-center">

    <!-- ICON -->
    <div class="flex justify-center mb-4">
        <component
            :is="config.icon"
            class="w-12 h-12"
            :class="`text-${config.color}-500`"
        />
    </div>

    <!-- TITLE -->
    <h2 class="text-lg font-bold mb-2">
        {{ title || 'Xác nhận' }}
    </h2>

    <!-- MESSAGE -->
    <p class="text-gray-600 mb-6">
        {{ message || 'Bạn có chắc chắn?' }}
    </p>

    <!-- ACTION -->
    <div class="flex justify-center gap-3">
        <button
            @click="closeModal"
            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
        >
            Hủy
        </button>

        <button
            @click="confirm"
            class="px-4 py-2 text-white rounded"
            :class="config.btn"
        >
            Xác nhận
        </button>
    </div>

</div>
</template>