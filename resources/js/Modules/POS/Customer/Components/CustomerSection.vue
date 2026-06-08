<script setup>
import { ref } from 'vue'
import { useCustomerSearch } from '@/Composables/useCustomerSearch'
import { customerService } from '@/Modules/POS/Customer/Services/customerService'

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
    customer: Object,
})

// Modal nợ
const showDebtModal = ref(false)

// Danh sách nợ
const debts = ref([])

// Load nợ của khách hàng
const loadDebts = async () => {

    if (!props.customer) {

        return
    }

    const response =
        await customerService
            .getDebts(
                props.customer.id
            )

    debts.value =
        response.debts

    showDebtModal.value = true
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

                            <button
                                v-if="Number(props.customer.debt_balance) > 0"
                                type="button"
                                @click="showDebtModal = true"
                                class="ml-1 font-semibold text-red-600 hover:underline"
                            >
                                (
                                {{
                                    Number(
                                        props.customer.debt_balance
                                    ).toLocaleString('vi-VN')
                                }}
                                đ)
                            </button>
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

<div
    v-if="showDebtModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
>

    <div
        class="bg-white w-[700px] rounded-lg shadow-lg p-5"
    >

        <div
            class="flex items-center justify-between mb-4"
        >

            <h3
                class="font-bold text-lg"
            >
                Chi tiết công nợ
            </h3>

            <button
                @click="
                    showDebtModal = false
                "
            >
                ✕
            </button>

        </div>

        <table
            class="w-full text-sm"
        >

            <thead>

                <tr>

                    <th class="text-left p-2">
                        Ngày
                    </th>

                    <th class="text-left p-2">
                        Nội dung
                    </th>

                    <th class="text-right p-2">
                        Số tiền
                    </th>

                </tr>

            </thead>

            <tbody>

                <tr
                    v-for="
                        debt in debts
                    "
                    :key="debt.id"
                >

                    <td class="p-2">
                        {{ debt.created_at }}
                    </td>

                    <td class="p-2">
                        {{ debt.note }}
                    </td>

                    <td
                        class="p-2 text-right"
                    >

                        {{
                            Number(
                                debt.amount
                            ).toLocaleString(
                                'vi-VN'
                            )
                        }}

                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</div>

</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>