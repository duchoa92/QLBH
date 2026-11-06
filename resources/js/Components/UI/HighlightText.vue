<script setup>
import { computed } from 'vue'

// Tô vàng phần khớp với từ khóa, không phân biệt hoa/thường và dấu tiếng Việt
const props = defineProps({
    text: { type: [String, Number], default: '' },
    keyword: { type: String, default: '' },
})

const fold = (value) => {
    const text = String(value ?? '')
    let normalized = ''
    const sourceIndexes = []

    Array.from(text).forEach((character, sourceIndex) => {
        const folded = character
            .normalize('NFD')
            .replace(/\p{Diacritic}/gu, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLocaleLowerCase('vi-VN')

        Array.from(folded).forEach((foldedCharacter) => {
            normalized += foldedCharacter
            sourceIndexes.push(sourceIndex)
        })
    })

    return { text, normalized, sourceIndexes }
}

const segments = computed(() => {
    const searchable = fold(props.text)
    const keyword = fold(props.keyword.trim()).normalized
    if (!keyword) return [{ text: searchable.text, match: false }]

    const normalizedStart = searchable.normalized.indexOf(keyword)
    if (normalizedStart < 0) return [{ text: searchable.text, match: false }]

    const start = searchable.sourceIndexes[normalizedStart]
    const endIndex = normalizedStart + keyword.length - 1
    const end = (searchable.sourceIndexes[endIndex] ?? start) + 1

    return [
        { text: searchable.text.slice(0, start), match: false },
        { text: searchable.text.slice(start, end), match: true },
        { text: searchable.text.slice(end), match: false },
    ].filter(segment => segment.text)
})
</script>

<template>
    <template v-for="(segment, index) in segments" :key="index"><mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark><template v-else>{{ segment.text }}</template></template>
</template>
