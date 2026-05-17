<script setup>
import { useForm, Link } from '@inertiajs/vue3';

// khởi tạo form với các trường cần thiết
const form = useForm({
    category_id: '',
    name: '',
    sku: '',
    cost_price: '',
    sell_price: '',
    stock: 0,
});

// gửi dữ liệu form đến route products.store
const submit = () => {
    form.post(route('products.store'));
};
// nhận dữ liệu categories từ controller
const props = defineProps({
    categories: Array,
});

</script>

<template>
    <div class="p-6 max-w-3xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">
                Thêm sản phẩm
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
                    Danh mục
                </label>

                <select
                    v-model="form.category_id"
                    class="w-full border rounded p-2"
                >
                    <option value="">
                        -- Chọn danh mục --
                    </option>

                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
            </div>


            <div>
                <label class="block mb-1">
                    Tên sản phẩm
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
                class="bg-blue-600 text-white px-5 py-2 rounded"
                :disabled="form.processing"
            >
                Lưu sản phẩm
            </button>
        </form>
    </div>
</template>