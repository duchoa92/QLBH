<script setup>
import { formatCurrency } from '@/utils/format'

import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
    watch,
} from 'vue'

import {
    Truck,
    X,
    Plus,
    CalendarDays,
    UserRound,
    Search,
    UserRoundPlus,
    LoaderCircle,
} from 'lucide-vue-next'

import FloatingInput from '@/Components/UI/FloatingInput.vue'

import { useSupplierSearch } from './Composables/useSupplierSearch.js'
import { openModal } from '@/Stores/modal'
import SupplierForm from '@/Pages/Suppliers/Form.vue'
import CustomerFormModal from '@/Pages/Customers/CustomerFormModal.vue'
import CustomerIdCardScanner from '@/Components/CustomerIdCardScanner.vue'
import api from '@/Services/api'


/*
|--------------------------------------------------------------------------
| PROPS / EMITS
|--------------------------------------------------------------------------
*/

const emit = defineEmits([
    'selected',
    'customer-selected',
    'customer-draft',
    'update:party-type',
    'update:date',
])

const props = defineProps({

    supplier: {
        type: Object,
        default: null,
    },

    date: {
        type: String,
        default: '',
    },

    error: {
        type: String,
        default: '',
    },
    partyType: { type: String, default: 'supplier' },
    partyTypeLocked: { type: Boolean, default: false },
    customer: { type: Object, default: null },

})


/*
|--------------------------------------------------------------------------
| NGÀY NHẬP HÀNG
|--------------------------------------------------------------------------
*/

const importDate = ref(
    props.date ||
    new Date().toISOString().slice(0, 10)
)

watch(
    () => props.date,
    value => {
        if (value) {
            importDate.value = value
        }
    }
)

watch(
    importDate,
    value => {
        emit('update:date', value)
    }
)


/*
|--------------------------------------------------------------------------
| SUPPLIER SEARCH
|--------------------------------------------------------------------------
*/

const {
    keyword,
    suppliers,
    search,
    selectSupplier,
    clearSupplier,
    activeIndex,
    setItemRef,
    onKeyDown,
    setActive,
} = useSupplierSearch(emit)


/*
|--------------------------------------------------------------------------
| ĐỒNG BỘ SUPPLIER TỪ PARENT
|--------------------------------------------------------------------------
*/

watch(
    () => props.supplier,

    supplier => {

        if (supplier) {
            keyword.value = supplier.name
            return
        }

        keyword.value = ''

    },

    {
        immediate: true,
    }
)


/*
|--------------------------------------------------------------------------
| SEARCH REF
|--------------------------------------------------------------------------
*/

const supplierSearchRef = ref(null)
const customerSearchRef = ref(null)
const customerKeyword = ref('')
const customerResults = ref([])
const customerSearchLoading = ref(false)
const scannedCustomerIdentity = ref(null)
let customerSearchTimeout = null

const onCustomerIdCardRead = details => {
    scannedCustomerIdentity.value = details
    customerKeyword.value = details.cccd
}

watch(() => props.customer, customer => {
    if (customer) customerKeyword.value = customer.full_name
}, { immediate: true })

watch(customerKeyword, value => {
    clearTimeout(customerSearchTimeout)
    if (props.customer || value.trim().length < 2) {
        customerResults.value = []
        return
    }
    customerSearchTimeout = setTimeout(async () => {
        customerSearchLoading.value = true
        try {
            const { data } = await api.get('/stock-import/customers/search', { params: { search: value.trim() } })
            customerResults.value = data.customers || []
        } catch {
            customerResults.value = []
        } finally {
            customerSearchLoading.value = false
        }
    }, 220)
})

const selectCustomer = customer => {
    customerKeyword.value = customer.full_name
    customerResults.value = []
    emit('customer-selected', customer)
    emit('customer-draft', {
        full_name: customer.full_name || '', phone: customer.phone || '',
        address: customer.address || '', cccd: customer.cccd || '',
    })
}

const clearCustomer = () => {
    customerKeyword.value = ''
    emit('customer-selected', null)
    emit('customer-draft', { full_name: 'Khách lẻ', phone: '', address: '', cccd: '' })
}

const createCustomer = () => {
    openModal(CustomerFormModal, {
        props: {
            title: 'Thêm khách lẻ',
            apiMode: true,
            customer: {
                ...(scannedCustomerIdentity.value || {}),
                full_name: scannedCustomerIdentity.value?.full_name || customerKeyword.value.trim(),
            },
        },
        onUpdated: customer => {
            if (customer) selectCustomer(customer)
        },
    })
}


/*
|--------------------------------------------------------------------------
| HIỆN NÚT THÊM NCC
|--------------------------------------------------------------------------
*/

const showCreateButton = computed(() => {
    return (
        keyword.value.trim() &&
        !suppliers.value.length
    )
})


/*
|--------------------------------------------------------------------------
| THÊM NHÀ CUNG CẤP
|--------------------------------------------------------------------------
*/

const createSupplier = () => {

    openModal(SupplierForm, {

        props: {
            name: keyword.value,
            title: 'Thêm nhà cung cấp',
            size: 'sm',
            apiMode: true,
        },

        onUpdated: newSupplier => {
            emit('selected', newSupplier)
        },

    })

}


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE
|--------------------------------------------------------------------------
*/

const handleClickOutside = event => {

    if (
        supplierSearchRef.value &&
        !supplierSearchRef.value.contains(
            event.target
        )
    ) {
        suppliers.value = []
    }

}


onMounted(() => {

    document.addEventListener(
        'click',
        handleClickOutside
    )

    document.addEventListener('pointerdown', handleCustomerOutsideClick)

})


onBeforeUnmount(() => {

    document.removeEventListener(
        'click',
        handleClickOutside
    )

    document.removeEventListener('pointerdown', handleCustomerOutsideClick)
    clearTimeout(customerSearchTimeout)

})


/*
|--------------------------------------------------------------------------
| FORMAT MONEY
|--------------------------------------------------------------------------
*/

const money = formatCurrency

const handleCustomerOutsideClick = event => {
    if (!customerSearchRef.value?.contains(event.target)) customerResults.value = []
}

</script>


<template>

    <div
        class="
            bg-white
            transition-colors
        "
        :class="
            error
                ? 'border-red-400 bg-red-50/20'
                : 'border-slate-200'
        "
    >

        <!-- ===================================================== -->
        <!-- NCC + NGÀY NHẬP HÀNG -->
        <!-- ===================================================== -->

        <div class="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2 lg:grid-cols-[auto_minmax(0,1fr)_minmax(190px,0.42fr)] lg:items-center">
            <div v-if="!props.partyTypeLocked" class="col-span-full inline-flex w-fit rounded-xl bg-slate-100 p-1 lg:col-span-1">
                <button type="button" :class="props.partyType === 'supplier' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold transition" @click="emit('update:party-type', 'supplier')">
                    <Truck class="h-4 w-4" /> Nhà cung cấp
                </button>
                <button type="button" :class="props.partyType === 'customer' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold transition" @click="emit('update:party-type', 'customer')">
                    <UserRound class="h-4 w-4" /> Khách lẻ
                </button>
            </div>

            <!-- ================================================= -->
            <!-- NHÀ CUNG CẤP -->
            <!-- ================================================= -->

            <div v-if="props.partyType === 'supplier'" class="min-w-0">

                <!-- ĐÃ CHỌN NCC -->
                <div
                    v-if="props.supplier"
                    class="
                        flex
                        h-10
                        w-full
                        items-center
                        justify-between
                        rounded-md
                        border
                        border-emerald-300
                        bg-emerald-50
                        px-3
                        shadow-sm
                    "
                >

                    <div
                        class="
                            flex
                            min-w-0
                            flex-1
                            items-center
                            gap-2
                        "
                    >

                        <Truck
                            :size="15"
                            class="shrink-0 text-emerald-700"
                        />

                        <div class="min-w-0">

                            <div
                                class="
                                    truncate
                                    text-sm
                                    font-semibold
                                    text-emerald-900
                                "
                            >
                                {{ props.supplier.name }}

                                <span
                                    v-if="
                                        Number(
                                            props.supplier
                                                .debt_balance
                                        ) !== 0
                                    "
                                >
                                    (
                                    nợ:
                                    {{
                                        money(
                                            props.supplier
                                                .debt_balance
                                        )
                                    }}
                                    )
                                </span>

                            </div>

                        </div>

                    </div>


                    <!-- XÓA NCC -->

                    <button
                        type="button"
                        title="Xóa nhà cung cấp"
                        @click="clearSupplier"
                        class="
                            ml-2
                            shrink-0
                            rounded-full
                            p-1
                            text-emerald-400
                            transition-colors
                            hover:bg-red-100
                            hover:text-rose-600
                        "
                    >
                        <X :size="15" />
                    </button>

                </div>


                <!-- CHƯA CHỌN NCC -->

                <div
                    v-else
                    ref="supplierSearchRef"
                    class="relative"
                >

                    <FloatingInput
                        v-model="keyword"
                        @input="search"
                        @keydown="onKeyDown"
                        type="text"
                        label="Nhập tên, SĐT hoặc mã nhà cung cấp"
                    />


                    <!-- NÚT THÊM NCC -->

                    <button
                        v-if="keyword.trim()"
                        type="button"
                        @click="createSupplier"
                        class="
                            absolute
                            right-0
                            top-1/2
                            -translate-y-1/2
                            px-3
                            py-1.5
                            text-emerald-600
                            hover:text-emerald-900
                        "
                    >
                        <Plus :size="20" />
                    </button>


                    <!-- DROPDOWN -->

                    <div
                        v-if="suppliers.length"
                        class="
                            absolute
                            z-50
                            mt-1
                            max-h-64
                            w-full
                            overflow-auto
                            rounded-md
                            border
                            border-slate-200
                            bg-white
                            shadow-lg
                            custom-scrollbar
                        "
                    >

                        <button
                            v-for="(
                                supplier,
                                index
                            ) in suppliers"

                            :key="supplier.id"

                            :ref="
                                el =>
                                    setItemRef(
                                        el,
                                        index
                                    )
                            "

                            type="button"

                            @mouseenter="
                                setActive(index)
                            "

                            @click="
                                selectSupplier(
                                    supplier
                                )
                            "

                            :class="[
                                'block w-full border-b border-slate-100 p-2.5 text-left last:border-b-0 transition-colors',
                                index === activeIndex
                                    ? 'bg-emerald-50'
                                    : 'hover:bg-slate-50'
                            ]"
                        >

                            <!-- TÊN -->

                            <div
                                class="
                                    text-sm
                                    font-semibold
                                    text-slate-900
                                "
                            >
                                {{ supplier.name }}
                            </div>


                            <!-- THÔNG TIN -->

                            <div
                                class="
                                    mt-0.5
                                    flex
                                    gap-2
                                    text-xs
                                    text-slate-500
                                "
                            >

                                <span
                                    v-if="supplier.code"
                                >
                                    {{ supplier.code }}
                                </span>

                                <span
                                    v-if="
                                        supplier.code &&
                                        supplier.phone
                                    "
                                >
                                    •
                                </span>

                                <span
                                    v-if="supplier.phone"
                                >
                                    {{ supplier.phone }}
                                </span>

                            </div>

                        </button>

                    </div>

                </div>

            </div>

            <div v-else ref="customerSearchRef" class="relative min-w-0">
                <div v-if="props.customer" class="flex h-10 items-center justify-between rounded-md border border-blue-200 bg-blue-50 px-3 shadow-sm">
                    <div class="flex min-w-0 items-center gap-2">
                        <UserRound class="h-4 w-4 shrink-0 text-blue-700" />
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-blue-950">{{ props.customer.full_name }}</div>
                            <div class="truncate text-[11px] text-blue-700">{{ props.customer.phone || 'Khách lẻ' }}<span v-if="props.customer.code"> · {{ props.customer.code }}</span></div>
                        </div>
                    </div>
                    <button type="button" title="Bỏ chọn người bán" class="ml-2 rounded-full p-1 text-blue-500 hover:bg-white hover:text-rose-600" @click="clearCustomer"><X :size="15" /></button>
                </div>
                <div v-else class="relative">
                    <FloatingInput v-model="customerKeyword" type="text" autocomplete="off" label="Tìm khách hàng theo tên, SĐT hoặc CCCD" placeholder-text="Khách lẻ" input-class="pr-20" />
                    <LoaderCircle v-if="customerSearchLoading" class="absolute right-11 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-blue-500" />
                    <button v-if="customerKeyword.trim()" type="button" title="Thêm khách lẻ mới" aria-label="Thêm khách lẻ mới" class="absolute right-1 top-1/2 z-10 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-blue-700 transition hover:bg-blue-50" @click="createCustomer"><UserRoundPlus class="h-5 w-5" /></button>
                    <CustomerIdCardScanner class="absolute right-10 top-1/2 z-10 -translate-y-1/2 !h-8 !w-8" @read="onCustomerIdCardRead" @search="customerKeyword = $event" />
                    <div v-if="customerResults.length" class="absolute left-0 right-0 top-full z-50 mt-1 max-h-56 overflow-auto rounded-xl border border-slate-200 bg-white shadow-xl">
                        <button v-for="customer in customerResults" :key="customer.id" type="button" class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-0 hover:bg-blue-50" @mousedown.prevent="selectCustomer(customer)">
                            <span class="block text-sm font-semibold text-slate-900">{{ customer.full_name }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ customer.phone || 'Chưa có SĐT' }} · {{ customer.cccd || customer.code || 'Chưa có CCCD' }}</span>
                        </button>
                    </div>
                </div>
            </div>


            <!-- ================================================= -->
            <!-- NGÀY NHẬP HÀNG -->
            <!-- ================================================= -->

            <div class="relative min-w-0">

                <FloatingInput
                    v-model="importDate"
                    type="date"
                    label="Ngày nhập hàng"
                />

            </div>

        </div>


        <!-- ===================================================== -->
        <!-- ERROR -->
        <!-- ===================================================== -->

        <p
            v-if="error"
            class="
                mt-1.5
                flex
                items-center
                gap-1
                text-xs
                font-semibold
                text-red-600
            "
        >
            {{ error }}
        </p>

    </div>

</template>


<style scoped>

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

</style>
