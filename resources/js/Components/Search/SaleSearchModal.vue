<script setup>
// Modal hóa đơn mở từ ô tìm nhanh: dùng đúng InvoiceDetailModal của trang Hóa đơn.
import { computed, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { AlertTriangle } from 'lucide-vue-next'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { fetchPageProps } from '@/utils/inertiaFetch'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    saleId: { type: [Number, String], required: true },
    modalId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const page = usePage()
const sale = ref(null)
const loading = ref(true)
const canCancelSales = computed(() => page.props.auth?.permissions?.includes('sales.cancel') ?? false)

onMounted(async () => {
    try {
        const data = await fetchPageProps(route('sales.index', { open_sale: props.saleId }), 'Sales/Index', ['selectedSale'], page.version)
        sale.value = data.selectedSale
        if (!sale.value) throw new Error('empty')
    } catch {
        toast.error('Không tải được hóa đơn.')
        emit('close')
    } finally {
        loading.value = false
    }
})

/* ---- In hóa đơn (giống trang Hóa đơn) ---- */
const printFrameUrl = ref('')
const printFrameKey = ref(0)
const openPrintWindow = (saleId) => {
    printFrameUrl.value = route('sales.receipt', saleId)
    printFrameKey.value++
}
const printReceiptFrame = (event) => {
    const frame = event.target
    const frameWindow = frame?.contentWindow
    const frameDocument = frame?.contentDocument
    if (!frameWindow || !frameDocument) return

    const settings = page.props.settings || {}
    const paper = settings.print_paper_size || 'a4'
    const landscape = settings.print_orientation === 'landscape'
    const sizes = { a4: 'A4', a5: 'A5', '80mm': '80mm 200mm', '58mm': '58mm 150mm' }
    const thermal = paper === '80mm' || paper === '58mm'
    const width = paper === '58mm' ? '54mm'
        : paper === '80mm' ? '76mm'
            : paper === 'a5' ? (landscape ? '190mm' : '128mm')
                : (landscape ? '277mm' : '190mm')

    let style = frameDocument.getElementById('direct-print-settings')
    if (!style) {
        style = frameDocument.createElement('style')
        style.id = 'direct-print-settings'
        frameDocument.head.appendChild(style)
    }
    style.textContent = `.no-print,.print-bar,.btn-print{display:none!important} :root{--configured-receipt-width:${width}} @page{size:${sizes[paper] || 'A4'}${thermal ? '' : ` ${landscape ? 'landscape' : 'portrait'}`};margin:${thermal ? '0' : '10mm'}} .receipt-container{width:var(--configured-receipt-width)!important;margin-left:auto!important;margin-right:auto!important}`

    frameWindow.addEventListener('afterprint', () => { printFrameUrl.value = '' }, { once: true })
    window.setTimeout(() => frameWindow.print(), 120)
}

/* ---- Hủy hóa đơn (giống trang Hóa đơn) ---- */
const defaultReasons = [
    'Khách đổi ý không mua nữa',
    'Đổi sang sản phẩm khác',
    'Nhầm sản phẩm / số lượng',
    'Nhập sai giá / giảm giá',
    'Chưa nhận được tiền',
    'Trùng hóa đơn',
]
const showCancelModal = ref(false)
const cancelReason = ref('')
const confirmCancel = () => { cancelReason.value = ''; showCancelModal.value = true }
const handleCancelSale = () => {
    const reasonText = cancelReason.value.trim()
    if (!sale.value || !reasonText) return
    router.post(route('sales.cancel', sale.value.id), { reason: reasonText, cancel_reason: reasonText }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showCancelModal.value = false
            emit('close')
        },
    })
}
</script>

<template>
    <div v-if="loading" class="fixed bottom-4 right-4 z-[1200] rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-lg">
        Đang tải hóa đơn...
    </div>

    <InvoiceDetailModal
        v-if="sale"
        :show="true"
        :invoice="sale"
        :can-cancel="canCancelSales && sale.status !== 'cancelled'"
        can-print
        @close="emit('close')"
        @cancel="confirmCancel"
        @print="openPrintWindow($event.id)"
    />
    <iframe
        v-if="printFrameUrl"
        :key="printFrameKey"
        :src="printFrameUrl"
        title="In hóa đơn"
        aria-hidden="true"
        tabindex="-1"
        class="pointer-events-none fixed left-0 top-0 h-px w-px border-0 opacity-0"
        @load="printReceiptFrame"
    />

    <BaseModal v-if="showCancelModal" title="Xác nhận hủy hóa đơn" size="md" layer-class="z-[1300]" @close="showCancelModal = false">
        <div class="space-y-4 p-1">
            <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5">
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-xs leading-relaxed text-amber-800">
                    <strong>Lưu ý quan trọng:</strong> Khi hủy hóa đơn <strong>#{{ sale?.code }}</strong>, toàn bộ số lượng sản phẩm và mã IMEI/Serial liên quan sẽ được <strong>tự động hoàn trả về kho</strong>.
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Lý do hủy hóa đơn:</label>
                <textarea v-model="cancelReason" rows="3" placeholder="Nhập lý do hủy hoặc chọn từ các gợi ý bên dưới..." class="w-full rounded-xl border border-slate-200 p-3 text-xs outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"></textarea>
                <div class="mt-2.5">
                    <div class="mb-1.5 text-[11px] font-semibold text-slate-400">💡 Gợi ý lý do phổ biến (Bấm để chọn nhanh):</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="(reason, idx) in defaultReasons"
                            :key="idx"
                            type="button"
                            :class="['rounded-lg border px-2.5 py-1 text-xs font-medium transition', cancelReason === reason ? 'border-rose-300 bg-rose-50 font-bold text-rose-700' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900']"
                            @click="cancelReason = reason"
                        >{{ reason }}</button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 pt-2">
                <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50" @click="showCancelModal = false">Bỏ qua</button>
                <button type="button" :disabled="!cancelReason.trim()" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50" @click="handleCancelSale">Xác nhận Hủy Đơn</button>
            </div>
        </div>
    </BaseModal>
</template>
