<script setup>
import { onMounted, ref } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'

const props = defineProps({
    type: { type: String, required: true },
    recordId: { type: [Number, String], required: true },
})

defineEmits(['close'])

const detail = ref(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
    try {
        const { data } = await window.axios.get(route('records.show', {
            type: props.type,
            id: props.recordId,
        }), {
            headers: { Accept: 'application/json' },
        })
        detail.value = data
    } catch {
        error.value = 'Không tải được thông tin chi tiết.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <BaseModal :title="detail?.title || 'Chi tiết'" size="lg" body-class="p-4 sm:p-5" @close="$emit('close')">
        <p v-if="detail?.subtitle" class="mb-4 text-sm font-medium text-slate-500">{{ detail.subtitle }}</p>

        <div v-if="loading" class="py-10 text-center text-sm text-slate-500">Đang tải chi tiết…</div>
        <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
        <div v-else class="grid gap-2 sm:grid-cols-2">
            <div v-for="row in detail?.rows || []" :key="row.label" class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ row.label }}</div>
                <div class="mt-1 break-words text-sm font-medium text-slate-800">{{ row.value || '—' }}</div>
            </div>
            <div v-if="!detail?.rows?.length" class="sm:col-span-2 py-6 text-center text-sm text-slate-500">Chưa có thông tin chi tiết.</div>
        </div>
    </BaseModal>
</template>
