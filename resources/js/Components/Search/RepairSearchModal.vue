<script setup>
// Modal phiếu sửa chữa mở từ ô tìm nhanh: dùng đúng RepairDetailModal của trang Sửa chữa.
import { computed, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import RepairDetailModal from '@/Pages/Repairs/RepairDetailModal.vue'
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue'
import { fetchPageProps } from '@/utils/inertiaFetch'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    repairId: { type: [Number, String], required: true },
    modalId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const page = usePage()
const permissions = computed(() => page.props.auth?.permissions || [])
const can = (permission) => permissions.value.includes(permission)
const isSuperAdmin = computed(() => (page.props.auth?.roles || []).includes('Super Admin'))
const canViewCredentials = computed(() => can('repairs.create') || can('repairs.edit') || can('repairs.complete'))
const canProgress = (repair) => {
    if (repair.status === 'done') return can('repairs.return')
    if (repair.status === 'repairing') return can('repairs.complete') || can('repairs.edit')
    return can('repairs.edit')
}

const statusClasses = {
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    repairing: 'bg-purple-50 text-purple-700 border-purple-200',
    done: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    returned: 'bg-slate-100 text-slate-600 border-slate-200',
    cancelled: 'bg-rose-50 text-rose-700 border-rose-200',
}
const statusLabels = {
    pending: 'Tiếp nhận',
    repairing: 'Đang sửa',
    done: 'Hoàn tất sửa',
    returned: 'Đã trả khách',
    cancelled: 'Hủy',
}

const repair = ref(null)
const credentials = ref(null)
const loading = ref(true)
const printPreviewUrl = ref('')

const progressLabel = computed(() => {
    const r = repair.value
    if (!r || !['pending', 'repairing', 'done'].includes(r.status) || !canProgress(r)) return ''
    if (r.status === 'done') return 'Thanh toán / trả máy'
    return r.status === 'pending' ? 'Chuyển sang bước sửa' : 'Cập nhật sửa chữa'
})

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

    if (canViewCredentials.value) {
        try {
            const { data } = await window.axios.get(route('repairs.credentials', repair.value.id))
            credentials.value = data
        } catch {
            toast.error('Không tải được thông tin bảo mật của phiếu.')
        }
    }
})

// Các thao tác cần biểu mẫu/luồng riêng của trang Sửa chữa: chuyển sang trang đó đúng phiếu này.
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
        @delete="openInRepairPage"
        @edit="openInRepairPage"
        @progress="openInRepairPage"
    />
    <PrintPreviewModal :show="Boolean(printPreviewUrl)" title="Xem trước phiếu sửa chữa" :src="printPreviewUrl" @close="printPreviewUrl = ''" />
</template>
