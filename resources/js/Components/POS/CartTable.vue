<template>
    <table class="w-full text-sm">

        <thead>
            <tr class="border-b bg-gray-50">

                <th class="text-left p-2">
                    Tên SP
                </th>

                <th class="text-center p-2 w-[100px]">
                    SL
                </th>

                <th class="text-right p-2 w-[150px]">
                    Giá
                </th>

                <th class="text-right p-2 w-[150px]">
                    Thành tiền
                </th>

            </tr>
        </thead>

        <tbody>

            <tr
                v-for="item in items"
                :key="item.id"
                class="border-b"
            >
                <td class="p-2">
                    <div>
                        {{ item.name }}
                    </div>

                    <div
                        v-if="item.imei"
                        class="text-xs text-blue-600"
                    >
                        IMEI: {{ item.imei }}
                    </div>
                </td>

                <td class="p-2 text-center">
                    {{ item.quantity }}
                </td>

                <td class="p-2 text-right">
                    {{ format(item.sell_price) }}
                </td>

                <td class="p-2 text-right font-bold">
                    {{ format(item.sell_price * item.quantity) }}
                </td>
            </tr>

        </tbody>

    </table>
</template>

<script setup>
defineProps({
    items: Array
})

const format = (number) => {
    return Number(number).toLocaleString('vi-VN')
}
</script>