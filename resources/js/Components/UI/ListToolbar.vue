<script setup>
import { computed, Fragment, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue'
import { ChevronLeft, FilterX, Globe, LoaderCircle, Search, SlidersHorizontal, X } from 'lucide-vue-next'
import GlobalSearchResults from '@/Components/GlobalSearchResults.vue'
import { useGlobalSearch } from '@/Composables/useGlobalSearch'
import { floatingActionsCount, floatingActionsTarget } from '@/Stores/floatingActions'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: 'Tìm kiếm...' },
    loading: { type: Boolean, default: false },
    searchId: { type: String, default: undefined },
    searchClass: { type: String, default: '' },
    // Cho phép chuyển từ tìm trong trang sang tìm toàn hệ thống ngay dưới ô tìm kiếm.
    globalSearch: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'clear', 'clear-filters', 'focus', 'blur', 'submit'])
const slots = useSlots()
const hasValue = computed(() => String(props.modelValue ?? '').length > 0)

const input = event => emit('update:modelValue', event.target.value)
const clear = () => {
    emit('update:modelValue', '')
    emit('clear')
}

/* TÌM TOÀN HỆ THỐNG: mặc định ô này lọc danh sách của trang (giữ nguyên bộ lọc theo trang).
   Khi đang gõ, bên dưới hiện lựa chọn "Tìm trên toàn hệ thống"; bấm vào thì hiện kết quả toàn hệ thống ngay tại đây. */
const searchRoot = ref(null)
const focused = ref(false)
const globalMode = ref(false)
const keyword = computed(() => String(props.modelValue ?? ''))
const hasKeyword = computed(() => keyword.value.trim().length >= 2)
const panelOpen = computed(() => props.globalSearch && focused.value && hasKeyword.value)
const closePanel = () => { focused.value = false; globalMode.value = false }
const { results: globalResults, loading: globalLoading, activeIndex, groups: globalGroups, select: selectGlobal, handleKey } = useGlobalSearch(keyword, {
    enabled: globalMode,
    onSelected: closePanel,
})
const startGlobal = () => { globalMode.value = true }
watch(hasKeyword, (value) => { if (!value) globalMode.value = false })

const onFocus = (event) => { focused.value = true; emit('focus', event) }
const onKeydown = (event) => {
    if (event.key === 'Escape' && panelOpen.value) { closePanel(); return }
    if (globalMode.value) {
        if (!handleKey(event) && event.key === 'Enter') event.preventDefault()
        return
    }
    if (event.key !== 'Enter') return
    event.preventDefault()
    if ((event.ctrlKey || event.metaKey) && panelOpen.value) startGlobal()
    else emit('submit')
}
const closeIfOutside = (event) => { if (!searchRoot.value?.contains(event.target)) closePanel() }
onMounted(() => document.addEventListener('mousedown', closeIfOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', closeIfOutside))

/* NÚT CHỨC NĂNG: slot `actions` được đưa vào nút nổi của layout thay vì chiếm một hàng riêng trên trang. */
const hasActions = Boolean(slots.actions)
onMounted(() => { if (hasActions) floatingActionsCount.value += 1 })
onBeforeUnmount(() => { if (hasActions) floatingActionsCount.value = Math.max(0, floatingActionsCount.value - 1) })

/* BỘ LỌC: ẩn sau nút bên cạnh ô tìm kiếm; bấm để hiện các ô lọc ngay bên dưới. */
const flatten = nodes => (nodes || []).flatMap(node => (node?.type === Fragment ? flatten(node.children) : [node]))
const isFilled = value => value !== '' && value !== null && value !== undefined && value !== false

// Đếm số ô lọc đang có giá trị bằng cách đọc props (modelValue / from / to) của các ô trong slot.
const activeCount = computed(() => flatten(slots.filters?.()).filter((node) => {
    const nodeProps = node?.props || {}

    return isFilled(nodeProps.modelValue) || isFilled(nodeProps['model-value']) || isFilled(nodeProps.from) || isFilled(nodeProps.to)
}).length)

// Xóa mọi ô lọc đang có giá trị bằng cách gọi handler v-model (update:modelValue / update:from / update:to) của từng ô trong slot.
const callHandler = (handler, value) => (Array.isArray(handler) ? handler : [handler]).forEach(fn => typeof fn === 'function' && fn(value))
const clearFilters = () => {
    flatten(slots.filters?.()).forEach((node) => {
        const nodeProps = node?.props || {}
        if (isFilled(nodeProps.modelValue) || isFilled(nodeProps['model-value'])) callHandler(nodeProps['onUpdate:modelValue'] ?? nodeProps['onUpdate:model-value'], '')
        if (isFilled(nodeProps.from)) callHandler(nodeProps['onUpdate:from'], '')
        if (isFilled(nodeProps.to)) callHandler(nodeProps['onUpdate:to'], '')
    })
    emit('clear-filters')
}

// null = chưa bấm: tự mở nếu đang có lọc (vd. tải lại trang theo URL) để người dùng thấy điều kiện đang áp dụng.
const manualOpen = ref(null)
const filtersOpen = computed(() => manualOpen.value ?? activeCount.value > 0)
const toggleFilters = () => { manualOpen.value = !filtersOpen.value }
</script>

<template>
    <div class="app-list-toolbar-wrap">
        <!-- Nút Tạo mới / Thêm mới... của trang: gom vào nút nổi ở góc màn hình (xem FloatingActions.vue) -->
        <Teleport v-if="$slots.actions && floatingActionsTarget" :to="floatingActionsTarget">
            <slot name="actions" />
        </Teleport>

        <div class="app-list-toolbar">
            <div class="app-list-toolbar__row">
                <div ref="searchRoot" class="app-list-toolbar__search" :class="searchClass">
                    <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        :id="searchId"
                        :value="modelValue"
                        type="search"
                        autocomplete="off"
                        :placeholder="placeholder"
                        class="app-list-toolbar__input"
                        @input="input"
                        @focus="onFocus"
                        @blur="emit('blur', $event)"
                        @keydown="onKeydown"
                    />
                    <LoaderCircle v-if="loading" class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-emerald-600" :class="hasValue ? 'right-10' : 'right-3.5'" />
                    <button v-if="hasValue" type="button" class="app-list-toolbar__clear" title="Xóa từ khóa" aria-label="Xóa từ khóa" @click="clear">
                        <X class="h-3 w-3" />
                    </button>
                    <slot name="search-end" />

                    <!-- Lựa chọn tìm toàn hệ thống nằm ngay dưới ô tìm kiếm -->
                    <div v-if="panelOpen" class="app-list-toolbar__global">
                        <button v-if="!globalMode" type="button" class="app-list-toolbar__global-option" @click="startGlobal">
                            <Globe class="h-4 w-4 shrink-0 text-blue-600" />
                            <span class="min-w-0 flex-1 truncate">Tìm “{{ keyword.trim() }}” trên toàn hệ thống</span>
                            <kbd class="hidden rounded border border-slate-200 bg-slate-50 px-1.5 text-[10px] font-bold text-slate-400 sm:inline">Ctrl ↵</kbd>
                        </button>
                        <template v-else>
                            <button type="button" class="app-list-toolbar__global-back" @click="globalMode = false">
                                <ChevronLeft class="h-3.5 w-3.5" />Quay lại tìm trong trang
                            </button>
                            <div class="max-h-[60dvh] overflow-y-auto">
                                <GlobalSearchResults :groups="globalGroups" :total="globalResults.length" :loading="globalLoading" :keyword="keyword" :active-index="activeIndex" @select="selectGlobal" @hover="activeIndex = $event" />
                            </div>
                        </template>
                    </div>
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
                <button
                    v-if="$slots.filters && activeCount"
                    type="button"
                    class="app-list-toolbar__clear-filters"
                    title="Xóa tất cả bộ lọc"
                    aria-label="Xóa tất cả bộ lọc"
                    @click="clearFilters"
                >
                    <FilterX class="h-4 w-4" />
                    <span class="hidden sm:inline">Xóa lọc</span>
                </button>
            </div>

            <!-- v-show (không v-if) để các ô lọc giữ nguyên trạng thái khi đóng/mở -->
            <div v-if="$slots.filters" v-show="filtersOpen" class="app-list-toolbar__filters"><slot name="filters" /></div>
            <div v-if="$slots.expanded" class="app-list-toolbar__expanded"><slot name="expanded" /></div>
        </div>
    </div>
</template>

<style scoped>
.app-list-toolbar-wrap {
    @apply flex w-full flex-col gap-2;
    /* Dính đỉnh vùng cuộn, ngay dưới thanh trên (AdminLayout cung cấp --app-sticky-top; thanh trên ẩn thì về 0). */
    position: sticky;
    top: var(--app-sticky-top, 0px);
    z-index: 20;
    transition: top 0.2s ease-out;
    /* Nền mờ + đệm nhỏ để nội dung cuộn phía dưới không lộ qua các góc bo của ô tìm kiếm. */
    padding-block: 0.375rem;
    margin-block: -0.375rem;
    /* Không dùng backdrop-filter: nó biến khối này thành "khối chứa" của phần tử fixed (lịch, bảng trượt) làm lịch bị lệch/che. */
    background: rgb(248 250 252 / 0.97);
}
.app-list-toolbar-wrap:focus-within { z-index: 29; }
.app-list-toolbar__input::-webkit-search-cancel-button,
.app-list-toolbar__input::-webkit-search-decoration { -webkit-appearance: none; appearance: none; display: none; }
.app-list-toolbar { @apply flex w-full flex-col items-stretch gap-2.5 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:p-4; }
.app-list-toolbar__row { @apply flex w-full items-center gap-2; }
.app-list-toolbar__search { @apply relative min-w-0 flex-1; }
.app-list-toolbar__input { @apply w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100; }
.app-list-toolbar__clear { @apply absolute right-3 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-slate-200 text-slate-500 transition hover:bg-slate-300; }
.app-list-toolbar__filter-btn { @apply relative inline-flex h-[42px] shrink-0 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 active:scale-95; }
.app-list-toolbar__filter-btn.is-active { @apply border-blue-300 bg-blue-50 text-blue-700; }
.app-list-toolbar__clear-filters { @apply inline-flex h-[42px] shrink-0 items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-600 transition hover:bg-rose-100 active:scale-95; }
.app-list-toolbar__badge { @apply inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold leading-none text-white; }
/* Các ô lọc xếp đuổi nhau trên một hàng khi đủ chỗ, tự xuống hàng khi hết chỗ; điện thoại: 2 ô mỗi hàng. */
.app-list-toolbar__filters { @apply grid w-full grid-cols-2 gap-2 border-t border-slate-100 pt-2.5 sm:flex sm:flex-wrap sm:items-center; }
.app-list-toolbar__global { @apply absolute left-0 right-0 top-full z-30 mt-1.5 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl; }
.app-list-toolbar__global-option { @apply flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-blue-50; }
.app-list-toolbar__global-back { @apply mb-1 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 transition hover:bg-slate-100; }
.app-list-toolbar__expanded { @apply w-full border-t border-slate-200 pt-2.5; }

</style>
