<script setup>
defineProps({
    tabs: Array,
    activeTab: String,
})

const emit = defineEmits(['change'])
</script>

<template>

    <div class="border-b mb-4 flex gap-2 overflow-x-auto">

        <button
            v-for="tab in tabs"
            :key="tab"
            @click="emit('change', tab)"
            class="px-4 py-2 border-b-2 whitespace-nowrap"
            :class="activeTab === tab
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500'"
        >
            {{ tab }}
        </button>

    </div>

</template>