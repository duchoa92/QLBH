<script setup>
import { watch } from 'vue'
import { useAutocomplete } from '@/Composables/useAutocomplete'
import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'

const props = defineProps({
    fetcher: Function,
    placeholder: {
        type: String,
        default: 'Search...',
    },
    labelKey: {
        type: String,
        default: 'name',
    },
})

const emit = defineEmits(['select'])

const {
    keyword,
    results,
    search,
} = useAutocomplete(props.fetcher)

const {
    activeIndex,
    itemRefs,
    setItemRef,
    onKeyDown,
    setActive,
    reset,
} = useAutocompleteKeyboard(results, (item) => {
    emit('select', item)
    keyword.value = item[props.labelKey]
})

watch(keyword, search)

const selectItem = (item) => {
    emit('select', item)
    keyword.value = item[props.labelKey]
    reset()
}
</script>

<template>
    <div class="relative w-full">

        <!-- INPUT -->
        <input
            v-model="keyword"
            :placeholder="placeholder"
            @keydown="onKeyDown"
            class="w-full border rounded px-3 py-2"
        />

        <!-- DROPDOWN -->
        <ul
            v-if="results.length"
            class="absolute z-50 w-full bg-white border mt-1 max-h-60 overflow-auto"
        >
            <li
                v-for="(item, index) in results"
                :key="item.id"
                :ref="el => setItemRef(el, index)"
                @mouseenter="setActive(index)"
                @click="selectItem(item)"
                :class="[
                    'px-3 py-2 cursor-pointer',
                    index === activeIndex ? 'bg-blue-500 text-white' : '',
                ]"
            >
                {{ item[labelKey] }}
            </li>
        </ul>

    </div>
</template>