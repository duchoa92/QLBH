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
    <div class="cart-tabs-scroll flex w-full items-center gap-1.5 overflow-x-auto pb-2">
        <!-- Danh sách các tab đơn hàng -->
        <button
            v-for="(tab, index) in tabs"
            :key="tab.id || index"
            type="button"
            class="group relative flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition-all shrink-0"
            :class="[
                activeTabId === tab.id
                    ? 'bg-blue-600 text-white shadow-sm'
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
            class="flex items-center justify-center rounded-xl bg-slate-100 p-1.5 text-slate-500 hover:bg-blue-50 hover:text-blue-600 transition-colors shrink-0"
            title="Thêm đơn hàng mới"
            @click="emit('create')"
        >
            <Plus class="h-4 w-4" />
        </button>
    </div>
</template>

<style scoped>
.cart-tabs-scroll {
    scrollbar-color: #94a3b8 transparent;
    scrollbar-width: thin;
}

.cart-tabs-scroll::-webkit-scrollbar {
    height: 6px;
}

.cart-tabs-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.cart-tabs-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: #94a3b8;
}
</style>
