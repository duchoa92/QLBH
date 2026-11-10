<script setup>
import { computed } from 'vue'
import { Building2, ChevronRight, Package, PackageCheck, ReceiptText, Smartphone, UserRound, Users, Wrench } from 'lucide-vue-next'

// Một dòng gợi ý / kết quả trong trang Tra cứu: thiết bị (imei), phiếu sửa (repair),
// khách hàng, người dùng, nhà cung cấp, hóa đơn, phiếu nhập, hoặc dòng thông tin (line, không bấm được).
const props = defineProps({
    item: { type: Object, required: true },
    active: { type: Boolean, default: false },
})
defineEmits(['select'])

const type = computed(() => props.item.type)
const isLine = computed(() => type.value === 'line')
const isDevice = computed(() => type.value === 'imei')
const isRepair = computed(() => type.value === 'repair')

const toneClass = {
    blue: 'bg-blue-100 text-blue-700',
    slate: 'bg-slate-100 text-slate-700',
    amber: 'bg-amber-100 text-amber-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    purple: 'bg-purple-100 text-purple-700',
    indigo: 'bg-indigo-100 text-indigo-700',
}
const tileClass = {
    imei: 'bg-indigo-50 text-indigo-600',
    repair: 'bg-purple-50 text-purple-600',
    customer: 'bg-blue-50 text-blue-600',
    user: 'bg-slate-100 text-slate-600',
    supplier: 'bg-amber-50 text-amber-600',
    sale: 'bg-emerald-50 text-emerald-600',
    import: 'bg-purple-50 text-purple-600',
    product: 'bg-indigo-50 text-indigo-600',
}
const icon = computed(() => ({
    imei: Smartphone, repair: Wrench, customer: UserRound, user: Users,
    supplier: Building2, sale: ReceiptText, import: PackageCheck, product: Package,
}[type.value] || Smartphone))

const title = computed(() => {
    if (isRepair.value) return props.item.device_name || 'Thiết bị sửa chữa'
    if (isDevice.value) return props.item.product_name || 'Thiết bị'
    return props.item.title || '-'
})

const detailLine = computed(() => {
    if (isDevice.value) {
        return [props.item.imei ? `IMEI ${props.item.imei}` : '', props.item.serial ? `SN ${props.item.serial}` : '']
            .filter(Boolean).join(' · ')
    }
    if (isRepair.value) {
        return [props.item.code ? `Phiếu ${props.item.code}` : '', props.item.imei ? `IMEI ${props.item.imei}` : (props.item.serial ? `SN ${props.item.serial}` : '')]
            .filter(Boolean).join(' · ')
    }
    return (props.item.lines || []).join(' · ')
})

// Tách dòng thông tin thành từng mảnh để dễ quét mắt; số tiền (kết thúc bằng "đ") được làm đậm.
const parts = computed(() => (isDevice.value || isRepair.value ? [] : (props.item.lines || []))
    .filter(Boolean)
    .map(text => ({ text: String(text), money: /đ$/.test(String(text)) })))

const customerLine = computed(() => (isDevice.value || isRepair.value)
    ? [props.item.customer_name, props.item.customer_phone, props.item.sale_code ? `HĐ ${props.item.sale_code}` : ''].filter(Boolean).join(' · ')
    : '')

const statusBadge = computed(() => {
    if (isLine.value) return null
    if (isRepair.value) {
        const labels = {
            pending: 'Chờ sửa', repairing: 'Đang sửa', done: 'Đã sửa xong', completed: 'Hoàn tất',
            returned: 'Đã trả khách', warranty: 'Bảo hành', cancelled: 'Đã hủy',
        }
        const key = String(props.item.status || '').toLowerCase()

        return { label: labels[key] || 'Phiếu sửa', class: toneClass.purple }
    }
    if (isDevice.value) {
        if (props.item.status === 'in_stock') return { label: 'Trong kho', class: toneClass.emerald }
        if (props.item.status === 'sold') return { label: 'Đã bán', class: toneClass.blue }
        return { label: 'Đã rời kho', class: toneClass.slate }
    }
    return { label: props.item.badge, class: toneClass[props.item.tone] || toneClass.slate }
})
</script>

<template>
    <div v-if="isLine" class="flex items-start gap-3 px-3.5 py-2.5">
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-800">{{ title }}</span>
            <span v-if="detailLine" class="mt-0.5 block truncate text-xs text-slate-500">{{ detailLine }}</span>
        </span>
    </div>

    <button
        v-else
        type="button"
        class="flex w-full items-start gap-3 px-3.5 py-2.5 text-left transition"
        :class="active ? 'bg-indigo-50' : 'hover:bg-slate-50'"
        @mousedown.prevent
        @click="$emit('select', item)"
    >
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="tileClass[type] || tileClass.imei">
            <component :is="icon" class="h-4 w-4" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-baseline gap-x-2">
                <span class="truncate text-sm font-bold text-slate-900">{{ title }}</span>
                <span v-if="item.variant" class="text-xs font-medium text-slate-500">{{ item.variant }}</span>
            </span>
            <span v-if="parts.length" class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-500">
                <template v-for="(part, index) in parts" :key="index">
                    <span v-if="index" class="text-slate-300">·</span>
                    <span :class="part.money ? 'font-semibold tabular-nums text-slate-800' : ''">{{ part.text }}</span>
                </template>
            </span>
            <span v-else-if="detailLine" class="mt-0.5 block truncate font-mono text-xs text-slate-600">{{ detailLine }}</span>
            <span v-if="customerLine" class="mt-0.5 block truncate text-xs text-slate-500">{{ customerLine }}</span>
        </span>
        <span class="flex shrink-0 flex-col items-end gap-1">
            <span v-if="item.match" class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ item.match }}</span>
            <span v-if="statusBadge" class="rounded-md px-2 py-0.5 text-[10px] font-bold" :class="statusBadge.class">{{ statusBadge.label }}</span>
        </span>
        <ChevronRight class="mt-2 h-4 w-4 shrink-0 text-slate-300" />
    </button>
</template>
