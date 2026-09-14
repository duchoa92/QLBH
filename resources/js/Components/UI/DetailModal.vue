<script setup>
import BaseModal from '@/Components/UI/BaseModal.vue'

defineProps({
    title: {
        type: String,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    size: {
        type: String,
        default: 'lg',
    },
})

const emit = defineEmits(['close'])
</script>

<template>
    <BaseModal
        :title="title"
        :size="size"
        @close="emit('close')"
    >
        <div class="grid gap-3 sm:grid-cols-2">
            <div
                v-for="row in rows"
                :key="row.label"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                :class="row.full ? 'sm:col-span-2' : ''"
            >
                <div class="text-xs font-semibold uppercase text-slate-400">
                    {{ row.label }}
                </div>

                <div class="mt-1 break-words text-sm font-medium text-slate-800">
                    {{ row.value || '-' }}
                </div>
            </div>
        </div>

        <slot />
    </BaseModal>
</template>
