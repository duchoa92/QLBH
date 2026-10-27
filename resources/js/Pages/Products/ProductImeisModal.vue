<script setup>
import { onMounted, ref } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatCurrency } from '@/utils/format'

const props = defineProps({
    productId: { type: [Number, String], required: true },
    productName: { type: String, default: '' },
})

defineEmits(['close'])

const product = ref(null)
const loading = ref(true)
const error = ref('')

const statusLabel = (status) => ({
    in_stock: 'Còn hàng',
    sold: 'Đã bán',
    repairing: 'Đang sửa',
    returned: 'Đã trả',
    0: 'Còn hàng',
    1: 'Đã bán',
    2: 'Đang sửa',
    3: 'Đã trả',
}[status] || 'Khác')

const statusClass = (status) => status === 'in_stock' || status === 0
    ? 'bg-emerald-50 text-emerald-700'
    : status === 'sold' || status === 1
        ? 'bg-slate-100 text-slate-600'
        : 'bg-amber-50 text-amber-700'

onMounted(async () => {
    try {
        const { data } = await window.axios.get(route('product-imeis.index', props.productId), {
            headers: { Accept: 'application/json' },
        })
        product.value = data.product
    } catch {
        error.value = 'Không tải được danh sách IMEI.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <BaseModal :title="`Danh sách IMEI · ${product?.name || productName}`" size="lg" header-class="px-4 py-3 sm:px-5" body-class="p-4 sm:p-5" @close="$emit('close')">
        <div v-if="loading" class="px-6 py-14 text-center text-sm text-slate-500">Đang tải IMEI…</div>
        <div v-else-if="error" class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">{{ error }}</div>
        <div v-else-if="!product?.imeis?.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center text-sm text-slate-500">Sản phẩm chưa có IMEI.</div>
        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-sky-100 bg-sky-50/70 px-3 py-2.5 sm:px-4">
                <div class="text-sm font-semibold text-slate-700">Tổng số IMEI <span class="ml-1 text-slate-900">{{ product.imeis.length }}</span></div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    {{ product.imeis.filter(item => item.status === 'in_stock' || item.status === 0).length }} còn hàng
                </div>
            </div>
            <div class="max-h-[min(62vh,38rem)] overflow-auto rounded-lg border border-slate-200 shadow-sm">
                <table class="w-full min-w-[480px] border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-100 text-left text-[11px] font-bold uppercase tracking-wider text-slate-600 shadow-[0_1px_0_0_#cbd5e1]">
                        <tr>
                            <th class="w-[40%] px-4 py-3">IMEI</th>
                            <th class="w-[30%] border-l border-slate-200 px-4 py-3">Trạng thái</th>
                            <th class="border-l border-slate-200 px-4 py-3 text-right">Giá bán</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(imei, index) in product.imeis" :key="imei.id" class="border-t border-slate-200 transition-colors hover:bg-sky-50/60" :class="index % 2 ? 'bg-slate-50/50' : 'bg-white'">
                            <td class="px-4 py-3.5 font-mono font-medium tracking-wide text-slate-800">{{ imei.imei || imei.serial || '—' }}</td>
                            <td class="border-l border-slate-200 px-4 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(imei.status)">{{ statusLabel(imei.status) }}</span></td>
                            <td class="border-l border-slate-200 px-4 py-3.5 text-right font-semibold tabular-nums text-slate-800">{{ formatCurrency(imei.sell_price) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </BaseModal>
</template>
