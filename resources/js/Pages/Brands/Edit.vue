<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    brand: Object,
});

const form = useForm({

    name: props.brand.name,

    sort_order: props.brand.sort_order,

    is_active: props.brand.is_active,
});

const submit = () => {

    form.put(
        route(
            'brands.update',
            props.brand.id
        )
    );
};
</script>

<template>
    <div class="p-6 max-w-3xl">

        <div
            class="flex items-center justify-between mb-6"
        >
            <h1 class="text-2xl font-bold">
                Sửa thương hiệu
            </h1>

            <Link
                :href="route('brands.index')"
                class="px-4 py-2 bg-gray-200 rounded"
            >
                Quay lại
            </Link>
        </div>

        <form
            @submit.prevent="submit"
            class="space-y-4"
        >

            <div>
                <label class="block mb-1">
                    Tên thương hiệu
                </label>

                <input
                    v-model="form.name"
                    type="text"
                    class="w-full border rounded p-2"
                >

                <div
                    v-if="form.errors.name"
                    class="text-red-500 text-sm mt-1"
                >
                    {{ form.errors.name }}
                </div>
            </div>

            <div>
                <label class="block mb-1">
                    Thứ tự sắp xếp
                </label>

                <input
                    v-model="form.sort_order"
                    type="number"
                    class="w-full border rounded p-2"
                >
            </div>

            <div class="flex items-center gap-2">

                <input
                    v-model="form.is_active"
                    type="checkbox"
                >

                <label>
                    Hoạt động
                </label>
            </div>

            <button
                type="submit"
                class="bg-blue-600 text-white px-5 py-2 rounded"
            >
                Cập nhật
            </button>

        </form>
    </div>
</template>