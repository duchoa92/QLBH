<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },

    cart: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'remove',
])

const page = usePage()
const allowsNegativeStock = computed(() => [true, 1, '1', 'true', 'on'].includes(page.props.settings?.allow_negative_stock))
const stockLimit = computed(() => {
    const value = Number(props.item.stock_limit)

    return Number.isFinite(value) ? Math.max(0, Math.floor(value)) : null
})
// stock_limit là giới hạn của đúng dòng này (và với hàng theo lô là của
// đúng lô). Không trừ thêm các dòng khác cùng sản phẩm/biến thể: chúng có
// thể đang thuộc một lô giá khác và đã được giới hạn riêng.
const maximumQuantity = computed(() => stockLimit.value === null
    ? null
    : Math.max(0, stockLimit.value))
const canIncrease = computed(() => !props.item.imei_id
    && (allowsNegativeStock.value || maximumQuantity.value === null || Number(props.item.quantity || 0) < maximumQuantity.value))

const setQuantity = (value) => {
    const normalized = Math.floor(Number(value))
    const requested = Number.isFinite(normalized) ? Math.max(1, normalized) : 1

    if (!allowsNegativeStock.value && maximumQuantity.value !== null && requested > maximumQuantity.value) {
        props.item.quantity = Math.max(1, maximumQuantity.value)
        toast.error(`Sản phẩm ${props.item.name} chỉ còn ${maximumQuantity.value} trong kho`)
        return
    }

    props.item.quantity = requested
}

const increase = () => {
    if (!canIncrease.value) {
        toast.error(`Sản phẩm ${props.item.name} không đủ tồn kho`)
        return
    }

    setQuantity(Number(props.item.quantity || 0) + 1)
}

const handleQuantityInput = event => {
    const requested = Math.floor(Number(event.target.value))

    if (!allowsNegativeStock.value && maximumQuantity.value !== null && requested > maximumQuantity.value) {
        const cappedQuantity = Math.max(1, maximumQuantity.value)
        event.target.value = String(cappedQuantity)
        setQuantity(cappedQuantity)
        return
    }

    setQuantity(event.target.value)
}

const quantity = computed({
    get: () => props.item.quantity,
    set: setQuantity,
})

</script>

<template>

    <div
        class="
            flex
            items-center
            border
            rounded-lg
            overflow-hidden
            h-7
            shrink-0
            bg-white
        "
        :class="{
            'opacity-60':
            item.imei_id
        }"
    >

        <button
            :disabled="item.imei_id"
            class="
                w-6
                h-full
                self-stretch
                bg-slate-50
                border-r
                text-xs
                font-bold
                text-slate-600
                transition-colors
                hover:bg-blue-50
                hover:text-blue-700
            "
            @click="item.quantity > 1 ? setQuantity(item.quantity - 1) : emit('remove')"
        >
            -
        </button>

        <input
            :value="quantity"
            type="number"
            min="1"
            step="1"
            :max="!allowsNegativeStock && maximumQuantity !== null ? Math.max(1, maximumQuantity) : undefined"
            :disabled="item.imei_id"
            @input="handleQuantityInput"
            class="
                quantity-input
                w-12
                h-full
                text-center
                border-none
                bg-white
                px-2
                text-xs
                font-semibold
                outline-none
                focus:outline-none
                focus:ring-0
            "
        >

        <button
            :disabled="item.imei_id || !canIncrease"
            class="
                w-6
                h-full
                self-stretch
                bg-slate-50
                border-l
                text-xs
                font-bold
                text-slate-600
                transition-colors
                hover:bg-blue-50
                hover:text-blue-700
                disabled:cursor-not-allowed
                disabled:bg-slate-100
                disabled:text-slate-300
            "
            @click="increase"
        >
            +
        </button>

    </div>

</template>

<style scoped>
.quantity-input {
    appearance: textfield;
    -moz-appearance: textfield;
}

.quantity-input::-webkit-inner-spin-button,
.quantity-input::-webkit-outer-spin-button {
    margin: 0;
    -webkit-appearance: none;
}
</style>
