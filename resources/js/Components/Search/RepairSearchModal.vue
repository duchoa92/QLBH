<script setup>
// Modal phiếu sửa chữa mở từ ô tìm nhanh: dùng đúng RepairDetailModal của trang Sửa chữa.
import { computed, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import RepairDetailModal from '@/Pages/Repairs/RepairDetailModal.vue'
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue'
import { fetchPageProps } from '@/utils/inertiaFetch'
import { useRepairAccess, fetchRepairCredentials } from '@/Composables/useRepairAccess'
import { REPAIR_STATUS_CLASSES, REPAIR_STATUS_LABELS } from '@/utils/repairStatus'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    repairId: { type: [Number, String], required: true },
    modalId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const page = usePage()
const { can, isSuperAdmin, canViewCredentials, progressLabel: progressLabelFor, removeRepair } = useRepairAccess()

const statusClasses = REPAIR_STATUS_CLASSES
const statusLabels = REPAIR_STATUS_LABELS

const repair = ref(null)
const credentials = ref(null)
const loading = ref(true)
const printPreviewUrl = ref('')

const progressLabel = computed(() => progressLabelFor(repair.value))

onMounted(async () => {
    try {
        const data = await fetchPageProps(route('repairs.index', { open_repair: props.repairId }), 'Repairs/Index', ['repairs'], page.version)
        repair.value = data.repairs?.data?.find(item => Number(item.id) === Number(props.repairId)) || null
        if (!repair.value) throw new Error('empty')
    } catch {
        toast.error('Không tải được phiếu sửa chữa.')
        emit('close')
        return
    } finally {
        loading.value = false
    }

    if (canViewCredentials.value) credentials.value = await fetchRepairCredentials(repair.value.id)
})

// Sửa/tiến độ cần form và thanh toán nằm ở trang Sửa chữa (modal global chỉ hiện một cửa sổ một lúc,
// nên không giữ được luồng thanh toán): chuyển sang trang đó đúng phiếu này.
const openInRepairPage = () => {
    emit('close')
    router.visit(route('repairs.index', { open_repair: repair.value.id }))
}
</script>

<template>
    <div v-if="loading" class="fixed bottom-4 right-4 z-[1200] rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-lg">
        Đang tải phiếu sửa chữa...
    </div>

    <RepairDetailModal
        v-if="repair"
        :repair="repair"
        :credentials="credentials"
        :can-view-credentials="canViewCredentials"
        :can-print="can('repairs.view')"
        :can-edit="can('repairs.edit')"
        :can-delete="isSuperAdmin"
        :progress-label="progressLabel"
        :status-labels="statusLabels"
        :status-classes="statusClasses"
        @close="emit('close')"
        @print="printPreviewUrl = route('repairs.print', repair.id)"
        @delete="removeRepair(repair, () => emit('close'))"
        @edit="openInRepairPage"
        @progress="openInRepairPage"
    />
    <PrintPreviewModal :show="Boolean(printPreviewUrl)" title="Xem trước phiếu sửa chữa" :src="printPreviewUrl" @close="printPreviewUrl = ''" />
</template>
