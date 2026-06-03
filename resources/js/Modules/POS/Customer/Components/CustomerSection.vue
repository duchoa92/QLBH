<script setup>

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
    customer: Object,
})
</script>

<template>

    <div>

        <div class="mb-2 flex items-center justify-between">

            <div class="text-sm font-bold text-slate-900">
                Khach hang
            </div>

            <button
                v-if="props.customer"
                type="button"
                @click="clearCustomer"
                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
            >
                Bo chon
            </button>

        </div>

        <div class="relative">

            <input
                v-model="keyword"
                @input="search"
                @keydown="onKeyDown"
                type="text"
                placeholder="Ten hoac SDT khach"
                class="h-11 w-full rounded-md border-slate-300 px-3 text-sm font-medium shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />

            <div
                v-if="customers.length"
                class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg"
            >

                <button
                    v-for="(customer, index) in customers"
                    :ref="el => setItemRef(el, index)"
                    @mouseenter="setActive(index)"
                    :key="customer.id"
                    type="button"
                    @click="
                        selectCustomer(
                            customer
                        )
                    "
                    :class="[
                        'block w-full border-b border-slate-100 p-3 text-left last:border-b-0',
                        index === activeIndex
                            ? 'bg-blue-50'
                            : 'hover:bg-slate-50'
                    ]"
                >

                    <div class="font-semibold text-slate-900">
                        {{
                            customer.full_name
                        }}
                    </div>

                    <div class="text-sm text-slate-500">
                        {{
                            customer.phone
                        }}
                    </div>

                </button>

            </div>

        </div>

        <div
            v-if="props.customer"
            class="mt-3 rounded-md border border-blue-200 bg-blue-50 p-3"
        >

            <div class="font-semibold text-blue-950">
                {{
                    props.customer.full_name
                }}
            </div>

            <div class="text-sm text-blue-700">
                {{
                    props.customer.phone
                }}
            </div>

        </div>

    </div>

</template>
