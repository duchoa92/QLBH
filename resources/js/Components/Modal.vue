<script setup>
import { computed } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: '2xl' },
})

const emit = defineEmits(['close'])

const size = computed(() => ({
    sm: 'sm',
    md: 'md',
    lg: 'lg',
    xl: 'xl',
    '2xl': 'xl',
}[props.maxWidth] || 'xl'))
</script>

<template>
    <BaseModal v-if="show" :title="title" :size="size" @close="emit('close')">
        <slot />
        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </BaseModal>
</template>
