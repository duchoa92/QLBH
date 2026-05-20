<script setup>
import { Link, useForm }
    from '@inertiajs/vue3';

import { ref, } from 'vue';

import CustomerAutocomplete from '@/Components/CustomerAutocomplete.vue';
import PatternLock from '@/Components/PatternLock.vue';

const form = useForm({

    customer_name: '',

    customer_phone: '',

    contact_phone: '',

    device_name: '',

    imei: '',

    screen_password: '',

    screen_pattern: '',

    account_type: '',

    account_email: '',

    account_password: '',

    issue: '',

    accessories: '',

    note: '',

    images: [],
});


// preview ảnh
const imagePreviews = ref([]);

const handleImages = event => {

    const files = Array.from(
        event.target.files
    );

    form.images = files;

    imagePreviews.value = [];

    files.forEach(file => {

        imagePreviews.value.push(

            URL.createObjectURL(file)
        );
    });
};


// submit
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




            <!-- khách hàng -->
            <div>

                <label class="block mb-1">
                    Khách hàng
                </label>

                <CustomerAutocomplete

                    @select="
                        customer => {

                            form.customer_name =
                                customer.customer_name;

                            form.customer_phone =
                                customer.customer_phone;
                        }
                    "
                />
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


<!-- mật khẩu mở màn -->
<div>

    <label class="block mb-1">
        Mật khẩu mở màn
    </label>

    <input
        v-model="form.screen_password"
        type="text"
        placeholder="PIN / Pattern / Password"
        class="w-full border rounded p-3"
    >
</div>
 <!-- mẫu hình mở khóa -->
<div>

    <label class="block mb-3 font-medium">
        Mẫu hình mở khóa
    </label>

    <PatternLock
        v-model="form.screen_pattern"
    />
</div>

<!-- loại tài khoản -->
<div>

    <label class="block mb-1">
        Loại tài khoản
    </label>

    <select
        v-model="form.account_type"
        class="w-full border rounded p-3"
    >

        <option value="">
            Chọn tài khoản
        </option>

        <option value="google">
            Google
        </option>

        <option value="icloud">
            iCloud
        </option>

        <option value="xiaomi">
            Xiaomi
        </option>

        <option value="samsung">
            Samsung
        </option>
    </select>
</div>

<!-- email tài khoản -->
<div>

    <label class="block mb-1">
        Email / SĐT tài khoản
    </label>

    <input
        v-model="form.account_email"
        type="text"
        placeholder="example@gmail.com"
        class="w-full border rounded p-3"
    >
</div>

<!-- mật khẩu tài khoản -->
<div>

    <label class="block mb-1">
        Mật khẩu tài khoản
    </label>

    <input
        v-model="form.account_password"
        type="text"
        placeholder="Nhập mật khẩu"
        class="w-full border rounded p-3"
    >
</div>

<!-- sdt liên hệ -->
<div>

    <label class="block mb-1">
        SĐT liên hệ khác
    </label>

    <input
        v-model="form.contact_phone"
        type="text"
        placeholder="Số điện thoại khác"
        class="w-full border rounded p-3"
    >
</div>

<!-- ghi chú -->
<div>

    <label class="block mb-1">
        Ghi chú thêm
    </label>

    <textarea v-model="form.note"
        rows="4"
        placeholder="Mô tả thêm..."
        class="w-full border rounded p-3"
    />
</div>

<!-- phụ kiện -->
<div>

    <label class="block mb-1">
        Phụ kiện kèm theo
    </label>

    <textarea
        v-model="form.accessories"
        class="w-full border rounded p-3"
        placeholder="Ví dụ: sạc, cáp..."
    />
</div>

<!-- lỗi -->
<div>

    <label class="block mb-1">
        Tình trạng lỗi
    </label>

    <textarea
        v-model="form.issue"
        class="w-full border rounded p-3"
        placeholder="Mô tả lỗi..."
    />
</div>

            <!-- ảnh -->
            <div>

                <label class="block mb-1">
                    Ảnh tình trạng máy
                </label>

                <input
                    type="file"
                    multiple
                    @input="handleImages"
                    class="w-full border rounded p-3"
                >

                <div
                    v-if="imagePreviews.length"
                    class="grid grid-cols-3 gap-3 mt-3"
                >

                    <img
                        v-for="(image, index) in imagePreviews"
                        :key="index"

                        :src="image"

                        class="w-full h-28 object-cover rounded border"
                    >
                </div>
            </div>

            <!-- submit -->
            <button
                type="submit"

                :disabled="form.processing"

                class="bg-blue-600 text-white px-5 py-3 rounded disabled:opacity-50"
            >
                {{
                    form.processing
                        ? 'Đang lưu...'
                        : 'Lưu phiếu sửa'
                }}
            </button>
        </form>
    </div>
</template>