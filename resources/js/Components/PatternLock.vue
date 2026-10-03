<script setup>
import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
    watch,
} from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:modelValue',
]);

const dots = [
    1, 2, 3,
    4, 5, 6,
    7, 8, 9,
];

const selectedDots = ref([]);
const isDrawing = ref(false);

watch(() => props.modelValue, (value) => {
    selectedDots.value = value
        ? value.split('-').map(Number).filter((dot) => dots.includes(dot))
        : [];
}, { immediate: true });

// Bắt đầu vẽ
const startDraw = (dot) => {
    if (props.readonly) return;
    isDrawing.value = true;
    selectedDots.value = [dot];
    updateValue();
};

// Rê qua chấm khác
const handleEnter = (dot) => {
    if (!isDrawing.value) return;
    if (!selectedDots.value.includes(dot)) {
        selectedDots.value.push(dot);
        updateValue();
    }
};

// Kết thúc vẽ
const stopDraw = () => {
    isDrawing.value = false;
};

// Cập nhật giá trị
const updateValue = () => {
    emit('update:modelValue', selectedDots.value.join('-'));
};

// Reset
const reset = () => {
    selectedDots.value = [];
    emit('update:modelValue', '');
};

/*
|--------------------------------------------------------------------------
| Tọa độ SVG để vẽ đường nối có MŨI TÊN CHỈ HƯỚNG
|--------------------------------------------------------------------------
| Khung 192x192px (W:48 x H:48 x gap:24) -> Tọa độ tâm mỗi nút (x, y):
| 1: (24, 24)   2: (96, 24)   3: (168, 24)
| 4: (24, 96)   5: (96, 96)   6: (168, 96)
| 7: (24, 168)  8: (96, 168)  9: (168, 168)
*/
const getDotCenter = (dot) => {
    const col = (dot - 1) % 3;
    const row = Math.floor((dot - 1) / 3);
    return {
        x: 24 + col * 72,
        y: 24 + row * 72,
    };
};

const lines = computed(() => {
    const result = [];
    for (let i = 0; i < selectedDots.value.length - 1; i++) {
        const start = getDotCenter(selectedDots.value[i]);
        const end = getDotCenter(selectedDots.value[i + 1]);
        result.push({
            x1: start.x,
            y1: start.y,
            x2: end.x,
            y2: end.y,
        });
    }
    return result;
});

onMounted(() => {
    window.addEventListener('mouseup', stopDraw);
    window.addEventListener('touchend', stopDraw);
});

onBeforeUnmount(() => {
    window.removeEventListener('mouseup', stopDraw);
    window.removeEventListener('touchend', stopDraw);
});
</script>

<template>
    <div>
        <!-- KHUNG CHÍNH CHỨA LƯỚI & LỚP VẼ SVG MŨI TÊN -->
        <div class="relative w-48 h-48 mx-auto select-none">
            <!-- LỚP SVG TỰ ĐỘNG VẼ ĐƯỜNG NỐI VÀ MŨI TÊN HƯỚNG VUỐT -->
            <svg 
                class="absolute inset-0 w-full h-full pointer-events-none z-10" 
                viewBox="0 0 192 192"
            >
                <defs>
                    <marker
                        id="arrow"
                        viewBox="0 0 10 10"
                        refX="22"
                        refY="5"
                        markerWidth="6"
                        markerHeight="6"
                        orient="auto-start-reverse"
                    >
                        <path d="M 0 1 L 10 5 L 0 9 z" fill="#2563eb" />
                    </marker>
                </defs>

                <line
                    v-for="(line, index) in lines"
                    :key="index"
                    :x1="line.x1"
                    :y1="line.y1"
                    :x2="line.x2"
                    :y2="line.y2"
                    stroke="#2563eb"
                    stroke-width="3"
                    stroke-linecap="round"
                    marker-end="url(#arrow)"
                />
            </svg>

            <!-- LƯỚI 9 CHẤM CỦA PATTERN LOCK -->
            <div class="grid grid-cols-3 gap-6 w-full h-full relative z-20">
                <div
                    v-for="dot in dots"
                    :key="dot"
                    class="w-12 h-12 rounded-full border-2 flex items-center justify-center font-bold text-xs transition"
                    :class="[
                        selectedDots.includes(dot)
                            ? 'bg-blue-600 border-blue-600 text-white shadow-md shadow-blue-200'
                            : 'bg-white border-slate-300 text-slate-500 hover:border-blue-400',
                        readonly ? 'cursor-default' : 'cursor-pointer'
                    ]"
                    @mousedown="startDraw(dot)"
                    @mouseenter="handleEnter(dot)"
                    @touchstart.prevent="startDraw(dot)"
                    @touchmove.prevent
                >
                    {{ dot }}
                </div>
            </div>
        </div>

        <!-- MÔ TẢ MẪU HÌNH -->
        <div class="mt-3 text-center text-xs font-semibold text-slate-600">
            Mẫu hình:
            <span class="text-blue-600 font-mono font-bold">{{ modelValue || 'Chưa nhập' }}</span>
        </div>

        <div v-if="!readonly && selectedDots.length > 0" class="mt-2 flex justify-center">
            <button
                type="button"
                class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] rounded-lg font-medium transition"
                @click="reset"
            >
                Xóa mẫu hình
            </button>
        </div>
    </div>
</template>