<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import { collapseAlert, collapsedAlerts } from '@/Stores/systemAlerts'

// Cảnh báo mạng (IP máy chủ chưa cố định / lệch cấu hình). Ẩn được: khi ẩn sẽ gộp vào nút chức năng nổi.
const page = usePage()
const problem = computed(() => page.props.networkHealth || null)
const collapsed = computed(() => !!problem.value && collapsedAlerts.network === problem.value.title)
</script>

<template>
    <div v-if="problem && !collapsed" role="alert" class="flex flex-wrap items-start gap-x-3 gap-y-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-900 shadow-sm">
        <span class="text-lg leading-none" aria-hidden="true">🌐</span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ problem.title }}</p>
            <p class="mt-0.5">{{ problem.message }}</p>
        </div>
        <div class="flex items-center gap-2">
            <Link :href="route('settings.index', { section: 'network' })" class="rounded-md bg-amber-600 px-3 py-1.5 font-semibold text-white hover:bg-amber-700">Mở cài đặt mạng</Link>
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-md hover:bg-black/10 focus:outline-none focus:ring-2 focus:ring-amber-400" title="Ẩn thông báo (vẫn còn biểu tượng cảnh báo trên nút chức năng)" aria-label="Ẩn thông báo" @click="collapseAlert('network', problem.title)">
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
