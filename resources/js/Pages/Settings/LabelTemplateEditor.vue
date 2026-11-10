<script setup>
import { computed } from 'vue'
import { RotateCcw } from 'lucide-vue-next'
import { DEFAULT_LABEL_TEMPLATE, labelCss, labelHtml, mergeLabelTemplate } from '@/utils/printLabels'

const props = defineProps({
    modelValue: { type: Object, required: true },
    shopName: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])

const t = computed(() => mergeLabelTemplate(props.modelValue))
const set = (patch) => emit('update:modelValue', { ...t.value, ...patch })
const num = (key, value) => set({ [key]: value === '' ? '' : Number(value) })

const presets = [
    { label: 'Cuộn 50×30', patch: { layout: 'roll', width_mm: 50, height_mm: 30 } },
    { label: 'Cuộn 40×30', patch: { layout: 'roll', width_mm: 40, height_mm: 30 } },
    { label: 'Cuộn 35×22', patch: { layout: 'roll', width_mm: 35, height_mm: 22 } },
    { label: 'A4 lưới 50×30', patch: { layout: 'sheet', width_mm: 50, height_mm: 30 } },
]

const toggles = [
    ['show_shop', 'Tên cửa hàng'],
    ['show_name', 'Tên sản phẩm'],
    ['show_variant', 'Biến thể'],
    ['show_barcode', 'Mã vạch'],
    ['show_code', 'Chữ IMEI/Serial'],
    ['show_price', 'Giá bán'],
]

const sizeFields = [
    ['width_mm', 'Rộng tem (mm)', 20, 150],
    ['height_mm', 'Cao tem (mm)', 10, 100],
    ['name_size', 'Cỡ chữ tên (pt)', 5, 24],
    ['code_size', 'Cỡ chữ mã (pt)', 5, 20],
    ['price_size', 'Cỡ chữ giá (pt)', 5, 28],
    ['barcode_height_mm', 'Cao mã vạch (mm)', 3, 40],
]

const sample = { productName: 'iPhone 15 Pro Max 256GB', variant: 'Titan tự nhiên', code: '356789012345678', kind: 'IMEI', price: 28990000 }

const previewHtml = computed(() => {
    const one = labelHtml(sample, t.value, props.shopName)
    const count = t.value.layout === 'roll' ? 1 : 3
    return `<style>${labelCss(t.value)}</style><div class="lbl-wrap">${one.repeat(count)}</div>`
})

const reset = () => emit('update:modelValue', { ...DEFAULT_LABEL_TEMPLATE })

const inputClass = 'mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none focus:border-blue-500'
</script>

<template>
    <div class="grid gap-4 p-4 lg:grid-cols-[1fr_minmax(0,360px)]">
        <div class="space-y-4">
            <div>
                <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-slate-500">Khổ tem có sẵn</div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="preset in presets" :key="preset.label" type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="set(preset.patch)">{{ preset.label }}</button>
                    <button type="button" class="ml-auto inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100" @click="reset"><RotateCcw class="h-3.5 w-3.5" /> Mặc định</button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold text-slate-600">Kiểu in
                    <select :class="inputClass" :value="t.layout" @change="set({ layout: $event.target.value })">
                        <option value="sheet">Nhiều tem trên 1 trang (A4/A5)</option>
                        <option value="roll">Mỗi tem 1 trang (máy in tem cuộn)</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Viền tem
                    <select :class="inputClass" :value="t.border" @change="set({ border: $event.target.value })">
                        <option value="none">Không viền</option>
                        <option value="dashed">Nét đứt</option>
                        <option value="solid">Nét liền</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Căn chữ
                    <select :class="inputClass" :value="t.align" @change="set({ align: $event.target.value })">
                        <option value="left">Căn trái</option>
                        <option value="center">Căn giữa</option>
                    </select>
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <label v-for="[key, label, min, max] in sizeFields" :key="key" class="text-xs font-semibold text-slate-600">{{ label }}
                    <input type="number" step="0.5" :min="min" :max="max" :class="inputClass" :value="t[key]" @input="num(key, $event.target.value)" />
                    <span v-if="errors[`label_template.${key}`]" class="text-rose-600">{{ errors[`label_template.${key}`] }}</span>
                </label>
                <template v-if="t.layout === 'sheet'">
                    <label class="text-xs font-semibold text-slate-600">Khoảng cách giữa tem (mm)
                        <input type="number" step="0.5" min="0" max="15" :class="inputClass" :value="t.gap_mm" @input="num('gap_mm', $event.target.value)" />
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Lề giấy (mm)
                        <input type="number" step="0.5" min="0" max="25" :class="inputClass" :value="t.margin_mm" @input="num('margin_mm', $event.target.value)" />
                    </label>
                </template>
            </div>

            <div>
                <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-slate-500">Nội dung trên tem</div>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    <label v-for="[key, label] in toggles" :key="key" class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="t[key]" @change="set({ [key]: $event.target.checked })" />
                        {{ label }}
                    </label>
                </div>
            </div>

            <label class="block text-xs font-semibold text-slate-600">Dòng chữ cuối tem (không bắt buộc)
                <input type="text" maxlength="60" placeholder="Ví dụ: Bảo hành 12 tháng - Hotline 09xx" :class="inputClass" :value="t.footer_text" @input="set({ footer_text: $event.target.value })" />
            </label>
        </div>

        <div>
            <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-slate-500">Xem trước (đúng kích thước thật)</div>
            <div class="overflow-auto rounded-xl border border-dashed border-slate-300 bg-slate-100 p-3" v-html="previewHtml"></div>
            <p class="mt-2 text-xs text-slate-500">Với máy in tem cuộn, hãy chọn đúng khổ giấy trong hộp thoại in của máy và để lề bằng "Không".</p>
        </div>
    </div>
</template>
