<script setup>
import CustomerForm from './Partials/CustomerForm.vue'
</script>

<template>

    <div class="p-4">

        <h1 class="text-2xl font-bold mb-4">
            Thêm khách hàng
        </h1>

        <CustomerForm
            :submit-url="route('customers.store')"
            method="post"
        />

    </div>

</template>