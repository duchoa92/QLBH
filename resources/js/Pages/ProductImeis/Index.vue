<script setup>
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({

    product: Object,
});

const form = useForm({

    imei: '',
});

const submit = () => {

    form.post(

        route(

            'product-imeis.store',

            props.product.id
        ),

        {
            preserveScroll: true,

            onSuccess: () => {

                form.reset();
            },
        }
    );
};
</script>

<template>
    <div class="p-6">

        <div class="flex justify-between mb-6">

            <div>

                <h1 class="text-2xl font-bold">
                    Quản lý IMEI
                </h1>

                <p class="text-gray-500">
                    {{ product.name }}
                </p>
            </div>

            <Link
                :href="route('products.index')"
                class="px-4 py-2 bg-gray-200 rounded"
            >
                Quay lại
            </Link>
        </div>

        <!-- thêm imei -->

        <form
            @submit.prevent="submit"
            class="flex gap-3 mb-6"
        >

            <input
                v-model="form.imei"
                type="text"
                placeholder="Nhập IMEI..."
                class="flex-1 border rounded p-3"
            >

            <button
                type="submit"
                class="bg-blue-600 text-white px-5 rounded"
            >
                Thêm
            </button>
        </form>

        <!-- errors -->

        <div
            v-if="form.errors.imei"
            class="text-red-500 mb-4"
        >
            {{ form.errors.imei }}
        </div>

        <!-- danh sách -->

        <div class="border rounded overflow-hidden">

            <table class="w-full">

                <thead class="bg-gray-100">

                    <tr>

                        <th class="p-3 text-left">
                            IMEI
                        </th>

                        <th class="p-3 text-left">
                            Trạng thái
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <tr
                        v-for="imei in product.imeis"
                        :key="imei.id"
                        class="border-t"
                    >

                        <td class="p-3">
                            {{ imei.imei }}
                        </td>

                        <td class="p-3">

                            <span
                                v-if="imei.status === 'available'"
                                class="text-green-600"
                            >
                                Chưa bán
                            </span>

                            <span
                                v-else
                                class="text-red-600"
                            >
                                Đã bán
                            </span>
                        </td>
                    </tr>

                    <tr v-if="!product.imeis.length">

                        <td
                            colspan="2"
                            class="p-5 text-center text-gray-500"
                        >
                            Chưa có IMEI
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>