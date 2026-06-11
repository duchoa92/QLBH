<script setup>
defineProps({

    show: Boolean,

    debts: Array,

    total: Number,
})

const emit = defineEmits([
    'close',
])

const formatMoney = (value) => {

    return Number(value || 0)
        .toLocaleString('vi-VN')
}
</script>

<template>

    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 "
    >

        <div class="bg-white w-[700px] max-h-[80vh] rounded-lg shadow-lg p-5 flex flex-col">

            <div class="mb-4 flex justify-between">

                <h3 class="font-bold text-lg">
                    Chi tiết nợ
                </h3>

                <button
                    @click="$emit('close')"
                >
                    X
                </button>
            </div>

            <div class="overflow-y-auto flex-1 border rounded">
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

                                <a
                                    v-if="debt.source_code"
                                    :href="`/sales/${debt.sale_id}`"
                                    target="_blank"
                                    class="text-xs text-blue-600 hover:underline"
                                >
                                    HĐ: {{ debt.source_code }}
                                </a>

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

            <div
                class="mt-4 border-t pt-4 text-right font-bold"
            >

                Tổng nợ:

                {{ formatMoney(total) }}

            </div>

        </div>

    </div>

</template>