<script setup>
import { formatCurrency } from '@/utils/format'
import { useForm, Link, router } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { openModal } from '@/Stores/modal';
import PriceFormModal from './PriceFormModal.vue';

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

const hasExtraInfo = (imei) => {
    const info = imei.extra_info || {};

    return Boolean(
        info.note ||
        info.image_path
    );
};

const extraInfoImageUrl = (imei) =>
    imei?.extra_info?.image_path
        ? `/storage/${imei.extra_info.image_path}`
        : null;

const money = formatCurrency;

const openPriceEditor = (imei) => {
    openModal(PriceFormModal, {
        props: { imei },
        onUpdated: () => router.reload({ only: ['product'] }),
    });
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

                        <th class="p-3 text-right">
                            Giá bán
                        </th>

                        <th class="p-3 text-left">
                            Thông tin thêm
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
                            <Link
                                :href="
                                    route(
                                        'product-imeis.show',
                                        imei.id
                                    )
                                "
                            >
                                {{ imei.imei }}
                            </Link>
                        </td>

                        <td class="p-3">

                            <span
                                v-if="imei.status === 'in_stock' || imei.status === 0"
                                class="text-green-600"
                            >
                                Còn hàng
                            </span>

                            <span
                                v-else-if="imei.status === 'sold' || imei.status === 1"
                                class="text-red-600"
                            >
                                Đã bán
                            </span>

                            <span
                                v-else-if="imei.status === 2"
                            >
                                Đang sửa
                            </span>

                            <span
                                v-else-if="imei.status === 3"
                            >
                                Đã trả
                            </span>
                        </td>

                        <td class="p-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <span class="font-semibold text-slate-800">{{ money(imei.sell_price) }}</span>
                                <button
                                    v-if="imei.status === 'in_stock' || imei.status === 0"
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50"
                                    title="Sửa giá bán"
                                    @click="openPriceEditor(imei)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                            </div>
                        </td>

                        <td class="p-3 text-sm text-slate-600">
                            <div
                                v-if="hasExtraInfo(imei)"
                                class="space-y-0.5"
                            >
                                <div v-if="imei.extra_info?.note">
                                    {{ imei.extra_info.note }}
                                </div>

                                <a
                                    v-if="extraInfoImageUrl(imei)"
                                    :href="extraInfoImageUrl(imei)"
                                    target="_blank"
                                    class="inline-flex font-semibold text-blue-600 hover:text-blue-700"
                                >
                                    Xem ảnh
                                </a>
                            </div>

                            <span v-else class="text-slate-400">-</span>
                        </td>
                    </tr>

                    <tr v-if="!product.imeis.length">

                        <td
                            colspan="4"
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
