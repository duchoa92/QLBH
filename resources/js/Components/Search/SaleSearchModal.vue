<script setup>
// Modal hóa đơn mở từ ô tìm nhanh: dùng đúng InvoiceDetailModal của trang Hóa đơn.
import { computed, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { AlertTriangle } from 'lucide-vue-next'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
import CancelOrderModal from '@/Components/CancelOrderModal.vue'
import { saleCancelItems, saleCancelRequest } from '@/utils/cancelOrder'
import { fetchPageProps } from '@/utils/inertiaFetch'
import { DEFAULT_CANCEL_REASONS, openReceiptInTab, printReceiptFrame as printReceiptInFrame } from '@/utils/receiptPrint'

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
    const url = route('sales.receipt', saleId)
    if (openReceiptInTab(url)) return

    printFrameUrl.value = url
    printFrameKey.value++
}
const printReceiptFrame = (event) => printReceiptInFrame(event, page.props.settings || {}, () => { printFrameUrl.value = '' })

/* ---- Hủy hóa đơn: modal dùng chung với trang Hóa đơn / POS ---- */
const showCancelModal = ref(false)
const cancelSubmitting = ref(false)
const cancelError = ref('')
const cancelSaleItems = computed(() => saleCancelItems(sale.value))
const confirmCancel = () => { cancelError.value = ''; showCancelModal.value = true }
const handleCancelSale = (payload) => {
    if (!sale.value || cancelSubmitting.value) return
    const { url, data } = saleCancelRequest(sale.value.id, payload)
    cancelSubmitting.value = true
    cancelError.value = ''
    router.post(url, data, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showCancelModal.value = false
            emit('close')
        },
        onError: (errors) => {
            cancelError.value = Object.values(errors || {})[0] || 'Không thể hủy hóa đơn. Vui lòng thử lại.'
        },
        onFinish: () => { cancelSubmitting.value = false },
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
        layer-class="z-[1100]"
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

    <CancelOrderModal
        v-if="showCancelModal"
        order-label="hóa đơn"
        :code="sale?.code || ''"
        :items="cancelSaleItems"
        :submitting="cancelSubmitting"
        :error="cancelError"
        layer-class="z-[1300]"
        @close="showCancelModal = false"
        @confirm="handleCancelSale"
    />
</template>
