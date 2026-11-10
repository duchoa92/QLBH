<script setup>
// Ảnh đại diện: có ảnh thì hiện ảnh, không thì hiện chữ cái đầu với màu cố định theo tên.
import { computed } from 'vue'
import { imageUrl } from '@/utils/imageUrl'

const props = defineProps({
    name: { type: String, default: '' },
    image: { type: String, default: '' },
    icon: { type: Object, default: null },
    size: { type: String, default: 'lg' },
})

const palette = [
    'bg-emerald-100 text-emerald-700', 'bg-blue-100 text-blue-700', 'bg-violet-100 text-violet-700',
    'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700', 'bg-cyan-100 text-cyan-700',
]
const tone = computed(() => palette[[...props.name].reduce((sum, char) => sum + char.charCodeAt(0), 0) % palette.length])
const initials = computed(() => {
    const words = props.name.trim().split(/\s+/).filter(Boolean)
    if (!words.length) return '?'
    return (words.length === 1 ? words[0].slice(0, 2) : words[0][0] + words[words.length - 1][0]).toUpperCase()
})
const box = computed(() => props.size === 'lg' ? 'h-14 w-14 text-lg' : 'h-10 w-10 text-sm')
</script>

<template>
    <span class="grid shrink-0 place-items-center overflow-hidden rounded-2xl font-black ring-2 ring-white" :class="[box, image ? 'bg-slate-100' : tone]">
        <img v-if="image" :src="imageUrl(image)" :alt="name" class="h-full w-full object-cover" />
        <component :is="icon" v-else-if="icon" :size="size === 'lg' ? 26 : 20" />
        <template v-else>{{ initials }}</template>
    </span>
</template>
