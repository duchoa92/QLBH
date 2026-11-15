<script setup>
import { computed } from 'vue'
import { ScanFace } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'

/**
 * Hộp thông báo chi tiết khi POS nhận ra khách bằng khuôn mặt (bật/tắt ở Cài đặt → Khách hàng).
 * Vẫn chỉ là GỢI Ý: nhân viên bấm "Đúng, chọn khách này" mới gán khách vào đơn.
 */
const props = defineProps({
    suggestions: { type: Array, required: true },
})
const emit = defineEmits(['confirm', 'dismiss', 'dismiss-all'])

const many = computed(() => props.suggestions.length > 1)
const hasDebt = (item) => Number(item.customer?.debt_balance || 0) > 0

// Chỉ hiện 4 số cuối CCCD để tránh lộ thông tin cá nhân trên màn hình quầy.
const maskCccd = (value) => {
    const text = String(value || '')
    return text.length > 4 ? `${'•'.repeat(text.length - 4)}${text.slice(-4)}` : text
}
</script>

<template>
    <BaseModal
        :title="many ? `Nhận ra ${suggestions.length} khách trước camera` : 'Nhận ra khách hàng'"
        size="md"
        body-class="p-3 sm:p-4"
        @close="emit('dismiss-all')"
    >
        <p class="mb-3 text-xs text-slate-500">
            {{ many ? 'Chọn đúng người đang thanh toán.' : 'Kiểm tra thông tin rồi xác nhận đúng người đang thanh toán.' }}
        </p>

        <div class="space-y-2.5">
            <div
                v-for="item in suggestions"
                :key="item.trackId"
                class="rounded-xl border border-slate-200 bg-slate-50/60 p-3"
            >
                <div class="flex items-start gap-3">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-2 ring-blue-200">
                        <img v-if="item.thumb" :src="item.thumb" alt="" class="h-full w-full -scale-x-100 object-cover">
                        <ScanFace v-else class="h-6 w-6 text-blue-500" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="min-w-0 break-words text-base font-bold text-slate-900">{{ item.customer.full_name }}</span>
                            <span
                                class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                :class="hasDebt(item) ? 'bg-rose-500' : 'bg-emerald-500'"
                                :title="hasDebt(item) ? 'Đang có công nợ' : 'Không có công nợ'"
                            ></span>
                        </div>
                        <dl class="mt-1.5 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                            <dt class="text-slate-400">SĐT</dt>
                            <dd class="font-semibold text-slate-700">{{ item.customer.phone || 'Chưa có' }}</dd>
                            <template v-if="item.customer.cccd">
                                <dt class="text-slate-400">CCCD</dt>
                                <dd class="font-mono font-semibold text-slate-700">{{ maskCccd(item.customer.cccd) }}</dd>
                            </template>
                            <dt class="text-slate-400">Công nợ</dt>
                            <dd class="font-bold" :class="hasDebt(item) ? 'text-rose-600' : 'text-emerald-600'">
                                {{ hasDebt(item) ? $money(item.customer.debt_balance) : 'Không nợ' }}
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        class="min-h-11 flex-1 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 active:bg-blue-800"
                        @click="emit('confirm', item)"
                    >
                        {{ many ? 'Chọn khách này' : 'Đúng, chọn khách này' }}
                    </button>
                    <button
                        type="button"
                        class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        @click="emit('dismiss', item)"
                    >
                        Không phải
                    </button>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end">
                <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-500 hover:bg-white hover:text-slate-700" @click="emit('dismiss-all')">
                    {{ many ? 'Không ai cả' : 'Đóng' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>
