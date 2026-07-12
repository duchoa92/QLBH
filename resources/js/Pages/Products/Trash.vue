<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
    products: Object
})

const restore = (id) => {
    router.post(route('products.restore', id))
}

const forceDelete = (id) => {
    if (!confirm('Xóa vĩnh viễn?')) return

    router.delete(route('products.forceDelete', id))
}
</script>

<template>
<div>
    <h1 class="text-xl font-bold mb-4">Thùng rác</h1>

    <table class="w-full border">
        <tr v-for="p in products.data" :key="p.id">
            <td>{{ p.name }}</td>

            <td>
                <button @click="restore(p.id)">Khôi phục</button>
                <button @click="forceDelete(p.id)">Xóa</button>
            </td>
        </tr>
    </table>
</div>
</template>