<script setup>
import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
    watch ,
} from 'vue'
import CustomerDebtModal from './CustomerDebtModal.vue'
import { customerDebtService } from '../Services/customerDebtService'
import { useCustomerSearch } from '@/Modules/POS/Customer/Composables/useCustomerSearch.js'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { User, UserRoundPlus } from 'lucide-vue-next'
import CreateCustomerModal from './CreateCustomerModal.vue'
import { customerService } from '../Services/customerService'
import { openModal } from '@/Stores/modal'
import CustomerFormModal from '@/Pages/Customers/CustomerFormModal.vue'
import CustomerIdCardScanner from '@/Components/CustomerIdCardScanner.vue'

const emit = defineEmits([
    'selected',
])

const {
    keyword,
    customers,
    identityPrefill,
    search,
    selectCustomer,
    onIdentityCardScanned,
    clearCustomer,
    activeIndex,
    setItemRef,
    onKeyDown,
    setActive,
} = useCustomerSearch(emit)

const props = defineProps({

    customer: {

        type: Object,

        default: null,
    },
})

watch(
    () => props.customer,

    customer => {

        if (customer) {

            keyword.value =
                customer.full_name

            return
        }

        keyword.value = ''
    },

    {
        immediate: true,
    }
)

// Modal nợ
const showDebtModal = ref(false)

// Danh sách nợ
const debts = ref([])

// Tổng nợ
const debtTotal = ref(0)


// Mở modal nợ
const openDebtModal = async () => {

    try {

        if (!props.customer?.id) {

            return
        }

        const response =
            await customerDebtService.getDebts(
                props.customer.id
            )

        debts.value =
            response.data.debts

        debtTotal.value =
            response.data.total

        showDebtModal.value = true

    } catch (error) {

        console.error(error)
    }
}

// Mở modal tạo KH
const showCreateCustomerModal = ref(false)

// Tạo ref cho vùng tìm kiếm
const customerSearchRef = ref(null)


// Hiện nút tạo KH
const showCreateButton = computed(() => {

    return (

        keyword.value?.trim()

        &&

        customers.value.length === 0

    )
})

// Lưu KH
const saveCustomer = async (
    data
) => {

    try {

        const customer =

            await customerService.create(
                data
            )

        selectCustomer(
            customer
        )

        showCreateCustomerModal.value =
            false

    } catch (error) {

        console.error(error)
    }
}

// Mở modal "Tạo chi tiết" (dùng chung với trang Khách hàng)
const openFullCustomerModal = (
    prefill
) => {

    showCreateCustomerModal.value =
        false

    openModal(CustomerFormModal, {

        props: {

            title: 'Thêm khách hàng mới',

            apiMode: true,

            customer: {

                ...(prefill || identityPrefill.value || {}),
            },
        },

        onUpdated: (customer) => {

            if (customer) {

                selectCustomer(customer)
            }
        },
    })
}

// Thêm hàm click ngoài
const handleClickOutside = (event) => {

    if (
        customerSearchRef.value &&
        !customerSearchRef.value.contains(
            event.target
        )
    ) {

        customers.value = []
    }
}


// Đăng ký sự kiện
onMounted(() => {

    document.addEventListener(
        'click',
        handleClickOutside
    )
})
onBeforeUnmount(() => {

    document.removeEventListener(
        'click',
        handleClickOutside
    )
})


</script>

<template>
    <div>
        <!-- <div class="mb-1 text-xs font-bold uppercase tracking-wide">
            Khách hàng
        </div> -->

        <div class="relative">
            <div 
                v-if="props.customer" 
                class="flex min-h-[44px] items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-1 ring-blue-100">
                        <User :size="15" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-xs font-semibold leading-tight text-slate-900" :title="props.customer.full_name">
                            {{ props.customer.full_name }}<span v-if="props.customer.phone" class="font-normal text-slate-400"> - {{ props.customer.phone }}</span>
                            <button
                                v-if="Number(props.customer.debt_balance) > 0"
                                type="button"
                                @click.stop="openDebtModal"
                                class="ml-1 whitespace-nowrap text-[11px] font-extrabold text-rose-600 hover:text-rose-700"
                            >
                                (Nợ: {{ $money(props.customer.debt_balance) }})
                            </button>
                        </span>
                </div>

                <!-- Xóa khách hàng -->
                <button 
                    type="button" 
                    @click="clearCustomer" 
                    class="shrink-0 rounded-lg px-1.5 py-1 text-xs font-semibold text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-700"
                    title="Đổi khách hàng"
                >
                    Đổi
                </button>
            </div>

            <div v-else>
                <div ref="customerSearchRef" class="relative">
                    <FloatingInput
                        v-model="keyword"
                        placeholder-text="Khách lẻ"
                        @input="search"
                        @keydown="onKeyDown"
                        type="text"
                        label="Nhập tên, SĐT hoặc CCCD"
                        input-class="pr-12"
                    />
                    <CustomerIdCardScanner class="absolute right-1 top-1/2 -translate-y-1/2 !h-8 !w-8" @read="onIdentityCardScanned" @search="keyword = $event; search()" />

                    <button
                        v-if="keyword.trim()"
                        type="button"
                        title="Thêm KH mới"
                        @click="identityPrefill ? openFullCustomerModal(identityPrefill) : showCreateCustomerModal = true"
                        class="absolute right-10 top-1/2 -translate-y-1/2
                            px-3 py-1.5
                            text-cyan-600
                            hover:text-cyan-900
                            rounded"
                    >
                        <UserRoundPlus />
                    </button>

                </div>

                <!--Danh sách KH-->
                <div
                    v-if="customers.length"
                    class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg custom-scrollbar"
                >
                    <button
                        v-for="(customer, index) in customers"
                        :ref="el => setItemRef(el, index)"
                        @mouseenter="setActive(index)"
                        :key="customer.id"
                        type="button"
                        @click="selectCustomer(customer)"
                        :class="[
                            'block w-full border-b border-slate-100 p-2.5 text-left last:border-b-0 transition-colors',
                            index === activeIndex ? 'bg-blue-50' : 'hover:bg-slate-50'
                        ]"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate text-sm font-semibold text-slate-900">
                                {{ customer.full_name }}
                            </span>
                            <span
                                v-if="Number(customer.debt_balance || 0) > 0"
                                class="shrink-0 whitespace-nowrap rounded-full bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700"
                            >
                                Nợ {{ $money(customer.debt_balance) }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ customer.phone }}
                        </div>
                    </button>
                </div>
            </div>            
        </div>
    </div>

<CustomerDebtModal

    :show="showDebtModal"

    :debts="debts"

    :total="debtTotal"

    @close="
        showDebtModal = false
    "
/>

<CreateCustomerModal
    :show="showCreateCustomerModal"
    :keyword="keyword"
    @close="showCreateCustomerModal = false"
    @save="saveCustomer"
    @full-detail="openFullCustomerModal"
/>

</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
