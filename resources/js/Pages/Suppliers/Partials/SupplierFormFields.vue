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

    </div>

</template>
