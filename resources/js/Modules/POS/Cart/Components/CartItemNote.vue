<script setup>

import FloatingInput
    from '@/Components/UI/FloatingInput.vue'

defineProps({

    item: {
        type: Object,
        required: true,
    },
})

</script>

<template>

    <div
        v-if="item.note"
        class="
            bg-white
            rounded-lg
            flex
            items-center
            w-full
            mt-2
            border
            overflow-hidden
        "
    >

        <div
            class="
                bg-gray-100
                px-3
                py-1
                font-semibold
                text-blue-900
                text-sm
                border-r
            "
        >
            Ghi chú:
        </div>

        <div
            class="
                flex-1
                px-3
                py-1
                flex
                justify-between
                items-center
            "
        >

            <span>
                {{ item.note }}
            </span>

            <button
                @click="
                    item.note = ''
                "
            >
                ×
            </button>

        </div>

    </div>

    <div
        v-if="item.showNote"
        class="mt-2"
    >

        <FloatingInput

            v-model="item.note"

            @blur="
                item.showNote = false
            "

            label="Nhập ghi chú"
        />

    </div>

</template>