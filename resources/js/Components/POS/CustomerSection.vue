<script setup>

import axios from 'axios'

import {
    ref,
} from 'vue'

const emit = defineEmits([
    'selected',
])

const keyword = ref('')

const customers = ref([])

// chỉ số khách hàng được chọn trong danh sách kết quả tìm kiếm
const selectedIndex = ref(-1)

const selectedCustomer =
    ref(null)

let timeout = null

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const search = () => {

    clearTimeout(timeout)

    timeout = setTimeout(
        async () => {

            if (
                !keyword.value
            ) {

                customers.value = []

                return
            }

            const response =
                await axios.get(
                    '/api/customers/search',
                    {
                        params: {
                            q: keyword.value,
                        },
                    }
                )

            customers.value =
                response.data

                // reset chỉ số khách hàng được chọn
                selectedIndex.value = 0
        },
        300
    )
}

/*
|--------------------------------------------------------------------------
| Select
|--------------------------------------------------------------------------
*/

const selectCustomer = (
    customer
) => {

    selectedCustomer.value =
        customer

    keyword.value =
        customer.name

    customers.value = []

   
    emit(
        'selected',
        customer
    )
}

/*
|--------------------------------------------------------------------------
| Clear
|--------------------------------------------------------------------------
*/

const clearCustomer = () => {

    selectedCustomer.value =
        null

    keyword.value = ''

    emit(
        'selected',
        null
    )
}


/*
|--------------------------------------------------------------------------
| Keyboard Navigation
|--------------------------------------------------------------------------
*/

const handleKeydown = (event) => {

    /*
    |--------------------------------------------------------------------------
    | Arrow Down
    |--------------------------------------------------------------------------
    */

    if (event.key === 'ArrowDown') {

        event.preventDefault()

        if (
            selectedIndex.value <
            customers.value.length - 1
        ) {

            selectedIndex.value++
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Arrow Up
    |--------------------------------------------------------------------------
    */

    if (event.key === 'ArrowUp') {

        event.preventDefault()

        if (selectedIndex.value > 0) {

            selectedIndex.value--
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Enter
    |--------------------------------------------------------------------------
    */

    if (event.key === 'Enter') {

        event.preventDefault()

        const customer =
            customers.value[
                selectedIndex.value
            ]

        if (customer) {

            selectCustomer(customer)
        }
    }
}

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
                v-if="selectedCustomer"
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
                @keydown="handleKeydown"
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
                    :key="customer.id"
                    @click="
                        selectCustomer(
                            customer
                        )
                    "
                    :class="[
                        'p-2 cursor-pointer border-b',
                        index === selectedIndex
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
            v-if="selectedCustomer"
            class="mt-3 border rounded p-2 bg-blue-50"
        >

            <div
                class="font-medium"
            >
                {{
                    selectedCustomer.full_name
                }}
            </div>

            <div
                class="text-sm text-gray-600"
            >
                {{
                    selectedCustomer.phone
                }}
            </div>

        </div>

    </div>

</template>