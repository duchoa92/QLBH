<script setup>

import TagBadge from '@/Components/UI/TagBadge.vue'

defineProps({

    gifts: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'remove',
])

</script>

<template>

    <div
        class="flex flex-wrap gap-1"
    >

        <TagBadge
            v-for="gift in gifts"
            :key="gift.id"
            removable
            @remove="
                emit(
                    'remove',
                    gift.id
                )
            "
        >

            {{ gift.name }}

            <span
                class="
                    font-bold
                    text-red-700
                    px-1
                "
            >
                x {{ gift.quantity }}
            </span>

        </TagBadge>

    </div>
</template>