<script setup>
import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';

import { ref, watch } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';

const emit = defineEmits(['select']);

const keyword = ref('');
const customers = ref([]);
const selectedCustomer = ref(null);

watch(
    keyword,
    debounce(async value => {
        if (!value) {
            customers.value = [];
            return;
        }

        const response = await axios.get(
            route('repairs.customer-search'),
            {
                params: { keyword: value },
            }
        );

        customers.value = response.data;
    }, 300)
);

const selectCustomer = customer => {

    selectedCustomer.value = customer;
    emit('select', customer);

    customers.value = [];
};
</script>

<template>
    <Combobox v-model="selectedCustomer" @update:modelValue="selectCustomer">

        <div class="relative">

            <ComboboxInput
                class="w-full border rounded p-3"
                placeholder="Nhập tên hoặc số điện thoại"
                @change="keyword = $event.target.value"
                :displayValue="customer => customer?.customer_name ?? ''"
            />

            <ComboboxOptions
                v-if="customers.length"
                class="absolute z-50 mt-1 w-full bg-white border rounded shadow max-h-60 overflow-y-auto"
            >
                <ComboboxOption
                    v-for="customer in customers"
                    :key="customer.customer_phone"
                    :value="customer"
                    v-slot="{ active }"
                >
                    <div
                        :class="[
                            'p-3 cursor-pointer',
                            active ? 'bg-blue-100' : ''
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