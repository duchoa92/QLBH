<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({

    repair: Object,
});

const form = useForm({

    device_name:
        props.repair.device_name,

    imei:
        props.repair.imei,

    issue:
        props.repair.issue || [],

    repair_request:
        props.repair.repair_request,

    note:
        props.repair.note,

    estimated_cost:
        props.repair.estimated_cost,

    final_cost:
        props.repair.final_cost,

    status:
        props.repair.status,

    images: [],
});

const submit = () => {

    form.post(

        route(
            'repairs.update',
            props.repair.id
        ),

        {

            forceFormData: true,

            _method: 'put',
        }
    );
};
</script>

<template>

    <div class="min-h-screen bg-gray-100">

        <div
            class="max-w-5xl mx-auto px-4 py-6"
        >

            <!-- HEADER -->

            <div
                class="flex items-center justify-between mb-6"
            >

                <div>

                    <h1
                        class="text-3xl font-bold text-gray-800"
                    >
                        Cập nhật sửa chữa
                    </h1>

                    <p
                        class="text-gray-500 mt-1"
                    >
                        {{ repair.code }}
                    </p>

                </div>

                <Link
                    :href="route('repairs.show', repair.id)"
                    class="px-4 py-2 rounded-2xl bg-gray-200 hover:bg-gray-300"
                >
                    Quay lại
                </Link>

            </div>

            <form
                @submit.prevent="submit"
                class="space-y-6"
            >

                <!-- DEVICE -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6"
                >

                    <h2
                        class="text-lg font-bold text-gray-800 mb-5"
                    >
                        Thiết bị
                    </h2>

                    <div
                        class="grid grid-cols-1 md:grid-cols-2 gap-5"
                    >

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                Thiết bị
                            </label>

                            <input
                                v-model="form.device_name"
                                type="text"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            >

                        </div>

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                IMEI
                            </label>

                            <input
                                v-model="form.imei"
                                type="text"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            >

                        </div>

                    </div>

                </div>

                <!-- ISSUE -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6"
                >

                    <h2
                        class="text-lg font-bold text-gray-800 mb-5"
                    >
                        Tình trạng sửa chữa
                    </h2>

                    <div class="space-y-5">

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                Tình trạng
                            </label>

                            <textarea
                                :value="form.issue.join(', ')"
                                @input="
                                    form.issue = $event.target.value
                                        .split(',')
                                        .map(v => v.trim())
                                        .filter(Boolean)
                                "
                                rows="3"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            />

                        </div>

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                Yêu cầu sửa chữa
                            </label>

                            <textarea
                                v-model="form.repair_request"
                                rows="4"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            />

                        </div>

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                Ghi chú kỹ thuật
                            </label>

                            <textarea
                                v-model="form.note"
                                rows="4"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            />

                        </div>

                    </div>

                </div>

                <!-- COST -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6"
                >

                    <h2
                        class="text-lg font-bold text-gray-800 mb-5"
                    >
                        Chi phí
                    </h2>

                    <div
                        class="grid grid-cols-1 md:grid-cols-3 gap-5"
                    >

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                Chi phí dự kiến
                            </label>

                            <input
                                v-model="form.estimated_cost"
                                type="text"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            >

                        </div>

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                Chi phí thực tế
                            </label>

                            <input
                                v-model="form.final_cost"
                                type="text"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            >

                        </div>

                        <div>

                            <label
                                class="block mb-2 text-sm font-medium"
                            >
                                Trạng thái
                            </label>

                            <select
                                v-model="form.status"
                                class="w-full rounded-2xl border border-gray-300 px-4 py-3"
                            >

                                <option value="pending">
                                    Mới nhận
                                </option>

                                <option value="checking">
                                    Đang kiểm tra
                                </option>

                                <option value="waiting_parts">
                                    Chờ linh kiện
                                </option>

                                <option value="repairing">
                                    Đang sửa
                                </option>

                                <option value="done">
                                    Hoàn thành
                                </option>

                                <option value="returned">
                                    Đã trả khách
                                </option>

                                <option value="cancelled">
                                    Đã hủy
                                </option>

                            </select>

                        </div>

                    </div>

                </div>

                <!-- IMAGES -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6"
                >

                    <h2
                        class="text-lg font-bold text-gray-800 mb-5"
                    >
                        Upload ảnh mới
                    </h2>

                    <input
                        type="file"
                        multiple
                        @change="
                            form.images = Array.from(
                                $event.target.files
                            )
                        "
                    >

                </div>

                <!-- SUBMIT -->

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-2xl bg-blue-600 hover:bg-blue-700 text-white py-4 font-bold transition disabled:opacity-50"
                >

                    {{
                        form.processing
                            ? 'Đang lưu...'
                            : 'Lưu cập nhật'
                    }}

                </button>

            </form>

        </div>

    </div>

</template>