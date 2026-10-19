<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { toast } from 'vue-sonner'
import WorkShiftOpenModal from '@/Components/WorkShiftOpenModal.vue'
import { formatMoney } from '@/utils/format'

const page = usePage()
const denominations = [500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000]
const visible = ref(false)
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
const money = formatMoney

const openIfNeeded = async () => {
    if (!(page.props.auth?.permissions || []).includes('pos.access')) return
    try {
        const { data } = await axios.get(route('pos.shifts.current'))
        if (!data.shift && !data.prompt_dismissed) {
            counts.value = Object.fromEntries(denominations.map(value => [value, 0]))
            balances.value = Object.fromEntries(values.value.map(account => [account.key, 0]))
            visible.value = true
        }
    } catch (error) {
        // The POS page owns the actionable error state; do not interrupt other screens.
    }
}

onMounted(openIfNeeded)

const handleShiftOpened = () => {
    visible.value = false
    toast.success('Đã mở ca làm và lưu bảng kê đầu ca.')
}
</script>

<template>
    <WorkShiftOpenModal v-if="visible" :denominations="denominations" :counts="counts" :balances="balances" :accounts="values" :money="money" @close="visible = false" @opened="handleShiftOpened" />
</template>
