<script setup>
import { Link, useForm } from '@inertiajs/vue3';

import {
    ref,
    watch,
    onMounted,
} from 'vue';
// gửi request
import axios from 'axios';
// quét mã vạch bằng camera (nếu cần)
import { BrowserMultiFormatReader } from '@zxing/browser';

import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';

import CustomerAutocomplete from '@/Components/CustomerAutocomplete.vue';
import PatternLock from '@/Components/PatternLock.vue';
import { Toaster } from 'vue-sonner';

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

    issue: [],

    repair_request: '',

    estimated_cost: '',

    accessories: [],

    note: '',

    images: [],
    
    identity_card: '',
});

/*
|--------------------------------------------------------------------------
| Chọn khách hàng
|--------------------------------------------------------------------------
*/

const onSelectCustomer = customer => {

    form.customer_name =
        customer.customer_name;

    form.customer_phone =
        customer.customer_phone;
};

/*
|--------------------------------------------------------------------------
| Preview ảnh
|--------------------------------------------------------------------------
*/
// Lưu URL tạm thời của ảnh để hiển thị preview
const imagePreviews = ref([]);

/*
|--------------------------------------------------------------------------
| Scan IMEI
|--------------------------------------------------------------------------
*/

const videoRef = ref(null);

const scanning = ref(false);

const codeReader =
    new BrowserMultiFormatReader();

const startScan = async () => {

    try {

        scanning.value = true;

        const devices =
            await BrowserMultiFormatReader.listVideoInputDevices();

        const selectedDeviceId =
            devices[0]?.deviceId;

        if (!selectedDeviceId) {

            error('Không tìm thấy camera');

            return;
        }

        codeReader.decodeFromVideoDevice(

            selectedDeviceId,

            videoRef.value,

            (result, err) => {

                if (result) {

                    form.imei =
                        result.getText();

                    stopScan();
                }
            }
        );

    } catch (error) {

        console.log(error);

        error('Không thể mở camera');
    }
};

const stopScan = () => {

    scanning.value = false;

    codeReader.reset();
};

// Lưu input lỗi để lọc gợi ý
const issueInput = ref('');

const issueSuggestions = ref([]);

const filteredIssues = ref([]);

const selectedIssue = ref(null);

const handleImages = event => {

    const files = Array.from(
        event.target.files
    );

    form.images = files;

    imagePreviews.value =

        files.map(file =>

            URL.createObjectURL(file)
        );
};

// Lấy gợi ý lỗi phổ biến
onMounted(async () => {

    try {

        const response = await axios.get(
            route('repairs.suggestions')
        );

        issueSuggestions.value =
            response.data.issues ?? [];

    } catch (error) {

        console.log(error);
    }
});

// Lọc gợi ý
watch(issueInput, value => {

    if (!value) {

        filteredIssues.value = [];

        return;
    }

    if (value.endsWith(',')) {

        addIssue(
            value.replace(',', '')
        );

        return;
    }

    filteredIssues.value =

        issueSuggestions.value.filter(

            item =>

                item
                    .toLowerCase()
                    .includes(
                        value.toLowerCase()
                    )

                &&

                !form.issue.includes(item)
        );
});

// Chọn gợi ý
watch(selectedIssue, value => {

    if (!value) {
        return;
    }

    selectIssue(value);

    selectedIssue.value = null;

    issueInput.value = '';
});

const selectIssue = item => {

    if (
        !form.issue.includes(item)
    ) {

        form.issue.push(item);
    }

    issueInput.value = '';

    filteredIssues.value = [];
};

const addIssue = value => {

    value = value.trim();

    if (
        value &&
        !form.issue.includes(value)
    ) {

        form.issue.push(value);
    }

    issueInput.value = '';

    filteredIssues.value = [];
};

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

const submit = () => {

    form.post(
        route('repairs.store'),
        {
            forceFormData: true,

            onError: errors => {

                console.log(
                    'VALIDATION',
                    errors
                );
            },

            onSuccess: () => {

                console.log('SUCCESS');
            },

            onFinish: () => {

                console.log('FINISH');
            },
        }
    );
};
</script>

<template>

    <div class="min-h-screen bg-gray-100">

        <!-- HEADER -->

        <div
            class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm"
        >

            <div
                class="max-w-7xl mx-auto px-4 md:px-6 py-4 flex items-center justify-between"
            >

                <div>

                    <h1
                        class="text-2xl md:text-3xl font-bold text-gray-800"
                    >
                        Nhận máy sửa chữa
                    </h1>

                    <p class="text-sm text-gray-500 mt-1">
                        Tạo phiếu tiếp nhận sửa chữa thiết bị
                    </p>

                </div>

                <Link
                    :href="route('repairs.index')"
                    class="px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition"
                >
                    Quay lại
                </Link>

            </div>

        </div>

        <div
            class="max-w-7xl mx-auto px-4 md:px-6 py-6"
        >

            <form
                @submit.prevent="submit"
                class="grid grid-cols-1 xl:grid-cols-3 gap-6"
            >

                <!-- LEFT -->

                <div class="xl:col-span-2 space-y-6">

                    <!-- KHÁCH HÀNG -->

                    <div
                        class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden"
                    >

                        <div
                            class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white"
                        >

                            <h2
                                class="text-lg font-bold text-gray-800"
                            >
                                Thông tin khách hàng
                            </h2>

                            <p
                                class="text-sm text-gray-500 mt-1"
                            >
                                Thông tin người gửi máy
                            </p>

                        </div>

                        <div class="p-6">

                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-5"
                            >

                                <!-- khách hàng -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        Khách hàng
                                    </label>

                                    <CustomerAutocomplete
                                        v-model="form.customer_name"
                                        @select="onSelectCustomer"
                                    />

                                    <div
                                        v-if="form.errors.customer_name"
                                        class="text-red-500 text-sm mt-2"
                                    >
                                        {{ form.errors.customer_name }}
                                    </div>

                                </div>

                                <!-- sdt -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        Số điện thoại
                                    </label>

                                    <input
                                        v-model="form.customer_phone"
                                        type="text"
                                        class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    >

                                </div>

                                <!-- CCCD -->
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-700">
                                        CCCD
                                    </label>

                                    <input
                                        v-model="form.identity_card"
                                        type="text"
                                        class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                                    >
                                </div>

                                <!-- sdt khác -->

                                <div class="md:col-span-2">

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        SĐT liên hệ khác
                                    </label>

                                    <input
                                        v-model="form.contact_phone"
                                        type="text"
                                        class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- THÔNG TIN MÁY -->

                    <div
                        class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden"
                    >

                        <div
                            class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white"
                        >

                            <h2
                                class="text-lg font-bold text-gray-800"
                            >
                                Thông tin thiết bị
                            </h2>

                            <p
                                class="text-sm text-gray-500 mt-1"
                            >
                                Thông tin máy khách gửi sửa
                            </p>

                        </div>

                        <div class="p-6 space-y-6">

                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-5"
                            >

                                <!-- thiết bị -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        Thiết bị
                                    </label>

                                    <input
                                        v-model="form.device_name"
                                        type="text"
                                        placeholder="Ví dụ: iPhone 11"
                                        class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    >

                                    <div
                                        v-if="form.errors.device_name"
                                        class="text-red-500 text-sm mt-2"
                                    >
                                        {{ form.errors.device_name }}
                                    </div>

                                </div>

                                <!-- imei -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        IMEI / Serial
                                    </label>

                                    <div class="flex gap-2">

                                        <input
                                            v-model="form.imei"
                                            type="text"
                                            placeholder="Quét hoặc nhập IMEI"
                                            class="flex-1 rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                        >

                                        <!-- scan camera -->

                                        <button
                                            type="button"
                                            @click="startScan"
                                            class="px-4 rounded-2xl bg-blue-600 text-white hover:bg-blue-700 transition"
                                        >
                                            📷
                                        </button>

                                    </div>

                                    <!-- camera -->

                                    <div
                                        v-if="scanning"
                                        class="mt-4 border rounded-2xl overflow-hidden bg-black relative"
                                    >

                                        <video
                                            ref="videoRef"
                                            class="w-full h-64 object-cover"
                                        />

                                        <button
                                            type="button"
                                            @click="stopScan"
                                            class="absolute top-3 right-3 bg-red-500 text-white px-3 py-1 rounded-xl"
                                        >
                                            Tắt
                                        </button>

                                    </div>

                                    <p class="text-xs text-gray-500 mt-2">
                                        Hỗ trợ camera và đầu quét mã vạch USB
                                    </p>

                                </div>

                                <!-- mk -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        Mật khẩu mở màn
                                    </label>

                                    <input
                                        v-model="form.screen_password"
                                        type="text"
                                        placeholder="PIN / Password"
                                        class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    >

                                </div>

                                <!-- account -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        Loại tài khoản
                                    </label>

                                    <select
                                        v-model="form.account_type"
                                        class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
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

                                <!-- email -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        Email / SĐT tài khoản
                                    </label>

                                    <input
                                        v-model="form.account_email"
                                        type="text"
                                        class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    >

                                </div>

                                <!-- pass -->

                                <div>

                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"
                                    >
                                        Mật khẩu tài khoản
                                    </label>

                                    <input
                                        v-model="form.account_password"
                                        type="text"
                                        class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    >

                                </div>

                            </div>

                            <!-- pattern -->

                            <div>

                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-3"
                                >
                                    Mẫu hình mở khóa
                                </label>

                                <div
                                    class="inline-flex rounded-3xl border border-gray-200 bg-gray-50 p-5"
                                >

                                    <PatternLock
                                        v-model="form.screen_pattern"
                                    />

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- TÌNH TRẠNG -->

                    <div
                        class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden"
                    >

                        <div
                            class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-red-50 to-white"
                        >

                            <h2
                                class="text-lg font-bold text-gray-800"
                            >
                                Tình trạng máy
                            </h2>

                            <p
                                class="text-sm text-gray-500 mt-1"
                            >
                                Mô tả lỗi và yêu cầu sửa chữa
                            </p>

                        </div>

                        <div class="p-6 space-y-6">

                            <!-- tình trạng -->

                            <div>

                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-3"
                                >
                                    Tình trạng máy hiện tại
                                </label>

                                <div
                                    class="rounded-3xl border border-gray-300 p-4"
                                >

                                    <!-- tags -->

                                    <div
                                        class="flex flex-wrap gap-2 mb-4"
                                    >

                                        <div
                                            v-for="(item, index) in form.issue"
                                            :key="index"
                                            class="bg-red-100 text-red-700 px-4 py-2 rounded-full flex items-center gap-2 text-sm font-medium"
                                        >

                                            <span>
                                                {{ item }}
                                            </span>

                                            <button
                                                type="button"
                                                class="hover:text-red-900"
                                                @click="
                                                    form.issue.splice(index, 1)
                                                "
                                            >
                                                ×
                                            </button>

                                        </div>

                                    </div>

                                    <!-- autocomplete -->

                                    <Combobox
                                        v-model="selectedIssue"
                                    >

                                        <div class="relative">

                                            <ComboboxInput
                                                class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                                placeholder="Nhập tình trạng máy rồi nhấn Enter để thêm"

                                                :displayValue="() => issueInput"

                                                @change="
                                                    issueInput = $event.target.value
                                                "

                                                @keydown.enter.prevent="
                                                    if (
                                                        issueInput.trim()
                                                        &&
                                                        !selectedIssue
                                                    ) {

                                                        addIssue(issueInput);
                                                    }
                                                "
                                            />

                                            <ComboboxOptions
                                                v-if="filteredIssues.length"
                                                class="absolute z-50 mt-2 w-full rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden"
                                            >

                                                <ComboboxOption
                                                    v-for="item in filteredIssues"
                                                    :key="item"
                                                    :value="item"
                                                    as="template"
                                                    v-slot="{ active }"
                                                >

                                                    <li
                                                        :class="[

                                                            'px-4 py-3 cursor-pointer list-none transition',

                                                            active
                                                                ? 'bg-blue-50 text-blue-700'
                                                                : 'hover:bg-gray-50'
                                                        ]"
                                                    >
                                                        {{ item }}
                                                    </li>

                                                </ComboboxOption>

                                            </ComboboxOptions>

                                        </div>

                                    </Combobox>

                                </div>

                            </div>

                            <!-- lỗi cần sửa -->

                            <div>

                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-2"
                                >
                                    Lỗi cần sửa
                                </label>

                                <textarea
                                    v-model="form.repair_request"
                                    rows="4"
                                    class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    placeholder="Ví dụ: thay màn hình, sửa Face ID..."
                                />

                            </div>

                            <!-- chi phí -->

                            <div>

                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-2"
                                >
                                    Chi phí dự kiến
                                </label>

                                <input
                                    v-model="form.estimated_cost"
                                    type="text"
                                    class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    placeholder="Ví dụ: 500.000 - 700.000"
                                >

                            </div>

                            <!-- phụ kiện -->

                            <div>

                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-2"
                                >
                                    Phụ kiện kèm theo
                                </label>

                                <textarea
                                    :value="form.accessories.join(', ')"
                                    @input="
                                        form.accessories = $event.target.value
                                            .split(',')
                                            .map(v => v.trim())
                                            .filter(Boolean)
                                    "
                                    rows="3"
                                    class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    placeholder="Ví dụ: sạc, cáp, SIM..."
                                />

                            </div>

                            <!-- note -->

                            <div>

                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-2"
                                >
                                    Ghi chú
                                </label>

                                <textarea
                                    v-model="form.note"
                                    rows="4"
                                    class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                    placeholder="Ghi chú thêm..."
                                />

                            </div>

                        </div>

                    </div>

                </div>

                <!-- RIGHT -->

                <div class="space-y-6">

                    <!-- upload -->

                    <div
                        class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden"
                    >

                        <div
                            class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-emerald-50 to-white"
                        >

                            <h2
                                class="text-lg font-bold text-gray-800"
                            >
                                Ảnh tình trạng máy
                            </h2>

                            <p
                                class="text-sm text-gray-500 mt-1"
                            >
                                Hình ảnh trước khi nhận sửa
                            </p>

                        </div>

                        <div class="p-6">

                            <label
                                class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-3xl p-8 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"
                            >

                                <input
                                    type="file"
                                    multiple
                                    class="hidden"
                                    @change="handleImages"
                                >

                                <div
                                    class="text-5xl mb-3"
                                >
                                    📷
                                </div>

                                <div
                                    class="font-semibold text-gray-700"
                                >
                                    Chọn ảnh tải lên
                                </div>

                                <div
                                    class="text-sm text-gray-500 mt-1"
                                >
                                    Hỗ trợ nhiều ảnh
                                </div>

                            </label>

                            <div
                                v-if="imagePreviews.length"
                                class="grid grid-cols-2 gap-3 mt-5"
                            >

                                <div
                                    v-for="(image, index) in imagePreviews"
                                    :key="index"
                                    class="relative"
                                >

                                    <img
                                        :src="image"
                                        class="w-full h-32 object-cover rounded-2xl border border-gray-200"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- summary -->

                    <div
                        class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl shadow-lg overflow-hidden text-white"
                    >

                        <div class="p-6">

                            <h2
                                class="text-xl font-bold"
                            >
                                Xác nhận phiếu sửa
                            </h2>

                            <p
                                class="text-blue-100 text-sm mt-2"
                            >
                                Kiểm tra lại thông tin trước khi lưu
                            </p>

                            <div
                                class="mt-6 space-y-4 text-sm"
                            >

                                <div
                                    class="flex items-center justify-between border-b border-white/10 pb-3"
                                >

                                    <span class="text-blue-100">
                                        Khách hàng
                                    </span>

                                    <span class="font-semibold">
                                        {{
                                            form.customer_name || '---'
                                        }}
                                    </span>

                                </div>

                                <div
                                    class="flex items-center justify-between border-b border-white/10 pb-3"
                                >

                                    <span class="text-blue-100">
                                        Thiết bị
                                    </span>

                                    <span class="font-semibold">
                                        {{
                                            form.device_name || '---'
                                        }}
                                    </span>

                                </div>

                                <div
                                    class="flex items-center justify-between border-b border-white/10 pb-3"
                                >

                                    <span class="text-blue-100">
                                        IMEI
                                    </span>

                                    <span class="font-semibold">
                                        {{
                                            form.imei || '---'
                                        }}
                                    </span>

                                </div>

                                <div
                                    class="flex items-center justify-between"
                                >

                                    <span class="text-blue-100">
                                        Tổng lỗi
                                    </span>

                                    <span
                                        class="bg-white/20 px-3 py-1 rounded-full font-semibold"
                                    >
                                        {{ form.issue.length }}
                                    </span>

                                </div>

                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="mt-6 w-full rounded-2xl bg-white text-blue-700 py-4 font-bold hover:bg-blue-50 transition disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? 'Đang lưu phiếu...'
                                        : 'Lưu phiếu sửa chữa'
                                }}
                            </button>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>

</template>