<script setup>
// Khung chung cho các modal xem chi tiết: tiêu đề có ảnh đại diện, hàng số liệu, tab và nội dung.
import { X } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'

defineProps({
    size: { type: String, default: 'lg' },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
})

defineEmits(['close'])
</script>

<template>
    <BaseModal :size="size" body-class="p-0" @close="$emit('close')">
        <template #header>
            <!-- margin âm để phủ kín vùng padding mặc định của BaseModal -->
            <div class="relative -mx-4 -my-3 min-w-0 flex-1 self-stretch bg-gradient-to-br from-slate-50 via-white to-emerald-50/50 px-5 py-4 pr-14">
                <button
                    type="button"
                    class="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-white hover:text-rose-500"
                    aria-label="Đóng"
                    @click="$emit('close')"
                >
                    <X :size="18" />
                </button>
                <slot name="header" />
            </div>
        </template>

        <div v-if="loading" class="py-16 text-center text-sm text-slate-500">Đang tải chi tiết…</div>
        <div v-else-if="error" class="m-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
        <template v-else>
            <div v-if="$slots.stats" class="grid grid-cols-2 gap-2.5 px-5 pt-4 lg:grid-cols-4"><slot name="stats" /></div>
            <div v-if="$slots.tabs" class="px-5 pt-4"><slot name="tabs" /></div>
            <div class="space-y-4 p-5"><slot /></div>
        </template>

        <template v-if="$slots.footer" #footer><slot name="footer" /></template>
    </BaseModal>
</template>
