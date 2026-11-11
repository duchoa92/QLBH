<script setup>
import { computed } from 'vue'
import { LoaderCircle, Search, X } from 'lucide-vue-next'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: 'Tìm kiếm...' },
    loading: { type: Boolean, default: false },
    searchId: { type: String, default: undefined },
    searchClass: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'clear', 'focus', 'blur', 'submit'])
const hasValue = computed(() => String(props.modelValue ?? '').length > 0)

const input = event => emit('update:modelValue', event.target.value)
const clear = () => {
    emit('update:modelValue', '')
    emit('clear')
}
</script>

<template>
    <div class="app-list-toolbar-wrap">
        <!-- Hàng trên đầu: nút Tạo mới / Thêm mới... — tách riêng khỏi ô tìm kiếm & lọc -->
        <div v-if="$slots.actions" class="app-list-toolbar__top">
            <div class="app-list-toolbar__actions"><slot name="actions" /></div>
        </div>

        <div class="app-list-toolbar">
            <div class="app-list-toolbar__search" :class="searchClass">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    :id="searchId"
                    :value="modelValue"
                    type="search"
                    autocomplete="off"
                    :placeholder="placeholder"
                    class="app-list-toolbar__input"
                    @input="input"
                    @focus="emit('focus', $event)"
                    @blur="emit('blur', $event)"
                    @keydown.enter.prevent="emit('submit')"
                />
                <LoaderCircle v-if="loading" class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-emerald-600" />
                <button v-else-if="hasValue" type="button" class="app-list-toolbar__clear" title="Xóa từ khóa" aria-label="Xóa từ khóa" @click="clear">
                    <X class="h-3 w-3" />
                </button>
                <slot name="search-end" />
            </div>
            <div v-if="$slots.filters" class="app-list-toolbar__filters"><slot name="filters" /></div>
            <div v-if="$slots.expanded" class="app-list-toolbar__expanded"><slot name="expanded" /></div>
        </div>
    </div>
</template>

<style scoped>
.app-list-toolbar-wrap { @apply flex w-full flex-col gap-2; }
.app-list-toolbar__top { @apply flex w-full justify-end; }
.app-list-toolbar { @apply flex w-full flex-col items-stretch gap-2.5 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:p-4; }
.app-list-toolbar__search { @apply relative min-w-0 w-full sm:min-w-[220px] sm:flex-1; }
.app-list-toolbar__input { @apply w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100; }
.app-list-toolbar__clear { @apply absolute right-3 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-slate-200 text-slate-500 transition hover:bg-slate-300; }
.app-list-toolbar__filters { @apply flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center; }
.app-list-toolbar__actions { @apply flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto; }
.app-list-toolbar__expanded { @apply w-full border-t border-slate-200 pt-2.5; }
</style>
