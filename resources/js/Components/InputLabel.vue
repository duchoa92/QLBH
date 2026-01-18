<script setup>
defineProps({
    value: {
        type: String,
    },
    dau_sao: {
        type: Boolean,
        default: false,
    }
});
</script>

<template>
    <label class="block text-sm font-medium text-gray-700">
        <span v-if="value">{{ value }}</span>
        <!-- <span v-else><slot /></span>  -->       
        <span v-if="dau_sao" class="text-red-500 ml-1" title="Trường bắt buộc">*</span>
    </label>
</template>
