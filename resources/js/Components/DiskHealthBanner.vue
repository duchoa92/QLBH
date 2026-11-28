<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import { diskAlertCollapsedLevel, setDiskAlertCollapsed } from '@/Stores/diskAlert'

const page = usePage()

const health = computed(() => page.props.diskHealth || null)
const danger = computed(() => health.value?.level === 'danger')
// Đã ẩn thì thông báo được gộp vào nút chức năng nổi (xem FloatingActions.vue): bấm nút đó để xem lại.
const collapsed = computed(() => !!health.value && diskAlertCollapsedLevel.value === health.value.level)
const barColor = computed(() => {
    const level = health.value?.capacity_level
    return level === 'danger' ? 'bg-red-600' : level === 'warning' ? 'bg-amber-500' : 'bg-emerald-600'
})
const collapse = () => setDiskAlertCollapsed(health.value?.level ?? null)
</script>

<template>
    <div
        v-if="health && !collapsed"
        role="alert"
        class="flex flex-wrap items-start gap-x-3 gap-y-2 rounded-lg border px-3 py-2.5 text-sm shadow-sm"
        :class="danger ? 'border-red-300 bg-red-50 text-red-800' : 'border-amber-300 bg-amber-50 text-amber-900'"
    >
        <span class="text-lg leading-none" aria-hidden="true">{{ danger ? '🚨' : '⚠️' }}</span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ danger ? 'Cảnh báo ổ đĩa nghiêm trọng' : 'Cảnh báo ổ đĩa' }}</p>
            <!-- Nội dung lỗi trước -->
            <ul class="mt-0.5 list-disc space-y-0.5 pl-5">
                <li v-for="message in health.messages" :key="message">{{ message }}</li>
            </ul>
            <!-- Dung lượng dạng đồ họa -->
            <div v-if="health.used_percent !== null" class="mt-2 max-w-xl">
                <div class="relative h-3 overflow-hidden rounded-full bg-white/70 ring-1 ring-black/10" role="img" :aria-label="`Đã dùng ${health.used_percent}% dung lượng ổ đĩa`">
                    <div class="h-full rounded-full transition-all" :class="barColor" :style="{ width: Math.min(100, health.used_percent) + '%' }" />
                    <span class="absolute inset-y-0 w-px bg-black/30" :style="{ left: health.warning_percent + '%' }" />
                    <span class="absolute inset-y-0 w-px bg-black/30" :style="{ left: health.danger_percent + '%' }" />
                </div>
                <div class="mt-1 flex flex-wrap justify-between gap-x-3 text-xs">
                    <span><b>{{ health.used_percent }}%</b> đã dùng</span>
                    <span>Còn trống <b>{{ health.free_gb }} GB</b> / {{ health.total_gb }} GB</span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <Link
                :href="route('backups.index')"
                class="rounded-md px-3 py-1.5 font-semibold text-white"
                :class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700'"
            >
                {{ health.backup_now ? 'Sao lưu ngay' : 'Mở trang sao lưu' }}
            </Link>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-md hover:bg-black/10 focus:outline-none focus:ring-2"
                :class="danger ? 'focus:ring-red-400' : 'focus:ring-amber-400'"
                title="Ẩn thông báo (vẫn còn biểu tượng cảnh báo trên nút chức năng)"
                aria-label="Ẩn thông báo"
                @click="collapse"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
