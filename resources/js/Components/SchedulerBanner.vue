<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import { collapseAlert, collapsedAlerts } from '@/Stores/systemAlerts'

const page = usePage()
const problem = computed(() => page.props.schedulerHealth || null)
// Đã ẩn thì được gộp vào nút chức năng nổi (FloatingActions): bấm nút đó để xem lại.
const signature = computed(() => (problem.value?.backup_enabled ? 'backup' : 'plain'))
const collapsed = computed(() => !!problem.value && collapsedAlerts.scheduler === signature.value)
</script>

<template>
    <div v-if="problem && !collapsed" role="alert" class="flex flex-wrap items-start gap-x-3 gap-y-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-red-800 shadow-sm">
        <span class="text-lg leading-none" aria-hidden="true">⏱️</span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">Lịch chạy nền chưa hoạt động{{ problem.backup_enabled ? ': sao lưu tự động KHÔNG chạy' : '' }}</p>
            <p class="mt-0.5">
                {{ problem.last_run ? `Lần chạy gần nhất: ${problem.last_run} (${problem.minutes_ago} phút trước).` : 'Chưa ghi nhận lần chạy nào.' }}
                Sao lưu tự động, nhắc việc và dọn dữ liệu đều phụ thuộc vào lịch này.
            </p>
            <p class="mt-1 text-xs">
                <b>Cách khắc phục (Ubuntu):</b> chạy <code class="rounded bg-white/70 px-1">sudo bash scripts/install-ubuntu.sh</code>
                hoặc kiểm tra bằng <code class="rounded bg-white/70 px-1">sudo bash scripts/qlbh-doctor.sh</code>.
                Windows: mở Task Scheduler, kiểm tra tác vụ "QLBH Laravel Scheduler".
            </p>
        </div>
        <div class="flex items-center gap-2">
            <Link v-if="problem.backup_enabled" :href="route('backups.index')" class="rounded-md bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700">Sao lưu thủ công ngay</Link>
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-md hover:bg-black/10 focus:outline-none focus:ring-2 focus:ring-red-400" title="Ẩn thông báo (vẫn còn biểu tượng cảnh báo trên nút chức năng)" aria-label="Ẩn thông báo" @click="collapseAlert('scheduler', signature)">
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
