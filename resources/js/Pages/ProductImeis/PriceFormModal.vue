<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Calculator, Save, Smartphone } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import { formatCurrency } from '@/utils/format'

const props = defineProps({
    imei: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    sell_price: props.imei.sell_price ?? 0,
})

const costPrice = computed(() => Number(props.imei.cost_price) || 0)
const currentPrice = computed(() => Number(props.imei.sell_price) || 0)
const newPrice = computed(() => Number(form.sell_price) || 0)
const priceDifference = computed(() => newPrice.value - currentPrice.value)
const profit = computed(() => newPrice.value - costPrice.value)
const profitPercent = computed(() => costPrice.value > 0 ? profit.value / costPrice.value * 100 : null)

const suggestPrice = (markup) => {
    if (costPrice.value > 0) form.sell_price = Math.round(costPrice.value * (1 + markup / 100))
}

const submit = () => {
    form.patch(route('product-imeis.update-price', props.imei.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated')
            emit('close')
        },
    })
}
</script>

<template>
    <BaseModal size="md" body-class="p-0" header-class="bg-slate-50 px-5 py-4" @close="emit('close')">
        <template #header>
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-700"><Smartphone :size="20" /></div>
                <div class="min-w-0">
                    <h2 class="text-base font-extrabold text-slate-900">Cập nhật giá bán IMEI</h2>
                    <p class="mt-0.5 truncate font-mono text-xs text-slate-500">SP #{{ imei.product_id }} · {{ imei.imei || imei.serial }}</p>
                </div>
            </div>
            <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-700" aria-label="Đóng" @click="emit('close')">✕</button>
        </template>

        <div class="space-y-5 p-5">
            <div class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs">
                <div><span class="text-slate-500">Giá nhập</span><strong class="mt-1 block text-sm text-slate-900">{{ formatCurrency(costPrice) }}</strong></div>
                <div><span class="text-slate-500">Giá bán hiện tại</span><strong class="mt-1 block text-sm text-slate-600 line-through">{{ formatCurrency(currentPrice) }}</strong></div>
            </div>

            <div>
                <label for="imei-sell-price" class="block text-xs font-bold uppercase tracking-wide text-slate-700">Giá bán mới</label>
                <div class="relative mt-2">
                    <FloatingInput id="imei-sell-price" v-model.number="form.sell_price" type="number" format-thousands :floating-label="false" input-class="!h-14 !w-full !rounded-xl !border-2 !border-emerald-500 !bg-white !px-4 !py-3 !pr-12 !text-xl !font-extrabold !text-emerald-800 focus:!ring-emerald-500/15" />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">đ</span>
                </div>
                <p v-if="form.errors.sell_price" class="mt-1.5 text-xs text-red-600">{{ form.errors.sell_price }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="flex items-center gap-1 text-slate-500"><Calculator :size="14" />Gợi ý theo giá vốn:</span>
                    <button v-for="markup in [30, 50, 100]" :key="markup" type="button" :disabled="costPrice <= 0" class="rounded-md bg-slate-100 px-2.5 py-1.5 font-semibold text-slate-700 hover:bg-emerald-100 hover:text-emerald-800 disabled:cursor-not-allowed disabled:opacity-50" @click="suggestPrice(markup)">+{{ markup }}%</button>
                </div>
            </div>

            <div class="space-y-2 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs">
                <h3 class="font-bold text-emerald-950">Phân tích lợi nhuận</h3>
                <div class="flex items-center justify-between gap-3 text-slate-700"><span>Chênh lệch giá bán</span><strong :class="priceDifference >= 0 ? 'text-emerald-700' : 'text-amber-700'">{{ priceDifference >= 0 ? 'Tăng' : 'Giảm' }} {{ formatCurrency(Math.abs(priceDifference)) }}</strong></div>
                <div class="flex items-center justify-between gap-3 text-slate-700"><span>Lợi nhuận dự kiến</span><strong :class="profit >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatCurrency(profit) }}<template v-if="profitPercent !== null"> ({{ profitPercent.toFixed(1) }}%)</template></strong></div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton variant="secondary" @click="emit('close')">Hủy</ActionButton>
                <ActionButton variant="primary" :disabled="form.processing" @click="submit"><Save :size="16" />{{ form.processing ? 'Đang lưu...' : 'Lưu giá' }}</ActionButton>
            </div>
        </template>
    </BaseModal>
</template>
