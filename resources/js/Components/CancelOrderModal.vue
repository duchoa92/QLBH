<script setup>
// Modal hủy dùng chung (hóa đơn bán / phiếu nhập): chọn sản phẩm + số lượng cần hủy, lý do không bắt buộc.
// Mặc định chọn tất cả với đủ số lượng = hủy toàn bộ đơn. Bỏ chọn/giảm số lượng = hủy một phần.
import { computed, reactive, ref, watch } from 'vue'
import { AlertTriangle, Minus, Plus } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatCurrency } from '@/utils/format'
import { DEFAULT_CANCEL_REASONS } from '@/utils/receiptPrint'

const props = defineProps({
    // 'hóa đơn' | 'phiếu nhập'
    orderLabel: { type: String, default: 'hóa đơn' },
    code: { type: String, default: '' },
    // [{ id, name, subtitle, unit, price, max, editableQty }]
    items: { type: Array, default: () => [] },
    reasons: { type: Array, default: () => DEFAULT_CANCEL_REASONS },
    loading: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    error: { type: String, default: '' },
    layerClass: { type: String, default: 'z-[1000]' },
})
const emit = defineEmits(['close', 'confirm'])

const picked = reactive({})
const reason = ref('')

const reset = () => {
    Object.keys(picked).forEach((key) => delete picked[key])
    props.items.forEach((item) => { picked[item.id] = item.max })
    reason.value = ''
}
watch(() => props.items, reset, { immediate: true })

const hasItems = computed(() => props.items.length > 0)
const pickedRows = computed(() => props.items.filter((item) => (picked[item.id] || 0) > 0))
const isWhole = computed(() => !hasItems.value || props.items.every((item) => (picked[item.id] || 0) >= item.max))
const pickedAmount = computed(() => pickedRows.value.reduce((sum, item) => sum + item.price * picked[item.id], 0))
const canSubmit = computed(() => !props.submitting && !props.loading && (isWhole.value || pickedRows.value.length > 0))

const allChecked = computed(() => hasItems.value && props.items.every((item) => (picked[item.id] || 0) > 0))
const toggleAll = () => {
    const next = !allChecked.value
    props.items.forEach((item) => { picked[item.id] = next ? item.max : 0 })
}
const toggle = (item) => { picked[item.id] = (picked[item.id] || 0) > 0 ? 0 : item.max }
const setQty = (item, value) => {
    const number = Math.round(Number(value))
    picked[item.id] = Number.isFinite(number) ? Math.min(item.max, Math.max(0, number)) : item.max
}

const submit = () => {
    if (!canSubmit.value) return
    emit('confirm', {
        whole: isWhole.value,
        reason: reason.value.trim(),
        items: pickedRows.value.map((item) => ({ id: item.id, quantity: picked[item.id] })),
    })
}

const money = formatCurrency
const title = computed(() => `Hủy ${props.orderLabel}`)
</script>

<template>
    <BaseModal :title="title" size="lg" :layer-class="layerClass" @close="emit('close')">
        <div class="space-y-4 p-1">
            <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5">
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-xs leading-relaxed text-amber-800">
                    <template v-if="isWhole">
                        Hủy <strong>toàn bộ {{ orderLabel }} <span v-if="code">#{{ code }}</span></strong>: tất cả sản phẩm được hoàn kho, tiền và công nợ được hoàn tác.
                    </template>
                    <template v-else>
                        Chỉ hủy <strong>{{ pickedRows.length }}/{{ items.length }} sản phẩm</strong> đã chọn của {{ orderLabel }} <span v-if="code">#{{ code }}</span>:
                        hoàn kho phần hủy, tổng tiền và công nợ được <strong>tính lại</strong>. Phần còn lại giữ nguyên.
                    </template>
                </div>
            </div>

            <div v-if="loading" class="py-6 text-center text-xs text-slate-500">Đang tải danh sách sản phẩm...</div>

            <div v-else-if="hasItems" class="overflow-hidden rounded-xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-700">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500" :checked="allChecked" @change="toggleAll" />
                        Chọn tất cả ({{ items.length }} sản phẩm)
                    </label>
                    <span class="text-[11px] font-semibold text-slate-500">Số lượng hủy</span>
                </div>
                <ul class="max-h-72 divide-y divide-slate-100 overflow-y-auto">
                    <li v-for="item in items" :key="item.id" class="flex items-center gap-3 px-3 py-2.5" :class="(picked[item.id] || 0) > 0 ? 'bg-rose-50/40' : ''">
                        <input type="checkbox" class="h-4 w-4 shrink-0 rounded border-slate-300 text-rose-600 focus:ring-rose-500" :checked="(picked[item.id] || 0) > 0" @change="toggle(item)" />
                        <div class="min-w-0 flex-1 cursor-pointer" @click="toggle(item)">
                            <div class="truncate text-sm font-bold text-slate-800">{{ item.name }}</div>
                            <div class="truncate text-[11px] text-slate-500">
                                <span v-if="item.subtitle">{{ item.subtitle }} · </span>{{ money(item.price) }}<span v-if="item.unit"> / {{ item.unit }}</span>
                            </div>
                        </div>
                        <div v-if="item.editableQty" class="flex shrink-0 items-center gap-1">
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40" :disabled="(picked[item.id] || 0) <= 0" @click="setQty(item, (picked[item.id] || 0) - 1)"><Minus class="h-3.5 w-3.5" /></button>
                            <input type="number" min="0" :max="item.max" :value="picked[item.id]" class="h-7 w-14 rounded-lg border border-slate-200 px-1 text-center text-xs font-bold focus:border-rose-400 focus:outline-none" @input="setQty(item, $event.target.value)" />
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40" :disabled="(picked[item.id] || 0) >= item.max" @click="setQty(item, (picked[item.id] || 0) + 1)"><Plus class="h-3.5 w-3.5" /></button>
                            <span class="w-9 text-[11px] text-slate-400">/ {{ item.max }}</span>
                        </div>
                        <div v-else class="w-24 shrink-0 text-right text-xs font-semibold text-slate-500">{{ item.max }} {{ item.unit }}</div>
                    </li>
                </ul>
                <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                    <span class="font-semibold text-slate-500">Giá trị hàng hủy (chưa tính giảm giá/VAT)</span>
                    <span class="font-extrabold text-rose-700">{{ money(pickedAmount) }}</span>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Lý do hủy <span class="font-normal text-slate-400">(không bắt buộc)</span></label>
                <textarea
                    v-model="reason"
                    rows="2"
                    placeholder="Nhập lý do hoặc chọn gợi ý bên dưới..."
                    class="w-full rounded-xl border border-slate-200 p-3 text-xs outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                ></textarea>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button
                        v-for="(item, index) in reasons"
                        :key="index"
                        type="button"
                        :class="['rounded-lg border px-2.5 py-1 text-xs font-medium transition', reason === item ? 'border-rose-300 bg-rose-50 font-bold text-rose-700' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900']"
                        @click="reason = item"
                    >{{ item }}</button>
                </div>
            </div>

            <p v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{{ error }}</p>

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50" :disabled="submitting" @click="emit('close')">Quay lại</button>
                <button type="button" :disabled="!canSubmit" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50" @click="submit">
                    {{ submitting ? 'Đang hủy...' : (isWhole ? `Xác nhận hủy toàn bộ` : `Hủy ${pickedRows.length} sản phẩm đã chọn`) }}
                </button>
            </div>
        </div>
    </BaseModal>
</template>
