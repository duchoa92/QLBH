<script setup>
import CustomerForm from './Partials/CustomerForm.vue'
</script>

<template>

    <div>

        <CustomerForm
            :submit-url="route('customers.store')"
            method="post"
        />

    </div>

</template>
