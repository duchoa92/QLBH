<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({

    customer_name: '',

    customer_phone: '',

    device_name: '',

    imei: '',

    issue: '',

    accessories: '',

    images: [],
});

const submit = () => {

    form.post(
        route('repairs.store')
    );
};
</script>

<template>
    <div class="p-6 max-w-3xl">

        <div class="flex justify-between mb-6">

            <h1 class="text-2xl font-bold">
                Nhận máy sửa chữa
            </h1>

            <Link
                :href="route('repairs.index')"
                class="px-4 py-2 bg-gray-200 rounded"
            >
                Quay lại
            </Link>
        </div>

        <form
            @submit.prevent="submit"
            class="space-y-4"
        >

            <!-- khách -->

            <div>

                <label class="block mb-1">
                    Tên khách hàng
                </label>

                <input
                    v-model="form.customer_name"
                    type="text"
                    class="w-full border rounded p-3"
                >

                <div
                    v-if="form.errors.customer_name"
                    class="text-red-500 text-sm mt-1"
                >
                    {{ form.errors.customer_name }}
                </div>
            </div>

            <!-- sdt -->

            <div>

                <label class="block mb-1">
                    Số điện thoại
                </label>

                <input
                    v-model="form.customer_phone"
                    type="text"
                    class="w-full border rounded p-3"
                >
            </div>

            <!-- thiết bị -->

            <div>

                <label class="block mb-1">
                    Thiết bị
                </label>

                <input
                    v-model="form.device_name"
                    type="text"
                    placeholder="Ví dụ: iPhone 11"
                    class="w-full border rounded p-3"
                >

                <div
                    v-if="form.errors.device_name"
                    class="text-red-500 text-sm mt-1"
                >
                    {{ form.errors.device_name }}
                </div>
            </div>

            <!-- imei -->

            <div>

                <label class="block mb-1">
                    IMEI / Serial
                </label>

                <input
                    v-model="form.imei"
                    type="text"
                    class="w-full border rounded p-3"
                >
            </div>

            <!-- phụ kiện kèm theo -->
            <div>

                <label class="block mb-1">
                    Phụ kiện kèm theo
                </label>

                <textarea
                    v-model="form.accessories"
                    rows="3"
                    placeholder="Ví dụ: sạc, cáp, sim..."
                    class="w-full border rounded p-3"
                ></textarea>
            </div>

            <!-- lỗi -->

            <div>

                <label class="block mb-1">
                    Tình trạng lỗi
                </label>

                <textarea
                    v-model="form.issue"
                    rows="5"
                    class="w-full border rounded p-3"
                ></textarea>

                <div
                    v-if="form.errors.issue"
                    class="text-red-500 text-sm mt-1"
                >
                    {{ form.errors.issue }}
                </div>
            </div>

            <!-- upload ảnh tình trạng máy -->
            <div>

                <label class="block mb-1">
                    Ảnh tình trạng máy
                </label>

                <input
                    type="file"
                    multiple
                    @input="form.images = $event.target.files"
                    class="w-full border rounded p-3"
                >
            </div>

            <!-- submit -->

            <button
                type="submit"
                :disabled="form.processing"
                class="bg-blue-600 text-white px-5 py-3 rounded disabled:opacity-50"
            >
                {{ form.processing
                    ? 'Đang lưu...'
                    : 'Lưu phiếu sửa'
                }}
            </button>
        </form>
    </div>
</template>