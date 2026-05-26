<script setup>
import axios from 'axios'
import { ref } from 'vue'

import { useDebounceSearch } from '@/Composables/useDebounceSearch'
import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'

const emit = defineEmits([
    'selected',
    'next'
])

/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/
const selectedCustomer = ref(null)

const customerMeta = ref({
    is_vip: false,
    debt: 0,
})

/*
|--------------------------------------------------------------------------
| SEARCH (SOURCE OF TRUTH)
|--------------------------------------------------------------------------
*/
const {
    keyword,
    results: customers,
    search,
} = useDebounceSearch(async (q) => {

    if (!q) return []

    const res = await axios.get('/api/customers/search', {
        params: { q }
    })

    return res.data?.data ?? res.data ?? []

}, 180)

/*
|--------------------------------------------------------------------------
| KEYBOARD ENGINE
|--------------------------------------------------------------------------
*/
const {
    activeIndex,
    itemRefs,
    setItemRef,
    onKeyDown,
    setActive,
    reset,
} = useAutocompleteKeyboard(customers, (customer) => {
    selectCustomer(customer)
})

/*
|--------------------------------------------------------------------------
| SELECT CUSTOMER (SOURCE OF TRUTH)
|--------------------------------------------------------------------------
*/
const selectCustomer = (customer) => {

    selectedCustomer.value = customer

    keyword.value = customer.full_name

    reset()

    customerMeta.value = {
        is_vip: customer.is_vip ?? false,
        debt: customer.debt ?? 0,
    }

    emit('selected', customer)
}

/*
|--------------------------------------------------------------------------
| CLEAR
|--------------------------------------------------------------------------
*/
const clearCustomer = () => {

    selectedCustomer.value = null
    keyword.value = ''

    reset()

    emit('selected', null)
}

/*
|--------------------------------------------------------------------------
| ENTER FLOW (POS ULTRA SAFE)
|--------------------------------------------------------------------------
*/
const onEnter = () => {

    const customer = customers.value?.[activeIndex.value]

    // CASE 1: chọn từ list
    if (customer) {
        selectCustomer(customer)
        emit('next', customer)
        return
    }

    // CASE 2: đã có customer
    if (selectedCustomer.value) {
        emit('next', selectedCustomer.value)
        return
    }

    // CASE 3: fallback search
    search()
}
</script>

<template>
    <div>

        <!-- HEADER -->
        <div class="flex justify-between items-center mb-2">
            <div class="font-bold">
                Khách hàng
            </div>

            <button
                v-if="selectedCustomer"
                @click="clearCustomer"
                class="text-xs text-red-500"
            >
                Bỏ chọn
            </button>
        </div>

        <!-- INPUT -->
        <div class="relative">

            <input
                v-model="keyword"
                @input="search"
                @keydown.enter.prevent="onEnter"
                @keydown="onKeyDown"
                type="text"
                placeholder="Tên hoặc SĐT khách"
                class="w-full border rounded p-2"
                autocomplete="off"
            />

            <!-- RESULTS -->
            <div
                v-if="customers.length"
                class="absolute z-50 w-full bg-white border rounded shadow mt-1 max-h-60 overflow-auto"
            >
                <div
                    v-for="(customer, index) in customers"
                    :key="customer.id"
                    :ref="el => setItemRef(el, index)"
                    @mouseenter="setActive(index)"
                    @click="selectCustomer(customer)"
                    :class="[
                        'p-2 cursor-pointer border-b',
                        index === activeIndex
                            ? 'bg-blue-100'
                            : 'hover:bg-gray-100'
                    ]"
                >
                    <div class="font-medium">
                        {{ customer.full_name }}

                        <span
                            v-if="customer.is_vip"
                            class="text-xs text-yellow-500 ml-1"
                        >
                            VIP
                        </span>
                    </div>

                    <div class="text-sm text-gray-500">
                        {{ customer.phone }}
                    </div>

                    <div
                        v-if="customer.debt > 0"
                        class="text-xs text-red-500"
                    >
                        Nợ: {{ customer.debt.toLocaleString() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- SELECTED -->
        <div
            v-if="selectedCustomer"
            class="mt-3 border rounded p-2 bg-blue-50"
        >
            <div class="font-medium">
                {{ selectedCustomer.full_name }}
            </div>

            <div class="text-sm text-gray-600">
                {{ selectedCustomer.phone }}
            </div>
        </div>

    </div>
</template>