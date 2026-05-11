<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: Object,
});

const form = useForm({
    name: props.product.name,
    sku: props.product.sku,
    cost_price: props.product.cost_price,
    sell_price: props.product.sell_price,
    stock: props.product.stock,
});

const submit = () => {
    form.put(
        route(
            'products.update',
            props.product.id
        )
    );
};
</script>

<template>
    <div class="p-6 max-w-3xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">
                Sửa sản phẩm
            </h1>

            <Link
                :href="route('products.index')"
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
                    Tên sản phẩm
                </label>

                <input
                    v-model="form.name"
                    type="text"
                    class="w-full border rounded p-2"
                >
            </div>

            <div>
                <label class="block mb-1">
                    Mã sản phẩm
                </label>

                <input
                    v-model="form.sku"
                    type="text"
                    class="w-full border rounded p-2"
                >
            </div>

            <div>
                <label class="block mb-1">
                    Giá nhập
                </label>

                <input
                    v-model="form.cost_price"
                    type="number"
                    class="w-full border rounded p-2"
                >
            </div>

            <div>
                <label class="block mb-1">
                    Giá bán
                </label>

                <input
                    v-model="form.sell_price"
                    type="number"
                    class="w-full border rounded p-2"
                >
            </div>

            <div>
                <label class="block mb-1">
                    Tồn kho
                </label>

                <input
                    v-model="form.stock"
                    type="number"
                    class="w-full border rounded p-2"
                >
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="bg-blue-600 text-white px-5 py-2 rounded disabled:opacity-50"
            >
                {{ form.processing ? 'Đang cập nhật...' : 'Cập nhật' }}
            </button>
        </form>
    </div>
</template>