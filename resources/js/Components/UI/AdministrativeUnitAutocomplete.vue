<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    label: { type: String, required: true },
    placeholder: { type: String, default: 'Gõ để tìm...' },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    id: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue', 'input', 'select'])
const isOpen = ref(false)
const activeIndex = ref(-1)

const normalize = value => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLocaleLowerCase('vi')
    .trim()

const filteredOptions = computed(() => {
    const query = normalize(props.modelValue)
    const matches = query
        ? props.options.filter(option => normalize(option.name).includes(query))
        : props.options

    return matches.slice(0, 40)
})

const handleInput = event => {
    emit('update:modelValue', event.target.value)
    emit('input', event.target.value)
    activeIndex.value = -1
    isOpen.value = true
}

const selectOption = option => {
    emit('update:modelValue', option.name)
    emit('select', option)
    isOpen.value = false
    activeIndex.value = -1
}

const handleKeydown = event => {
    if (event.key === 'ArrowDown') {
        event.preventDefault()
        isOpen.value = true
        activeIndex.value = filteredOptions.value.length
            ? (activeIndex.value + 1) % filteredOptions.value.length
            : -1
    } else if (event.key === 'ArrowUp') {
        event.preventDefault()
        isOpen.value = true
        activeIndex.value = filteredOptions.value.length
            ? (activeIndex.value <= 0 ? filteredOptions.value.length - 1 : activeIndex.value - 1)
            : -1
    } else if (event.key === 'Enter' && isOpen.value && activeIndex.value >= 0) {
        event.preventDefault()
        selectOption(filteredOptions.value[activeIndex.value])
    } else if (event.key === 'Escape') {
        if (!isOpen.value) return
        event.preventDefault()
        event.stopPropagation()
        isOpen.value = false
        activeIndex.value = -1
    }
}
</script>

<template>
    <div class="relative w-full">
        <input
            :id="id"
            :value="modelValue"
            type="text"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="isOpen"
            :aria-controls="`${id}-options`"
            :aria-activedescendant="activeIndex >= 0 ? `${id}-option-${activeIndex}` : undefined"
            placeholder=" "
            class="peer w-full rounded-lg border border-gray-300 bg-white px-2 pb-2 pt-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-0"
            @input="handleInput"
            @focus="isOpen = true"
            @keydown="handleKeydown"
            @blur="isOpen = false"
        />
        <label
            :for="id"
            class="pointer-events-none absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500"
        >{{ label }}</label>

        <div
            v-if="error"
            class="mt-1 text-sm text-red-500"
        >{{ error }}</div>

        <ul
            v-if="isOpen"
            :id="`${id}-options`"
            role="listbox"
            class="absolute left-0 right-0 z-[80] mt-1 max-h-60 overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
        >
            <li v-if="loading" class="px-3 py-2 text-sm text-slate-500">Đang tải danh sách...</li>
            <li v-else-if="!filteredOptions.length" class="px-3 py-2 text-sm text-slate-500">Không có kết quả phù hợp; bạn vẫn có thể nhập thủ công.</li>
            <li
                v-for="(option, index) in filteredOptions"
                :id="`${id}-option-${index}`"
                :key="option.code"
                role="option"
                :aria-selected="index === activeIndex"
                class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-emerald-50"
                :class="index === activeIndex ? 'bg-emerald-50 text-emerald-800' : ''"
                @mousedown.prevent="selectOption(option)"
            >{{ option.name }}</li>
        </ul>
    </div>
</template>
