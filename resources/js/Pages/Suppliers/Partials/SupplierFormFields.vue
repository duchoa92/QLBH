<script setup>
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { computed } from 'vue'

/*
|--------------------------------------------------------------------------
| Partial dùng chung cho Suppliers/Create.vue và Suppliers/Edit.vue
|--------------------------------------------------------------------------
|
| props.form là một Inertia useForm() instance (name, phone, email, address).
|
*/

const props = defineProps({
    form: Object,
})

const accounts = computed(() => props.form.bank_accounts || [])
const addBankAccount = () => {
    if (accounts.value.length >= 10) return
    accounts.value.push({ bank_name: '', bank_bin: '', bank_account: '', bank_account_name: '', bank_payment_url: '', is_default: accounts.value.length === 0 })
}
const removeBankAccount = index => {
    accounts.value.splice(index, 1)
    if (accounts.value.length && !accounts.value.some(account => account.is_default)) accounts.value[0].is_default = true
}
const setDefault = index => accounts.value.forEach((account, i) => { account.is_default = i === index })
</script>

<template>

    <div class="grid gap-4 sm:grid-cols-2">

        <FloatingInput
            v-model="form.name"
            label="Tên nhà cung cấp"
            name="name"
            :error="form.errors.name"
        />

        <FloatingInput
            v-model="form.phone"
            label="Số điện thoại"
            name="phone"
            :error="form.errors.phone"
        />

        <FloatingInput
            v-model="form.email"
            label="Email"
            name="email"
            type="email"
            :error="form.errors.email"
        />

        <FloatingInput
            v-model="form.address"
            label="Địa chỉ"
            name="address"
            :error="form.errors.address"
        />

        <section class="sm:col-span-2 rounded-xl border border-slate-200 p-3 sm:p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div><h3 class="font-semibold text-slate-800">Tài khoản ngân hàng nhà cung cấp</h3><p class="text-xs text-slate-500">Chọn tài khoản mặc định dùng khi thanh toán phiếu nhập.</p></div>
                <button type="button" class="shrink-0 rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50" :disabled="accounts.length >= 10" @click="addBankAccount">+ Thêm tài khoản</button>
            </div>
            <div v-if="!accounts.length" class="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">Chưa khai báo tài khoản ngân hàng.</div>
            <div v-for="(account, index) in accounts" :key="index" class="mb-3 rounded-lg border border-slate-200 p-3 last:mb-0">
                <div class="mb-3 flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input :checked="account.is_default" type="radio" name="supplier_default_bank_account" class="text-blue-600" @change="setDefault(index)" /> Mặc định</label>
                    <button type="button" class="text-sm font-medium text-rose-600 hover:text-rose-700" @click="removeBankAccount(index)">Xóa</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FloatingInput v-model="account.bank_name" :label="`Ngân hàng ${index + 1}`" :name="`bank_accounts_${index}_bank_name`" :error="form.errors[`bank_accounts.${index}.bank_name`]" />
                    <FloatingInput v-model="account.bank_bin" label="Mã BIN (6 chữ số)" :name="`bank_accounts_${index}_bank_bin`" inputmode="numeric" :error="form.errors[`bank_accounts.${index}.bank_bin`]" />
                    <FloatingInput v-model="account.bank_account" label="Số tài khoản" :name="`bank_accounts_${index}_bank_account`" :error="form.errors[`bank_accounts.${index}.bank_account`]" />
                    <FloatingInput v-model="account.bank_account_name" label="Tên chủ tài khoản" :name="`bank_accounts_${index}_bank_account_name`" :error="form.errors[`bank_accounts.${index}.bank_account_name`]" />
                    <div class="sm:col-span-2"><FloatingInput v-model="account.bank_payment_url" label="Link mở app ngân hàng (mobile)" :name="`bank_accounts_${index}_bank_payment_url`" :error="form.errors[`bank_accounts.${index}.bank_payment_url`]" /></div>
                </div>
            </div>
            <p v-if="form.errors.bank_accounts" class="mt-2 text-sm text-rose-600">{{ form.errors.bank_accounts }}</p>
        </section>

    </div>

</template>
