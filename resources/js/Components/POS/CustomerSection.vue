<script setup>
import axios from 'axios'
import { ref } from 'vue'

const emit = defineEmits([
    'selected',
])

const keyword = ref('')

const customers = ref([])

const selectedCustomer = ref(null)

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const search = async () => {

    if (!keyword.value) {

        customers.value = []

        return
    }

    const response = await axios.get(
        '/api/customers/search',
        {
            params: {
                q: keyword.value,
            }
        }
    )

    customers.value = response.data
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

    customers.value = []

    keyword.value =
        customer.name

    emit(
        'selected',
        customer
    )
}
</script>

<template>

    <div>

        <div class="font-bold mb-2">
            Khách hàng
        </div>

        <!-- Input -->

        <div class="relative">

            <input
                v-model="keyword"
                @input="search"
                type="text"
                placeholder="Tên / SĐT khách"
                class="w-full border rounded p-3"
            />

            <!-- Dropdown -->

            <div
                v-if="customers.length"
                class="absolute z-50 bg-white border rounded w-full mt-1 shadow"
            >

                <div
                    v-for="customer in customers"
                    :key="customer.id"
                    @click="
                        selectCustomer(
                            customer
                        )
                    "
                    class="p-3 border-b hover:bg-gray-100 cursor-pointer"
                >

                    <div class="font-medium">
                        {{ customer.name }}
                    </div>

                    <div class="text-sm text-gray-500">
                        {{ customer.phone }}
                    </div>

                </div>

            </div>

        </div>

        <!-- Selected -->

        <div
            v-if="selectedCustomer"
            class="mt-3 p-3 bg-blue-50 rounded border"
        >

            <div class="font-bold">
                {{ selectedCustomer.name }}
            </div>

            <div class="text-sm text-gray-600">
                {{ selectedCustomer.phone }}
            </div>

        </div>

    </div>

</template>