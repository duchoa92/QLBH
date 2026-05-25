<script setup>
import CustomerForm from './Partials/CustomerForm.vue'

const props = defineProps({
    customer: Object,
})
</script>

<template>

    <div class="p-4">

        <h1 class="text-2xl font-bold mb-4">
            Cập nhật khách hàng
        </h1>

        <CustomerForm
            :customer="customer"
            :submit-url="route('customers.update', customer.id)"
            method="put"
        />

    </div>

</template>