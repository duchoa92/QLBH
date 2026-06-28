<script setup>
import { Trash, Printer } from 'lucide-vue-next'

defineProps({
    selectedIds: Array
})

defineEmits(['delete', 'print'])
</script>

<template>
<div v-if="selectedIds.length"
     class="bg-blue-50 border rounded p-2 flex justify-between items-center">

    <div>
        Đã chọn <b>{{ selectedIds.length }}</b> sản phẩm
    </div>

    <div class="flex gap-2">
        <button @click="$emit('print')" class="btn-purple flex items-center gap-1">
            <Printer size="16"/> In tem
        </button>

        <button @click="$emit('delete')" class="btn-danger flex items-center gap-1">
            <Trash size="16"/> Xóa
        </button>
    </div>
</div>
</template>