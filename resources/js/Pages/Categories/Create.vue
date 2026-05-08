<script setup>

import { useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    sort_order: 0,
    is_active: true,
})

const submit = () => {
    form.post('/categories')
}

</script>

<template>

    <div class="p-6">

        <h1 class="text-2xl font-bold mb-5">
            Thêm danh mục
        </h1>

        <form @submit.prevent="submit">

            <div class="mb-4">

                <label>
                    Tên danh mục
                </label>

                <input
                    v-model="form.name"
                    type="text"
                    class="border w-full p-2 rounded"
                >

                <div
                    v-if="form.errors.name"
                    class="text-red-500 mt-1"
                >
                    {{ form.errors.name }}
                </div>

            </div>

            <button
                class="px-4 py-2 bg-black text-white rounded"
            >
                Lưu
            </button>

        </form>

    </div>

</template>