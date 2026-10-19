import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { useLocalStorage } from '@/Composables/useLocalStorage'
import { useCartTotals } from '@/Modules/POS/Cart/Composables/useCartTotals'

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
})

// Dữ liệu cũ trong localStorage có thể hỏng/thiếu -> chuẩn hoá để POS luôn mở được
const normalizeTabs = (raw) => {
    if (!Array.isArray(raw) || raw.length === 0) {
        return [makeDefaultTab()]
    }

    const tabs = raw
        .filter(tab => tab && typeof tab === 'object' && tab.id !== undefined && tab.id !== null)
        .map(tab => ({
            ...tab,
            default_name: tab.default_name || tab.name || 'Đơn',
            name: tab.name || tab.default_name || 'Đơn',
            cart: Array.isArray(tab.cart) ? tab.cart : [],
            customer: tab.customer ?? null,
        }))

    return tabs.length ? tabs : [makeDefaultTab()]
}


export function useCart() {

    /*
    |--------------------------------------------------------------------------
    | Local Storage
    |--------------------------------------------------------------------------
    */

    const {

        save,

        load,

        remove,

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

    } = useCartTotals(cart)

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

            const existing =
                cart.value.find((item) => {

                    return (
                        item.id === product.id
                        &&
                        item.variant_id === variant.id
                    )
                })

            if (existing) {

                Object.assign(existing, unitPayload(product))
                existing.quantity++

                return
            }

            cart.value.push({
                id: product.id,
                ...unitPayload(product),

                variant_id:
                    variant.id,

                variant_label:
                    variantLabel,

                sku:
                    variant.sku ?? product.sku ?? null,

                image_url:
                    product.image_url ?? null,

                name:
                    product.name,

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

                price:
                    Number(
                        (variant.sell_price > 0 ? variant.sell_price : null)
                        ??
                        (variant.price > 0 ? variant.price : null)
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
        | Sản phẩm thường
        |--------------------------------------------------
        */

        const existing =
            cart.value.find((item) => {

                return (
                    item.id ===
                    product.id
                    &&
                    !item.variant_id
                )
            })

        if (existing) {

            Object.assign(existing, unitPayload(product))
            existing.quantity++

            return
        }

        cart.value.push({
            id: product.id,
            ...unitPayload(product),

            variant_id: null,

            image_url:
                product.image_url ?? null,

            name:
                product.name,

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

            price:
                Number(
                    product.price
                ),

            quantity: 1,

            note: '',

            discount_type: null,

            discount_value: 0,

            gifts: [],
        })
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE QUANTITY
    |--------------------------------------------------------------------------
    */

    const increaseQty = (item) => {

        if (item.imei_id) {

            return
        }

        item.quantity++
    }


    const decreaseQty = (item) => {

        if (item.imei_id) {

            return
        }

        if (item.quantity > 1) {

            item.quantity--
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */

    const removeItem = (item) => {

        const index = cart.value.findIndex(cartItem => {

            if (item.imei_id) {

                return cartItem.imei_id === item.imei_id
            }

            if (item.variant_id) {

                return (
                    cartItem.id === item.id
                    &&
                    cartItem.variant_id === item.variant_id
                )
            }

            return (
                cartItem.id === item.id
                &&
                !cartItem.variant_id
            )
        })

        if (index !== -1) {

            cart.value.splice(index, 1)
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

        addToCart,

        removeItem,

        clearCart,

        increaseQty,

        decreaseQty,
    }
}
