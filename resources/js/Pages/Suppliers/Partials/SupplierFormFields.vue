<script setup>
import FloatingInput from '@/Components/UI/FloatingInput.vue'

/*
|--------------------------------------------------------------------------
| Partial dùng chung cho Suppliers/Create.vue và Suppliers/Edit.vue
|--------------------------------------------------------------------------
|
| props.form là một Inertia useForm() instance (name, phone, email, address).
|
*/

defineProps({
    form: Object,
})
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

        <FloatingInput v-model="form.bank_name" label="Ngân hàng" name="bank_name" :error="form.errors.bank_name" />
        <FloatingInput v-model="form.bank_bin" label="Mã BIN ngân hàng" name="bank_bin" inputmode="numeric" :error="form.errors.bank_bin" />
        <FloatingInput v-model="form.bank_account" label="Số tài khoản" name="bank_account" :error="form.errors.bank_account" />
        <FloatingInput v-model="form.bank_account_name" label="Tên chủ tài khoản" name="bank_account_name" :error="form.errors.bank_account_name" />
        <div class="sm:col-span-2"><FloatingInput v-model="form.bank_payment_url" label="Link mở app ngân hàng (mobile)" name="bank_payment_url" :error="form.errors.bank_payment_url" /></div>

    </div>

</template>
