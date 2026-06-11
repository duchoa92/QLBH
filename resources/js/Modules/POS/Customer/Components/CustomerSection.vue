<script setup>
import { ref } from 'vue'
import CustomerDebtModal from './CustomerDebtModal.vue'
import { customerDebtService } from '../Services/customerDebtService'
import { useCustomerSearch } from '@/Composables/useCustomerSearch'


const emit = defineEmits([
    'selected',
])

const {
    keyword,
    customers,
    search,
    selectCustomer,
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

</script>

<template>
    <div>
        <!-- <div class="mb-1 text-xs font-bold uppercase tracking-wide">
            Khách hàng
        </div> -->

        <div class="relative">
            <div 
                v-if="props.customer" 
                class="flex items-center justify-between w-full h-10 px-3 bg-blue-50 border border-blue-300 rounded-md shadow-sm"
            >
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    
                    <span class="font-semibold text-blue-900 text-sm truncate">
                        {{ props.customer.full_name }}
                        <span
                            class="font-normal text-blue-600 text-xs ml-1"
                            v-if="props.customer.phone"
                        >
                            - {{ props.customer.phone }}

                            <span
                                v-if="Number(props.customer.debt_balance) > 0"
                                @click.stop="openDebtModal"
                                class="
                                    ml-2
                                    px-2
                                    py-0.5
                                    rounded-full
                                    bg-red-100
                                    text-red-600
                                    text-xs
                                    font-bold
                                    cursor-pointer
                                "
                            >
                                Nợ:
                                {{
                                    Number(
                                        props.customer.debt_balance
                                    ).toLocaleString('vi-VN')
                                }}
                            </span>
                        </span>
                        
                    </span>
                    
                </div>

                <!-- Xóa khách hàng -->
                <button 
                    type="button" 
                    @click="clearCustomer" 
                    class="ml-2 p-1 text-blue-400 hover:text-rose-600 hover:bg-rose-100 rounded-full transition-colors shrink-0"
                    title="Xóa"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div v-else>
                <input
                    v-model="keyword"
                    @input="search"
                    @keydown="onKeyDown"
                    type="text"
                    placeholder="Tên hoặc SĐT khách (F2)..."
                    class="h-10 w-full rounded-md border-slate-300 px-3 text-sm font-medium shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />

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
                        <div class="font-semibold text-slate-900 text-sm">
                            {{ customer.full_name }}
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

</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>