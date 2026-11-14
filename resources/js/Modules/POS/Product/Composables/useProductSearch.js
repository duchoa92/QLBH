import {
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
} from 'vue'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { useEventBus } from '@/Composables/useEventBus'
import { useReferenceData } from '@/Stores/referenceData'
import { usePage } from '@inertiajs/vue3'
import { cacheOfflineProducts, readOfflineProducts } from '@/utils/offlinePos'

export function useProductSearch(emit) {

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const keyword = ref('')

    const categoryId = ref('')

    // Kiểu sắp xếp: nhớ lại lựa chọn lần trước của người bán trên thiết bị này
    const SORT_STORAGE_KEY = 'pos_product_sort'
    const readSavedSort = () => {
        try { return localStorage.getItem(SORT_STORAGE_KEY) || '' } catch { return '' }
    }
    const sort = ref(readSavedSort())

    const products = ref([])

    const { categories } = useReferenceData()
    const page = usePage()

    const loading = ref(false)

    let timeout = null
    let latestRequest = 0

    // Sự kiện
    const {

        onEvent,

        offEvent,

    } = useEventBus()

    /*
    |--------------------------------------------------------------------------
    | Tải Sản phẩm
    |--------------------------------------------------------------------------
    */

    // silent: nạp ngầm (không hiện trạng thái đang tải, không quét IMEI, giữ nguyên danh sách nếu lỗi mạng)
    const loadProducts = async (options = {}) => {

        const silent = options?.silent === true

        const requestId = ++latestRequest

        if (!silent) loading.value = true

        try {

            const response =
                await productService.search(
                    keyword.value,
                    categoryId.value,
                    sort.value
                )

            // Chỉ phản hồi mới nhất được phép thay catalog. Nếu phản hồi cũ
            // về sau khi vừa thanh toán, nó không được ghi đè giá lô kế tiếp.
            if (requestId !== latestRequest) return

            products.value = response
            if (Array.isArray(response) && response.length) {
                await cacheOfflineProducts(page.props.auth?.user?.id || 'current', `${keyword.value.trim().toLowerCase()}|${categoryId.value}|${sort.value}`, response).catch(() => {})
            }

            /*
            |--------------------------------------------------------------------------
            | Không tìm thấy sản phẩm
            |--------------------------------------------------------------------------
            */

            if (
                !silent &&
                keyword.value &&
                response.length === 0
            ) {

                try {

                    const scanResult =
                        await productService.scan(
                            keyword.value
                        )

                    emit(
                        'product-scanned',
                        scanResult
                    )

                    keyword.value = ''

                } catch {

                    // IMEI không tồn tại
                }
            }

        } catch (error) {
            if (silent) return
            const cached = await readOfflineProducts(page.props.auth?.user?.id || 'current', `${keyword.value.trim().toLowerCase()}|${categoryId.value}|${sort.value}`).catch(() => null)
            if (requestId !== latestRequest) return
            products.value = cached?.products || []

        } finally {

            if (!silent && requestId === latestRequest) loading.value = false
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    const searchProducts = () => {

        clearTimeout(timeout)

        timeout = setTimeout(
            () => {

                loadProducts()
            },
            300
        )
    }

    // Reset ô tìm sản phẩm
    const resetProductSearch = async () => {

        keyword.value = ''

        categoryId.value = ''

        products.value = []

        await loadProducts()
    }

    /*
    |--------------------------------------------------------------------------
    | WATCH
    |--------------------------------------------------------------------------
    */

    watch(
        [keyword, categoryId],
        () => {

            searchProducts()
        }
    )

    // Đổi cách sắp xếp: lưu lại và tải ngay (không cần chờ gõ phím)
    watch(sort, (value) => {
        try { localStorage.setItem(SORT_STORAGE_KEY, value || '') } catch { /* bỏ qua */ }
        loadProducts()
    })

    /*
    |--------------------------------------------------------------------------
    | TỰ CẬP NHẬT NGẦM
    |--------------------------------------------------------------------------
    | Danh sách sản phẩm (giá, tồn, lô) tự nạp lại khi:
    |  - nơi khác vừa lưu dữ liệu (nhập hàng, sửa sản phẩm...) — cùng tab, tab khác, cửa sổ khác;
    |  - quay lại tab POS;
    |  - định kỳ khi POS đang mở (phòng trường hợp thay đổi đến từ máy khác).
    */
    const SILENT_MIN_INTERVAL = 8000
    const SILENT_POLL_INTERVAL = 45000
    let lastSilentLoad = Date.now()
    let silentTimer = null
    let silentPoll = null
    let catalogChannel = null

    const refreshSilently = (force = false) => {
        if (document.hidden) return
        if (!force && Date.now() - lastSilentLoad < SILENT_MIN_INTERVAL) return
        lastSilentLoad = Date.now()
        loadProducts({ silent: true })
    }

    // Gộp nhiều tín hiệu liên tiếp (một lần lưu có thể phát vài sự kiện) thành một lần nạp.
    const scheduleSilentRefresh = () => {
        clearTimeout(silentTimer)
        silentTimer = setTimeout(() => refreshSilently(true), 700)
    }
    const onVisible = () => { if (!document.hidden) refreshSilently() }
    const onStorage = (event) => { if (event.key === 'reference-data:version') scheduleSilentRefresh() }

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    onMounted(() => {

        loadProducts()

        window.addEventListener('reference-data:changed', scheduleSilentRefresh)
        window.addEventListener('storage', onStorage)
        document.addEventListener('visibilitychange', onVisible)
        window.addEventListener('focus', onVisible)
        if ('BroadcastChannel' in window) {
            catalogChannel = new BroadcastChannel('reference-data')
            catalogChannel.addEventListener('message', scheduleSilentRefresh)
        }
        silentPoll = setInterval(() => refreshSilently(), SILENT_POLL_INTERVAL)

        onEvent(
            'products:refresh',
            loadProducts
        )


        onEvent(
            'pos:reset',
            resetProductSearch
        )
    })

    /*
    |--------------------------------------------------------------------------
    | UNMOUNT
    |--------------------------------------------------------------------------
    */

    onBeforeUnmount(() => {
        // Hủy bỏ timeout khi component bị hủy để tránh gọi API không cần thiết
        clearTimeout(timeout)
        clearTimeout(silentTimer)
        clearInterval(silentPoll)
        window.removeEventListener('reference-data:changed', scheduleSilentRefresh)
        window.removeEventListener('storage', onStorage)
        document.removeEventListener('visibilitychange', onVisible)
        window.removeEventListener('focus', onVisible)
        catalogChannel?.close()
        catalogChannel = null

       offEvent(
            'products:refresh',
            loadProducts
        )

        offEvent(
            'pos:reset',
            resetProductSearch
        )
    })

    return {

        keyword,

        categoryId,

        sort,

        products,

        categories,

        loading,

        loadProducts,
    }
}
