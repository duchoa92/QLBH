<script setup>
import { Banknote, CreditCard, Landmark } from 'lucide-vue-next'

const props = defineProps({
    modelValue: {
        type: String,
        default: 'cash',
    },
})

const emit = defineEmits(['update:modelValue'])

const methods = [
    { id: 'cash', label: 'Tiền mặt', icon: Banknote },
    { id: 'bank', label: 'Chuyển khoản', icon: Landmark },
    { id: 'card', label: 'Thẻ / POS', icon: CreditCard },
]
</script>

<template>
    <div class="grid grid-cols-3 gap-2">
        <button
            v-for="item in methods"
            :key="item.id"
            type="button"
            class="flex flex-col items-center justify-center gap-1.5 py-2.5 px-2 rounded-xl border text-xs font-bold transition-all"
            :class="props.modelValue === item.id
                ? 'border-indigo-600 bg-indigo-50/60 text-indigo-600 shadow-sm'
                : 'border-slate-200/80 bg-white text-slate-600 hover:border-slate-300'"
            @click="emit('update:modelValue', item.id)"
        >
            <component :is="item.icon" class="w-4 h-4" />
            <span>{{ item.label }}</span>
        </button>
    </div>
</template>