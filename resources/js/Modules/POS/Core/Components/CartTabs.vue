<script setup>
import { Plus, X } from 'lucide-vue-next'

const props = defineProps({
    tabs: {
        type: Array,
        default: () => [],
    },
    activeTabId: {
        type: [Number, String],
        default: null,
    },
})

const emit = defineEmits(['select', 'create', 'remove'])
</script>

<template>
    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1">
        <!-- Danh sách các tab đơn hàng -->
        <button
            v-for="(tab, index) in tabs"
            :key="tab.id || index"
            type="button"
            class="group relative flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition-all shrink-0"
            :class="[
                activeTabId === tab.id
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900'
            ]"
            @click="emit('select', tab.id)"
        >
            <span>{{ tab.name || `Đơn ${index + 1}` }}</span>

            <!-- Nút đóng/xóa tab (ẩn nếu chỉ có 1 tab) -->
            <span
                v-if="tabs.length > 1"
                class="bg-red-500 rounded-full p-0.5 hover:bg-black/10 transition-colors"
                @click.stop="emit('remove', tab.id)"
                title="Đóng tab"
            >
                <X class="h-3 w-3 text-white" />
            </span>
        </button>

        <!-- Nút tạo tab đơn hàng mới -->
        <button
            type="button"
            class="flex items-center justify-center rounded-xl bg-slate-100 p-1.5 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors shrink-0"
            title="Thêm đơn hàng mới"
            @click="emit('create')"
        >
            <Plus class="h-4 w-4" />
        </button>
    </div>
</template>

<style scoped>
/* Ẩn thanh cuộn cho danh sách tab */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>