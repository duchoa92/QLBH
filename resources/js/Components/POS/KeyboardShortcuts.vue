<script setup>

defineProps({

    show: {
        type: Boolean,
        default: false,
    },
})

defineEmits([
    'toggle',
])

</script>

<template>

    <div class="mb-4">

        <!-- Toggle -->

        <button
            @click="$emit('toggle')"
            class="text-sm text-blue-600 hover:underline"
        >

            {{
                show
                    ? 'Ẩn phím tắt'
                    : 'Xem phím tắt'
            }}

        </button>

        <!-- Content -->

        <div
            v-if="show"
            class="mt-2 border rounded p-3 bg-gray-50"
        >

            <div class="font-bold mb-2">
                Phím tắt POS
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm">

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        F2
                    </kbd>

                    → Thanh toán
                </div>

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        ESC
                    </kbd>

                    → Đóng popup
                </div>

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        DELETE
                    </kbd>

                    → Xóa sản phẩm
                </div>

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        CTRL + DELETE
                    </kbd>

                    → Xóa giỏ hàng
                </div>

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        +
                    </kbd>

                    → Tăng SL
                </div>

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        -
                    </kbd>

                    → Giảm SL
                </div>

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        ↑ ↓
                    </kbd>

                    → Chọn dòng
                </div>

                <div>
                    <kbd
                        class="px-2 py-1 bg-white border rounded shadow-sm text-xs"
                    >
                        ENTER
                    </kbd>

                    → Xác nhận
                </div>

            </div>

        </div>

    </div>

</template>