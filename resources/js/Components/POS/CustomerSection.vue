<script setup>

import { useCustomerSearch } from '@/Composables/useCustomerSearch'

const emit = defineEmits([
    'selected',
])

const {

    keyword,

    customers,

    selectedCustomer,

    search,

    selectCustomer,

    clearCustomer,

    activeIndex,

    itemRefs,

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

        <!-- Header -->

        <div
            class="flex justify-between items-center mb-2"
        >

            <div class="font-bold">
                Khách hàng
            </div>

            <button
                v-if="props.customer"
                @click="clearCustomer"
                class="text-xs text-red-500"
            >
                Bỏ chọn
            </button>

        </div>

        <!-- Input -->

        <div class="relative">

            <input
                v-model="keyword"
                @input="search"
                @keydown="onKeyDown"
                type="text"
                placeholder="Tên hoặc SĐT khách"
                class="w-full border rounded p-2"
            />

            <!-- Results -->

            <div
                v-if="customers.length"
                class="absolute z-50 w-full bg-white border rounded shadow mt-1"
            >

                <div
                    v-for="(customer, index) in customers"
                    :ref="el => setItemRef(el, index)"
                    @mouseenter="setActive(index)"
                    :key="customer.id"
                    @click="
                        selectCustomer(
                            customer
                        )
                    "
                    :class="[
                        'p-2 cursor-pointer border-b',
                        index === activeIndex
                            ? 'bg-blue-100'
                            : 'hover:bg-gray-100'
                    ]"
                >

                    <div
                        class="font-medium"
                    >
                        {{
                            customer.full_name
                        }}
                    </div>

                    <div
                        class="text-sm text-gray-500"
                    >
                        {{
                            customer.phone
                        }}
                    </div>

                </div>

            </div>

        </div>

        <!-- Selected -->

        <div
            v-if="props.customer"
            class="mt-3 border rounded p-2 bg-blue-50"
        >

            <div
                class="font-medium"
            >
                {{
                    props.customer.full_name
                }}
            </div>

            <div
                class="text-sm text-gray-600"
            >
                {{
                    props.customer.phone
                }}
            </div>

        </div>

    </div>

</template>