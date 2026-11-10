import { onMounted, ref } from 'vue'

/** Tải dữ liệu chi tiết (khách hàng, nhà cung cấp, nhân viên...) từ records.show để hiển thị trong modal. */
export function useRecordDetail(type, id) {
    const data = ref(null)
    const loading = ref(true)
    const error = ref('')

    onMounted(async () => {
        try {
            const { data: payload } = await window.axios.get(route('records.show', { type, id }), {
                headers: { Accept: 'application/json' },
            })
            data.value = payload.data || null
            if (!data.value) error.value = 'Không có dữ liệu chi tiết.'
        } catch (exception) {
            error.value = exception?.response?.status === 403
                ? 'Bạn không có quyền xem thông tin này.'
                : 'Không tải được thông tin chi tiết.'
        } finally {
            loading.value = false
        }
    })

    return { data, loading, error }
}
