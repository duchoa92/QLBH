<script setup>

import { computed } from 'vue'

import {
    PenSquare,
    Trash2,
    Gift,
    ShieldCheck,
} from 'lucide-vue-next'

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})

const noteCount = computed(() => {
    return props.item.note?.trim() ? 1 : 0
})

const giftCount = computed(() => {
    return (props.item.gifts || []).reduce((total, gift) => {
        return total + Math.max(1, Number(gift.quantity ?? 1))
    }, 0)
})

const warrantyCount = computed(() => {
    return Number(props.item.customer_warranty_days ?? 0) > 0 ? 1 : 0
})

const emit = defineEmits([
    'toggle-note',
    'toggle-promotion',
    'toggle-warranty',
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
                        type="button"
                        @click="
                            emit(
                                'toggle-note'
                            )
                        "
                        class="relative transition"
                        :class="noteCount > 0
                            ? 'text-blue-600 hover:text-blue-700'
                            : 'text-gray-400 hover:text-blue-600'"
                        title="Ghi chú"
                    >
                        <PenSquare
                            class="w-4 h-4"
                        />
                        <span
                            v-if="noteCount"
                            class="absolute -right-2 -top-2 inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-blue-600 px-0.5 text-[9px] font-bold leading-none text-white ring-2 ring-white"
                        >
                            {{ noteCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="
                            emit(
                                'toggle-promotion'
                            )
                        "
                        class="relative transition"
                        :class="giftCount > 0
                            ? 'text-amber-600 hover:text-amber-700'
                            : 'text-gray-400 hover:text-amber-600'"
                        title="Quà tặng và giảm giá"
                    >
                        <Gift
                            class="w-4 h-4"
                        />
                        <span
                            v-if="giftCount"
                            class="absolute -right-2 -top-2 inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-amber-500 px-0.5 text-[9px] font-bold leading-none text-white ring-2 ring-white"
                        >
                            {{ giftCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="emit('toggle-warranty')"
                        class="relative transition"
                        :class="warrantyCount > 0
                            ? 'text-emerald-600 hover:text-emerald-700'
                            : 'text-gray-400 hover:text-emerald-600'"
                        title="Bảo hành"
                    >
                        <ShieldCheck class="w-4 h-4" />
                        <span
                            v-if="warrantyCount"
                            class="absolute -right-2 -top-2 inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-emerald-600 px-0.5 text-[9px] font-bold leading-none text-white ring-2 ring-white"
                        >
                            {{ warrantyCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="
                            emit(
                                'remove'
                            )
                        "
                        class="
                            text-red-400
                            hover:text-red-600
                        "
                        title="Xóa sản phẩm"
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
                v-if="item.variant_label"
                class="
                    text-[11px]
                    text-indigo-600
                    font-medium
                "
            >
                {{ item.variant_label }}
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
