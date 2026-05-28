<script setup>

import CustomerSection from '@/Components/POS/CustomerSection.vue'
import SummarySection from '@/Components/POS/SummarySection.vue'
import KeyboardShortcuts from '@/Components/POS/KeyboardShortcuts.vue'

defineProps({

    cart: {
        type: Array,
        default: () => [],
    },

    selectedCustomer: {
        type: Object,
        default: null,
    },

    showShortcuts: {
        type: Boolean,
        default: false,
    },

    holdSales: {
        type: Array,
        default: () => [],
    },
})

defineEmits([

    'customer-selected',

    'toggle-shortcuts',

    'open-hold',

    'show-hold-list',

    'checkout',
])

</script>

<template>

    <div
        class="col-span-4 bg-white rounded shadow p-3 flex flex-col"
    >

        <!-- CUSTOMER -->

        <CustomerSection

            :customer="selectedCustomer"

            @selected="
                $emit(
                    'customer-selected',
                    $event
                )
            "
        />

        <!-- ACTION AREA -->

        <div class="mt-4">

            <!-- SHORTCUTS -->

            <KeyboardShortcuts

                :show="showShortcuts"

                @toggle="
                    $emit(
                        'toggle-shortcuts'
                    )
                "
            />

            <!-- BUTTONS -->

            <div class="space-y-2">

                <!-- HOLD -->

                <button
                    type="button"
                    @click="$emit('open-hold')"
                    class="w-full px-4 py-3 bg-yellow-500 text-white rounded"
                >
                    Lưu tạm hóa đơn
                </button>

                <!-- HOLD LIST -->

                <button
                    v-if="holdSales.length > 0"
                    @click="
                        $emit(
                            'show-hold-list'
                        )
                    "
                    class="w-full px-4 py-3 bg-blue-500 text-white rounded"
                >
                    Có:
                    ({{ holdSales.length }})
                    hóa đơn
                </button>

                <!-- SUMMARY -->

                <SummarySection

                    :items="cart"

                    @checkout="
                        $emit(
                            'checkout'
                        )
                    "
                />

            </div>

        </div>

    </div>

</template>