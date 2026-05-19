<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref,  watch, onMounted } from 'vue';
import axios from 'axios';

const form = useForm({

    customer_name: '',

    customer_phone: '',

    device_name: '',

    imei: '',

    issue: [],

    accessories: [],

    images: [],
});

// Gợi ý phụ kiện
const accessorySuggestions = ref([]);
// Gợi ý lỗi
const issueSuggestions = ref([]);

// Danh sách gợi ý lỗi sau khi lọc
const filteredIssues = ref([]);
// Danh sách gợi ý phụ kiện sau khi lọc
const filteredAccessories = ref([]);

// Input tạm thời để gắn với datalist
const issueInput = ref('');
// Input  tạm thời để gắn với datalist
const accessoryInput = ref('');

// Thêm lỗi vào form
const addIssue = () => {

    const value = issueInput.value.trim();

    if (
        value &&
        !form.issue.includes(value)
    ) {

        form.issue.push(value);

        issueInput.value = '';

        filteredIssues.value = [];
    }
};

// Xóa lỗi khỏi form
const removeIssue = index => {

    form.issue.splice(
        index,
        1
    );
};

// Thêm phụ kiện vào form
const addAccessory = () => {

    const value = accessoryInput.value.trim();

    if (
        value &&
        !form.accessories.includes(value)
    ) {

        form.accessories.push(value);

        accessoryInput.value = '';

        filteredAccessories.value = [];
    }
};

// Xóa phụ kiện khỏi form
const removeAccessory = index => {

    form.accessories.splice(
        index,
        1
    );
};

// Lọc gợi ý khi người dùng nhập vào input lỗi
watch(issueInput, value => {

    if (!value) {

        filteredIssues.value = [];

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
// Lọc gợi ý khi người dùng nhập vào input phụ kiện
watch(accessoryInput, value => {

    if (!value) {

        filteredAccessories.value = [];

        return;
    }

    filteredAccessories.value =

        accessorySuggestions.value.filter(

            item =>

                item
                    .toLowerCase()

                    .includes(
                        value.toLowerCase()
                    )

                &&

                !form.accessories.includes(item)
        );
});


// Chọn gợi ý lỗi
const selectIssue = item => {

    form.issue.push(item);

    issueInput.value = '';

    filteredIssues.value = [];
};

// Chọn gợi ý phụ kiện
const selectAccessory = item => {

    form.accessories.push(item);

    accessoryInput.value = '';

    filteredAccessories.value = [];
};

// Lấy gợi ý từ server khi component được mounted
onMounted(async () => {

    const response = await axios.get(

        route(
            'repairs.suggestions'
        )
    );

    accessorySuggestions.value =

        response.data.accessories;

    issueSuggestions.value =

        response.data.issues;
});

// xem trước ảnh tải lên
const imagePreviews = ref([]);
// Xử lý khi người dùng chọn ảnh
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
                <label class="block mb-2">
                    Phụ kiện kèm theo
                </label>

                <div class="border rounded p-3 relative">

                    <div class="flex flex-wrap gap-2 mb-3">

                        <div
                            v-for="(item, index) in form.accessories"
                            :key="index"
                            class="bg-blue-100 text-blue-700 px-3 py-1 rounded flex items-center gap-2"
                        >

                            <span>
                                {{ item }}
                            </span>

                            <button
                                type="button"
                                @click="removeAccessory(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <input
                        v-model="accessoryInput"
                        type="text"
                        placeholder="Nhập phụ kiện..."
                        class="w-full outline-none"
                        @keydown.enter.prevent="addAccessory"
                    >

                    <!-- dropdown -->

                    <div
                        v-if="filteredAccessories.length"
                        class="absolute left-0 right-0 bg-white border rounded shadow mt-2 z-10 max-h-60 overflow-y-auto"
                    >

                        <div
                            v-for="item in filteredAccessories"
                            :key="item"
                            @click="selectAccessory(item)"
                            class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                        >
                            {{ item }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- lỗi -->

            <div>

                <label class="block mb-2">
                    Tình trạng lỗi
                </label>

                <div class="border rounded p-3 relative">

                    <div class="flex flex-wrap gap-2 mb-3">

                        <div
                            v-for="(item, index) in form.issue"
                            :key="index"
                            class="bg-red-100 text-red-700 px-3 py-1 rounded flex items-center gap-2"
                        >

                            <span>
                                {{ item }}
                            </span>

                            <button
                                type="button"
                                @click="removeIssue(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <input
                        v-model="issueInput"
                        type="text"
                        placeholder="Nhập lỗi..."
                        class="w-full outline-none"
                        @keydown.enter.prevent="addIssue"
                    >

                    <!-- dropdown -->

                    <div
                        v-if="filteredIssues.length"
                        class="absolute left-0 right-0 bg-white border rounded shadow mt-2 z-10 max-h-60 overflow-y-auto"
                    >

                        <div
                            v-for="item in filteredIssues"
                            :key="item"
                            @click="selectIssue(item)"
                            class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                        >
                            {{ item }}
                        </div>
                    </div>
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
                {{ form.processing
                    ? 'Đang lưu...'
                    : 'Lưu phiếu sửa'
                }}
            </button>
        </form>
    </div>
</template>