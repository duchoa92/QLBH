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
<th
    @click="handleSort"
    class="cursor-pointer select-none px-3 py-2 whitespace-nowrap"
>
    <div class="flex items-center gap-1">
        <span>{{ label }}</span>

        <!-- ICON -->
        <template v-if="currentSort === field">
            <ArrowUp v-if="currentOrder === 'asc'" size="14" />
            <ArrowDown v-else size="14" />
        </template>

        <ArrowUpDown v-else size="14" class="opacity-40" />
    </div>
</th>
</template>