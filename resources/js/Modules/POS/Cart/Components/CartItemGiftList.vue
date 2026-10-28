<script setup>
import { computed } from 'vue'
import { Trash2 } from 'lucide-vue-next'

const props = defineProps({
    gifts: { type: Array, default: () => [] },
})

const emit = defineEmits(['remove', 'delete'])
const totalQuantity = computed(() => props.gifts.reduce((sum, gift) => sum + Math.max(1, Number(gift.quantity ?? 1)), 0))

const attributeText = (attribute) => {
    if (attribute === null || attribute === undefined || attribute === '') {
        return ''
    }

    if (typeof attribute !== 'object') {
        return String(attribute)
    }

    return attribute.value
        ?? attribute.label
        ?? attribute.name
        ?? attribute.title
        ?? attribute.text
        ?? ''
}

const variantLabel = (variant) => {
    const attributes = Object.values(variant?.attributes || {})
        .map(attributeText)
        .filter(Boolean)

    return attributes.join(' / ') || variant?.sku || `Biến thể #${variant.id}`
}
</script>

<template>
    <section class="w-full rounded-xl border border-amber-200 bg-amber-50/60 p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <span class="text-xs font-bold text-amber-950">Quà tặng đã chọn</span>
            <span v-if="totalQuantity" class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-amber-800">{{ totalQuantity }} món</span>
        </div>

        <div v-if="gifts.length" class="space-y-2">
            <div v-for="gift in gifts" :key="gift.key || gift.id" class="rounded-lg border border-amber-100 bg-white p-2.5 shadow-sm">
                <div class="flex min-w-0 items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-xs font-semibold leading-5 text-slate-800">{{ gift.name }}</p>
                        <p v-if="gift.sku" class="mt-0.5 text-[10px] text-slate-500">Mã: {{ gift.sku }}</p>
                    </div>
                    <span v-if="!gift.requires_imei" class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">× {{ gift.quantity }}</span>
                    <button v-if="!gift.requires_imei && Number(gift.quantity || 1) > 1" type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-lg font-bold text-slate-600 hover:bg-slate-50" title="Giảm một quà tặng" aria-label="Giảm một quà tặng" @click="emit('remove', gift.key || gift.id)">−</button>
                    <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100" :title="`Xóa ${gift.name} khỏi quà tặng`" :aria-label="`Xóa ${gift.name} khỏi quà tặng`" @click="emit('delete', gift.key || gift.id)">
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
                <select v-if="gift.requires_imei" v-model="gift.imei_id" class="mt-2 h-10 w-full rounded-lg border border-amber-200 bg-white px-2 text-xs font-semibold text-slate-700">
                    <option :value="null">Chọn IMEI cho quà tặng</option>
                    <option v-for="imei in gift.imei_options || []" :key="imei.id" :value="imei.id">{{ imei.display_code }}</option>
                </select>
                <select v-else-if="gift.requires_variant" v-model="gift.variant_id" class="mt-2 h-10 w-full rounded-lg border border-amber-200 bg-white px-2 text-xs font-semibold text-slate-700">
                    <option :value="null">Chọn biến thể quà tặng</option>
                    <option v-for="variant in gift.variant_options || []" :key="variant.id" :value="variant.id">{{ variantLabel(variant) }}</option>
                </select>
            </div>
        </div>
        <p v-else class="rounded-lg border border-dashed border-amber-200 bg-white/70 px-3 py-3 text-center text-xs font-medium text-amber-800">Chưa chọn quà nào</p>
    </section>
</template>
