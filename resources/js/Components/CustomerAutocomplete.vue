<script setup>
import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';

import {
    ref,
    watch,
} from 'vue';

import axios from 'axios';
import { debounce } from 'lodash';

/**
 * EMITS
 */
const emit = defineEmits([
    'select',
    'update:modelValue',
]);

/**
 * STATE
 */
const query = ref('');
const customers = ref([]);
const selectedCustomer = ref(null);

/**
 * FETCH CUSTOMERS
 */
const fetchCustomers = debounce(
    async (keyword) => {

        if (!keyword) {

            customers.value = [];

            return;
        }

        try {

            const response = await axios.get(
                route('repairs.customer-search'),
                {
                    params: {
                        keyword,
                    },
                }
            );

            customers.value =
                response.data;

        } catch (error) {

            console.error(error);
        }
    },
    300
);

/**
 * WATCH INPUT
 */
watch(query, value => {

    emit(
        'update:modelValue',
        value
    );

    fetchCustomers(value);
});

/**
 * SELECT CUSTOMER
 */
const selectCustomer = customer => {

    selectedCustomer.value =
        customer;

    query.value =
        customer.customer_name;

    emit(
        'update:modelValue',
        customer.customer_name
    );

    emit(
        'select',
        customer
    );

    customers.value = [];
};
</script>

<template>
    <Combobox
        v-model="selectedCustomer"
        @update:modelValue="selectCustomer"
    >

        <div class="relative">

            <!-- INPUT -->

            <ComboboxInput
                class="w-full border rounded p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Nhập tên hoặc số điện thoại"
                :displayValue="
                    customer =>
                        customer?.customer_name
                        || query
                "
                @change="
                    query = $event.target.value
                "
            />

            <!-- DROPDOWN -->

            <ComboboxOptions
                v-if="customers.length"
                class="absolute z-50 mt-1 w-full bg-white border rounded shadow-lg max-h-72 overflow-y-auto"
            >

                <ComboboxOption
                    v-for="customer in customers"
                    :key="
                        customer.customer_phone
                    "
                    :value="customer"
                    v-slot="{ active }"
                >

                    <div
                        :class="[

                            'p-3 cursor-pointer border-b',

                            active
                                ? 'bg-blue-100'
                                : 'bg-white'
                        ]"
                    >

                        <div class="font-medium">
                            {{ customer.customer_name }}
                        </div>

                        <div class="text-sm text-gray-500">
                            {{ customer.customer_phone }}
                        </div>
                    </div>
                </ComboboxOption>
            </ComboboxOptions>
        </div>
    </Combobox>
</template>