<script setup>

import { useForm } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    category: Object
})

const form = useForm({
    name: props.category.name,
    sort_order: props.category.sort_order,
    is_active: props.category.is_active,
})

const submit = () => {

    form.put(
        `/categories/${props.category.id}`
    )

}

</script>

<template>

    <div>

        <h1 class="text-2xl font-bold mb-5">
            Chỉnh sửa danh mục
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
                    class="text-red-500"
                >
                    {{ form.errors.name }}
                </div>

            </div>

            <button
                class="px-4 py-2 bg-black text-white rounded"
            >
                Cập nhật
            </button>

        </form>

    </div>

</template>