import { ref, watch, computed, } from 'vue'
import { useLocalStorage } from '@/Composables/useLocalStorage'
import { useCartTotals } from '@/Modules/POS/Cart/Composables/useCartTotals'



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

    // Tạo Tab giỏ hàng
    const tabs = ref(
        load(
            'pos_tabs',
            [
                {
                    id: 1,
                    default_name: 'Đơn 1',
                    name: 'Đơn 1',
                    cart: [],
                    customer: null,
                }
            ]
        )
    )

    tabs.value.forEach(tab => {

        if (!tab.default_name) {

            tab.default_name =
                tab.name
        }
    })


    const activeTabId = ref(
        load(
            'pos_active_tab',
            1
        )
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
    watch(
        tabs,
        value => {

            save(
                'pos_tabs',
                value
            )
        },
        {
            deep: true,
        }
    )

    watch(
        activeTabId,
        value => {

            save(
                'pos_active_tab',
                value
            )
        }
    )

  
    watch(
        selectedCartIndex,
        (value) => {

            save(
                'pos_selected_index',
                value
            )
        }
    )


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
     
        /*
        |--------------------------------------------------
        | Sản phẩm IMEI
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

                name:
                    product.name,

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
        | Sản phẩm thường
        |--------------------------------------------------
        */

        const existing =
            cart.value.find((item) => {

                return (
                    item.id ===
                    product.id
                )
            })

        if (existing) {

            existing.quantity++

            return
        }

        cart.value.push({
            id: product.id,

            image_url:
                product.image_url ?? null,

            name:
                product.name,

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

            return cartItem.id === item.id
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