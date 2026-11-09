import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { useLocalStorage } from '@/Composables/useLocalStorage'
import { useCartTotals } from '@/Modules/POS/Cart/Composables/useCartTotals'
import { usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

const attributeText = (attribute) => {
    if (attribute === null || attribute === undefined || attribute === '') {
        return ''
    }

    if (typeof attribute !== 'object') {
        return String(attribute)
    }

    return attribute.value
        ?? attribute.label
        ?? attribute.name
        ?? attribute.title
        ?? attribute.text
        ?? ''
}

const getVariantLabel = (variant) => {
    const attributes = Object.values(variant?.attributes || {})
        .map(attributeText)
        .filter(Boolean)

    return attributes.join(' / ')
        || variant?.sku
        || (variant?.id ? `#${variant.id}` : null)
}

const unitPayload = (product) => ({
    unit_id: product.unit_id ?? null,
    unit_name: product.unit_name ?? product.unit?.short_name ?? product.unit?.name ?? 'Cái',
    conversion_factor: 1,
    inventory_unit_name: product.unit_name ?? product.unit?.short_name ?? product.unit?.name ?? 'Cái',
})


const makeDefaultTab = () => ({
    id: 1,
    default_name: 'Đơn 1',
    name: 'Đơn 1',
    cart: [],
    customer: null,
    invoice_discount: 0,
    invoice_discount_type: 'amount',
    extra_fee: 0,
    extra_fee_type: 'amount',
})

// Dữ liệu cũ trong localStorage có thể hỏng/thiếu -> chuẩn hoá để POS luôn mở được
const normalizeTabs = (raw) => {
    if (!Array.isArray(raw) || raw.length === 0) {
        return [makeDefaultTab()]
    }

    const tabs = raw
        .filter(tab => tab && typeof tab === 'object' && tab.id !== undefined && tab.id !== null)
        .map(tab => {
            const cart = Array.isArray(tab.cart) ? tab.cart : []
            const hasItems = cart.length > 0

            return {
                ...tab,
                default_name: tab.default_name || tab.name || 'Đơn',
                name: tab.name || tab.default_name || 'Đơn',
                cart,
                customer: tab.customer ?? null,
                invoice_discount: hasItems ? Number(tab.invoice_discount || 0) : 0,
                invoice_discount_type: hasItems && tab.invoice_discount_type === 'percent' ? 'percent' : 'amount',
                extra_fee: hasItems ? Number(tab.extra_fee || 0) : 0,
                extra_fee_type: hasItems && tab.extra_fee_type === 'percent' ? 'percent' : 'amount',
            }
        })

    return tabs.length ? tabs : [makeDefaultTab()]
}


export function useCart() {
    const page = usePage()
    const allowsNegativeStock = computed(() => [true, 1, '1', 'true', 'on'].includes(page.props.settings?.allow_negative_stock))
    const defaultVatSelection = product => Boolean(page.props.settings?.pos_vat_default_enabled)
        && (page.props.settings?.pos_vat_default_scope !== 'vat_products' || Boolean(product.has_vat))

    const normalizedStockLimit = value => {
        const stock = Number(value)

        return Number.isFinite(stock) ? Math.max(0, Math.floor(stock)) : null
    }

    const canIncreaseItem = item => {
        if (allowsNegativeStock.value || item.imei_id) return !item.imei_id

        const stockLimit = normalizedStockLimit(item.stock_limit)
        return stockLimit !== null && Number(item.quantity || 0) < stockLimit
    }

    const increaseExistingItem = item => {
        if (canIncreaseItem(item)) {
            item.quantity++
            return true
        }

        toast.error(`Sản phẩm ${item.name} không đủ tồn kho`)
        return false
    }

    const sameBatchScope = (item, productId, variantId) =>
        Number(item.id) === Number(productId)
        && Number(item.variant_id || 0) === Number(variantId || 0)

    // Chọn lô kế tiếp sau số lượng đã có trong giỏ. Giá bán được gắn với
    // từng dòng giỏ để một sản phẩm có hai lô giá khác nhau không bị gộp giá.
    const nextSellableBatch = (product, variantId = null) => {
        const batches = (product.stock_batches || [])
            .filter(batch => Number(batch.variant_id || 0) === Number(variantId || 0))
        let legacyClaim = cart.value
            .filter(item => sameBatchScope(item, product.id, variantId) && !item.batch_id)
            .reduce((sum, item) => sum + Number(item.quantity || 0), 0)

        for (const batch of batches) {
            const explicitClaim = cart.value
                .filter(item => sameBatchScope(item, product.id, variantId) && Number(item.batch_id) === Number(batch.id))
                .reduce((sum, item) => sum + Number(item.quantity || 0), 0)
            const legacyForBatch = Math.min(legacyClaim, Number(batch.remaining_quantity || 0))
            legacyClaim -= legacyForBatch
            if (explicitClaim + legacyForBatch < Number(batch.remaining_quantity || 0)) {
                return batch
            }
        }

        return null
    }

    const hasTrackedBatches = (product, variantId = null) => (product.stock_batches || [])
        .some(batch => Number(batch.variant_id || 0) === Number(variantId || 0))

    const applyBatchToItem = (item, product, batch) => {
        item.stock_batches = product.stock_batches || item.stock_batches || []
        item.batch_id = batch?.id ?? null
        item.batch_quantity_limit = batch ? Number(batch.remaining_quantity || 0) : null
        item.stock_limit = batch
            ? Number(batch.remaining_quantity || 0)
            : (normalizedStockLimit(product.stock) ?? item.stock_limit)
        item.price = Number(batch?.sell_price > 0 ? batch.sell_price : (product.price ?? product.sell_price ?? item.price ?? 0))
    }

    /*
    |--------------------------------------------------------------------------
    | Local Storage
    |--------------------------------------------------------------------------
    */

    const {

        save,

        load,

    } = useLocalStorage()

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    // Tạo Tab giỏ hàng (nạp lại từ lần làm việc trước nếu có)
    const tabs = ref(
        normalizeTabs(
            load('pos_tabs', null)
        )
    )

    const savedActiveTabId = load('pos_active_tab', null)

    const activeTabId = ref(
        tabs.value.some(tab => tab.id === savedActiveTabId)
            ? savedActiveTabId
            : tabs.value[0].id
    )

    const currentTab = computed(() => {
        return (
            tabs.value.find(
                tab => tab.id === activeTabId.value
            )
            ??
            tabs.value[0]
        )
    })

    
    const cart = computed({
        get() {

            return currentTab.value.cart
        },

        set(value) {

            currentTab.value.cart = value
        },
    })

    const invoiceDiscount = computed({
        get: () => Number(currentTab.value.invoice_discount || 0),
        set: value => { currentTab.value.invoice_discount = Math.max(0, Number(value || 0)) },
    })
    const invoiceDiscountType = computed({
        get: () => currentTab.value.invoice_discount_type === 'percent' ? 'percent' : 'amount',
        set: value => { currentTab.value.invoice_discount_type = value === 'percent' ? 'percent' : 'amount' },
    })
    const extraFee = computed({
        get: () => Number(currentTab.value.extra_fee || 0),
        set: value => { currentTab.value.extra_fee = Math.max(0, Number(value || 0)) },
    })
    const extraFeeType = computed({
        get: () => currentTab.value.extra_fee_type === 'percent' ? 'percent' : 'amount',
        set: value => { currentTab.value.extra_fee_type = value === 'percent' ? 'percent' : 'amount' },
    })

    const resetOrderAdjustments = () => {
        invoiceDiscount.value = 0
        invoiceDiscountType.value = 'amount'
        extraFee.value = 0
        extraFeeType.value = 'amount'
    }

    // Clear order-level adjustments when the last cart line is removed.
    // Otherwise an empty order can retain a fee/discount for the next sale.
    watch(
        () => cart.value.length,
        (length, previousLength) => {
            if (length === 0 && previousLength > 0) {
                resetOrderAdjustments()
            }
        },
        { flush: 'sync' },
    )


    // 
    const selectedCustomer = computed({

        get() {

            return currentTab.value.customer
        },

        set(value) {

            currentTab.value.customer = value
        },
    })

    watch(
        selectedCustomer,

        customer => {

            if (!currentTab.value) {

                return
            }

            /*
            |----------------------------------
            | Không có khách
            |----------------------------------
            */

            if (!customer) {

                currentTab.value.name =
                    currentTab.value.default_name

                return
            }

            /*
            |----------------------------------
            | Có khách
            |----------------------------------
            */

            const fullName =

                customer.full_name
                ??
                customer.name
                ??
                ''

            const firstName =

                fullName
                    .trim()
                    .split(' ')
                    .pop()

            currentTab.value.name =
                firstName
        },

        {
            deep: true,
        }
    )

    const selectedCartIndex = ref(
        load('pos_selected_index', 0)
    )

    // Tính toán tổng tiền
    const {

        totalQuantity,

        subTotal,

        discount,

        vat,

        grandTotal,

        extraFeeAmount,
    } = useCartTotals(cart, invoiceDiscount, extraFee, invoiceDiscountType, computed(() => Number(page.props.settings?.pos_vat_rate ?? 10)), extraFeeType)

    /*
    |--------------------------------------------------------------------------
    | AUTO SAVE
    |--------------------------------------------------------------------------
    */
    // Lưu ngay lập tức (sync) để thay đổi cuối cùng không bị mất khi bấm X / đóng tab
    const flushSave = () => {

        save('pos_tabs', tabs.value)

        save('pos_active_tab', activeTabId.value)

        save('pos_selected_index', selectedCartIndex.value)
    }

    watch(
        tabs,
        flushSave,
        {
            deep: true,
            flush: 'sync',
        }
    )

    watch(
        activeTabId,
        flushSave,
        {
            flush: 'sync',
        }
    )

    watch(
        selectedCartIndex,
        flushSave,
        {
            flush: 'sync',
        }
    )

    // Lưu thêm khi tải lại trang / ẩn tab / đóng trình duyệt / rời trang POS
    const onPageHide = () => flushSave()

    const onVisibilityChange = () => {

        if (document.visibilityState === 'hidden') {

            flushSave()
        }
    }

    onMounted(() => {

        window.addEventListener('pagehide', onPageHide)

        window.addEventListener('beforeunload', onPageHide)

        document.addEventListener('visibilitychange', onVisibilityChange)
    })

    onBeforeUnmount(() => {

        flushSave()

        window.removeEventListener('pagehide', onPageHide)

        window.removeEventListener('beforeunload', onPageHide)

        document.removeEventListener('visibilitychange', onVisibilityChange)
    })


    // Hàm tạo tab
    const createTab = () => {
        const id = Date.now()
        const usedNumbers =
            tabs.value
                .map(tab => {

                    const match =
                        tab.default_name?.match(/\d+/)

                    return match
                        ? Number(match[0])
                        : null
                })
                .filter(Boolean)
        let nextNumber = 1
        while (
            usedNumbers.includes(
                nextNumber
            )
        ) {

            nextNumber++
        }

        const defaultName =
            `Đơn ${nextNumber}`

        tabs.value.push({
            id,
            default_name: defaultName,
            name: defaultName,
            cart: [],
            customer: null,
            invoice_discount: 0,
            invoice_discount_type: 'amount',
            extra_fee: 0,
            extra_fee_type: 'amount',
        })
        activeTabId.value = id
    }
    // Đóng tab
    const removeTab = (
        tabId
    ) => {
        if (
            tabs.value.length <= 1
        ) {
            return
        }
        const index =
            tabs.value.findIndex(
                tab => tab.id === tabId
            )
        tabs.value.splice(
            index,
            1
        )
        if (
            activeTabId.value === tabId
        ) {
            activeTabId.value =
                tabs.value[0].id
        }
    }

    // Dọn tab rông
    const cleanupEmptyTabs = () => {

        const tabsHasData = tabs.value.filter(tab => {

            return (
                tab.cart.length > 0
                ||
                tab.customer
            )
        })

        /*
        |----------------------------------
        | Có tab chứa dữ liệu
        |----------------------------------
        */

        if (tabsHasData.length > 0) {

            tabs.value = [...tabsHasData]

            return
        }

        /*
        |----------------------------------
        | Không còn tab nào
        |----------------------------------
        */

        const id = Date.now()

        tabs.value = [
            {
                id,
                default_name: 'Đơn 1',
                name: 'Đơn 1',
                cart: [],
                customer: null,
            },
        ]

        activeTabId.value = id
    }

    // dò tab có dữ liệu
    const switchToNextTab = () => {

        const tabHasItems = tabs.value.find(tab => {

            return (
                tab.id !== activeTabId.value
                &&
                tab.cart.length > 0
            )
        })

        if (tabHasItems) {

            activeTabId.value =
                tabHasItems.id

            return
        }

        createTab()
    }




    /*
    |--------------------------------------------------------------------------
    | Thêm sản phẩm vào giỏ hàng
    |--------------------------------------------------------------------------
    */

    const addToCart = (product) => {

        const variant = product.variant ?? null

        const variantLabel = getVariantLabel(variant)

        /*
        |--------------------------------------------------
        | Sản phẩm IMEI (có thể kèm biến thể)
        |--------------------------------------------------
        */

        if (
            product.imei_id ||
            product.imei
        ) {

            const exists =
                cart.value.some((item) => {

                    return (
                        item.imei_id === product.imei_id
                        ||
                        item.imei === product.imei
                    )
                })

            if (exists) {

                return
            }

            cart.value.push({
                id: product.id,
                ...unitPayload(product),

                variant_id:
                    variant?.id ?? null,

                variant_label:
                    variantLabel,

                image_url:
                    product.image_url ?? null,

                imei_id:
                    product.imei_id ?? null,

                imei:
                    product.imei ?? null,

                serial:
                    product.serial ?? null,

                color:
                    product.color ?? null,

                storage:
                    product.storage ?? null,

                cost_price:
                    product.cost_price ?? null,

                imei_sell_price:
                    product.imei_sell_price ?? null,

                price_source:
                    product.price_source ?? null,

                name:
                    product.name,

                has_vat: Boolean(product.has_vat),
                apply_vat: defaultVatSelection(product),
                tax_percent: Number(product.tax_percent || 0),

                warranty_days:
                    Number(product.warranty_days ?? product.product?.warranty_days ?? 0),

                customer_warranty_days:
                    Number(product.customer_warranty_days ?? product.warranty_days ?? product.product?.warranty_days ?? 0),

                customer_warranty_value:
                    null,

                customer_warranty_unit:
                    null,

                showWarranty:
                    false,

                price:
                    Number(
                        product.sell_price
                        ??
                        product.price
                        ??
                        0
                    ),

                quantity: 1,

                note: '',

                discount_type: null,

                discount_value: 0,

                gifts: [],
            })


            return
        }

        /*
        |--------------------------------------------------
        | Sản phẩm có biến thể (không quản lý IMEI)
        |--------------------------------------------------
        */

        if (variant) {

            const batch = nextSellableBatch(product, variant.id)

            if (!batch && hasTrackedBatches(product, variant.id) && !allowsNegativeStock.value) {
                toast.error(`Sản phẩm ${product.name} đã được chọn hết vào giỏ`)
                return
            }

            const existing =
                cart.value.find((item) => {

                    return (
                        item.id === product.id
                        &&
                        item.variant_id === variant.id
                        && Number(item.batch_id || 0) === Number(batch?.id || 0)
                    )
                })

            if (existing) {

                Object.assign(existing, unitPayload(product))
                applyBatchToItem(existing, product, batch)
                increaseExistingItem(existing)

                return
            }

            cart.value.push({
                id: product.id,
                ...unitPayload(product),

                variant_id:
                    variant.id,

                stock_limit: batch ? Number(batch.remaining_quantity || 0) : normalizedStockLimit(variant.stock),

                batch_id: batch?.id ?? null,
                batch_quantity_limit: batch ? Number(batch.remaining_quantity || 0) : null,
                stock_batches: product.stock_batches || [],

                variant_label:
                    variantLabel,

                sku:
                    variant.sku ?? product.sku ?? null,

                image_url:
                    product.image_url ?? null,

                name:
                    product.name,

                has_vat: Boolean(product.has_vat),
                apply_vat: defaultVatSelection(product),
                tax_percent: Number(product.tax_percent || 0),

                warranty_days:
                    Number(product.warranty_days ?? product.product?.warranty_days ?? 0),

                customer_warranty_days:
                    0,

                customer_warranty_value:
                    null,

                customer_warranty_unit:
                    null,

                showWarranty:
                    false,

                price: Number(batch?.sell_price > 0 ? batch.sell_price : (
                    (variant.sell_price > 0 ? variant.sell_price : null)
                    ?? (variant.price > 0 ? variant.price : null)
                    ?? product.price ?? product.sell_price ?? 0
                )),

                quantity: 1,

                note: '',

                discount_type: null,

                discount_value: 0,

                gifts: [],
            })

            return
        }

        /*
        |--------------------------------------------------
        | Sản phẩm thường
        |--------------------------------------------------
        */

        const batch = nextSellableBatch(product)
        if (!batch && hasTrackedBatches(product) && !allowsNegativeStock.value) {
            toast.error(`Sản phẩm ${product.name} đã được chọn hết vào giỏ`)
            return
        }
        const existing =
            cart.value.find((item) => {

                return (
                    item.id ===
                    product.id
                    &&
                    !item.variant_id
                    && Number(item.batch_id || 0) === Number(batch?.id || 0)
                )
            })

        if (existing) {

            Object.assign(existing, unitPayload(product))
            applyBatchToItem(existing, product, batch)
            increaseExistingItem(existing)

            return
        }

        cart.value.push({
            id: product.id,
            ...unitPayload(product),

            variant_id: null,

            stock_limit: batch ? Number(batch.remaining_quantity || 0) : normalizedStockLimit(product.stock),

            batch_id: batch?.id ?? null,
            batch_quantity_limit: batch ? Number(batch.remaining_quantity || 0) : null,
            stock_batches: product.stock_batches || [],

            image_url:
                product.image_url ?? null,

            name:
                product.name,

            has_vat: Boolean(product.has_vat),
            apply_vat: defaultVatSelection(product),
            tax_percent: Number(product.tax_percent || 0),

            warranty_days:
                Number(product.warranty_days ?? product.product?.warranty_days ?? 0),

            customer_warranty_days:
                0,

            customer_warranty_value:
                null,

            customer_warranty_unit:
                null,

            showWarranty:
                false,

            price: Number(batch?.sell_price > 0 ? batch.sell_price : (product.price ?? product.sell_price ?? 0)),

            quantity: 1,

            note: '',

            discount_type: null,

            discount_value: 0,

            gifts: [],
        })
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */

    const removeItem = (item) => {
        // Ưu tiên đúng dòng được bấm; khi không có (gọi từ phím tắt/luồng khác)
        // mới dò theo sản phẩm + biến thể + lô để không xóa nhầm dòng lô khác.
        let index = cart.value.findIndex(cartItem => cartItem === item)

        if (index === -1) {
            index = cart.value.findIndex(cartItem => {
                if (item.imei_id) {
                    return cartItem.imei_id === item.imei_id
                }

                return cartItem.id === item.id
                    && (cartItem.variant_id ?? null) === (item.variant_id ?? null)
                    && Number(cartItem.batch_id || 0) === Number(item.batch_id || 0)
            })
        }

        if (index !== -1) {
            cart.value.splice(index, 1)

            if (cart.value.length === 0) {
                resetOrderAdjustments()
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CART
    |--------------------------------------------------------------------------
    */

    const clearCart = () => {

        currentTab.value.cart = []
        currentTab.value.customer = null
        resetOrderAdjustments()
    }



    return {

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

        totalQuantity,

        subTotal,

        discount,

        vat,

        grandTotal,

        invoiceDiscount,

        invoiceDiscountType,

        extraFee,
        extraFeeType,
        extraFeeAmount,

        addToCart,

        removeItem,

        clearCart,
    }
}
