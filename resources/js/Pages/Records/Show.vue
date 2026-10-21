<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import PageHeader from '@/Components/UI/PageHeader.vue'

defineProps({
    type: { type: String, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
})

const goBack = () => {
    if (window.history.length > 1) window.history.back()
    else router.visit('/dashboard')
}
</script>

<template>
    <Head :title="subtitle ? title + ' · ' + subtitle : title" />
    <div class="space-y-3">
        <PageHeader :title="title" :description="subtitle">
            <template #actions>
                <button type="button" @click="goBack" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                    <ArrowLeft class="h-3.5 w-3.5" /> Quay lại
                </button>
            </template>
        </PageHeader>
        <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <dl class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="row in rows" :key="row.label" class="min-w-0 rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ row.label }}</dt>
                    <dd class="mt-0.5 break-words text-sm font-medium text-slate-800">{{ row.value }}</dd>
                </div>
                <div v-if="!rows.length" class="text-sm text-slate-500">Không có thông tin chi tiết.</div>
            </dl>
        </section>
    </div>
</template>
