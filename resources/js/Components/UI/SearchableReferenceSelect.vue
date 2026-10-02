<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Plus, Search } from 'lucide-vue-next'

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    label: { type: String, required: true },
    optionLabel: { type: String, default: 'name' },
    optionValue: { type: String, default: 'id' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'create'])
const query = ref('')
const open = ref(false)
const root = ref(null)

const selected = computed(() => props.options.find(option =>
    String(option[props.optionValue] ?? '') === String(props.modelValue ?? '')
))

watch(selected, option => {
    if (!open.value) query.value = option?.[props.optionLabel] || ''
}, { immediate: true })

const filteredOptions = computed(() => {
    const value = query.value.trim().toLocaleLowerCase()
    return props.options.filter(option =>
        !value || String(option[props.optionLabel] || '').toLocaleLowerCase().includes(value)
    )
})

const select = option => {
    emit('update:modelValue', option[props.optionValue])
    query.value = option[props.optionLabel] || ''
    open.value = false
}

const create = () => emit('create', query.value.trim())

const close = event => {
    if (!root.value?.contains(event.target)) open.value = false
}

if (typeof document !== 'undefined') document.addEventListener('mousedown', close)
onBeforeUnmount(() => {
    if (typeof document !== 'undefined') document.removeEventListener('mousedown', close)
})
</script>

<template>
    <div ref="root" class="relative w-full">
        <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">{{ label }}</label>
        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                v-model="query"
                :disabled="disabled"
                type="text"
                class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100"
                :placeholder="`Tìm ${label.toLocaleLowerCase()}...`"
                @focus="open = true"
                @input="open = true"
            />
        </div>
        <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
        <div v-if="open && !disabled" class="absolute z-40 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl">
            <button
                v-for="option in filteredOptions"
                :key="option[optionValue] ?? 'empty'"
                type="button"
                class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50"
                @mousedown.prevent="select(option)"
            >
                {{ option[optionLabel] }}
            </button>
            <div v-if="!filteredOptions.length" class="px-3 py-2 text-sm text-slate-500">Không tìm thấy dữ liệu phù hợp.</div>
            <button type="button" class="flex w-full items-center gap-2 border-t border-slate-100 px-3 py-2 text-left text-sm font-semibold text-blue-700 hover:bg-blue-50" @mousedown.prevent="create">
                <Plus class="h-4 w-4" />
                {{ query.trim() ? `Tạo mới “${query.trim()}”` : `Tạo ${label.toLocaleLowerCase()} mới` }}
            </button>
        </div>
    </div>
</template>
