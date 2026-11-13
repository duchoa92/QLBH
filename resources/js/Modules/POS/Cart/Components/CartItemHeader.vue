<script setup>

import {
    Trash2,
    MessageSquareText,
    Gift,
    BadgePercent,
    ShieldCheck,
    Cpu,
    Layers,
    Receipt,
} from 'lucide-vue-next'

import { computed } from 'vue'

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    showRemove: {
        type: Boolean,
        default: true,
    },
    showUnit: { type: Boolean, default: true },
    clickableVariant: { type: Boolean, default: false },
    clickableImei: { type: Boolean, default: false },
})

const noteCount = computed(() => props.item.note?.trim() ? 1 : 0)
const giftCount = computed(() => (props.item.gifts || []).reduce((total, gift) => total + Math.max(1, Number(gift.quantity ?? 1)), 0) + (Number(props.item.discount_value || 0) > 0 ? 1 : 0))
const warrantyCount = computed(() => Number(props.item.customer_warranty_days ?? props.item.warranty_days ?? 0) > 0 ? 1 : 0)

const emit = defineEmits([
    'edit-note',
    'edit-promotion',
    'edit-warranty',
    'select-variant',
    'select-imei',
    'remove',
])

</script>

<template>

    <div class="flex min-w-0 items-start gap-2">
        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 items-center gap-1.5">
                <div class="flex min-w-0 items-center gap-1"><span class="truncate text-xs font-semibold leading-tight text-slate-900" :title="item.name">{{ item.name }}</span><span v-if="item.is_used" class="inline-flex shrink-0 items-center rounded border border-amber-300 bg-amber-100 px-1 py-0.5 text-[9px] font-bold leading-none text-amber-800">CŨ</span></div>
                <span v-if="item.has_vat" class="inline-flex shrink-0 items-center gap-0.5 rounded bg-emerald-50 px-1 py-0.5 text-[9px] font-bold leading-none text-emerald-700" title="Mặt hàng được đánh dấu có VAT khi nhập hàng"><Receipt class="h-3 w-3" />Có VAT</span>
            </div>
            <div v-if="showUnit" class="mt-0.5 text-[11px] leading-tight text-slate-500">{{ item.unit_name ?? 'Cái' }}</div>

            <button
                v-if="item.is_imei || item.imei"
                type="button"
                class="mt-0.5 flex w-fit max-w-full items-center gap-1 text-left text-[11px] font-semibold leading-tight"
                :class="clickableImei ? 'cursor-pointer rounded-md border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-violet-700 transition hover:border-violet-300 hover:bg-violet-100' : 'cursor-default text-blue-600'"
                :title="clickableImei ? 'Nhấn để đổi IMEI' : undefined"
                @click="clickableImei && emit('select-imei')"
            >
                <span class="truncate">IMEI: {{ item.imei || 'Chọn IMEI' }}</span><Cpu v-if="clickableImei" class="h-3 w-3 shrink-0" aria-hidden="true" />
            </button>

            <button
                v-if="item.variant_label || item.variant_name"
                type="button"
                class="mt-0.5 flex w-fit max-w-full items-center gap-1 text-left text-[11px] font-medium leading-tight"
                :class="clickableVariant ? 'cursor-pointer rounded-md border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-blue-700 transition hover:border-blue-300 hover:bg-blue-100' : 'cursor-default text-slate-400'"
                :title="clickableVariant ? 'Nhấn để đổi biến thể' : undefined"
                @click="clickableVariant && emit('select-variant')"
            >
                <span class="truncate">{{ item.variant_label || item.variant_name }}</span><Layers v-if="clickableVariant" class="h-3 w-3 shrink-0" aria-hidden="true" />
            </button>
        </div>

        <div class="flex shrink-0 items-center gap-0.5 rounded-lg border border-slate-200/80 bg-slate-50 p-0.5">
            <button type="button" @click="emit('edit-note')" class="relative flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-white hover:text-blue-600" :class="noteCount ? 'text-blue-600' : ''" title="Ghi chú" aria-label="Ghi chú">
                <MessageSquareText class="h-4 w-4" />
                <span v-if="noteCount" class="absolute -right-1 -top-1 inline-flex h-3 min-w-3 items-center justify-center rounded-full bg-rose-600 px-0.5 text-[8px] font-bold text-white ring-2 ring-white">{{ noteCount }}</span>
            </button>
            <button type="button" @click="emit('edit-promotion')" class="relative flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-white hover:text-amber-600" :class="giftCount || Number(item.discount_value || 0) ? 'text-amber-600' : ''" title="Quà tặng và giảm giá" aria-label="Quà tặng và giảm giá">
                <BadgePercent v-if="Number(item.discount_value || 0) > 0 && !(item.gifts || []).length" class="h-4 w-4" />
                <Gift v-else class="h-4 w-4" />
                <span v-if="giftCount" class="absolute -right-1 -top-1 inline-flex h-3 min-w-3 items-center justify-center rounded-full bg-rose-600 px-0.5 text-[8px] font-bold text-white ring-2 ring-white">{{ giftCount }}</span>
            </button>
            <button type="button" @click="emit('edit-warranty')" class="relative flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-white hover:text-emerald-600" :class="warrantyCount ? 'text-emerald-600' : ''" title="Bảo hành" aria-label="Bảo hành">
                <ShieldCheck class="h-4 w-4" />
                <span v-if="warrantyCount" class="absolute -right-1 -top-1 inline-flex h-3 min-w-3 items-center justify-center rounded-full bg-rose-600 px-0.5 text-[8px] font-bold text-white ring-2 ring-white">{{ warrantyCount }}</span>
            </button>
            <button v-if="showRemove" type="button" @click="emit('remove')" class="flex h-7 w-7 items-center justify-center rounded-md bg-rose-50 text-rose-500 transition hover:bg-rose-100 hover:text-rose-700" title="Xóa sản phẩm" aria-label="Xóa sản phẩm khỏi giỏ hàng">
                <Trash2 class="h-4 w-4" />
            </button>
        </div>
    </div>

</template>
