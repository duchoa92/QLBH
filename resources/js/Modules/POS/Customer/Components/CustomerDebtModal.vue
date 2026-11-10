<script setup>
import { ref } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import SaleSearchModal from '@/Components/Search/SaleSearchModal.vue'
import { formatCurrency } from '@/utils/format'

defineProps({

    show: Boolean,

    debts: Array,

    total: Number,
})

const emit = defineEmits([
    'close',
])

const formatMoney = formatCurrency

// Xem hóa đơn ngay trong POS, không điều hướng sang trang khác.
const viewSaleId = ref(null)
</script>

<template>

    <BaseModal v-if="show" title="Chi tiết nợ" size="lg" body-class="flex min-h-0 flex-1 flex-col p-4" @close="emit('close')">
            <div class="min-h-0 flex-1 overflow-auto rounded border">
                <table
                    class="w-full text-sm"
                >
                    <thead class="sticky top-0 bg-white z-10 border-b-2 border-gray-300">
                         <tr>
                            <th class="w-[140px] p-2 whitespace-nowrap">
                                Ngày
                            </th>

                            <th class="p-2">
                                Nội dung
                            </th>

                            <th class="w-[120px] text-right p-2">
                                Phát sinh
                            </th>

                            <th class="w-[120px] text-right p-2">
                                Dư nợ
                            </th>
                        </tr>

                    </thead>

                    <tbody>

                        <tr
                            v-for="(debt, index) in debts"
                            :key="debt.id"
                            :class="[
                                'border-b border-dashed border-gray-300 hover:bg-blue-50 transition',
                                index % 2 === 0
                                    ? 'bg-white'
                                    : 'bg-gray-50'
                            ]"
                        >

                            <td>
                                {{ debt.created_at }}
                            </td>

                            <td class="p-2">

                                <div>
                                    {{ debt.note }}
                                </div>

                                <button
                                    v-if="debt.source_code && debt.sale_id"
                                    type="button"
                                    class="text-xs text-blue-600 hover:underline"
                                    @click="viewSaleId = debt.sale_id"
                                >
                                    HĐ: {{ debt.source_code }}
                                </button>

                            </td>

                            <td class="text-right p-2 whitespace-nowrap">

                                <span
                                    v-if="debt.type === 'increase'"
                                    class="text-red-600"
                                >
                                    +
                                    {{ formatMoney(debt.amount) }}
                                </span>

                                <span
                                    v-else
                                    class="text-green-600"
                                >
                                    -
                                    {{ formatMoney(debt.amount) }}
                                </span>

                            </td>
                            <td class="text-right p-2 whitespace-nowrap">
                                {{ formatMoney(debt.balance) }}
                            </td>

                        </tr>

                    </tbody>

                </table>
            </div>

        <template #footer>
            <div class="flex justify-between text-sm font-bold">
                <span>Tổng nợ</span>
                <span>{{ formatMoney(total) }}</span>
            </div>
        </template>
    </BaseModal>

    <SaleSearchModal v-if="viewSaleId" :sale-id="viewSaleId" @close="viewSaleId = null" />

</template>
