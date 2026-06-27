<script setup>

import {
    PenSquare,
    Trash2,
    Gift,
} from 'lucide-vue-next'

defineProps({

    item: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'toggle-note',
    'toggle-promotion',
    'remove',
])

</script>

<template>

    <div class="flex gap-3">

        <div
            class="
                w-12
                h-12
                rounded-lg
                border
                flex
                items-center
                justify-center
                shrink-0
                bg-gray-50
                overflow-hidden
            "
        >

            <img
                v-if="item.image_url"
                :src="item.image_url"
                class="w-full h-full object-cover"
            >

        </div>

        <div
            class="
                flex-1
                min-w-0
                flex
                flex-col
                justify-center
            "
        >

            <div
                class="
                    flex
                    justify-between
                    items-center
                "
            >

                <div
                    class="
                        font-semibold
                        text-sm
                        truncate
                    "
                >
                    {{ item.name }}
                </div>

                <div
                    class="
                        flex
                        gap-2
                        shrink-0
                    "
                >

                    <button
                        @click="
                            emit(
                                'toggle-note'
                            )
                        "
                        class="
                            text-gray-400
                            hover:text-blue-600
                        "
                    >
                        <PenSquare
                            class="w-4 h-4"
                        />
                    </button>

                    <button
                        @click="
                            emit(
                                'toggle-promotion'
                            )
                        "
                        class="
                            text-gray-400
                            hover:text-amber-600
                        "
                    >
                        <Gift
                            class="w-4 h-4"
                        />
                    </button>

                    <button
                        @click="
                            emit(
                                'remove'
                            )
                        "
                        class="
                            text-red-400
                            hover:text-red-600
                        "
                    >
                        <Trash2
                            class="w-4 h-4"
                        />
                    </button>

                </div>

            </div>

            <div
                class="
                    text-xs
                    text-gray-500
                "
            >
                {{ item.unit_name ?? 'Cái' }}
            </div>

            <div
                v-if="item.imei"
                class="
                    text-[11px]
                    text-blue-600
                    font-medium
                "
            >
                IMEI:
                {{ item.imei }}
            </div>

        </div>

    </div>

</template>