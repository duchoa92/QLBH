<script setup>
import CustomerForm from './Partials/CustomerForm.vue'

const props = defineProps({
    customer: Object,
})
</script>

<template>

    <div class="p-4">

        <CustomerForm
            :customer="customer"
            :submit-url="route('customers.update', customer.id)"
            method="put"
        />

    </div>

</template>
