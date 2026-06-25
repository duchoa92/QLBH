<script setup>

defineProps({

    tabs: Array,

    activeTabId: Number,
})

const emit = defineEmits([
    'select',
    'create',
    'remove',
])

</script>

<template>

<div
    class="flex items-center gap-2 overflow-x-auto pb-2"
>

    <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="emit('select', tab.id)"
        class="
            px-3
            py-1.5
            rounded-lg
            border
            text-sm
            flex
            items-center
            gap-2
            whitespace-nowrap
        "
        :class="
            activeTabId === tab.id
                ? 'bg-blue-600 text-white'
                : 'bg-white'
        "
    >

    <div class="flex items-center gap-1">

        <span>
            {{ tab.name }}
        </span>

        <span
            v-if="tab.cart.length"
            class="
                text-[11px]
                px-1.5
                rounded-full
            "
            :class="
                activeTabId === tab.id
                    ? 'bg-white text-blue-600'
                    : 'bg-slate-200 text-slate-700'
            "
        >
            {{ tab.cart.length }}
        </span>

    </div>

        <span
            v-if="tabs.length > 1"
            @click.stop="
                emit(
                    'remove',
                    tab.id
                )
            "
            class="
                text-xs
                opacity-70
                hover:opacity-100
            "
        >
            ×
        </span>

    </button>

    <button
        @click="emit('create')"
        class="px-3 py-1 rounded-lg border bg-green-600 text-white"
    >
        +
    </button>

</div>

</template>