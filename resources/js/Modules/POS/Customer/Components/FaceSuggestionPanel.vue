<script setup>
import { ScanFace } from 'lucide-vue-next'

/**
 * Bước "hỏi xác nhận" của nhận diện khuôn mặt, nằm ngay trong ô nhập khách:
 * một người thì hỏi "Có phải ...?", nhiều người thì liệt kê để nhân viên chọn đúng người đang thanh toán.
 */
defineProps({
    suggestions: { type: Array, required: true },
})
defineEmits(['confirm', 'dismiss', 'dismiss-all'])

const hasDebt = (item) => Number(item.customer?.debt_balance || 0) > 0
</script>

<template>
    <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-1.5 ring-1 ring-blue-100" role="alertdialog" aria-live="polite">
        <div
            v-for="item in suggestions"
            :key="item.trackId"
            class="flex flex-wrap items-center gap-x-2 gap-y-1.5 rounded-lg px-1 py-1 sm:flex-nowrap"
            :class="suggestions.length > 1 ? 'bg-white/70' : ''"
        >
            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-1 ring-blue-200">
                <img v-if="item.thumb" :src="item.thumb" alt="" class="h-full w-full -scale-x-100 object-cover">
                <ScanFace v-else class="h-4 w-4 text-blue-500" />
            </span>

            <div class="min-w-0 flex-1 basis-[10rem] text-xs leading-tight text-slate-700">
                <template v-if="suggestions.length === 1">
                    Có phải <strong class="text-slate-900">{{ item.customer.full_name }}</strong>
                    <span
                        class="ml-0.5 inline-block h-2 w-2 rounded-full align-middle"
                        :class="hasDebt(item) ? 'bg-rose-500' : 'bg-emerald-500'"
                        :title="hasDebt(item) ? 'Đang có công nợ' : 'Không có công nợ'"
                        role="img"
                        :aria-label="hasDebt(item) ? 'Đang có công nợ' : 'Không có công nợ'"
                    ></span>
                    <span v-if="item.customer.phone" class="text-slate-500"> ({{ item.customer.phone }})</span>?
                </template>
                <template v-else>
                    <div class="flex items-center gap-1.5 font-bold text-slate-900">
                        <span class="truncate">{{ item.customer.full_name }}</span>
                        <span
                            class="inline-block h-2 w-2 shrink-0 rounded-full"
                            :class="hasDebt(item) ? 'bg-rose-500' : 'bg-emerald-500'"
                            :title="hasDebt(item) ? 'Đang có công nợ' : 'Không có công nợ'"
                            role="img"
                            :aria-label="hasDebt(item) ? 'Đang có công nợ' : 'Không có công nợ'"
                        ></span>
                    </div>
                    <div class="truncate text-[11px] text-slate-500">{{ item.customer.phone || 'Chưa có SĐT' }}</div>
                </template>
            </div>

            <button type="button" class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700 sm:py-1" @click="$emit('confirm', item)">
                {{ suggestions.length === 1 ? 'Đúng' : 'Chọn' }}
            </button>
            <button
                v-if="suggestions.length === 1"
                type="button"
                class="shrink-0 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-500 hover:bg-white sm:py-1"
                @click="$emit('dismiss', item)"
            >
                Không
            </button>
            <button
                v-else
                type="button"
                class="shrink-0 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-400 hover:bg-white hover:text-slate-600 sm:px-1.5 sm:py-1"
                title="Không phải người này"
                aria-label="Không phải người này"
                @click="$emit('dismiss', item)"
            >
                ✕
            </button>
        </div>

        <div v-if="suggestions.length > 1" class="flex items-center justify-between gap-2 px-1.5 pb-0.5 pt-1 text-[11px] text-slate-500">
            <span>Có {{ suggestions.length }} khách trước camera, chọn đúng người đang thanh toán.</span>
            <button type="button" class="shrink-0 font-semibold text-slate-500 hover:text-slate-700" @click="$emit('dismiss-all')">Không ai cả</button>
        </div>
    </div>
</template>
