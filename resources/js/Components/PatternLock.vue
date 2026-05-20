<script setup>
import {
    ref,
    onMounted,
    onBeforeUnmount,
} from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'update:modelValue',
]);

// danh sách chấm
const dots = [
    1, 2, 3,
    4, 5, 6,
    7, 8, 9,
];

// các chấm đã chọn
const selectedDots = ref([]);

// trạng thái đang vẽ
const isDrawing = ref(false);

// bắt đầu vẽ
const startDraw = dot => {

    isDrawing.value = true;

    selectedDots.value = [dot];

    updateValue();
};

// rê qua chấm khác
const handleEnter = dot => {

    if (!isDrawing.value) {
        return;
    }

    if (
        !selectedDots.value.includes(dot)
    ) {

        selectedDots.value.push(dot);

        updateValue();
    }
};

// kết thúc vẽ
const stopDraw = () => {

    isDrawing.value = false;
};

// cập nhật dữ liệu
const updateValue = () => {

    emit(
        'update:modelValue',
        selectedDots.value.join('-')
    );
};

// reset
const reset = () => {

    selectedDots.value = [];

    emit(
        'update:modelValue',
        ''
    );
};

// sự kiện global
onMounted(() => {

    window.addEventListener(
        'mouseup',
        stopDraw
    );

    window.addEventListener(
        'touchend',
        stopDraw
    );
});

onBeforeUnmount(() => {

    window.removeEventListener(
        'mouseup',
        stopDraw
    );

    window.removeEventListener(
        'touchend',
        stopDraw
    );
});
</script>

<template>
    <div>

        <div
            class="grid grid-cols-3 gap-6 w-52 mx-auto select-none"
        >

            <div
                v-for="dot in dots"
                :key="dot"

                class="w-14 h-14 rounded-full border-4 flex items-center justify-center cursor-pointer transition"

                :class="
                    selectedDots.includes(dot)
                        ? 'bg-blue-500 border-blue-600 text-white'
                        : 'bg-white border-gray-400'
                "

                @mousedown="startDraw(dot)"
                @mouseenter="handleEnter(dot)"

                @touchstart.prevent="startDraw(dot)"
                @touchmove.prevent
            >
                {{ dot }}
            </div>
        </div>

        <div
            class="mt-4 text-center text-sm text-gray-500"
        >
            Mẫu hình:
            {{ modelValue || 'Chưa nhập' }}
        </div>

        <div class="mt-4 flex justify-center">

            <button
                type="button"

                class="px-4 py-2 bg-gray-200 rounded"

                @click="reset"
            >
                Xóa mẫu hình
            </button>
        </div>
    </div>
</template>