<script setup>

import {
    useForm,
    Link
} from '@inertiajs/vue3';

const props = defineProps({
    categories: Array,
    brands: Array,
    isModal: {
        type: Boolean,
        default: false
    }
})

const form = useForm({

    category_id: '',

    brand_id: '',

    name: '',

    sku: '',

    cost_price: '',

    sell_price: '',

    stock: 0,

    imeis: '',

    image: null,
});

const submit = () => {
    form.post(route('products.store'), {
        onSuccess: () => {
            emit('success')
        }
    })
}

const emit = defineEmits(['success'])


</script>

<template>
    <div class="p-6 max-w-3xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">
                Thêm sản phẩm
            </h1>

            <div v-if="!isModal">
                <Link
                    :href="route('products.index')"
                    class="px-4 py-2 bg-gray-200 rounded"
                >
                    Quay lại
                </Link>

            </div>
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

                <input
                    type="file"
                    @input="form.image = $event.target.files[0]"
                    class="w-full border rounded p-2"
                >

            </div>

            <!-- chọn danh mục -->
            <div>
                <label class="block mb-1">
                    Danh mục
                </label>

                <select
                    v-model="form.category_id"
                    class="w-full border rounded p-2"
                >
                    <option value="">
                        Chọn danh mục
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
            <!-- chọn thương hiệu từ danh sách brands -->
            <div>
                <label class="block mb-1">
                    Thương hiệu
                </label>

                <select
                    v-model="form.brand_id"
                    class="w-full border rounded p-2"
                >
                    <option value="">
                        Chọn thương hiệu
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


            <div
                v-if="form.errors.name"
                class="text-red-500 text-sm mt-1"
            >
                {{ form.errors.name }}
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
                    Danh sách IMEI
                </label>

                <textarea
                    v-model="form.imeis"
                    rows="6"
                    class="w-full border rounded p-2"
                    placeholder="Mỗi dòng 1 IMEI"
                />

                <p class="text-sm text-gray-500 mt-1">
                    Ví dụ:
                    <br>
                    356789111111111
                    <br>
                    356789222222222
                </p>

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