<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const problem = computed(() => page.props.schedulerHealth || null)
</script>

<template>
    <div v-if="problem" role="alert" class="flex flex-wrap items-start gap-x-3 gap-y-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-red-800 shadow-sm">
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
        <Link v-if="problem.backup_enabled" :href="route('backups.index')" class="rounded-md bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700">Sao lưu thủ công ngay</Link>
    </div>
</template>
