<script setup>
import { computed, ref } from 'vue'
import { BadgeCheck, Gift, MessageSquareText, X } from 'lucide-vue-next'
import CartItemHeader from './CartItemHeader.vue'
import CartItemQuantity from './CartItemQuantity.vue'
import CartItemGiftList from './CartItemGiftList.vue'
import CartItemPrice from './CartItemPrice.vue'
import CartItemPromotion from './CartItemPromotion.vue'
import { useCartGift } from '../Composables/useCartGift'
import { useCartDiscount } from '../Composables/useCartDiscount'

const props = defineProps({ item: { type: Object, required: true }, editorOnly: { type: Boolean, default: false }, showRemove: { type: Boolean, default: true }, clickableVariant: { type: Boolean, default: false }, clickableImei: { type: Boolean, default: false } })
const emit = defineEmits(['remove', 'editor-saved', 'select-variant', 'select-imei'])
const activeEditor = ref(null)
const draft = ref(null)
const promotionError = ref('')

const cloneItemForEditing = () => ({
    ...props.item,
    gifts: (props.item.gifts || []).map(gift => ({
        ...gift,
        imei_options: [...(gift.imei_options || [])],
        variant_options: [...(gift.variant_options || [])],
    })),
    gift_keyword: '',
    gift_results: [],
})

const openEditor = type => {
    const nextDraft = cloneItemForEditing()
    if (type === 'warranty') {
        const days = Number(props.item.customer_warranty_days ?? props.item.warranty_days ?? 0)
        const unit = props.item.customer_warranty_unit || (days > 0 && days % 30 === 0 ? 'month' : 'day')
        nextDraft.customer_warranty_unit = unit
        nextDraft.customer_warranty_value = props.item.customer_warranty_value ?? (days > 0 ? days / (unit === 'month' ? 30 : 1) : '')
        nextDraft.customer_warranty_days = days
    }
    if (type === 'promotion') nextDraft.showPromotion = true
    draft.value = nextDraft
    promotionError.value = ''
    activeEditor.value = type
}

const closeEditor = () => {
    activeEditor.value = null
    draft.value = null
}

const warrantyMultiplier = computed(() => draft.value?.customer_warranty_unit === 'month' ? 30 : 1)
const maxWarrantyValue = computed(() => draft.value?.customer_warranty_unit === 'month' ? Math.floor(3650 / 30) : 3650)
const warrantyValue = computed({
    get: () => draft.value?.customer_warranty_value ?? '',
    set: value => {
        if (!draft.value) return
        const normalized = value === '' ? null : Math.max(0, Math.min(maxWarrantyValue.value, Number(value || 0)))
        draft.value.customer_warranty_value = normalized
        draft.value.customer_warranty_days = normalized === null ? null : normalized * warrantyMultiplier.value
    },
})
const warrantyUnit = computed({
    get: () => draft.value?.customer_warranty_unit || 'day',
    set: value => {
        if (!draft.value) return
        const oldMultiplier = warrantyMultiplier.value
        const currentDays = Number(draft.value.customer_warranty_value || 0) * oldMultiplier
        draft.value.customer_warranty_unit = value
        draft.value.customer_warranty_value = currentDays ? currentDays / (value === 'month' ? 30 : 1) : ''
        draft.value.customer_warranty_days = currentDays || null
    },
})

const setQuickWarranty = months => {
    warrantyUnit.value = 'month'
    warrantyValue.value = months
}

const hasWarrantyDetails = computed(() => {
    return Boolean(
        draft.value?.customer_warranty_days
        || draft.value?.customer_warranty_note?.trim()
    )
})
const hasNote = computed(() => Boolean(draft.value?.note?.trim()))
const hasGifts = computed(() => (draft.value?.gifts || []).length > 0)
const hasPromotionDetails = computed(() => {
    return hasGifts.value || Number(draft.value?.discount_value || 0) > 0
})
const incompleteGift = computed(() => (draft.value?.gifts || []).find(gift => {
    return (gift.requires_imei && !gift.imei_id)
        || (gift.requires_variant && !gift.variant_id)
}))

const { searchGiftProducts, selectGiftProduct, removeGift, deleteGift } = useCartGift()
const { normalizeDiscount } = useCartDiscount()

const saveEditor = () => {
    if (!draft.value) return
    const savedEditor = activeEditor.value
    if (activeEditor.value === 'promotion' && incompleteGift.value) {
        promotionError.value = incompleteGift.value.requires_imei
            ? `Vui lòng chọn IMEI cho quà tặng ${incompleteGift.value.name}.`
            : `Vui lòng chọn biến thể cho quà tặng ${incompleteGift.value.name}.`
        return
    }

    promotionError.value = ''
    if (activeEditor.value === 'note') {
        props.item.note = draft.value.note || ''
    } else if (activeEditor.value === 'warranty') {
        props.item.customer_warranty_days = draft.value.customer_warranty_days
        props.item.customer_warranty_value = draft.value.customer_warranty_value
        props.item.customer_warranty_unit = draft.value.customer_warranty_unit
        props.item.customer_warranty_note = draft.value.customer_warranty_note?.trim() || null
    } else if (activeEditor.value === 'promotion') {
        props.item.gifts = draft.value.gifts.map(gift => ({
            ...gift,
            imei_options: [...(gift.imei_options || [])],
            variant_options: [...(gift.variant_options || [])],
        }))
        props.item.discount_type = draft.value.discount_type
        props.item.discount_value = draft.value.discount_value
    }
    emit('editor-saved', { item: props.item, editor: savedEditor })
    closeEditor()
}

const clearWarranty = () => {
    if (!draft.value) return

    draft.value.customer_warranty_days = 0
    draft.value.customer_warranty_value = null
    draft.value.customer_warranty_unit = null
    draft.value.customer_warranty_note = null
    saveEditor()
}

const clearNote = () => {
    if (!draft.value) return

    draft.value.note = ''
    saveEditor()
}

const clearPromotion = () => {
    if (!draft.value) return

    draft.value.gifts = []
    draft.value.discount_type = null
    draft.value.discount_value = 0
    draft.value.gift_keyword = ''
    draft.value.gift_results = []
    promotionError.value = ''
    saveEditor()
}

const editorTitle = computed(() => ({
    note: 'Ghi chú sản phẩm',
    promotion: 'Quà tặng và giảm giá',
    warranty: 'Thời hạn bảo hành',
}[activeEditor.value] || ''))
</script>

<template>
    <div :class="editorOnly ? 'min-w-0' : 'mb-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm transition-shadow hover:shadow-md'">
        <CartItemHeader
            :item="item"
            :show-remove="showRemove"
            :show-unit="false"
            :clickable-variant="clickableVariant"
            :clickable-imei="clickableImei"
            @edit-note="openEditor('note')"
            @edit-promotion="openEditor('promotion')"
            @edit-warranty="openEditor('warranty')"
            @select-variant="emit('select-variant')"
            @select-imei="emit('select-imei')"
            @remove="emit('remove', item)"
        />

        <div v-if="!editorOnly" class="mt-2 flex items-center justify-between gap-3 border-t border-slate-100 pt-1.5">
            <CartItemQuantity :item="item" @remove="emit('remove', item)" />
            <CartItemPrice :item="item" />
        </div>

        <Teleport to="body">
        <div v-if="activeEditor" class="fixed inset-0 z-[10000] flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm sm:p-4" style="padding-top: max(12px, env(safe-area-inset-top)); padding-bottom: max(12px, env(safe-area-inset-bottom));">
            <section role="dialog" aria-modal="true" :aria-label="editorTitle" class="relative flex max-h-[calc(100dvh-24px)] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" style="max-height: calc(100dvh - env(safe-area-inset-top) - env(safe-area-inset-bottom) - 24px);">
                <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50/70 px-4 py-3 pr-12 sm:px-5 sm:pr-5">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" :class="activeEditor === 'note' ? 'bg-blue-50 text-blue-600' : activeEditor === 'promotion' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600'">
                            <MessageSquareText v-if="activeEditor === 'note'" class="h-5 w-5" />
                            <Gift v-else-if="activeEditor === 'promotion'" class="h-5 w-5" />
                            <BadgeCheck v-else class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-slate-900">{{ editorTitle }}</h3>
                            <p class="truncate text-xs text-slate-500">{{ item.name }}</p>
                        </div>
                    </div>
                    <button type="button" class="absolute right-2 top-2 z-10 rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 sm:static sm:ml-2" aria-label="Đóng" @click="closeEditor"><X class="h-5 w-5" /></button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-5">
                    <div v-if="activeEditor === 'note'" class="space-y-3">
                        <textarea v-model="draft.note" rows="4" autofocus placeholder="Nhập ghi chú cho sản phẩm..." class="w-full rounded-xl border border-slate-300 p-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                    </div>

                    <div v-else-if="activeEditor === 'warranty'" class="space-y-4">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3.5">
                            <div class="flex items-center justify-between gap-3">
                                <label class="text-xs font-bold text-emerald-950">Thời hạn bảo hành cho khách</label>
                                <span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="draft.customer_warranty_days ? 'bg-emerald-100 text-emerald-800' : 'bg-white text-slate-500'">{{ draft.customer_warranty_days ? `${draft.customer_warranty_days} ngày` : 'Chưa thiết lập' }}</span>
                            </div>
                            <div class="mt-2 flex h-11 overflow-hidden rounded-lg border border-emerald-200 bg-white shadow-sm">
                                <input v-model.number="warrantyValue" type="number" min="1" :max="maxWarrantyValue" step="1" placeholder="0" aria-label="Thời hạn bảo hành" class="min-w-0 flex-1 border-0 bg-white px-3 text-base font-bold text-slate-800 outline-none placeholder:font-medium placeholder:text-slate-400 focus:ring-0" />
                                <select v-model="warrantyUnit" aria-label="Đơn vị thời hạn bảo hành" class="w-24 border-0 border-l border-emerald-100 bg-emerald-50 px-2 text-sm font-semibold text-emerald-900 outline-none focus:ring-0"><option value="month">Tháng</option><option value="day">Ngày</option></select>
                            </div>
                            <p class="mt-2 text-[11px] font-medium text-emerald-800/80">{{ draft.customer_warranty_days ? `Tương đương ${draft.customer_warranty_days} ngày bảo hành.` : 'Nhập số lượng và chọn đơn vị áp dụng.' }}</p>
                        </div>

                        <div class="grid grid-cols-4 gap-1.5">
                            <button v-for="months in [1, 6, 12, 24]" :key="months" type="button" class="rounded-lg border px-2 py-2 text-xs font-semibold transition" :class="warrantyUnit === 'month' && Number(warrantyValue) === months ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-emerald-200 hover:bg-emerald-50'" @click="setQuickWarranty(months)">{{ months }} Tháng</button>
                        </div>

                        <div>
                            <label for="pos-warranty-note" class="mb-1.5 block text-xs font-bold text-slate-700">Ghi chú bảo hành</label>
                            <textarea id="pos-warranty-note" v-model="draft.customer_warranty_note" rows="3" maxlength="1000" placeholder="Ví dụ: Không áp dụng với rơi vỡ, vào nước..." class="w-full resize-none rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                        </div>
                    </div>

                    <div v-else-if="activeEditor === 'promotion'" class="space-y-3">
                        <CartItemPromotion :item="draft" :search-gift-products="searchGiftProducts" :select-gift-product="selectGiftProduct" :normalize-discount="normalizeDiscount" />
                        <p v-if="promotionError" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">{{ promotionError }}</p>
                        <CartItemGiftList :gifts="draft.gifts" @remove="removeGift(draft, $event)" @delete="deleteGift(draft, $event)" />
                    </div>
                </div>

                <footer class="flex gap-2 border-t border-slate-200 bg-slate-50/70 p-3 sm:px-5">
                    <button v-if="activeEditor === 'warranty'" type="button" class="flex-1 rounded-xl border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400" :disabled="!hasWarrantyDetails" @click="clearWarranty"><X class="mr-1 inline h-4 w-4" />Xóa bảo hành</button>
                    <button v-else-if="activeEditor === 'note'" type="button" class="flex-1 rounded-xl border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400" :disabled="!hasNote" @click="clearNote"><X class="mr-1 inline h-4 w-4" />Xóa ghi chú</button>
                    <button v-else-if="activeEditor === 'promotion'" type="button" class="flex-1 rounded-xl border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400" :disabled="!hasPromotionDetails" @click="clearPromotion"><X class="mr-1 inline h-4 w-4" />Xóa</button>
                    <button v-else type="button" class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="closeEditor">Hủy</button>
                    <button type="button" class="flex-1 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-md shadow-blue-200 hover:bg-blue-700" @click="saveEditor">Lưu</button>
                </footer>
            </section>
        </div>
        </Teleport>
    </div>
</template>
