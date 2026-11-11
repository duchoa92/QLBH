<script setup>
import { useSlots } from 'vue'

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
})

// Không có mô tả/nút hành động thì không render gì (tránh thẻ rỗng làm phát sinh khoảng cách space-y thừa).
const slots = useSlots()
</script>

<template>
    <div
        v-if="description || slots.actions"
        class="flex flex-wrap items-center justify-between gap-2"
    >
        <div class="min-w-0">
            <p
                v-if="description"
                class="mt-0.5 text-xs text-slate-500"
            >
                {{ description }}
            </p>
        </div>

        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-1.5"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
