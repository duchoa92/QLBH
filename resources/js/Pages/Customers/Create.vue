<script setup>
import CustomerForm from './Partials/CustomerForm.vue'
</script>

<template>

    <div class="p-4">

        <CustomerForm
            :submit-url="route('customers.store')"
            method="post"
        />

    </div>

</template>
