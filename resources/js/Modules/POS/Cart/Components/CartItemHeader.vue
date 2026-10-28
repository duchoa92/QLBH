<script setup>

import {
    Trash2,
    PenSquare,
    Gift,
    ShieldCheck,
} from 'lucide-vue-next'

import { computed } from 'vue'

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
})

const noteCount = computed(() => props.item.note?.trim() ? 1 : 0)
const giftCount = computed(() => (props.item.gifts || []).reduce((total, gift) => total + Math.max(1, Number(gift.quantity ?? 1)), 0) + (Number(props.item.discount_value || 0) > 0 ? 1 : 0))
const warrantyCount = computed(() => Number(props.item.customer_warranty_days ?? props.item.warranty_days ?? 0) > 0 ? 1 : 0)

const emit = defineEmits([
    'edit-note',
    'edit-promotion',
    'edit-warranty',
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

                <div class="flex shrink-0 items-center gap-0">
                    <button type="button" @click="emit('edit-note')" class="relative flex h-8 w-8 items-center justify-center rounded-lg transition" :class="noteCount ? 'text-blue-600 hover:bg-blue-50' : 'text-gray-400 hover:bg-blue-50 hover:text-blue-600'" title="Ghi chú" aria-label="Ghi chú">
                        <PenSquare class="h-4 w-4" />
                        <span v-if="noteCount" class="absolute -right-1 -top-1 inline-flex h-3 min-w-3 items-center justify-center rounded-full bg-rose-600 px-0.5 text-[8px] font-bold text-white ring-2 ring-white">{{ noteCount }}</span>
                    </button>
                    <button type="button" @click="emit('edit-promotion')" class="relative flex h-8 w-8 items-center justify-center rounded-lg transition" :class="giftCount || Number(item.discount_value || 0) ? 'text-amber-600 hover:bg-amber-50' : 'text-gray-400 hover:bg-amber-50 hover:text-amber-600'" title="Quà tặng và giảm giá" aria-label="Quà tặng và giảm giá">
                        <Gift class="h-4 w-4" />
                        <span v-if="giftCount" class="absolute -right-1 -top-1 inline-flex h-3 min-w-3 items-center justify-center rounded-full bg-rose-600 px-0.5 text-[8px] font-bold text-white ring-2 ring-white">{{ giftCount }}</span>
                    </button>
                    <button type="button" @click="emit('edit-warranty')" class="relative flex h-8 w-8 items-center justify-center rounded-lg transition" :class="warrantyCount ? 'text-emerald-600 hover:bg-emerald-50' : 'text-gray-400 hover:bg-emerald-50 hover:text-emerald-600'" title="Bảo hành" aria-label="Bảo hành">
                        <ShieldCheck class="h-4 w-4" />
                        <span v-if="warrantyCount" class="absolute -right-1 -top-1 inline-flex h-3 min-w-3 items-center justify-center rounded-full bg-rose-600 px-0.5 text-[8px] font-bold text-white ring-2 ring-white">{{ warrantyCount }}</span>
                    </button>

                    <button
                        type="button"
                        @click="
                            emit(
                                'remove'
                            )
                        "
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 hover:text-rose-700"
                        title="Xóa sản phẩm"
                        aria-label="Xóa sản phẩm khỏi giỏ hàng"
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
                    text-blue-600
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
