<script setup>
import { computed } from 'vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

/*
|--------------------------------------------------------------------------
| UnitInput — ô "giá trị + đơn vị" dùng chung
|--------------------------------------------------------------------------
| Một khung duy nhất: ô nhập số (nhãn nổi) và phần đơn vị nằm sát bên phải, ngăn cách bằng vạch.
|  - Nhiều đơn vị (Tháng/Ngày...): đơn vị là danh sách chọn  → v-model:unit
|  - Hai chế độ đổi qua lại (₫ / %): đặt `toggle`, nút màu theo `tone` của từng đơn vị;
|    bấm nút phát sự kiện `toggle` để nơi dùng tự quy đổi giá trị rồi đổi đơn vị.
| Dùng cho: phí phụ thu, chiết khấu đơn (POS, sửa hóa đơn), thời hạn bảo hành (sản phẩm, giỏ hàng, nhập kho).
*/
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, required: true },
    unit: { type: [String, Number], default: '' },
    // [{ value, label, tone?: 'blue' | 'amber' | 'emerald' }]
    unitOptions: { type: Array, default: () => [] },
    toggle: { type: Boolean, default: false },
    unitLabel: { type: String, default: 'Đơn vị' },
    unitTitle: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm: thanh bên POS · md: biểu mẫu
    type: { type: String, default: 'text' },
    formatThousands: { type: Boolean, default: false },
    inputmode: { type: String, default: undefined },
    min: { type: [String, Number], default: undefined },
    max: { type: [String, Number], default: undefined },
    step: { type: [String, Number], default: undefined },
    name: { type: String, default: undefined },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    // true: tiêu đề nằm riêng phía trên ô (không nổi lên đè viền, không bị cắt chữ)
    stackedLabel: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:unit', 'toggle'])

const current = computed(() => props.unitOptions.find(option => String(option.value) === String(props.unit)) || props.unitOptions[0] || { label: '', value: '' })

const TONES = {
    blue: 'bg-blue-600 text-white hover:bg-blue-700',
    amber: 'bg-amber-500 text-white hover:bg-amber-600',
    emerald: 'bg-emerald-600 text-white hover:bg-emerald-700',
}
const toggleClass = computed(() => TONES[current.value.tone] || TONES.blue)

const small = computed(() => props.size === 'sm')
const inputClass = computed(() => {
    const height = small.value ? '!h-9 !py-0 !text-xs' : '!h-10 !py-0 !text-sm'
    const room = props.toggle ? (small.value ? '!pr-11' : '!pr-12') : (small.value ? '!pr-[4.75rem]' : '!pr-[6.5rem]')
    return `!rounded-lg !border-slate-300 !pl-2.5 ${height} ${room} focus:!border-blue-500 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none`
})
const labelClass = computed(() => small.value ? '!left-2 !px-0.5 !text-[10px]' : '')
</script>

<template>
    <div class="min-w-0">
        <label v-if="stackedLabel" class="mb-0.5 block truncate text-[11px] font-medium text-slate-500">{{ label }}</label>
        <div class="relative">
            <FloatingInput
                :model-value="modelValue"
                :label="label"
                :floating-label="!stackedLabel"
                :placeholder-text="stackedLabel ? '0' : ''"
                :name="name"
                :type="type"
                :format-thousands="formatThousands"
                :inputmode="inputmode"
                :min="min"
                :max="max"
                :step="step"
                :disabled="disabled"
                :input-class="inputClass"
                :label-class="labelClass"
                @update:model-value="emit('update:modelValue', $event)"
            />

            <!-- Phần đơn vị: nút đổi chế độ -->
            <button
                v-if="toggle"
                type="button"
                :disabled="disabled"
                :title="unitTitle"
                :aria-label="unitTitle || unitLabel"
                class="absolute bottom-[3px] right-[3px] top-[3px] flex w-8 items-center justify-center rounded-md text-xs font-extrabold shadow-sm transition disabled:opacity-60"
                :class="toggleClass"
                @click="emit('toggle')"
            >{{ current.label }}</button>

            <!-- Phần đơn vị: danh sách chọn -->
            <select
                v-else
                :value="unit"
                :disabled="disabled"
                :aria-label="unitLabel"
                class="absolute bottom-[3px] right-[3px] top-[3px] cursor-pointer appearance-none rounded-md border-0 bg-blue-50 bg-none py-0 pl-2 pr-2 text-center font-semibold text-blue-700 underline decoration-blue-300 decoration-dotted underline-offset-4 outline-none transition hover:bg-blue-100 focus:bg-blue-100 focus:ring-2 focus:ring-blue-300 disabled:opacity-60"
                :class="small ? 'w-[3.75rem] text-xs' : 'w-[4.5rem] text-sm'"
                :title="unitLabel"
                @change="emit('update:unit', $event.target.value)"
            >
                <option v-for="option in unitOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </div>
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
    </div>
</template>
