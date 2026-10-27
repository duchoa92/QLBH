<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import { useBarcodeScanner } from '@/Modules/POS/Product/Composables/useBarcodeScanner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { toast } from 'vue-sonner'
import WorkShiftCountForm from '@/Components/WorkShiftCountForm.vue'
import WorkShiftOpenModal from '@/Components/WorkShiftOpenModal.vue'
import { formatMoney as formatConfiguredMoney } from '@/utils/format'
import { useCart } from '@/Modules/POS/Cart/Composables/useCart'
import { useCheckout } from '@/Modules/POS/Payment/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Modules/POS/Core/Composables/useKeyboardShortcuts'
import PosSidebar from '@/Modules/POS/Core/Components/PosSidebar.vue'
import PosMainPanel from '@/Modules/POS/Core/Components/PosMainPanel.vue'
import PosLayout from '@/Modules/POS/Core/Layouts/PosLayout.vue'
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue'
import SelectProductModal from '@/Modules/POS/Product/Components/SelectProductModal.vue'
import { getPosReturnUrl, exitPosFullscreen } from '@/utils/pos'
import { enqueueOfflineSale, readOfflineSales, removeOfflineSale } from '@/utils/offlinePos'
import { paymentService } from '@/Modules/POS/Payment/Services/paymentService'

const {
    flushSave,
    tabs,
    activeTabId,
    createTab,
    removeTab,
    switchToNextTab,
    cleanupEmptyTabs,
    cart,
    selectedCustomer,
    selectedCartIndex,
    grandTotal,
    addToCart,
    removeItem,
    clearCart,
} = useCart()


const {
    confirmCheckout,
} = useCheckout(
    cart,
    selectedCustomer,
    clearCart,
)

// tab đang chọn
const onCustomerSelected = (customer) => {
    selectedCustomer.value = customer
}

// Hàm chuyển tab
const selectTab = (
    tabId
) => {

    activeTabId.value =
        tabId
}

// ---------------------------------------------------------------
// Đóng POS: lưu toàn bộ phiên làm việc rồi quay về trang trước
// ---------------------------------------------------------------
const page = usePage()
const shift = ref(null)
const shiftModal = ref(false)
const shiftMode = ref('open')
const denominationValues = [500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000]
const shiftDenominations = ref({})
const shiftCashAmount = ref(0)
const shiftCashDetail = ref(false)
const shiftBankBalances = ref({})
const shiftLoading = ref(false)
const shiftResult = ref(null)
const shiftHistoryModal = ref(false)
const shiftHistory = ref([])
const shiftHistoryLoading = ref(false)
let shiftRefreshTimer = null
const formatMoney = formatConfiguredMoney
const autoOpenShiftEnabled = computed(() => page.props.settings?.work_shift_auto_open_enabled !== false
    && page.props.settings?.work_shift_auto_open_enabled !== 0
    && page.props.settings?.work_shift_auto_open_enabled !== '0')
const denominationRows = counts => denominationValues.filter(value => Number(counts?.[value] || 0) > 0).map(value => ({ value, count: Number(counts[value]) }))
const denominationComparison = computed(() => denominationValues.map(value => {
    const openingCount = Number(shiftResult.value?.opening_denominations?.[value] || 0)
    const closingCount = Number(shiftResult.value?.closing_denominations?.[value] || 0)
    return { value, openingCount, closingCount, openingAmount: value * openingCount, closingAmount: value * closingCount }
}))
const groupedShiftHistory = computed(() => {
    const groups = new Map()
    for (const item of shiftHistory.value) {
        if (!groups.has(item.closed_date)) groups.set(item.closed_date, [])
        groups.get(item.closed_date).push(item)
    }
    return [...groups.entries()].map(([date, shifts]) => ({ date, shifts }))
})
const displayHistoryDate = date => new Intl.DateTimeFormat('vi-VN', { dateStyle: 'full' }).format(new Date(`${date}T00:00:00`))
const normalizedDenominations = () => Object.fromEntries(denominationValues.map(value => [value, shiftCashDetail.value ? Math.max(0, Math.floor(Number(shiftDenominations.value[value] || 0))) : 0]))
const normalizedBankBalances = () => Object.fromEntries(bankAccounts.value.map(account => [account.shift_key, Math.max(0, Number(shiftBankBalances.value[account.shift_key] || 0))]))
const bankAccounts = computed(() => {
    const accounts = page.props.settings?.bank_accounts || []
    if (accounts.length) return accounts.map(account => ({ ...account, shift_key: String(account.id || account.bank_account) }))
    const settings = page.props.settings || {}
    return settings.bank_bin && settings.bank_account ? [{ id: 'default', shift_key: 'default', bank_name: `BIN ${settings.bank_bin}`, bank_account: settings.bank_account, bank_account_name: settings.bank_account_name }] : []
})
const closingBankTotal = computed(() => bankAccounts.value.reduce((sum, account) => sum + Number(shiftResult.value?.closing_bank_balances?.[account.shift_key] || 0), 0))
const combinedClosingTotal = computed(() => Number(shiftResult.value?.closing_amount || 0) + closingBankTotal.value)
const balanceTotal = balances => Object.values(balances || {}).reduce((sum, amount) => sum + Number(amount || 0), 0)
const resultBankTransferTotal = computed(() => balanceTotal(shiftResult.value?.bank_collected))
const openingBankTotal = computed(() => balanceTotal(shift.value?.opening_bank_balances))
const openingCombinedTotal = computed(() => Number(shift.value?.opening_amount || 0) + openingBankTotal.value)
const bankTransferTotal = computed(() => balanceTotal(shift.value?.bank_collected))
const countedBankTotal = computed(() => balanceTotal(shiftBankBalances.value))
const countedCashTotal = computed(() => shiftCashDetail.value
    ? denominationValues.reduce((sum, value) => sum + value * Number(shiftDenominations.value[value] || 0), 0)
    : Math.max(0, Number(String(shiftCashAmount.value).replace(/\D/g, '') || 0)))
const countedCashAndBankTotal = computed(() => countedCashTotal.value + countedBankTotal.value)
const bankBalanceRows = balances => bankAccounts.value.map(account => ({ ...account, balance: balances?.[account.shift_key] ?? 0 }))

const loadShift = async () => {
    const { data } = await axios.get(route('pos.shifts.current'))
    shift.value = data.shift
    if (!data.shift && !data.prompt_dismissed && autoOpenShiftEnabled.value) {
        shiftMode.value = 'open'
        shiftDenominations.value = Object.fromEntries(denominationValues.map(value => [value, 0]))
        shiftCashAmount.value = 0
        shiftCashDetail.value = false
        shiftBankBalances.value = Object.fromEntries(bankAccounts.value.map(account => [account.shift_key, 0]))
        shiftModal.value = true
    }
}

const handleShiftOpened = openedShift => {
    shift.value = openedShift
    shiftModal.value = false
}

const refreshShiftSummary = async () => {
    try {
        const { data } = await axios.get(route('pos.shifts.current'))
        if (data.shift) shift.value = data.shift
    } catch (error) {
        console.error('Không thể cập nhật số liệu ca làm.', error)
    }
}

onMounted(async () => {
    await loadShift()
    shiftRefreshTimer = window.setInterval(refreshShiftSummary, 10000)
})

onBeforeUnmount(() => window.clearInterval(shiftRefreshTimer))

const manageShift = async () => {
    if (shift.value) await refreshShiftSummary()
    shiftMode.value = shift.value ? 'close' : 'open'
    shiftCashDetail.value = false
    shiftCashAmount.value = shiftMode.value === 'close' ? Number(shift.value?.expected_cash || 0) : 0
    shiftDenominations.value = Object.fromEntries(denominationValues.map(value => [
        value,
        shiftMode.value === 'close' ? Number(shift.value?.opening_denominations?.[value] || 0) : 0,
    ]))
    shiftBankBalances.value = Object.fromEntries(bankAccounts.value.map(account => [
        account.shift_key,
        shiftMode.value === 'close' ? Number(shift.value?.expected_bank_balances?.[account.shift_key] ?? shift.value?.opening_bank_balances?.[account.shift_key] ?? 0) : 0,
    ]))
    shiftResult.value = null
    shiftModal.value = true
}

const showShiftHistory = async () => {
    shiftHistoryModal.value = true
    shiftHistoryLoading.value = true
    try {
        const { data } = await axios.get(route('pos.shifts.history'))
        shiftHistory.value = data.shifts || []
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể tải lịch sử kiểm kê.')
    } finally {
        shiftHistoryLoading.value = false
    }
}

const submitShift = async () => {
    shiftLoading.value = true
    try {
        if (shiftMode.value === 'open') {
            const { data } = await axios.post(route('pos.shifts.open'), { denominations: normalizedDenominations(), cash_amount: shiftCashDetail.value ? null : countedCashTotal.value, bank_balances: normalizedBankBalances() })
            shift.value = data.shift
            shiftModal.value = false
        } else {
            const { data } = await axios.post(route('pos.shifts.close'), { denominations: normalizedDenominations(), cash_amount: shiftCashDetail.value ? null : countedCashTotal.value, bank_balances: normalizedBankBalances() })
            shiftResult.value = data.summary
            shift.value = null
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể cập nhật ca làm.')
    } finally {
        shiftLoading.value = false
    }
}

const fallbackUrl = computed(() => {
    const permissions = page.props.auth?.permissions || []

    const candidates = [
        ['dashboard.view', '/dashboard'],
        ['sales.view', '/sales'],
        ['products.view', '/products'],
        ['repairs.view', '/repairs'],
        ['customers.view', '/customers'],
    ]

    return candidates.find(([permission]) => permissions.includes(permission))?.[1] ?? '/'
})

const closePos = () => {

    // Lưu ngay tất cả tab đơn hàng, khách hàng, giỏ hàng đang làm dở
    flushSave()

    // Thoát chế độ toàn màn hình của trình duyệt (nếu đang bật)
    exitPosFullscreen()

    router.visit(
        getPosReturnUrl() || fallbackUrl.value
    )
}

const invoiceData = ref(null)
const cartItemCount = computed(() => cart.value.reduce((count, item) => count + Number(item.quantity || 1), 0))

const showInvoice = ref(false)
const paymentOutcome = ref(null)
const showPaymentOutcome = ref(false)
const presentPaymentOutcome = (saleResponse, expectedAmount = null, showDialog = false) => {
    const sale = saleResponse?.data || saleResponse || {}
    const received = Number(sale.paid ?? sale.received_amount ?? 0)
    const change = Number(sale.change ?? 0)
    const expected = expectedAmount === null ? Math.max(0, received - change) : Number(expectedAmount)
    const difference = received - expected
    paymentOutcome.value = {
        code: sale.code || '',
        received,
        expected,
        difference,
        status: difference > 0 ? 'excess' : difference < 0 ? 'short' : 'exact',
    }
    if (showDialog) {
        showPaymentOutcome.value = true
        return
    }

    const details = [
        sale.code ? `Hóa đơn ${sale.code}` : null,
        difference > 0 ? `Khách trả thừa ${formatMoney(difference)}.`
            : difference < 0 ? `Khách còn thiếu ${formatMoney(Math.abs(difference))}.`
                : 'Đã nhận đủ tiền.',
    ].filter(Boolean).join(' ')
    if (difference < 0) toast.warning(details)
    else toast.success(details)
}

// loading khi nhấn checkout để tránh việc click nhiều lần vào nút checkout
const loading = ref(false)
let offlineSyncRunning = false

const syncOfflineSales = async () => {
    if (!navigator.onLine || offlineSyncRunning) return
    offlineSyncRunning = true
    try {
        const queued = await readOfflineSales(page.props.auth?.user?.id)
        for (const sale of queued) {
            if (!navigator.onLine) break
            try {
                await paymentService.checkout(sale.payload)
                await removeOfflineSale(sale.id)
                window.dispatchEvent(new CustomEvent('qlbh:offline-queue-changed'))
                toast.success(`Đã đồng bộ hóa đơn ${sale.code}`)
            } catch (error) {
                // Retain rejected entries for staff review; stop on network loss.
                if (!error.response) break
                sale.syncError = error.response?.data?.message || 'Máy chủ từ chối đơn hàng.'
                sale.failedAt = Date.now()
                await enqueueOfflineSale(sale)
                break
            }
        }
    } finally {
        offlineSyncRunning = false
        window.dispatchEvent(new CustomEvent('qlbh:offline-queue-changed'))
    }
}

const onNetworkOnline = () => syncOfflineSales()
const onOfflineSyncRequested = () => syncOfflineSales()
onMounted(() => {
    window.addEventListener('online', onNetworkOnline)
    window.addEventListener('qlbh:offline-sync-requested', onOfflineSyncRequested)
    if (navigator.onLine) syncOfflineSales()
})
onBeforeUnmount(() => {
    window.removeEventListener('online', onNetworkOnline)
    window.removeEventListener('qlbh:offline-sync-requested', onOfflineSyncRequested)
})

const showSelectModal = ref(false)
const selectedProduct = ref(null)

const handleSelectProduct = (product) => {

    if (product.scanned_variant) {
        selectedProduct.value = product
        handleConfirmSelect({ variant: product.scanned_variant })
        return
    }

    // Đã xác định đúng 1 IMEI cụ thể rồi (quét mã hoặc quét barcode máy)
    // -> thêm thẳng vào giỏ, không cần hỏi lại
    if (product.imei_id) {
        addToCart(product)
        return
    }

    const hasVariants = Boolean(product.variants && product.variants.length)

    const hasImei =
        product.product_type === 'imei'
        || Boolean(product.manage_stock_by_serial)

    // 👉 có biến thể hoặc quản lý theo IMEI -> mở modal cho chọn
    if (hasVariants || hasImei) {
        selectedProduct.value = product
        showSelectModal.value = true
        return
    }

    // 👉 sản phẩm thường
    addToCart(product)
}

const handleConfirmSelect = ({ variant, imei }) => {
    const resolvedVariant =
        imei?.variant
        ?? variant
        ?? null

    // Giá theo IMEI (nếu có set > 0) > giá biến thể > giá gốc sản phẩm.
    // Lưu ý: sell_price của IMEI mặc định = 0 trong DB (không phải null),
    // nên không dùng "??" ở đây để tránh vô tình lấy giá 0.
    const price =
        (imei?.effective_sell_price > 0 ? imei.effective_sell_price : null)
        ?? (imei?.price > 0 ? imei.price : null)
        ?? (imei?.sell_price > 0 ? imei.sell_price : null)
        ?? (resolvedVariant?.sell_price > 0 ? resolvedVariant.sell_price : null)
        ?? (resolvedVariant?.price > 0 ? resolvedVariant.price : null)
        ?? selectedProduct.value?.sell_price
        ?? selectedProduct.value?.price

    addToCart({
        ...selectedProduct.value,

        variant: resolvedVariant,

        imei_id: imei?.id ?? null,
        imei: imei?.imei ?? imei?.serial ?? imei?.display_code ?? null,
        serial: imei?.serial ?? null,
        color: imei?.color ?? null,
        storage: imei?.storage ?? null,
        cost_price: imei?.cost_price ?? null,
        imei_sell_price: imei?.sell_price ?? null,
        warranty_days: selectedProduct.value?.warranty_days ?? 0,
        price_source: imei?.price_source ?? null,

        sell_price: price,
    })

    showSelectModal.value = false
}

const handleCheckout = async (data) => {

    if (!cart.value.length) {

        toast.error('Giỏ hàng trống')

        return
    }

    try {

        if (!navigator.onLine) {
            const supportsOffline = data.payment_method === 'cash'
                && !data.pay_old_debt
                && cart.value.every(item => !item.imei_id && item.product_type !== 'imei' && !item.manage_stock_by_serial
                    && !(item.gifts || []).some(gift => gift.imei_id))
            const payable = Number(grandTotal.value || 0)
            if (!supportsOffline || Number(data.paid_amount || 0) < payable) {
                toast.error('Khi mất mạng chỉ hỗ trợ thanh toán đủ bằng tiền mặt cho sản phẩm quản lý tồn kho thường, không dùng IMEI.')
                return
            }

            const payload = {
                items: cart.value.map(item => ({
                    id: item.id, price: item.price, quantity: item.quantity,
                    unit_id: item.unit_id ?? null, unit_name: item.unit_name ?? 'Cái',
                    conversion_factor: item.conversion_factor ?? 1, variant_id: item.variant_id ?? null,
                    imei_id: null, warranty_days: item.customer_warranty_days ?? item.warranty_days ?? null,
                    warranty_value: item.customer_warranty_value ?? null, warranty_unit: item.customer_warranty_unit ?? null,
                    note: item.note, discount_type: item.discount_type ?? null,
                    discount_value: Number(item.discount_value ?? 0),
                    gifts: (item.gifts ?? []).map(gift => ({ id: gift.id, quantity: gift.quantity, imei_id: null })),
                })),
                customer_id: selectedCustomer.value?.id || null,
                paid_amount: Number(data.paid_amount || 0),
                cash_received_amount: Number(data.cash_received_amount ?? data.paid_amount ?? 0),
                bank_received_amount: 0,
                payment_method: 'cash', bank_account: null,
                invoice_code: data.invoice_code, pay_old_debt: false,
            }
            await enqueueOfflineSale({ id: data.invoice_code, code: data.invoice_code, userId: page.props.auth?.user?.id, queuedAt: Date.now(), payload })
            clearCart()
            showCheckoutModal.value = false
            window.dispatchEvent(new CustomEvent('qlbh:offline-queue-changed'))
            toast.warning(`Đã lưu hóa đơn ${data.invoice_code} trên thiết bị. Hóa đơn chỉ được ghi nhận sau khi đồng bộ có mạng.`)
            return
        }

        loading.value = true

        const expectedAmount = Number(grandTotal.value || 0)
            + (data.pay_old_debt ? Number(selectedCustomer.value?.debt_balance || 0) : 0)
        const res = await confirmCheckout({
            ...data,
            cart: cart.value
        })

        showCheckoutModal.value = false

        if (!res) {
            return
        }

        invoiceData.value = res
        showInvoice.value = true
        presentPaymentOutcome(res?.data, expectedAmount)
        await refreshShiftSummary()
        // sau khi TT thành công chuyển về tab có DL
        switchToNextTab()
        // Dọn tab rỗng
        cleanupEmptyTabs()

    } catch (error) {

        console.error(error)

    } finally {

        loading.value = false
    }
}

const handleBankCheckoutCompleted = async (saleResponse) => {
    clearCart()
    showCheckoutModal.value = false
    invoiceData.value = saleResponse
    showInvoice.value = true
    presentPaymentOutcome(saleResponse, null, true)
    await refreshShiftSummary()
    switchToNextTab()
    cleanupEmptyTabs()
}

const handleBackgroundBankCheckoutCompleted = async (saleResponse, cartSnapshot) => {
    // A cashier may start editing a new cart after hiding the QR modal.
    // Only clear the cart if it is still exactly the one used for this intent.
    if (cartSnapshot && JSON.stringify(cart.value) === cartSnapshot) clearCart()
    presentPaymentOutcome(saleResponse, null, true)
    await refreshShiftSummary()
}

const handlePartialBankTransfer = () => {
    // Partial transfers remain visible in the checkout status while polling continues.
}


const checkout = async (data) => {

    if (!cart.value.length) {
        toast.error('Giỏ hàng trống')
        return
    }

    await handleCheckout({
        note: data.note,
        payment_method: data.payment_method,
        paid_amount: Number(data.paid_amount || 0),
        cash_received_amount: Number(data.cash_received_amount || 0),
        bank_received_amount: Number(data.bank_received_amount || 0),
        bank_account: data.bank_account ?? null,
        pay_old_debt: data.pay_old_debt,
        invoice_code: data.invoice_code,
    })
}


// Hiện popup thanh toán
const showCheckoutModal = ref(false)
const checkoutInvoiceCode = ref('')

const makeInvoiceCode = () => {
    const timeZone = page.props.settings?.app_timezone || 'Asia/Ho_Chi_Minh'
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date()).map(({ type, value }) => [type, value]))

    return `INV-${parts.year}${parts.month}${parts.day}${parts.hour}${parts.minute}${parts.second}`
}


const openCheckoutModal = () => {

    if (!cart.value.length) {

        toast.error('Giỏ hàng trống')

        return
    }

    checkoutInvoiceCode.value = makeInvoiceCode()
    showCheckoutModal.value = true
}

useKeyboardShortcuts({
    cart,
    selectedCartIndex,
    clearCart,
    showCheckoutModal,
    checkout: openCheckoutModal,
})

useBarcodeScanner(
    async (barcode) => {
        try {
            const result = await productService.scan(barcode)

            handleSelectProduct(result.data)
        } catch (error) {
            console.error(error)
            toast.error('Không tìm thấy sản phẩm với mã vạch này')
        }
    }
)

</script>

<template>

    <PosLayout :cart-count="cartItemCount" :grand-total="grandTotal" :shift="shift" @close="closePos" @manage-shift="manageShift" @show-shift-history="showShiftHistory">
        <template #main>
            <PosMainPanel
                :cart="cart"
                @add-product="handleSelectProduct"
            />
        </template>

        <template #sidebar>
            <PosSidebar
                :tabs="tabs"
                :active-tab-id="activeTabId"
                :cart="cart"
                :selected-customer="selectedCustomer"
                :grand-total="grandTotal"
                :loading="loading"
                @select-tab="selectTab"
                @create-tab="createTab"
                @remove-tab="removeTab"
                @customer-selected="onCustomerSelected"
                @remove-item="removeItem"
                @checkout="openCheckoutModal"
            />
        </template>

    </PosLayout>

    <CheckoutModal
        :loading="loading"
        :show="showCheckoutModal"
        :invoice-code="checkoutInvoiceCode"
        :cart="cart"
        :grand-total="grandTotal"
        :selected-customer="selectedCustomer"
        @close="showCheckoutModal = false"
        @confirm="checkout($event)"
        @bank-completed="handleBankCheckoutCompleted"
        @bank-completed-background="handleBackgroundBankCheckoutCompleted"
        @bank-transfer-partial="handlePartialBankTransfer"
    />

    <div v-if="showPaymentOutcome && paymentOutcome" class="fixed inset-0 z-[10050] flex min-h-screen items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm">
        <section role="dialog" aria-modal="true" aria-labelledby="payment-outcome-title" class="w-full max-w-xl rounded-3xl bg-white p-7 text-center shadow-2xl sm:p-10">
            <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full" :class="paymentOutcome.status === 'exact' ? 'bg-emerald-100 text-emerald-600' : paymentOutcome.status === 'excess' ? 'bg-amber-100 text-amber-600' : 'bg-rose-100 text-rose-600'">
                <span class="text-4xl font-black">{{ paymentOutcome.status === 'exact' ? '✓' : paymentOutcome.status === 'excess' ? '+' : '−' }}</span>
            </div>
            <h2 id="payment-outcome-title" class="text-2xl font-black text-slate-900 sm:text-3xl">
                {{ paymentOutcome.status === 'exact' ? 'Đã nhận đủ tiền' : paymentOutcome.status === 'excess' ? 'Khách đã trả thừa' : 'Khách còn thiếu tiền' }}
            </h2>
            <p v-if="paymentOutcome.code" class="mt-2 text-sm font-semibold text-slate-500">Hóa đơn {{ paymentOutcome.code }}</p>
            <div class="mt-7 space-y-3 rounded-2xl bg-slate-50 p-5 text-left">
                <div class="flex items-center justify-between gap-4 text-sm"><span class="text-slate-600">Tổng cần thanh toán</span><strong class="text-slate-900">{{ formatMoney(paymentOutcome.expected) }}</strong></div>
                <div class="flex items-center justify-between gap-4 text-sm"><span class="text-slate-600">Đã nhận</span><strong class="text-slate-900">{{ formatMoney(paymentOutcome.received) }}</strong></div>
                <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-3"><span class="font-bold text-slate-700">{{ paymentOutcome.status === 'excess' ? 'Tiền thừa' : paymentOutcome.status === 'short' ? 'Còn thiếu' : 'Trạng thái' }}</span><strong :class="paymentOutcome.status === 'short' ? 'text-rose-600' : paymentOutcome.status === 'excess' ? 'text-amber-600' : 'text-emerald-700'">{{ paymentOutcome.status === 'exact' ? 'Đã thanh toán đủ' : formatMoney(Math.abs(paymentOutcome.difference)) }}</strong></div>
            </div>
            <button type="button" autofocus class="mt-7 w-full rounded-xl bg-blue-600 px-6 py-4 text-base font-extrabold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700" @click="showPaymentOutcome = false">OK</button>
        </section>
    </div>

    <SelectProductModal
        v-if="selectedProduct"
        :show="showSelectModal"
        :product="selectedProduct"
        :cart="cart"
        @close="showSelectModal = false"
        @confirm="handleConfirmSelect"
    />

    <WorkShiftOpenModal v-if="shiftModal && shiftMode === 'open' && !shiftResult" :denominations="denominationValues" :counts="shiftDenominations" :balances="shiftBankBalances" :accounts="bankAccounts" :money="formatMoney" @close="shiftModal = false" @opened="handleShiftOpened" />

    <div v-if="shiftModal && (shiftMode === 'close' || shiftResult)" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-4">
        <section class="flex h-[92vh] max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <template v-if="!shiftResult">
                <header class="-mx-4 -mt-4 shrink-0 border-b border-slate-200 bg-slate-50 px-4 pb-3 pt-4 sm:-mx-5 sm:-mt-5 sm:px-5 sm:pt-5">
                    <h2 class="text-lg font-bold text-slate-900">{{ shiftMode === 'open' ? 'Mở ca làm' : 'Kết thúc ca làm' }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ shiftMode === 'open' ? 'Nhập tiền mặt đầu ca và số dư tài khoản.' : 'Kiểm đếm tiền mặt và cập nhật số dư tài khoản cuối ca.' }}</p>
                </header>
                <div class="shift-modal-scroll mt-3 min-h-0 flex-1 overflow-y-auto pr-1">
                    <WorkShiftCountForm :denominations="denominationValues" :counts="shiftDenominations" :cash-amount="shiftCashAmount" :cash-detail="shiftCashDetail" :balances="shiftBankBalances" :accounts="bankAccounts" :money="formatMoney" :mode="shiftMode" @update:cash-amount="shiftCashAmount = $event" @update:cash-detail="shiftCashDetail = $event" />
                    <div v-if="shiftMode === 'close' && shift" class="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm sm:p-4">
                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                <table class="no-grid w-full table-fixed text-xs sm:text-sm">
                                <thead class="border-b-2 border-blue-800 bg-blue-700 text-[11px] font-extrabold uppercase tracking-wide text-white sm:text-xs"><tr><th class="w-[55%] border-r border-blue-500 px-2 py-2.5 text-left sm:px-3">Khoản mục</th><th class="w-[22.5%] border-r border-blue-500 px-1 py-2.5 text-right sm:px-3">Đầu ca</th><th class="w-[22.5%] px-2 py-2.5 text-right sm:px-3">Cuối ca</th></tr></thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr><td class="px-2 py-2 text-left sm:px-3">Tiền mặt kiểm đếm</td><td class="whitespace-nowrap px-1 py-2 text-right font-bold tabular-nums sm:px-3">{{ formatMoney(shift.opening_amount) }}</td><td class="whitespace-nowrap px-2 py-2 text-right font-bold tabular-nums sm:px-3">{{ formatMoney(countedCashTotal) }}</td></tr>
                                    <tr><td class="px-2 py-2 text-left sm:px-3">Tiền tài khoản</td><td class="whitespace-nowrap px-1 py-2 text-right font-bold tabular-nums sm:px-3">{{ formatMoney(openingBankTotal) }}</td><td class="whitespace-nowrap px-2 py-2 text-right font-bold tabular-nums sm:px-3">{{ formatMoney(countedBankTotal) }}</td></tr>
                                    <tr class="text-slate-600"><td class="px-2 py-2 text-left sm:px-3">Tiền mặt bán hàng thu</td><td class="px-1 py-2 text-right">—</td><td class="whitespace-nowrap px-2 py-2 text-right tabular-nums sm:px-3">{{ formatMoney(shift.cash_collected) }}</td></tr>
                                    <tr class="text-slate-600"><td class="px-2 py-2 text-left sm:px-3">Tiền chuyển khoản thu</td><td class="px-1 py-2 text-right">—</td><td class="whitespace-nowrap px-2 py-2 text-right tabular-nums sm:px-3">{{ formatMoney(bankTransferTotal) }}</td></tr>
                                    <tr class="text-slate-600"><td class="px-2 py-2 text-left sm:px-3">Nợ khách phát sinh</td><td class="px-1 py-2 text-right">—</td><td class="whitespace-nowrap px-2 py-2 text-right tabular-nums sm:px-3">{{ formatMoney(shift.customer_debt_amount) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="grid gap-2 border-t border-slate-200 pt-3 sm:grid-cols-2">
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2"><span class="font-semibold text-slate-600">Tổng đầu ca</span><strong class="text-slate-900">{{ formatMoney(openingCombinedTotal) }}</strong></div>
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-100 px-3 py-2"><span class="font-bold text-emerald-800">Tổng cuối ca</span><strong class="text-emerald-900">{{ formatMoney(countedCashAndBankTotal) }}</strong></div>
                        </div>
                        <details v-if="shift.bank_sales?.length" class="border-t border-slate-200 pt-2">
                            <summary class="cursor-pointer font-semibold text-blue-700">Chi tiết {{ shift.bank_sales.length }} đơn chuyển khoản</summary>
                            <div class="mt-1.5 max-h-36 overflow-y-auto">
                                <p v-for="sale in shift.bank_sales" :key="sale.id" class="flex justify-between gap-3 py-1 text-xs"><span class="min-w-0 truncate text-slate-600">{{ sale.code }} · {{ sale.bank_name || sale.bank_account || 'Chưa xác định tài khoản' }}</span><strong class="shrink-0">{{ formatMoney(sale.amount) }}</strong></p>
                            </div>
                        </details>
                    </div>
                </div>
                <footer class="-mx-4 -mb-4 mt-3 flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:-mx-5 sm:-mb-5 sm:px-5"><button class="rounded-xl px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="shiftModal = false">Đóng</button><button :disabled="shiftLoading" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="submitShift">{{ shiftLoading ? 'Đang lưu…' : shiftMode === 'open' ? 'Bắt đầu ca' : 'Kết thúc ca' }}</button></footer>
            </template>
            <template v-else>
                <header class="-mx-4 -mt-4 shrink-0 border-b border-slate-200 bg-slate-50 px-4 pb-3 pt-4 sm:-mx-5 sm:-mt-5 sm:px-5 sm:pt-5"><h2 class="text-lg font-bold text-slate-900">Tổng kết ca làm</h2></header>
                <div class="shift-modal-scroll mt-3 min-h-0 flex-1 space-y-3 overflow-y-auto pr-1 text-sm">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 px-3 py-2"><span class="text-xs text-slate-500">Tiền đầu ca</span><strong class="mt-0.5 block">{{ shiftResult.opening_denominations === null ? 'Không kiểm kê' : formatMoney(shiftResult.opening_amount) }}</strong></div>
                        <div class="rounded-xl bg-emerald-50 px-3 py-2"><span class="text-xs text-emerald-700">Tiền mặt bán hàng thu</span><strong class="mt-0.5 block text-emerald-800">{{ formatMoney(shiftResult.cash_collected) }}</strong></div>
                        <div class="rounded-xl bg-amber-50 px-3 py-2"><span class="text-xs text-amber-700">Nợ khách trong ca</span><strong class="mt-0.5 block text-amber-800">{{ formatMoney(shiftResult.customer_debt_amount) }}</strong></div>
                        <div class="rounded-xl bg-blue-50 px-3 py-2"><span class="text-xs text-blue-700">Tiền mặt cuối ca dự kiến</span><strong class="mt-0.5 block text-blue-800">{{ formatMoney(shiftResult.expected_cash) }}</strong></div>
                        <div class="rounded-xl bg-blue-50 px-3 py-2"><span class="text-xs text-blue-700">Tiền chuyển khoản trong ca</span><strong class="mt-0.5 block text-blue-800">{{ formatMoney(resultBankTransferTotal) }}</strong></div>
                        <div class="rounded-xl bg-cyan-50 px-3 py-2"><span class="text-xs text-cyan-700">Tài khoản cuối ca</span><strong class="mt-0.5 block text-cyan-800">{{ formatMoney(closingBankTotal) }}</strong></div>
                        <div class="rounded-xl bg-emerald-100 px-3 py-2"><span class="text-xs text-emerald-800">Tổng tiền mặt + tài khoản</span><strong class="mt-0.5 block text-emerald-900">{{ formatMoney(combinedClosingTotal) }}</strong></div>
                    </div>


                    <details v-if="shiftResult.bank_sales?.length" class="overflow-hidden rounded-xl border border-slate-200">
                        <summary class="cursor-pointer bg-slate-50 px-3 py-2 text-sm font-bold text-blue-700">Chi tiết {{ shiftResult.bank_sales.length }} đơn chuyển khoản</summary>
                        <div class="max-h-40 overflow-y-auto"><p v-for="sale in shiftResult.bank_sales" :key="sale.id" class="flex justify-between gap-3 border-t border-slate-100 px-3 py-1.5 text-xs"><span class="min-w-0 truncate text-slate-600">{{ sale.code }} · {{ sale.bank_name || sale.bank_account || 'Chưa xác định tài khoản' }}</span><strong class="shrink-0">{{ formatMoney(sale.amount) }}</strong></p></div>
                    </details>

                    <section class="overflow-hidden rounded-xl border border-slate-200">
                        <h3 class="bg-slate-50 px-3 py-2 text-sm font-bold text-slate-800">Đối chiếu tiền mặt theo mệnh giá</h3>
                        <div class="overflow-x-auto">
                            <table class="no-grid w-full min-w-[620px] text-sm">
                                <thead class="border-b-2 border-blue-800 bg-blue-700 text-xs font-extrabold uppercase tracking-wide text-white"><tr><th class="border-r border-blue-500 px-3 py-2 text-left">Mệnh giá</th><th class="border-r border-blue-500 px-3 py-2 text-right">SL đầu ca</th><th class="border-r border-blue-500 px-3 py-2 text-right">Tiền đầu ca</th><th class="border-r border-blue-500 px-3 py-2 text-right">SL cuối ca</th><th class="px-3 py-2 text-right">Tiền cuối ca</th></tr></thead>
                                <tbody class="divide-y divide-slate-100"><tr v-for="(row, index) in denominationComparison" :key="row.value" :class="index % 2 === 0 ? 'bg-white' : 'bg-slate-50'"><td class="px-3 py-1.5 font-semibold">{{ formatMoney(row.value) }}</td><td class="px-3 py-1.5 text-right">{{ row.openingCount }}</td><td class="px-3 py-1.5 text-right text-slate-600">{{ formatMoney(row.openingAmount) }}</td><td class="px-3 py-1.5 text-right">{{ row.closingCount }}</td><td class="px-3 py-1.5 text-right font-semibold">{{ formatMoney(row.closingAmount) }}</td></tr></tbody>
                                <tfoot class="bg-slate-50 font-bold"><tr><td class="px-3 py-2">Tổng tiền mặt</td><td></td><td class="px-3 py-2 text-right">{{ formatMoney(shiftResult.opening_amount) }}</td><td></td><td class="px-3 py-2 text-right">{{ formatMoney(shiftResult.closing_amount) }}</td></tr></tfoot>
                            </table>
                        </div>
                    </section>

                    <section v-if="bankAccounts.length" class="overflow-hidden rounded-xl border border-slate-200">
                        <h3 class="bg-slate-50 px-3 py-2 text-sm font-bold text-slate-800">Đối chiếu số dư ngân hàng</h3>
                        <div class="overflow-x-auto"><table class="no-grid w-full min-w-[760px] text-sm"><thead class="border-b-2 border-blue-800 bg-blue-700 text-xs font-extrabold uppercase tracking-wide text-white"><tr><th class="border-r border-blue-500 px-3 py-2 text-left">Tài khoản</th><th class="border-r border-blue-500 px-3 py-2 text-right">Đầu ca</th><th class="border-r border-blue-500 px-3 py-2 text-right">Đơn CK</th><th class="border-r border-blue-500 px-3 py-2 text-right">Dự kiến</th><th class="border-r border-blue-500 px-3 py-2 text-right">Cuối ca</th><th class="px-3 py-2 text-right">Lệch</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(account, index) in bankAccounts" :key="account.shift_key" :class="index % 2 === 0 ? 'bg-white' : 'bg-slate-50'"><td class="px-3 py-1.5 font-medium">{{ account.bank_name || `BIN ${account.bank_bin}` }} · {{ account.bank_account }}</td><td class="px-3 py-1.5 text-right">{{ formatMoney(shiftResult.opening_bank_balances?.[account.shift_key]) }}</td><td class="px-3 py-1.5 text-right text-emerald-700">{{ formatMoney(shiftResult.bank_collected?.[account.shift_key]) }}</td><td class="px-3 py-1.5 text-right">{{ formatMoney(shiftResult.expected_bank_balances?.[account.shift_key]) }}</td><td class="px-3 py-1.5 text-right font-semibold">{{ formatMoney(shiftResult.closing_bank_balances?.[account.shift_key]) }}</td><td class="px-3 py-1.5 text-right">{{ formatMoney(Number(shiftResult.closing_bank_balances?.[account.shift_key] || 0) - Number(shiftResult.expected_bank_balances?.[account.shift_key] || 0)) }}</td></tr></tbody></table></div>
                    </section>


                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm"><span>Chênh lệch tiền mặt (thực tế so với dự kiến)</span><strong :class="shiftResult.difference < 0 ? 'text-rose-600' : 'text-emerald-700'">{{ formatMoney(shiftResult.difference) }}</strong></div>
                </div>
                <footer class="-mx-4 -mb-4 mt-3 shrink-0 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:-mx-5 sm:-mb-5 sm:px-5"><button class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" @click="shiftModal = false">Hoàn tất</button></footer>
            </template>
        </section>
    </div>

    <div v-if="shiftHistoryModal" class="fixed inset-0 z-[85] flex items-center justify-center bg-slate-950/50 p-4">
        <section class="flex max-h-[88vh] w-full max-w-4xl flex-col rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                <div><h2 class="text-lg font-bold text-slate-900">Lịch sử kiểm kê</h2><p class="mt-0.5 text-xs text-slate-500">Các ca đã kết thúc trong 30 ngày gần nhất.</p></div>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100" @click="shiftHistoryModal = false">Đóng</button>
            </div>
            <div class="mt-3 min-h-0 space-y-4 overflow-y-auto pr-1">
                <p v-if="shiftHistoryLoading" class="py-8 text-center text-sm text-slate-500">Đang tải lịch sử…</p>
                <p v-else-if="!groupedShiftHistory.length" class="py-8 text-center text-sm text-slate-500">Chưa có lịch sử kiểm kê trong 30 ngày gần đây.</p>
                <section v-for="day in groupedShiftHistory" :key="day.date" class="space-y-2">
                    <h3 class="sticky top-0 rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold capitalize text-slate-700">{{ displayHistoryDate(day.date) }}</h3>
                    <article v-for="item in day.shifts" :key="item.id" class="rounded-xl border border-slate-200 p-3">
                        <div class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <p><span class="block text-xs text-slate-500">Thời gian đóng ca</span><strong>{{ item.closed_at }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Tiền đầu ca</span><strong>{{ item.opening_denominations === null ? 'Không kiểm kê' : formatMoney(item.opening_amount) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Tiền mặt cuối ca dự kiến</span><strong>{{ formatMoney(item.expected_cash) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Tiền thực tế kiểm đếm</span><strong>{{ item.closing_amount === null ? 'Không kiểm kê' : formatMoney(item.closing_amount) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Tiền mặt bán hàng thu</span><strong>{{ formatMoney(item.cash_collected) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Nợ khách phát sinh</span><strong>{{ formatMoney(item.customer_debt_amount) }}</strong></p>
                            <p v-if="item.difference !== null"><span class="block text-xs text-slate-500">Chênh lệch</span><strong :class="item.difference < 0 ? 'text-rose-600' : 'text-emerald-700'">{{ formatMoney(item.difference) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Hóa đơn trong ca</span><strong>{{ item.sale_count }}</strong></p>
                        </div>
                        <details class="mt-2 border-t border-slate-100 pt-2 text-xs">
                            <summary class="cursor-pointer font-semibold text-blue-700">Chi tiết kiểm đếm cuối ca</summary>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <div><p class="mb-1 font-semibold text-slate-700">Tiền mặt theo mệnh giá</p><p v-for="row in denominationRows(item.closing_denominations)" :key="row.value" class="flex justify-between py-0.5 text-slate-600"><span>{{ formatMoney(row.value) }} × {{ row.count }}</span><span>{{ formatMoney(row.value * row.count) }}</span></p><p v-if="!denominationRows(item.closing_denominations).length" class="text-slate-500">Không có dữ liệu chi tiết.</p></div>
                                <div v-if="bankAccounts.length"><p class="mb-1 font-semibold text-slate-700">Số dư tài khoản ngân hàng</p><p v-for="account in bankBalanceRows(item.closing_bank_balances)" :key="account.shift_key" class="flex justify-between gap-2 py-0.5 text-slate-600"><span>{{ account.bank_name || account.bank_account }}</span><strong>{{ formatMoney(account.balance) }}</strong></p></div>
                            </div>
                        </details>
                    </article>
                </section>
            </div>
        </section>
    </div>


</template>

<style scoped>
.shift-modal-scroll {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.shift-modal-scroll::-webkit-scrollbar {
    width: 3px;
    height: 3px;
}

.shift-modal-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.shift-modal-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: #cbd5e1;
}

.shift-modal-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
