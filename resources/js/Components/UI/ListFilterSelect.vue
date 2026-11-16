<script setup>
import { computed } from 'vue'
import FloatingSelect from './FloatingSelect.vue'

// Ô chọn lọc dùng trong ListToolbar — cùng kiểu với bộ lọc "Trạng thái thanh toán" ở trang Hóa đơn.
// Mọi prop khác (modelValue, label, optionLabel...) được chuyển thẳng cho FloatingSelect.
//
// Luôn chỉ có MỘT mục "Tất cả" (giá trị rỗng): mục "Chọn..." mặc định và các mục rỗng tự đặt tên
// kiểu "Tất cả trạng thái" được gộp lại, tránh hiện hai lựa chọn cùng nghĩa.
const props = defineProps({
    width: { type: String, default: 'sm:w-[180px]' },
    options: { type: Array, default: () => [] },
    optionValue: { type: String, default: 'value' },
    placeholderText: { type: String, default: 'Tất cả' },
    // Giữ lại để các trang cũ truyền vào không lỗi; ô lọc luôn có mục "Tất cả".
    showPlaceholder: { type: Boolean, default: true },
})

const isEmpty = value => value === '' || value === null || value === undefined
const filterOptions = computed(() => props.options.filter(option => !isEmpty(option?.[props.optionValue])))
</script>

<template>
    <FloatingSelect
        :options="filterOptions"
        :option-value="optionValue"
        :placeholder-text="placeholderText"
        :show-placeholder="true"
        input-class="!h-[42px] !rounded-xl !border-slate-200 !px-3 !pb-1 !pt-3 !text-xs !font-semibold focus:!border-blue-400"
        :wrapper-class="`w-full ${width}`"
    />
</template>
