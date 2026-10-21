<script setup>
import { ArrowUp, ArrowDown, ArrowUpDown } from 'lucide-vue-next'

const props = defineProps({
    label: String,
    field: String,
    currentSort: String,
    currentOrder: String
})

const emit = defineEmits(['sort'])

const handleSort = () => {
    let order = 'asc'

    if (props.currentSort === props.field) {
        order = props.currentOrder === 'asc' ? 'desc' : 'asc'
    }

    emit('sort', {
        field: props.field,
        order: order
    })
}
</script>

<template>
<button
    type="button"
    @click="handleSort"
    class="inline-flex cursor-pointer select-none items-center gap-1 whitespace-nowrap text-left transition-colors hover:text-emerald-700"
>
    <span>{{ label }}</span>

    <template v-if="currentSort === field">
        <ArrowUp v-if="currentOrder === 'asc'" size="14" />
        <ArrowDown v-else size="14" />
    </template>

    <ArrowUpDown v-else size="14" class="opacity-40" />
</button>
</template>
