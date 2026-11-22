import { onMounted, ref } from 'vue'
import { useLiveReload } from '@/Composables/useLiveReload'

const CHANNELS = {
    customer: ['customers', 'debts', 'sales', 'repairs'],
    supplier: ['suppliers', 'debts', 'stock_imports'],
    user: ['users'],
}

/** Tải dữ liệu chi tiết (khách hàng, nhà cung cấp, nhân viên...) từ records.show để hiển thị trong modal; tự tải lại ngầm khi dữ liệu đổi. */
export function useRecordDetail(type, id) {
    const data = ref(null)
    const loading = ref(true)
    const error = ref('')

    const load = async ({ silent = false } = {}) => {
        try {
            const { data: payload } = await window.axios.get(route('records.show', { type, id }), {
                headers: { Accept: 'application/json', 'Cache-Control': 'no-cache' },
            })
            if (payload.data) {
                data.value = payload.data
                error.value = ''
            } else if (!silent) {
                error.value = 'Không có dữ liệu chi tiết.'
            }
        } catch (exception) {
            if (silent) return
            error.value = exception?.response?.status === 403
                ? 'Bạn không có quyền xem thông tin này.'
                : 'Không tải được thông tin chi tiết.'
        } finally {
            if (!silent) loading.value = false
        }
    }

    onMounted(() => load())
    useLiveReload(CHANNELS[type] || null, () => load({ silent: true }))

    return { data, loading, error }
}
