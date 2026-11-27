<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const dismissed = ref(false)

const health = computed(() => page.props.diskHealth || null)
const danger = computed(() => health.value?.level === 'danger')
const visible = computed(() => health.value && (danger.value || !dismissed.value))
</script>

<template>
    <div
        v-if="visible"
        role="alert"
        class="flex flex-wrap items-start gap-x-3 gap-y-2 rounded-lg border px-3 py-2.5 text-sm shadow-sm"
        :class="danger ? 'border-red-300 bg-red-50 text-red-800' : 'border-amber-300 bg-amber-50 text-amber-900'"
    >
        <span class="text-lg leading-none" aria-hidden="true">{{ danger ? '🚨' : '⚠️' }}</span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold">
                {{ danger ? 'Cảnh báo ổ đĩa nghiêm trọng' : 'Cảnh báo ổ đĩa' }}
                <span v-if="health.used_percent !== null" class="font-normal">
                    · đã dùng {{ health.used_percent }}% (còn trống {{ health.free_gb }} GB / {{ health.total_gb }} GB)
                </span>
            </p>
            <ul class="mt-0.5 list-disc space-y-0.5 pl-5">
                <li v-for="message in health.messages" :key="message">{{ message }}</li>
            </ul>
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
                v-if="!danger"
                type="button"
                class="rounded-md px-2 py-1.5 font-medium hover:bg-black/5"
                @click="dismissed = true"
            >
                Ẩn
            </button>
        </div>
    </div>
</template>
