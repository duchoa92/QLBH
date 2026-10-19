<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { toast } from 'vue-sonner'

const page = usePage()
const denominations = [500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000]
const visible = ref(false)
const saving = ref(false)
const counts = ref({})
const balances = ref({})
const values = computed(() => {
    const accounts = page.props.settings?.bank_accounts || []
    if (accounts.length) return accounts.map(account => ({ ...account, key: String(account.id || account.bank_account) }))
    const settings = page.props.settings || {}
    return settings.bank_bin && settings.bank_account
        ? [{ key: 'default', bank_name: `BIN ${settings.bank_bin}`, bank_account: settings.bank_account, bank_account_name: settings.bank_account_name }]
        : []
})
const total = computed(() => denominations.reduce((sum, value) => sum + value * Number(counts.value[value] || 0), 0))
const bankTotal = computed(() => values.value.reduce((sum, account) => sum + Number(balances.value[account.key] || 0), 0))
const money = value => `${Number(value || 0).toLocaleString('vi-VN')} ₫`
const selectInputValue = event => event.target.select()

const openIfNeeded = async () => {
    if (!(page.props.auth?.permissions || []).includes('pos.access')) return
    try {
        const { data } = await axios.get(route('pos.shifts.current'))
        if (!data.shift) {
            counts.value = Object.fromEntries(denominations.map(value => [value, 0]))
            balances.value = Object.fromEntries(values.value.map(account => [account.key, 0]))
            visible.value = true
        }
    } catch (error) {
        // The POS page owns the actionable error state; do not interrupt other screens.
    }
}

onMounted(openIfNeeded)

const focusNext = event => {
    event.preventDefault()
    const inputs = [...document.querySelectorAll('[data-auto-shift-count]')]
    const next = inputs.indexOf(event.currentTarget) + 1
    if (inputs[next]) inputs[next].focus()
}

const startShift = async () => {
    saving.value = true
    try {
        await axios.post(route('pos.shifts.open'), {
            denominations: Object.fromEntries(denominations.map(value => [value, Math.max(0, Math.floor(Number(counts.value[value] || 0)))])),
            bank_balances: balances.value,
        })
        visible.value = false
        toast.success('Đã mở ca làm và lưu bảng kê đầu ca.')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể mở ca làm.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4">
        <section class="flex max-h-[92vh] w-full max-w-5xl flex-col rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div><h2 class="text-lg font-bold text-slate-900">Bảng kê tiền đầu ca</h2><p class="mt-0.5 text-xs text-slate-500">Bạn vừa đăng nhập và chưa mở ca hôm nay. Nhập số lượng tiền mặt và số dư tài khoản để bắt đầu.</p></div>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-100" @click="visible = false">Đóng</button>
            </div>
            <div class="mt-3 grid min-h-0 gap-4 overflow-y-auto lg:grid-cols-2">
              <div>
                <h3 class="mb-2 text-sm font-bold text-slate-800">Tiền mặt theo mệnh giá</h3>
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-100 text-[11px] uppercase text-slate-500"><tr><th class="rounded-l-lg px-2 py-1.5">Mệnh giá</th><th class="px-2 py-1.5">Số lượng</th><th class="rounded-r-lg px-2 py-1.5 text-right">Thành tiền</th></tr></thead>
                    <tbody class="divide-y divide-slate-100"><tr v-for="value in denominations" :key="value"><td class="px-2 py-1.5 font-semibold">{{ money(value) }}</td><td class="px-2 py-1"><input v-model.number="counts[value]" data-auto-shift-count type="text" inputmode="numeric" class="w-20 rounded-lg border-slate-300 px-2 py-1 text-right" @focus="selectInputValue" @keydown.enter="focusNext" /></td><td class="px-2 py-1.5 text-right">{{ money(value * Number(counts[value] || 0)) }}</td></tr></tbody>
                </table>
                <p class="mt-2 rounded-lg bg-indigo-50 px-3 py-2 text-right text-sm font-bold text-indigo-800">Tổng tiền mặt đầu ca: {{ money(total) }}</p>
              </div>
              <div v-if="values.length" class="space-y-2">
                <h3 class="text-sm font-bold text-slate-800">Số dư tài khoản ngân hàng đầu ca</h3>
                <table class="w-full text-left text-sm"><thead class="bg-slate-100 text-[11px] uppercase text-slate-500"><tr><th class="rounded-l-lg px-2 py-1.5">Tài khoản</th><th class="px-2 py-1.5">Chủ tài khoản</th><th class="rounded-r-lg px-2 py-1.5 text-right">Số dư</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="account in values" :key="account.key"><td class="px-2 py-2"><strong>{{ account.bank_name || `BIN ${account.bank_bin}` }}</strong><div class="text-xs text-slate-500">{{ account.bank_account }}</div></td><td class="px-2 py-2 text-xs">{{ account.bank_account_name || '—' }}</td><td class="px-2 py-2 text-right"><input v-model.number="balances[account.key]" data-auto-shift-count type="text" inputmode="numeric" class="w-28 rounded-lg border-slate-300 px-2 py-1 text-right" @focus="selectInputValue" @keydown.enter="focusNext" /></td></tr></tbody></table>
                <p class="text-right text-xs font-bold">Tổng số dư ngân hàng: {{ money(bankTotal) }}</p>
              </div>
            </div>
            <div class="mt-3 flex justify-end border-t border-slate-100 pt-3"><button :disabled="saving" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="startShift">{{ saving ? 'Đang lưu…' : 'Bắt đầu ca' }}</button></div>
        </section>
    </div>
</template>
