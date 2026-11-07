<script setup>
import FloatingSelect from './FloatingSelect.vue'

// Ô chọn lọc dùng trong ListToolbar — cùng kiểu với bộ lọc "Trạng thái thanh toán" ở trang Hóa đơn.
// Mọi prop khác (modelValue, label, options, optionLabel...) được chuyển thẳng cho FloatingSelect.
defineProps({
    width: { type: String, default: 'sm:w-[180px]' },
})
</script>

<template>
    <FloatingSelect
        input-class="!h-[42px] !rounded-xl !border-slate-200 !px-3 !pb-1 !pt-3 !text-xs !font-semibold focus:!border-blue-400"
        :wrapper-class="`w-full ${width}`"
    />
</template>
