<script setup>
import { Plus, X } from 'lucide-vue-next';


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
    class="flex items-center gap-2 overflow-x-auto pb-3"
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
                ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                : 'bg-white'
        "
    >

        <div class="flex items-center ">

            <span class="max-w-[90px] truncate">
                {{ tab.name }}
            </span>

            
        </div>
        <div class="flex items-center  gap-1">
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
                {{ tab.cart.reduce((total, item) => total + Number(item.quantity || 0), 0) }}
            </span>

            <span
                v-if="tabs.length > 1"
                @click.stop="
                    emit(
                        'remove',
                        tab.id
                    )
                "
                class="
                    text-sm
                    opacity-70
                    hover:opacity-100
                "
            >
                <X size="13"/>
            </span>

        </div>
        

    </button>

    <button
        @click="emit('create')"
        title="Tạo đơn mới"
        class="px-3 py-2 rounded-lg border bg-green-600 text-white"
    >
        <Plus size="15" />
    </button>

</div>

</template>