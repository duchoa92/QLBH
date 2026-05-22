<script setup>
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({

    repair: Object,
});

// Nhãn hiển thị cho từng trạng thái
const statusLabels = {

    pending: 'Mới nhận',

    checking: 'Đang kiểm tra',

    waiting_parts: 'Chờ linh kiện',

    repairing: 'Đang sửa',

    done: 'Hoàn thành',

    returned: 'Đã trả khách',

    cancelled: 'Đã hủy',
};

// class tương ứng với từng trạng thái
const statusClasses = {

    pending:
        'bg-yellow-100 text-yellow-700',

    checking:
        'bg-blue-100 text-blue-700',

    waiting_parts:
        'bg-orange-100 text-orange-700',

    repairing:
        'bg-indigo-100 text-indigo-700',

    done:
        'bg-green-100 text-green-700',

    returned:
        'bg-gray-100 text-gray-700',

    cancelled:
        'bg-red-100 text-red-700',
};

// Cập nhật trạng thái sửa chữa
const updateStatus = event => {

    router.patch(

        route(
            'repairs.update-status',
            props.repair.id
        ),

        {

            status: event.target.value,
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
                        {{ repair.code }}
                    </h1>

                    <p
                        class="text-sm text-gray-500 mt-1"
                    >
                        Chi tiết phiếu sửa chữa
                    </p>

                </div>

                <div class="flex gap-3">

                   <a
                        :href="route('repairs.print', repair.id)"
                        target="_blank"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition"
                    >
                        In phiếu
                    </a>

                    <Link
                        :href="route('repairs.index')"
                        class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 transition"
                    >
                        Quay lại
                    </Link>

                </div>

            </div>

        </div>

        <div
            class="max-w-7xl mx-auto px-4 md:px-6 py-6 grid grid-cols-1 xl:grid-cols-3 gap-6"
        >

            <!-- LEFT -->

            <div class="xl:col-span-2 space-y-6">

                <!-- CUSTOMER -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >

                    <div
                        class="px-6 py-5 border-b border-gray-100"
                    >

                        <h2
                            class="text-lg font-bold text-gray-800"
                        >
                            Thông tin khách hàng
                        </h2>

                    </div>

                    <div
                        class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5"
                    >

                        <div>

                            <div
                                class="text-sm text-gray-500"
                            >
                                Khách hàng
                            </div>

                            <div
                                class="font-semibold text-gray-800 mt-1"
                            >
                                {{
                                    repair.customer_name
                                }}
                            </div>

                        </div>

                        <div>

                            <div
                                class="text-sm text-gray-500"
                            >
                                Số điện thoại
                            </div>

                            <div
                                class="font-semibold text-gray-800 mt-1"
                            >
                                {{
                                    repair.customer_phone
                                }}
                            </div>

                        </div>

                        <div>

                            <div
                                class="text-sm text-gray-500"
                            >
                                Liên hệ khác
                            </div>

                            <div
                                class="font-semibold text-gray-800 mt-1"
                            >
                                {{
                                    repair.contact_phone || '---'
                                }}
                            </div>

                        </div>

                    </div>

                </div>

                <!-- DEVICE -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >

                    <div
                        class="px-6 py-5 border-b border-gray-100"
                    >

                        <h2
                            class="text-lg font-bold text-gray-800"
                        >
                            Thông tin thiết bị
                        </h2>

                    </div>

                    <div
                        class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5"
                    >

                        <div>

                            <div
                                class="text-sm text-gray-500"
                            >
                                Thiết bị
                            </div>

                            <div
                                class="font-semibold text-gray-800 mt-1"
                            >
                                {{
                                    repair.device_name
                                }}
                            </div>

                        </div>

                        <div>

                            <div
                                class="text-sm text-gray-500"
                            >
                                IMEI
                            </div>

                            <div
                                class="font-semibold text-gray-800 mt-1"
                            >
                                {{
                                    repair.imei || '---'
                                }}
                            </div>

                        </div>

                        <div>

                            <div
                                class="text-sm text-gray-500"
                            >
                                Mật khẩu màn hình
                            </div>

                            <div
                                class="font-semibold text-gray-800 mt-1"
                            >
                                {{
                                    repair.screen_password || '---'
                                }}
                            </div>

                        </div>

                        <div>

                            <div
                                class="text-sm text-gray-500"
                            >
                                Tài khoản
                            </div>

                            <div
                                class="font-semibold text-gray-800 mt-1"
                            >
                                {{
                                    repair.account_type || '---'
                                }}
                            </div>

                        </div>

                    </div>

                </div>

                <!-- ISSUE -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >

                    <div
                        class="px-6 py-5 border-b border-gray-100"
                    >

                        <h2
                            class="text-lg font-bold text-gray-800"
                        >
                            Tình trạng máy
                        </h2>

                    </div>

                    <div class="p-6">

                        <!-- issue -->

                        <div
                            class="flex flex-wrap gap-2 mb-6"
                        >

                            <div
                                v-for="item in (repair.issue || [])"
                                :key="item"
                                class="bg-red-100 text-red-700 px-4 py-2 rounded-full text-sm font-semibold"
                            >
                                {{ item }}
                            </div>

                        </div>

                        <!-- repair request -->

                        <div class="mb-6">

                            <div
                                class="text-sm text-gray-500 mb-2"
                            >
                                Yêu cầu sửa chữa
                            </div>

                            <div
                                class="rounded-2xl bg-gray-50 p-4 text-gray-700"
                            >
                                {{
                                    repair.repair_request || '---'
                                }}
                            </div>

                        </div>

                        <!-- accessories -->

                        <div class="mb-6">

                            <div
                                class="text-sm text-gray-500 mb-2"
                            >
                                Phụ kiện kèm theo
                            </div>

                            <div
                                class="rounded-2xl bg-gray-50 p-4 text-gray-700"
                            >
                                {{
                                    repair.accessories?.join(', ') || '---'
                                }}
                            </div>

                        </div>

                        <!-- note -->

                        <div>

                            <div
                                class="text-sm text-gray-500 mb-2"
                            >
                                Ghi chú
                            </div>

                            <div
                                class="rounded-2xl bg-gray-50 p-4 text-gray-700 whitespace-pre-line"
                            >
                                {{
                                    repair.note || '---'
                                }}
                            </div>

                        </div>

                    </div>

                </div>

                <!-- IMAGES -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >

                    <div
                        class="px-6 py-5 border-b border-gray-100"
                    >

                        <h2
                            class="text-lg font-bold text-gray-800"
                        >
                            Ảnh tình trạng máy
                        </h2>

                    </div>

                    <div class="p-6">

                        <div
                            v-if="repair.images?.length"
                            class="grid grid-cols-2 md:grid-cols-3 gap-4"
                        >

                            <img
                                v-for="image in repair.images"
                                :key="image.id"
                                :src="`/storage/${image.image_path}`"
                                class="w-full h-40 object-cover rounded-2xl border border-gray-200"
                            >

                        </div>

                        <div
                            v-else
                            class="text-gray-400"
                        >
                            Không có hình ảnh
                        </div>

                    </div>

                </div>

            </div>

            <!-- RIGHT -->

            <div class="space-y-6">

                <!-- STATUS -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >

                    <div
                        class="px-6 py-5 border-b border-gray-100"
                    >

                        <h2
                            class="text-lg font-bold text-gray-800"
                        >
                            Trạng thái sửa chữa
                        </h2>

                    </div>

                    <div class="p-6">

                        <span
                            :class="[

                                'inline-flex items-center px-4 py-2 rounded-full text-sm font-bold',

                                statusClasses[
                                    repair.status
                                ] || 'bg-gray-100 text-gray-700'
                            ]"
                        >
                            {{
                                statusLabels[
                                    repair.status
                                ] || repair.status
                            }}
                        </span>

                        <div class="mt-5">

                        
                        <!-- Dropdown đổi trạng thái -->

                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Đổi trạng thái
                        </label>

                        <select
                            :value="repair.status"
                            @change="updateStatus"
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

                        <div class="mt-6 space-y-4">

                            <div>

                                <div
                                    class="text-sm text-gray-500"
                                >
                                    Ngày nhận
                                </div>

                                <div
                                    class="font-semibold text-gray-800 mt-1"
                                >
                                    {{
                                        repair.received_at || '---'
                                    }}
                                </div>

                            </div>

                            <div>

                                <div
                                    class="text-sm text-gray-500"
                                >
                                    Kỹ thuật viên
                                </div>

                                <div
                                    class="font-semibold text-gray-800 mt-1"
                                >
                                    {{
                                        repair.technician?.name || '---'
                                    }}
                                </div>

                            </div>

                            <div>

                                <div
                                    class="text-sm text-gray-500"
                                >
                                    Chi phí dự kiến
                                </div>

                                <div
                                    class="font-bold text-green-600 mt-1 text-lg"
                                >
                                    {{
                                        Number(
                                            repair.estimated_cost || 0
                                        ).toLocaleString('vi-VN')
                                    }} đ
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- TIMELINE -->

                <div
                    class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
                >

                    <div
                        class="px-6 py-5 border-b border-gray-100"
                    >

                        <h2
                            class="text-lg font-bold text-gray-800"
                        >
                            Timeline
                        </h2>

                    </div>

                    <div class="p-6">

                        <div
                            class="relative border-l-2 border-blue-200 ml-3 pl-6 space-y-8"
                        >

                            <div
                                v-for="timeline in (repair.timelines || [])"
                                :key="timeline.id"
                                class="relative"
                            >

                                <div
                                    class="absolute -left-[34px] top-1 w-4 h-4 rounded-full bg-blue-600"
                                />

                                <div
                                    class="font-semibold text-gray-800"
                                >
                                    {{ timeline.title }}
                                </div>

                                <div
                                    v-if="timeline.description"
                                    class="text-sm text-gray-600 mt-1"
                                >
                                    {{ timeline.description }}
                                </div>

                                <div
                                    class="text-xs text-gray-400 mt-2"
                                >

                                    {{ timeline.created_at }}

                                    <span v-if="timeline.user">
                                        • {{ timeline.user.name }}
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</template>