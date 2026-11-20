<script setup>
import { computed, Fragment, ref, useSlots } from 'vue'
import { LoaderCircle, Search, SlidersHorizontal, X } from 'lucide-vue-next'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: 'Tìm kiếm...' },
    loading: { type: Boolean, default: false },
    searchId: { type: String, default: undefined },
    searchClass: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'clear', 'focus', 'blur', 'submit'])
const slots = useSlots()
const hasValue = computed(() => String(props.modelValue ?? '').length > 0)

const input = event => emit('update:modelValue', event.target.value)
const clear = () => {
    emit('update:modelValue', '')
    emit('clear')
}

/* BỘ LỌC: ẩn sau nút bên cạnh ô tìm kiếm; bấm để hiện các ô lọc ngay bên dưới. */
const flatten = nodes => (nodes || []).flatMap(node => (node?.type === Fragment ? flatten(node.children) : [node]))
const isFilled = value => value !== '' && value !== null && value !== undefined && value !== false

// Đếm số ô lọc đang có giá trị bằng cách đọc props (modelValue / from / to) của các ô trong slot.
const activeCount = computed(() => flatten(slots.filters?.()).filter((node) => {
    const nodeProps = node?.props || {}

    return isFilled(nodeProps.modelValue) || isFilled(nodeProps['model-value']) || isFilled(nodeProps.from) || isFilled(nodeProps.to)
}).length)

// null = chưa bấm: tự mở nếu đang có lọc (vd. tải lại trang theo URL) để người dùng thấy điều kiện đang áp dụng.
const manualOpen = ref(null)
const filtersOpen = computed(() => manualOpen.value ?? activeCount.value > 0)
const toggleFilters = () => { manualOpen.value = !filtersOpen.value }
</script>

<template>
    <div class="app-list-toolbar-wrap">
        <!-- Hàng trên đầu: nút Tạo mới / Thêm mới... — tách riêng khỏi ô tìm kiếm & lọc -->
        <div v-if="$slots.actions" class="app-list-toolbar__top">
            <div class="app-list-toolbar__actions"><slot name="actions" /></div>
        </div>

        <div class="app-list-toolbar">
            <div class="app-list-toolbar__row">
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

                <button
                    v-if="$slots.filters"
                    type="button"
                    class="app-list-toolbar__filter-btn"
                    :class="filtersOpen || activeCount ? 'is-active' : ''"
                    :aria-expanded="filtersOpen"
                    title="Bộ lọc"
                    aria-label="Bộ lọc"
                    @click="toggleFilters"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                    <span class="hidden sm:inline">Lọc</span>
                    <span v-if="activeCount" class="app-list-toolbar__badge">{{ activeCount }}</span>
                </button>
            </div>

            <!-- v-show (không v-if) để các ô lọc giữ nguyên trạng thái khi đóng/mở -->
            <div v-if="$slots.filters" v-show="filtersOpen" class="app-list-toolbar__filters"><slot name="filters" /></div>
            <div v-if="$slots.expanded" class="app-list-toolbar__expanded"><slot name="expanded" /></div>
        </div>
    </div>
</template>

<style scoped>
.app-list-toolbar-wrap { @apply flex w-full flex-col gap-2; }
.app-list-toolbar__top { @apply flex w-full justify-end; }
.app-list-toolbar { @apply flex w-full flex-col items-stretch gap-2.5 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:p-4; }
.app-list-toolbar__row { @apply flex w-full items-center gap-2; }
.app-list-toolbar__search { @apply relative min-w-0 flex-1; }
.app-list-toolbar__input { @apply w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100; }
.app-list-toolbar__clear { @apply absolute right-3 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-slate-200 text-slate-500 transition hover:bg-slate-300; }
.app-list-toolbar__filter-btn { @apply relative inline-flex h-[42px] shrink-0 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 active:scale-95; }
.app-list-toolbar__filter-btn.is-active { @apply border-blue-300 bg-blue-50 text-blue-700; }
.app-list-toolbar__badge { @apply inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold leading-none text-white; }
/* Các ô lọc xếp đuổi nhau trên một hàng khi đủ chỗ, tự xuống hàng khi hết chỗ; điện thoại: 2 ô mỗi hàng. */
.app-list-toolbar__filters { @apply grid w-full grid-cols-2 gap-2 border-t border-slate-100 pt-2.5 sm:flex sm:flex-wrap sm:items-center; }
.app-list-toolbar__actions { @apply flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto; }
.app-list-toolbar__expanded { @apply w-full border-t border-slate-200 pt-2.5; }

/* Ở điện thoại hẹp, chữ trên các nút thao tác đầu danh sách chiếm quá nhiều chỗ.
   Chỉ thu nút trong toolbar về biểu tượng; text vẫn tồn tại cho trình đọc màn hình. */
@media (max-width: 540px) {
    .app-list-toolbar__actions { @apply gap-1.5; }
    .app-list-toolbar__actions :deep(button),
    .app-list-toolbar__actions :deep(a) {
        @apply h-[42px] w-[42px] shrink-0 justify-center gap-0 px-0;
        font-size: 0;
    }
}
</style>
