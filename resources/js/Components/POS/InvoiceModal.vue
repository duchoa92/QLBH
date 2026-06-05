<template>
<div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center">

    <div class="bg-white p-6 rounded w-[400px]">

        <h2 class="text-lg font-bold">Hóa đơn</h2>

        <div v-for="i in data.cart" :key="i.id">
            {{ i.name }} x {{ i.qty }}
        </div>

        <div class="mt-4 font-bold">
            Tổng: {{ data.total }}
        </div>

        <button
            @click="window.print()"
            class="mt-4 w-full bg-green-600 text-white py-2 rounded"
        >
            In hóa đơn
        </button>

    </div>

</div>
</template>

<script setup>
defineProps(['show', 'data'])
</script>