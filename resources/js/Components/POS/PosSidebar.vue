<script setup>

import { ref } from 'vue'
import CustomerSection from '@/Modules/POS/Customer/Components/CustomerSection.vue'
import SummarySection from '@/Components/POS/SummarySection.vue'
import KeyboardShortcuts from '@/Components/POS/KeyboardShortcuts.vue'
import CartTable from '@/Components/POS/CartTable.vue'


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

    'remove-item',

    'checkout',
])


const note = ref('')
const paymentMethod = ref('cash')

</script>

<template>

    <aside
        class="min-h-0 rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-5 xl:col-span-4"
    >

        <div class="flex h-full min-h-0 flex-col">

            <div
                class="flex items-center justify-between border-b border-slate-200 px-4 py-3"
            >

                <div>

                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Hoa don hien tai
                    </div>

                    <h2 class="text-lg font-bold text-slate-900">
                        Don ban hang
                    </h2>

                </div>

                <div class="rounded-md bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                    {{ cart.length }} mat hang
                </div>

            </div>

            <div class="space-y-3 border-b border-slate-200 p-4">

                <CustomerSection

                    :customer="selectedCustomer"

                    @selected="
                        $emit(
                            'customer-selected',
                            $event
                        )
                    "
                />

                <div class="grid grid-cols-2 gap-2">

                    <button
                        type="button"
                        @click="$emit('open-hold')"
                        class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100"
                    >
                        Luu tam
                    </button>

                    <button
                        type="button"
                        :disabled="holdSales.length === 0"
                        @click="
                            $emit(
                                'show-hold-list'
                            )
                        "
                        class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-50 disabled:text-slate-400"
                    >
                        Don giu ({{ holdSales.length }})
                    </button>

                </div>

                <KeyboardShortcuts

                    :show="showShortcuts"

                    @toggle="
                        $emit(
                            'toggle-shortcuts'
                        )
                    "
                />

            </div>

            <div class="min-h-0 flex-1 overflow-auto">

                <CartTable

                    :items="cart"

                    @remove="
                        $emit(
                            'remove-item',
                            $event
                        )
                    "
                />

            </div>

            <div class="border-t border-slate-200 p-4">

                <div class="p-4 border-t bg-white">

                    <button
                        @click="$emit('checkout', {
                            note,
                            paymentMethod
                        })"
                        class="w-full bg-blue-600 text-white py-3 rounded font-semibold"
                    >
                        Thanh toán
                    </button>

                </div>

            </div>

            <div class="p-4 border-t space-y-3">

                <!-- NOTE -->
                <textarea
                    v-model="note"
                    placeholder="Ghi chú..."
                    class="w-full border rounded p-2 text-sm"
                />

                <!-- PAYMENT -->
                <select
                    v-model="paymentMethod"
                    class="w-full border rounded p-2 text-sm"
                >
                    <option value="cash">Tiền mặt</option>
                    <option value="bank">Chuyển khoản</option>
                </select>

            </div>

        </div>

    </aside>

</template>
