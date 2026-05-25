<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },

    submitUrl: {
        type: String,
        required: true,
    },

    method: {
        type: String,
        default: 'post',
    },
})

const form = useForm({
    full_name: props.customer?.full_name || '',
    phone: props.customer?.phone || '',
    birthday: props.customer?.birthday || '',
    gender: props.customer?.gender || '',

    cccd: props.customer?.cccd || '',

    province: props.customer?.province || '',
    district: props.customer?.district || '',
    ward: props.customer?.ward || '',
    address: props.customer?.address || '',

    note: props.customer?.note || '',

    images: [],
})

/*
|--------------------------------------------------
| SUBMIT
|--------------------------------------------------
*/
const submit = () => {

    if (props.method === 'put') {

        form.put(props.submitUrl)

        return
    }

    form.post(props.submitUrl)
}
</script>

<template>

    <form
        @submit.prevent="submit"
        class="space-y-6"
    >

        <!-- BASIC INFO -->
        <div class="bg-white rounded shadow p-4">

            <h2 class="font-bold text-lg mb-4">
                Thông tin khách hàng
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- FULL NAME -->
                <div>

                    <label class="block mb-1">
                        Họ tên *
                    </label>

                    <input
                        v-model="form.full_name"
                        type="text"
                        class="w-full border rounded p-2"
                    />

                    <div
                        v-if="form.errors.full_name"
                        class="text-red-500 text-sm mt-1"
                    >
                        {{ form.errors.full_name }}
                    </div>

                </div>

                <!-- PHONE -->
                <div>

                    <label class="block mb-1">
                        Số điện thoại
                    </label>

                    <input
                        v-model="form.phone"
                        type="text"
                        class="w-full border rounded p-2"
                    />

                </div>

                <!-- BIRTHDAY -->
                <div>

                    <label class="block mb-1">
                        Ngày sinh
                    </label>

                    <input
                        v-model="form.birthday"
                        type="date"
                        class="w-full border rounded p-2"
                    />

                </div>

                <!-- GENDER -->
                <div>

                    <label class="block mb-1">
                        Giới tính
                    </label>

                    <select
                        v-model="form.gender"
                        class="w-full border rounded p-2"
                    >
                        <option value="">
                            -- Chọn --
                        </option>

                        <option value="male">
                            Nam
                        </option>

                        <option value="female">
                            Nữ
                        </option>

                        <option value="other">
                            Khác
                        </option>

                    </select>

                </div>

                <!-- CCCD -->
                <div>

                    <label class="block mb-1">
                        CCCD
                    </label>

                    <input
                        v-model="form.cccd"
                        type="text"
                        class="w-full border rounded p-2"
                    />

                </div>

            </div>

        </div>

        <!-- ADDRESS -->
        <div class="bg-white rounded shadow p-4">

            <h2 class="font-bold text-lg mb-4">
                Địa chỉ
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- PROVINCE -->
                <div>

                    <label class="block mb-1">
                        Tỉnh / Thành
                    </label>

                    <input
                        v-model="form.province"
                        type="text"
                        class="w-full border rounded p-2"
                    />

                </div>

                <!-- DISTRICT -->
                <div>

                    <label class="block mb-1">
                        Quận / Huyện
                    </label>

                    <input
                        v-model="form.district"
                        type="text"
                        class="w-full border rounded p-2"
                    />

                </div>

                <!-- WARD -->
                <div>

                    <label class="block mb-1">
                        Phường / Xã
                    </label>

                    <input
                        v-model="form.ward"
                        type="text"
                        class="w-full border rounded p-2"
                    />

                </div>

                <!-- ADDRESS -->
                <div>

                    <label class="block mb-1">
                        Địa chỉ chi tiết
                    </label>

                    <input
                        v-model="form.address"
                        type="text"
                        class="w-full border rounded p-2"
                    />

                </div>

            </div>

        </div>

        <!-- IMAGES -->
        <div class="bg-white rounded shadow p-4">

            <h2 class="font-bold text-lg mb-2">
                Hình ảnh đối chiếu
            </h2>

            <p class="text-sm text-gray-500 mb-4">
                Ảnh dùng để xác minh khách hàng khi nhận máy,
                không phải ảnh đại diện.
            </p>

            <input
                type="file"
                multiple
                @input="form.images = $event.target.files"
            />

        </div>

        <!-- Xem ảnh -->
        <div
            v-if="customer?.images?.length"
            class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4"
        >

            <div
                v-for="image in customer.images"
                :key="image.id"
            >

                <img
                    :src="`/storage/${image.path}`"
                    class="rounded border"
                />

            </div>

        </div>

        <!-- NOTE -->
        <div class="bg-white rounded shadow p-4">

            <label class="block mb-1">
                Ghi chú
            </label>

            <textarea
                v-model="form.note"
                rows="4"
                class="w-full border rounded p-2"
            />

        </div>

        <!-- ACTION -->
        <div class="flex justify-end gap-2">

            <button
                type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Đang lưu...' : 'Lưu khách hàng' }}
            </button>

        </div>

    </form>

</template>