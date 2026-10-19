<script setup>
import { computed } from 'vue'
import CartItemHeader from './CartItemHeader.vue'
import CartItemQuantity from './CartItemQuantity.vue'
import CartItemGiftList from './CartItemGiftList.vue'
import {useCartItem,} from '../Composables/useCartItem'
import CartItemPrice from './CartItemPrice.vue'
import CartItemNote from './CartItemNote.vue'
import CartItemPromotion from './CartItemPromotion.vue'
import { useCartGift, } from '../Composables/useCartGift'
import { useCartDiscount, } from '../Composables/useCartDiscount'


const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})

const defaultWarrantyDays = computed(() => {
    return Number(props.item.customer_warranty_days ?? props.item.warranty_days ?? 0)
})

const warrantyUnit = computed({
    get: () => {
        if (props.item.customer_warranty_unit) {
            return props.item.customer_warranty_unit
        }

        return defaultWarrantyDays.value > 0 && defaultWarrantyDays.value % 30 === 0
            ? 'month'
            : 'day'
    },
    set: (value) => {
        const previousUnit = props.item.customer_warranty_unit || (
            defaultWarrantyDays.value > 0 && defaultWarrantyDays.value % 30 === 0
                ? 'month'
                : 'day'
        )
        const currentValue = props.item.customer_warranty_value ?? (
            defaultWarrantyDays.value / (previousUnit === 'month' ? 30 : 1)
        )

        props.item.customer_warranty_unit = value
        props.item.customer_warranty_value = currentValue
        props.item.customer_warranty_days = Math.max(
            0,
            Math.min(3650, Number(currentValue) * warrantyMultiplier.value)
        )
    },
})

const warrantyMultiplier = computed(() => {
    return warrantyUnit.value === 'month' ? 30 : 1
})

const maxWarrantyValue = computed(() => {
    return warrantyUnit.value === 'month' ? Math.floor(3650 / 30) : 3650
})

const warrantyDays = computed({
    get: () => {
        const value = props.item.customer_warranty_value
        if (value !== undefined && value !== null) return Number(value)

        const days = defaultWarrantyDays.value
        return days > 0 ? days / warrantyMultiplier.value : ''
    },
    set: (value) => {
        const normalized = value === ''
            ? null
            : Math.max(0, Math.min(maxWarrantyValue.value, Number(value || 0)))

        props.item.customer_warranty_value = normalized
        props.item.customer_warranty_days = normalized === null
            ? null
            : normalized * warrantyMultiplier.value
    },
})

const emit = defineEmits([
    'remove',
])

const {

    toggleNote,

    togglePromotion,

    toggleWarranty,

} = useCartItem()

const {

    searchGiftProducts,

    selectGiftProduct,

    removeGift,

} = useCartGift()

const {

    lineTotal,

    normalizeDiscount,

} = useCartDiscount()


</script>

<template>
    <div
        class="bg-white border border-gray-200 rounded-xl px-3 py-2 mb-1 shadow-sm"
    >
        <!--Thông tin SP, và Chức năng-->
        <CartItemHeader

            :item="item"

            @toggle-note="
                toggleNote(item)
            "

            @toggle-promotion="
                togglePromotion(item)
            "

            @toggle-warranty="
                toggleWarranty(item)
            "

            @remove="
                emit(
                    'remove',
                    item
                )
            "
        />

        <div v-if="item.showWarranty" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-emerald-100 bg-emerald-50/60 px-2.5 py-2">
            <span class="text-[11px] font-semibold text-emerald-900">Bảo hành</span>

            <div class="inline-flex h-8 overflow-hidden rounded-md border border-emerald-200 bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                <input
                    :id="`pos-warranty-${item.imei_id || item.imei || item.serial || item.id}`"
                    v-model.number="warrantyDays"
                    type="number"
                    min="1"
                    :max="maxWarrantyValue"
                    step="1"
                    class="h-full w-20 border-0 bg-transparent px-2 text-center text-xs font-semibold text-slate-800 focus:ring-0"
                    aria-label="Thời hạn bảo hành"
                />

                <select
                    v-model="warrantyUnit"
                    class="h-full border-0 border-l border-emerald-200 bg-emerald-50 py-0 pl-2 pr-7 text-xs font-semibold text-slate-800 focus:ring-0"
                    aria-label="Đơn vị thời hạn bảo hành"
                >
                    <option value="month">Tháng</option>
                    <option value="day">Ngày</option>
                </select>
            </div>

            <span class="text-[10px] text-slate-500">
                <template v-if="Number(item.customer_warranty_days ?? 0) > 0">
                    Tương đương {{ item.customer_warranty_days ?? 0 }} ngày
                </template>
                <template v-else>
                    Để trống nếu không bảo hành
                </template>
            </span>
        </div>

        <!--Số lượng và tiền-->
        <div class="flex justify-between items-start mt-2">
            <div class="flex items-center gap-3">
                <CartItemQuantity
                    :item="item"
                    @remove="emit('remove', item)"
                />
            </div>

            <!--giảm giá-->
            <CartItemPrice
                :item="item"
            />
                
        </div>

        <CartItemGiftList
            v-if="item.gifts && item.gifts.length"
            :gifts="item.gifts"
            @remove="removeGift(item, $event)"
        />

        <!--nơi hiện Ghi chú-->
        <CartItemNote
            :item="item"
        />
        <!--nơi nhập quà tặng và giảm giá-->
        <CartItemPromotion
            v-if="item.showPromotion"
            :item="item"
            :searchGiftProducts="searchGiftProducts"
            :selectGiftProduct="selectGiftProduct"
            :normalizeDiscount="normalizeDiscount"
        />
        

    </div>
</template>
