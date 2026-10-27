<script setup>
import { computed } from 'vue'
import FormattedNumberInput from '@/Components/UI/FormattedNumberInput.vue'

const props = defineProps({
    denominations: { type: Array, required: true },
    counts: { type: Object, required: true },
    cashAmount: { type: [Number, String], default: 0 },
    cashDetail: { type: Boolean, default: false },
    balances: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    money: { type: Function, required: true },
    mode: { type: String, default: 'open' },
})
const emit = defineEmits(['update:cashAmount', 'update:cashDetail'])

const detailedCashTotal = computed(() => props.denominations.reduce((sum, value) => sum + Number(value) * Number(props.counts[value] || 0), 0))
const cashTotal = computed(() => props.cashDetail ? detailedCashTotal.value : Number(props.cashAmount || 0))
const bankTotal = computed(() => props.accounts.reduce((sum, account) => sum + Number(props.balances[account.key ?? account.shift_key] || 0), 0))
const grandTotal = computed(() => cashTotal.value + bankTotal.value)
const accountKey = account => account.key ?? account.shift_key
const selectInputValue = event => event.target.select()
const focusNext = event => {
    event.preventDefault()
    const inputs = [...document.querySelectorAll('[data-work-shift-count]')]
    const next = inputs.indexOf(event.currentTarget) + 1
    if (inputs[next]) inputs[next].focus()
}
</script>

<template>
    <div class="grid min-h-0 gap-4 lg:grid-cols-2">
        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-800">Tiền mặt theo mệnh giá</h3>
            <label class="mb-2 flex items-center gap-2 px-1 text-xs font-medium text-slate-600">
                <input type="checkbox" :checked="cashDetail" class="h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500" @change="emit('update:cashDetail', $event.target.checked)" />
                Nhập chi tiết theo mệnh giá
            </label>
            <label v-if="!cashDetail" class="block text-xs font-semibold text-slate-600">
                Tổng tiền mặt {{ mode === 'close' ? 'kiểm đếm cuối ca' : 'đầu ca' }}
                <FormattedNumberInput :model-value="cashAmount" class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-right text-sm" @focus="selectInputValue" @update:model-value="emit('update:cashAmount', $event)" />
            </label>
            <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <table class="no-grid w-full text-left text-sm">
                <thead class="border-b-2 border-indigo-700 bg-indigo-600 text-[11px] font-extrabold uppercase tracking-wide text-white"><tr><th class="border-r border-indigo-500 px-2 py-2">Mệnh giá</th><th class="border-r border-indigo-500 px-2 py-2">Số lượng</th><th class="px-2 py-2 text-right">Thành tiền</th></tr></thead>
                <tbody class="divide-y divide-slate-200"><tr v-for="value in denominations" :key="value">
                    <td class="px-2 py-1.5 font-semibold text-slate-700">{{ money(value) }}</td>
                    <td class="px-2 py-1"><input v-model.number="counts[value]" data-work-shift-count type="text" inputmode="numeric" class="w-20 rounded-lg border-slate-300 px-2 py-1 text-right text-sm" @focus="selectInputValue" @keydown.enter="focusNext" /></td>
                    <td class="px-2 py-1.5 text-right text-slate-600">{{ money(value * Number(counts[value] || 0)) }}</td>
                </tr></tbody>
              </table>
            </div>
        </div>
        <div class="space-y-2">
            <h3 class="text-sm font-bold text-slate-800">Số dư tài khoản ngân hàng {{ mode === 'close' ? 'cuối ca' : 'đầu ca' }}</h3>
            <div v-if="accounts.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <table class="no-grid w-full text-left text-sm">
                <thead class="border-b-2 border-indigo-700 bg-indigo-600 text-[11px] font-extrabold uppercase tracking-wide text-white"><tr><th class="border-r border-indigo-500 px-2 py-2">Tài khoản</th><th class="border-r border-indigo-500 px-2 py-2">Chủ tài khoản</th><th class="px-2 py-2 text-right">Số dư</th></tr></thead>
                <tbody class="divide-y divide-slate-200"><tr v-for="account in accounts" :key="accountKey(account)">
                    <td class="px-2 py-2"><strong>{{ account.bank_name || `BIN ${account.bank_bin}` }}</strong><div class="text-xs text-slate-500">{{ account.bank_account }}</div></td>
                    <td class="px-2 py-2 text-xs text-slate-600">{{ account.bank_account_name || '—' }}</td>
                    <td class="px-2 py-2 text-right">
                        <FormattedNumberInput :model-value="balances[accountKey(account)]" data-work-shift-count class="w-28 rounded-lg border-slate-300 px-2 py-1 text-right text-sm" @focus="selectInputValue" @keydown.enter="focusNext" @update:model-value="balances[accountKey(account)] = $event" />
                    </td>
                </tr></tbody>
              </table>
            </div>
            <p v-else class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Chưa cấu hình tài khoản ngân hàng.</p>
            <div v-if="mode !== 'close'" class="grid gap-2 pt-1">
                <p class="flex items-center justify-between gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm"><span class="font-medium text-indigo-700">Tổng tiền mặt {{ mode === 'close' ? 'kiểm đếm' : 'đầu ca' }}</span><strong class="text-indigo-900">{{ money(cashTotal) }}</strong></p>
                <p class="flex items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm"><span class="font-medium text-blue-700">Tổng tiền tài khoản</span><strong class="text-blue-900">{{ money(bankTotal) }}</strong></p>
                <p class="flex items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm"><span class="font-bold text-emerald-800">Tổng tiền mặt + tài khoản</span><strong class="text-emerald-900">{{ money(grandTotal) }}</strong></p>
            </div>
        </div>
    </div>
</template>
