<script setup>
import { Link, useForm } from '@inertiajs/vue3';
// nhận dữ liệu product và categories từ controller
const props = defineProps({
    product: Object,
    categories: Array,
    brands: Array,
});
const { product } = props;

// khởi tạo form với dữ liệu sản phẩm hiện tại
const form = useForm({
    _method: 'put',
    category_id: props.product.category_id,
    brand_id: props.product.brand_id,
    name: props.product.name,
    sku: props.product.sku,
    cost_price: props.product.cost_price,
    sell_price: props.product.sell_price,
    stock: props.product.stock,
    image: null,

});
// gửi dữ liệu form đến route products.update
const submit = () => {
    form.post(
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
                    Ảnh sản phẩm
                </label>
                <div v-if="product.image_url">
                    <label class="block mb-2">
                        Ảnh hiện tại
                    </label>

                    <img
                        :src="product.image_url"
                        class="w-32 h-32 object-cover rounded border"
                    >
                </div>
                <input
                    type="file"
                    @input="form.image = $event.target.files[0]"
                    class="w-full border rounded p-2"
                >

            </div>
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

            <!-- chọn thương hiệu -->
            <div>
                <label class="block mb-1">
                    Thương hiệu
                </label>

                <select
                    v-model="form.brand_id"
                    class="w-full border rounded p-2"
                >
                    <option value="">
                        -- Chọn thương hiệu --
                    </option>

                    <option
                        v-for="brand in brands"
                        :key="brand.id"
                        :value="brand.id"
                    >
                        {{ brand.name }}
                    </option>
                </select>
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