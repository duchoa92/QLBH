<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import { useBarcodeScanner } from '@/Modules/POS/Product/Composables/useBarcodeScanner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { toast } from 'vue-sonner'
import { useCart } from '@/Modules/POS/Cart/Composables/useCart'
import { useCheckout } from '@/Modules/POS/Payment/Composables/useCheckout'
import { useKeyboardShortcuts } from '@/Modules/POS/Core/Composables/useKeyboardShortcuts'
import PosSidebar from '@/Modules/POS/Core/Components/PosSidebar.vue'
import PosMainPanel from '@/Modules/POS/Core/Components/PosMainPanel.vue'
import PosLayout from '@/Modules/POS/Core/Layouts/PosLayout.vue'
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue'
import SelectProductModal from '@/Modules/POS/Product/Components/SelectProductModal.vue'
import { getPosReturnUrl, exitPosFullscreen } from '@/utils/pos'

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
const shiftBankBalances = ref({})
const shiftLoading = ref(false)
const shiftResult = ref(null)
const shiftHistoryModal = ref(false)
const shiftHistory = ref([])
const shiftHistoryLoading = ref(false)
let shiftRefreshTimer = null
const formatMoney = value => `${Number(value || 0).toLocaleString('vi-VN')} ₫`
const denominationTotal = computed(() => Object.entries(shiftDenominations.value).reduce((total, [value, count]) => total + Number(value) * Number(count || 0), 0))
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
const normalizedDenominations = () => Object.fromEntries(denominationValues.map(value => [value, Math.max(0, Math.floor(Number(shiftDenominations.value[value] || 0)))]))
const normalizedBankBalances = () => Object.fromEntries(bankAccounts.value.map(account => [account.shift_key, Math.max(0, Number(shiftBankBalances.value[account.shift_key] || 0))]))
const bankAccounts = computed(() => {
    const accounts = page.props.settings?.bank_accounts || []
    if (accounts.length) return accounts.map(account => ({ ...account, shift_key: String(account.id || account.bank_account) }))
    const settings = page.props.settings || {}
    return settings.bank_bin && settings.bank_account ? [{ id: 'default', shift_key: 'default', bank_name: `BIN ${settings.bank_bin}`, bank_account: settings.bank_account, bank_account_name: settings.bank_account_name }] : []
})
const bankBalanceRows = balances => bankAccounts.value.map(account => ({ ...account, balance: balances?.[account.shift_key] ?? 0 }))
const bankBalanceTotal = balances => bankAccounts.value.reduce((total, account) => total + Number(balances?.[account.shift_key] || 0), 0)
const focusNextCount = event => {
    event.preventDefault()
    const inputs = [...document.querySelectorAll('[data-shift-count]')]
    const next = inputs.indexOf(event.currentTarget) + 1
    if (inputs[next]) inputs[next].focus()
}
const selectShiftInputValue = event => event.target.select()

const loadShift = async () => {
    const { data } = await axios.get(route('pos.shifts.current'))
    shift.value = data.shift
    if (!data.shift) {
        shiftMode.value = 'open'
        shiftDenominations.value = Object.fromEntries(denominationValues.map(value => [value, 0]))
        shiftBankBalances.value = Object.fromEntries(bankAccounts.value.map(account => [account.shift_key, 0]))
        shiftModal.value = true
    }
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
    shiftDenominations.value = Object.fromEntries(denominationValues.map(value => [
        value,
        shiftMode.value === 'close' ? Number(shift.value?.opening_denominations?.[value] || 0) : 0,
    ]))
    shiftBankBalances.value = Object.fromEntries(bankAccounts.value.map(account => [
        account.shift_key,
        shiftMode.value === 'close' ? Number(shift.value?.opening_bank_balances?.[account.shift_key] || 0) : 0,
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
            const { data } = await axios.post(route('pos.shifts.open'), { denominations: normalizedDenominations(), bank_balances: normalizedBankBalances() })
            shift.value = data.shift
            shiftModal.value = false
        } else {
            const { data } = await axios.post(route('pos.shifts.close'), { denominations: normalizedDenominations(), bank_balances: normalizedBankBalances() })
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

// loading khi nhấn checkout để tránh việc click nhiều lần vào nút checkout
const loading = ref(false)

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

        loading.value = true

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


const checkout = async (data) => {

    if (!cart.value.length) {
        toast.error('Giỏ hàng trống')
        return
    }

    await handleCheckout({
        note: data.note,
        payment_method: data.payment_method,
        paid_amount: Number(data.paid_amount || 0),
        pay_old_debt: data.pay_old_debt,
    })
}


// Hiện popup thanh toán
const showCheckoutModal = ref(false)


const openCheckoutModal = () => {

    if (!cart.value.length) {

        toast.error('Giỏ hàng trống')

        return
    }

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
        :cart="cart"
        :grand-total="grandTotal"
        :selected-customer="selectedCustomer"
        @close="showCheckoutModal = false"
        @confirm="checkout($event)"
    />

    <SelectProductModal
        v-if="selectedProduct"
        :show="showSelectModal"
        :product="selectedProduct"
        :cart="cart"
        @close="showSelectModal = false"
        @confirm="handleConfirmSelect"
    />

    <div v-if="shiftModal" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-4">
        <section class="flex h-[92vh] max-h-[92vh] w-full max-w-5xl flex-col rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <template v-if="!shiftResult">
                <h2 class="text-lg font-bold text-slate-900">{{ shiftMode === 'open' ? 'Mở ca làm' : 'Kết thúc ca làm' }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ shiftMode === 'open' ? 'Nhập số lượng tiền mặt đầu ca và số dư các tài khoản.' : 'Kiểm đếm tiền mặt và cập nhật số dư các tài khoản cuối ca.' }}</p>
                <div class="mt-3 grid min-h-0 flex-1 gap-4 overflow-y-auto lg:grid-cols-2">
                  <div>
                    <h3 class="mb-2 text-sm font-bold text-slate-800">Tiền mặt theo mệnh giá</h3>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-100 text-[11px] uppercase text-slate-500"><tr><th class="rounded-l-lg px-2 py-1.5">Mệnh giá</th><th class="px-2 py-1.5">Số lượng</th><th class="rounded-r-lg px-2 py-1.5 text-right">Thành tiền</th></tr></thead>
                        <tbody class="divide-y divide-slate-100"><tr v-for="value in denominationValues" :key="value">
                            <td class="px-2 py-1.5 font-semibold text-slate-700">{{ formatMoney(value) }}</td>
                            <td class="px-2 py-1"><input v-model.number="shiftDenominations[value]" data-shift-count type="text" inputmode="numeric" class="w-20 rounded-lg border-slate-300 px-2 py-1 text-right text-sm" @focus="selectShiftInputValue" @keydown.enter="focusNextCount" /></td>
                            <td class="px-2 py-1.5 text-right text-slate-600">{{ formatMoney(value * Number(shiftDenominations[value] || 0)) }}</td>
                        </tr></tbody>
                    </table>
                    <div class="mt-2 flex justify-between rounded-lg bg-indigo-50 px-3 py-2 text-sm"><span class="font-semibold text-indigo-800">{{ shiftMode === 'open' ? 'Tổng tiền đầu ca' : 'Tổng tiền kiểm đếm' }}</span><strong class="text-indigo-800">{{ formatMoney(denominationTotal) }}</strong></div>
                  </div>
                  <div class="space-y-3">
                    <div v-if="bankAccounts.length">
                        <h3 class="mb-2 text-sm font-bold text-slate-800">Số dư tài khoản ngân hàng</h3>
                        <table class="w-full text-left text-sm"><thead class="bg-slate-100 text-[11px] uppercase text-slate-500"><tr><th class="rounded-l-lg px-2 py-1.5">Tài khoản</th><th class="px-2 py-1.5">Chủ tài khoản</th><th class="rounded-r-lg px-2 py-1.5 text-right">Số dư</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="account in bankAccounts" :key="account.shift_key"><td class="px-2 py-2"><strong>{{ account.bank_name || `BIN ${account.bank_bin}` }}</strong><div class="text-xs text-slate-500">{{ account.bank_account }}</div></td><td class="px-2 py-2 text-xs text-slate-600">{{ account.bank_account_name || '—' }}</td><td class="px-2 py-2 text-right"><input v-model.number="shiftBankBalances[account.shift_key]" data-shift-count type="text" inputmode="numeric" class="w-28 rounded-lg border-slate-300 px-2 py-1 text-right text-sm" @focus="selectShiftInputValue" @keydown.enter="focusNextCount" /></td></tr></tbody></table>
                        <p class="mt-1 text-right text-xs font-bold text-slate-700">Tổng số dư: {{ formatMoney(bankBalanceTotal(shiftBankBalances)) }}</p>
                    </div>
                    <div v-if="shiftMode === 'close' && shift" class="space-y-1.5 rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="flex justify-between"><span>Tiền đầu ca</span><strong>{{ formatMoney(shift.opening_amount) }}</strong></p>
                        <p class="flex justify-between"><span>Tiền mặt bán hàng thu được</span><strong>{{ formatMoney(shift.cash_collected) }}</strong></p>
                        <p class="flex justify-between"><span>Nợ khách phát sinh trong ca</span><strong>{{ formatMoney(shift.customer_debt_amount) }}</strong></p>
                        <p class="flex justify-between border-t border-slate-200 pt-1.5"><span>Tổng Tiền mặt cuối ca</span><strong>{{ formatMoney(shift.expected_cash) }}</strong></p>
                    </div>
                  </div>
                </div>
                <div class="mt-3 flex justify-end gap-2 border-t border-slate-100 pt-3"><button class="rounded-xl px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="shiftModal = false">Đóng</button><button :disabled="shiftLoading" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="submitShift">{{ shiftLoading ? 'Đang lưu…' : shiftMode === 'open' ? 'Bắt đầu ca' : 'Kết thúc ca' }}</button></div>
            </template>
            <template v-else>
                <h2 class="shrink-0 text-lg font-bold text-slate-900">Tổng kết ca làm</h2>
                <div class="mt-3 min-h-0 flex-1 space-y-3 overflow-y-auto pr-1 text-sm">
                    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 px-3 py-2"><span class="text-xs text-slate-500">Tiền đầu ca</span><strong class="mt-0.5 block">{{ formatMoney(shiftResult.opening_amount) }}</strong></div>
                        <div class="rounded-xl bg-emerald-50 px-3 py-2"><span class="text-xs text-emerald-700">Tiền mặt bán hàng thu</span><strong class="mt-0.5 block text-emerald-800">{{ formatMoney(shiftResult.cash_collected) }}</strong></div>
                        <div class="rounded-xl bg-amber-50 px-3 py-2"><span class="text-xs text-amber-700">Nợ khách trong ca</span><strong class="mt-0.5 block text-amber-800">{{ formatMoney(shiftResult.customer_debt_amount) }}</strong></div>
                        <div class="rounded-xl bg-indigo-50 px-3 py-2"><span class="text-xs text-indigo-700">Tiền mặt cuối ca</span><strong class="mt-0.5 block text-indigo-800">{{ formatMoney(shiftResult.expected_cash) }}</strong></div>
                    </div>

                    <section class="overflow-hidden rounded-xl border border-slate-200">
                        <h3 class="bg-slate-50 px-3 py-2 text-sm font-bold text-slate-800">Đối chiếu tiền mặt theo mệnh giá</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-sm">
                                <thead class="bg-slate-100 text-xs text-slate-500"><tr><th class="px-3 py-2 text-left">Mệnh giá</th><th class="px-3 py-2 text-right">SL đầu ca</th><th class="px-3 py-2 text-right">Tiền đầu ca</th><th class="px-3 py-2 text-right">SL cuối ca</th><th class="px-3 py-2 text-right">Tiền cuối ca</th></tr></thead>
                                <tbody class="divide-y divide-slate-100"><tr v-for="(row, index) in denominationComparison" :key="row.value" :class="index % 2 === 0 ? 'bg-white' : 'bg-slate-50'"><td class="px-3 py-1.5 font-semibold">{{ formatMoney(row.value) }}</td><td class="px-3 py-1.5 text-right">{{ row.openingCount }}</td><td class="px-3 py-1.5 text-right text-slate-600">{{ formatMoney(row.openingAmount) }}</td><td class="px-3 py-1.5 text-right">{{ row.closingCount }}</td><td class="px-3 py-1.5 text-right font-semibold">{{ formatMoney(row.closingAmount) }}</td></tr></tbody>
                                <tfoot class="bg-slate-50 font-bold"><tr><td class="px-3 py-2">Tổng tiền mặt</td><td></td><td class="px-3 py-2 text-right">{{ formatMoney(shiftResult.opening_amount) }}</td><td></td><td class="px-3 py-2 text-right">{{ formatMoney(shiftResult.closing_amount) }}</td></tr></tfoot>
                            </table>
                        </div>
                    </section>

                    <section v-if="bankAccounts.length" class="overflow-hidden rounded-xl border border-slate-200">
                        <h3 class="bg-slate-50 px-3 py-2 text-sm font-bold text-slate-800">Đối chiếu số dư ngân hàng</h3>
                        <div class="overflow-x-auto"><table class="w-full min-w-[480px] text-sm"><thead class="bg-slate-100 text-xs text-slate-500"><tr><th class="px-3 py-2 text-left">Tài khoản</th><th class="px-3 py-2 text-right">Đầu ca</th><th class="px-3 py-2 text-right">Cuối ca</th><th class="px-3 py-2 text-right">Chênh lệch</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(account, index) in bankAccounts" :key="account.shift_key" :class="index % 2 === 0 ? 'bg-white' : 'bg-slate-50'"><td class="px-3 py-1.5 font-medium">{{ account.bank_name || `BIN ${account.bank_bin}` }} · {{ account.bank_account }}</td><td class="px-3 py-1.5 text-right">{{ formatMoney(shiftResult.opening_bank_balances?.[account.shift_key]) }}</td><td class="px-3 py-1.5 text-right font-semibold">{{ formatMoney(shiftResult.closing_bank_balances?.[account.shift_key]) }}</td><td class="px-3 py-1.5 text-right">{{ formatMoney(Number(shiftResult.closing_bank_balances?.[account.shift_key] || 0) - Number(shiftResult.opening_bank_balances?.[account.shift_key] || 0)) }}</td></tr></tbody></table></div>
                    </section>

                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm"><span>Chênh lệch tiền mặt (thực tế so với dự kiến)</span><strong :class="shiftResult.difference < 0 ? 'text-rose-600' : 'text-emerald-700'">{{ formatMoney(shiftResult.difference) }}</strong></div>
                </div>
                <div class="shrink-0 border-t border-slate-100 pt-3"><button class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white" @click="shiftModal = false">Hoàn tất</button></div>
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
                            <p><span class="block text-xs text-slate-500">Tiền đầu ca</span><strong>{{ formatMoney(item.opening_amount) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Tiền mặt cuối ca</span><strong>{{ formatMoney(item.expected_cash) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Tiền thực tế kiểm đếm</span><strong>{{ formatMoney(item.closing_amount) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Tiền mặt bán hàng thu</span><strong>{{ formatMoney(item.cash_collected) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Nợ khách phát sinh</span><strong>{{ formatMoney(item.customer_debt_amount) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Chênh lệch</span><strong :class="item.difference < 0 ? 'text-rose-600' : 'text-emerald-700'">{{ formatMoney(item.difference) }}</strong></p>
                            <p><span class="block text-xs text-slate-500">Hóa đơn trong ca</span><strong>{{ item.sale_count }}</strong></p>
                        </div>
                        <details class="mt-2 border-t border-slate-100 pt-2 text-xs">
                            <summary class="cursor-pointer font-semibold text-indigo-700">Chi tiết kiểm đếm cuối ca</summary>
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
