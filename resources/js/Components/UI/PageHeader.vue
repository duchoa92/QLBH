<script setup>
import { onBeforeUnmount, onMounted, useSlots } from 'vue'
import { floatingActionsCount, floatingActionsTarget } from '@/Stores/floatingActions'

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

// Nút hành động của trang được đưa vào nút nổi (xem FloatingActions.vue) thay vì nằm trên đầu trang.
const hasActions = Boolean(slots.actions)
onMounted(() => { if (hasActions) floatingActionsCount.value += 1 })
onBeforeUnmount(() => { if (hasActions) floatingActionsCount.value = Math.max(0, floatingActionsCount.value - 1) })
</script>

<template>
    <div v-if="description" class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <p class="mt-0.5 text-xs text-slate-500">{{ description }}</p>
        </div>
    </div>

    <Teleport v-if="$slots.actions && floatingActionsTarget" :to="floatingActionsTarget">
        <slot name="actions" />
    </Teleport>
</template>
