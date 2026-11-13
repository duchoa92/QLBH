<script setup>

import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Receipt } from 'lucide-vue-next'

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})


// Hàng cũ chỉ được chọn VAT khi cửa hàng bật tính VAT cho hàng cũ.
const page = usePage()
const vatLocked = computed(() => Boolean(props.item.is_used) && !page.props.settings?.pos_vat_used_enabled)

const lineTotal = computed(() => {

    const total =

        Number(props.item.price)
        *
        Number(props.item.quantity)

    if (
        props.item.discount_type === 'percent'
    ) {

        return total -
            (
                total *
                Number(
                    props.item.discount_value
                )
                / 100
            )
    }

    if (
        props.item.discount_type === 'amount'
    ) {

        return total -
            Number(
                props.item.discount_value
            )
    }

    return total
})

const originalTotal = computed(() =>
    Number(props.item.price) * Number(props.item.quantity)
)

const discountAmount = computed(() => {
    if (props.item.discount_type === 'percent') {
        return originalTotal.value * Number(props.item.discount_value || 0) / 100
    }
    if (props.item.discount_type === 'amount') {
        return Number(props.item.discount_value || 0)
    }
    return 0
})

const hasDiscount = computed(() =>
    Number(props.item.discount_value || 0) > 0
)

</script>

<template>

    <div class="flex min-w-0 shrink-0 flex-col items-end gap-0.5 text-right">
        <div class="flex items-center justify-end gap-1.5">
            <button type="button" role="checkbox" :aria-checked="Boolean(item.apply_vat)" :disabled="vatLocked" class="inline-flex h-5 items-center gap-0.5 rounded px-1 text-[9px] font-bold leading-none transition disabled:cursor-not-allowed disabled:opacity-40" :class="item.apply_vat ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'" :title="vatLocked ? 'Sản phẩm cũ không áp dụng VAT' : (item.apply_vat ? 'Bỏ tính VAT cho sản phẩm này' : 'Tính VAT cho sản phẩm này')" @click="!vatLocked && (item.apply_vat = !item.apply_vat)"><Receipt class="h-3 w-3" />VAT</button>
            <strong class="text-base font-extrabold leading-tight text-green-700">{{ $money(lineTotal) }}</strong>
        </div>

        <div v-if="hasDiscount" class="flex items-center justify-end gap-1 text-[11px] leading-tight">
            <span class="text-slate-400 line-through">{{ $money(originalTotal) }}</span>
            <span class="font-medium text-rose-600">−{{ $money(discountAmount) }}</span>
            <button type="button" @click="item.discount_value = 0" title="Hủy giảm giá" aria-label="Hủy giảm giá" class="ml-0.5 font-bold text-rose-500 hover:text-rose-700">×</button>
        </div>
        <div v-else-if="item.quantity > 1" class="text-[10px] leading-tight text-slate-500">
            {{ item.quantity }} × {{ $money(item.price) }}
        </div>
    </div>

</template>
