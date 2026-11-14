<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next'

// Bộ lọc khoảng ngày — tách từ trang Hóa đơn bán hàng để các trang khác dùng chung.
// v-model:from / v-model:to dạng YYYY-MM-DD; @change phát ra ngay khi người dùng chọn xong khoảng ngày
// (chọn ngày bắt đầu rồi ngày kết thúc, hoặc chọn một khoảng có sẵn) — không cần bấm thêm nút Áp dụng.
const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    align: { type: String, default: 'right' }, // right | left — phía mở của lịch
    allowAll: { type: Boolean, default: true }, // cho phép "Toàn thời gian" / Xóa (báo cáo luôn cần một khoảng ngày thì tắt)
    compact: { type: Boolean, default: false }, // nút thấp hơn để đặt cạnh các điều khiển nhỏ (ví dụ biểu đồ ở trang chủ)
})
const emit = defineEmits(['update:from', 'update:to', 'change'])

const allPresetOptions = [
    { label: 'Toàn thời gian', value: 'all' },
    { label: 'Hôm nay', value: 'today' },
    { label: 'Hôm qua', value: 'yesterday' },
    { label: '7 ngày qua', value: 'last_7_days' },
    { label: '30 ngày qua', value: 'last_30_days' },
    { label: 'Tháng này', value: 'this_month' },
    { label: 'Tháng trước', value: 'last_month' },
]

const datePresetOptions = computed(() => props.allowAll ? allPresetOptions : allPresetOptions.filter(preset => preset.value !== 'all'))

const open = ref(false)
const rootRef = ref(null)
const datePreset = ref(props.from || props.to || !props.allowAll ? 'custom' : 'all')
const pickerStart = ref('')
const pickerEnd = ref('')
const calendarMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

const dateInputValue = (date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}
const dateFromInput = value => value ? new Date(`${value}T12:00:00`) : null
const formatDate = value => new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(dateFromInput(value))

const dateRangeFor = (preset) => {
    const today = new Date()
    if (preset === 'all') return { from: '', to: '' }
    if (preset === 'today') {
        const value = dateInputValue(today)
        return { from: value, to: value }
    }
    if (preset === 'yesterday') {
        const yesterday = new Date(today)
        yesterday.setDate(today.getDate() - 1)
        const value = dateInputValue(yesterday)
        return { from: value, to: value }
    }
    if (preset === 'last_7_days') {
        const from = new Date(today)
        from.setDate(today.getDate() - 6)
        return { from: dateInputValue(from), to: dateInputValue(today) }
    }
    if (preset === 'last_30_days') {
        const from = new Date(today)
        from.setDate(today.getDate() - 29)
        return { from: dateInputValue(from), to: dateInputValue(today) }
    }
    if (preset === 'this_month') return { from: dateInputValue(new Date(today.getFullYear(), today.getMonth(), 1)), to: dateInputValue(today) }
    if (preset === 'last_month') return { from: dateInputValue(new Date(today.getFullYear(), today.getMonth() - 1, 1)), to: dateInputValue(new Date(today.getFullYear(), today.getMonth(), 0)) }
    return null
}

const commit = (from, to) => {
    emit('update:from', from)
    emit('update:to', to)
    open.value = false
    emit('change', { from, to })
}

const applyDatePreset = (preset) => {
    datePreset.value = preset
    const range = dateRangeFor(preset)
    if (!range) return
    commit(range.from, range.to)
}
const monthLabel = month => new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric' }).format(month)
const calendarCells = computed(() => {
    const month = calendarMonth.value
    const first = new Date(month.getFullYear(), month.getMonth(), 1)
    const start = new Date(first)
    start.setDate(1 - ((first.getDay() + 6) % 7))
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start)
        date.setDate(start.getDate() + index)
        return { key: dateInputValue(date), day: date.getDate(), currentMonth: date.getMonth() === month.getMonth() }
    })
})
const isPickerSelected = key => key === pickerStart.value || key === pickerEnd.value
const isPickerInRange = key => Boolean(pickerStart.value && pickerEnd.value && key > pickerStart.value && key < pickerEnd.value)
const pickerRangeLabel = computed(() => {
    if (!pickerStart.value) return 'Chọn ngày bắt đầu, rồi chọn ngày kết thúc'
    return pickerEnd.value ? `${formatDate(pickerStart.value)} – ${formatDate(pickerEnd.value)}` : `Từ ${formatDate(pickerStart.value)} · chọn ngày kết thúc (bấm lại ngày này nếu chỉ lọc 1 ngày)`
})
const selectedLabel = computed(() => {
    if (!props.from && !props.to) return 'Toàn thời gian'
    if (!props.to || props.to === props.from) return formatDate(props.from || props.to)
    return `${formatDate(props.from)} – ${formatDate(props.to)}`
})

const openPicker = () => {
    pickerStart.value = props.from
    pickerEnd.value = props.to
    const anchor = dateFromInput(pickerStart.value) || new Date()
    calendarMonth.value = new Date(anchor.getFullYear(), anchor.getMonth(), 1)
    open.value = true
}
const moveMonth = amount => { calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + amount, 1) }
const selectDate = (key) => {
    if (!pickerStart.value || pickerEnd.value) { pickerStart.value = key; pickerEnd.value = ''; return }
    // Chọn xong ngày thứ hai: áp dụng luôn khoảng đó. Muốn lọc đúng 1 ngày thì bấm lại chính ngày đó.
    const [from, to] = key < pickerStart.value ? [key, pickerStart.value] : [pickerStart.value, key]
    pickerStart.value = from
    pickerEnd.value = to
    datePreset.value = 'custom'
    commit(from, to)
}
const closeOnOutside = event => {
    if (open.value && rootRef.value && !rootRef.value.contains(event.target)) open.value = false
}
onMounted(() => window.addEventListener('pointerdown', closeOnOutside))
onBeforeUnmount(() => window.removeEventListener('pointerdown', closeOnOutside))
</script>

<template>
    <div ref="rootRef" class="relative w-full min-w-0 shrink-0 sm:w-auto">
        <button type="button" class="inline-flex w-full min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-left text-xs font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 sm:w-auto" :class="compact ? 'h-9 sm:min-w-[190px]' : 'h-[42px] sm:min-w-[220px]'" title="Lọc theo khoảng ngày" @click="open ? open = false : openPicker()">
            <CalendarDays class="h-4 w-4 shrink-0 text-blue-600" />
            <span class="min-w-0 flex-1 truncate">{{ selectedLabel }}</span>
            <ChevronRight class="h-4 w-4 shrink-0 rotate-90 text-slate-400" />
        </button>
        <div v-if="open" class="absolute top-full z-50 mt-2 w-[min(470px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" :class="align === 'left' ? 'left-0' : 'right-0'">
            <div class="flex flex-col sm:flex-row">
                <aside class="border-b border-slate-200 bg-slate-50/70 p-2 sm:w-40 sm:shrink-0 sm:border-b-0 sm:border-r">
                    <p class="mb-1 px-2 pt-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Khoảng có sẵn</p>
                    <div class="flex flex-wrap gap-1 sm:block sm:space-y-0.5">
                        <button v-for="preset in datePresetOptions" :key="preset.value" type="button" class="w-auto rounded-lg px-2.5 py-1.5 text-left text-[11px] font-semibold transition sm:w-full sm:py-2" :class="datePreset === preset.value ? 'bg-blue-100 text-blue-700' : 'text-slate-600 hover:bg-white hover:text-slate-900'" @click="applyDatePreset(preset.value)">{{ preset.label }}</button>
                    </div>
                </aside>
                <section class="min-w-0 flex-1 p-2.5 sm:p-3">
                    <header class="mb-2 flex items-center justify-between gap-2">
                        <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100" aria-label="Tháng trước" @click="moveMonth(-1)"><ChevronLeft class="h-4 w-4" /></button>
                        <div class="min-w-0 flex-1 text-center text-xs font-bold capitalize text-slate-800">{{ monthLabel(calendarMonth) }}</div>
                        <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100" aria-label="Tháng sau" @click="moveMonth(1)"><ChevronRight class="h-4 w-4" /></button>
                    </header>
                    <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400"><span v-for="day in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="day" class="py-1">{{ day }}</span></div>
                    <div class="grid grid-cols-7">
                        <button v-for="cell in calendarCells" :key="cell.key" type="button" class="relative mx-auto my-px grid h-8 w-8 place-items-center rounded-md text-[11px] transition" :class="isPickerSelected(cell.key) ? 'bg-blue-600 font-bold text-white shadow-sm hover:bg-blue-700' : isPickerInRange(cell.key) ? 'rounded-none bg-blue-100 text-blue-800 hover:bg-blue-200' : cell.currentMonth ? 'text-slate-700 hover:bg-slate-100' : 'text-slate-400 hover:bg-slate-100'" @click="selectDate(cell.key)">{{ cell.day }}</button>
                    </div>
                </section>
            </div>
            <footer class="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/50 px-3 py-2.5">
                <p class="min-w-0 truncate text-xs font-medium text-slate-600">Đã chọn: <span class="font-bold text-slate-800">{{ pickerRangeLabel }}</span></p>
                <button v-if="allowAll" type="button" class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-rose-600" @click="applyDatePreset('all')">Xóa</button>
            </footer>
        </div>
    </div>
</template>
