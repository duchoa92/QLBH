<script setup>

const props = defineProps({

    selectedIndex: Number,

    items: Array,
})

// Xóa sản phẩm khỏi giỏ hàng
const emit = defineEmits([
    'remove',
])

/*
|--------------------------------------------------------------------------
| Format money
|--------------------------------------------------------------------------
*/

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

/*
|--------------------------------------------------------------------------
| Update quantity
|--------------------------------------------------------------------------
*/

const updateQty = (item, event) => {

    let qty = Number(event.target.value)

    if (isNaN(qty) || qty < 1) {

        qty = 1
    }

    item.quantity = qty
}
</script>

<template>

    <table class="w-full text-sm">

        <thead>

            <tr class="border-b bg-gray-50">

                <th class="text-left p-2">
                    Tên SP
                </th>

                <th class="text-center p-2 w-[120px]">
                    SL
                </th>

                <th class="text-right p-2 w-[150px]">
                    Giá
                </th>

                <th class="text-right p-2 w-[150px]">
                    Thành tiền
                </th>
                <th class="w-[60px]"></th>

            </tr>

        </thead>

        <tbody>

            <tr
                v-for="(item, index) in items"
                :key="item.id + '-' + (item.imei_id || index)"
                :class="{
                    'bg-blue-100':
                        selectedIndex === index,
                }"
            >

                <!-- Tên -->

                <td class="p-2">

                    <div class="font-medium">
                        {{ item.name }}
                    </div>

                    <div
                        v-if="item.imei"
                        class="text-xs text-blue-600"
                    >
                        IMEI:
                        {{ item.imei }}
                    </div>

                    <div
                        v-if="item.serial"
                        class="text-xs text-gray-500"
                    >
                        Serial:
                        {{ item.serial }}
                    </div>

                    <div
                        v-if="
                            item.color ||
                            item.storage
                        "
                        class="text-xs text-gray-500"
                    >
                        {{ item.color }}

                        <span
                            v-if="
                                item.color &&
                                item.storage
                            "
                        >
                            -
                        </span>

                        {{ item.storage }}
                    </div>

                </td>

                <!-- Số lượng -->

                <td class="p-2 text-center">

                    <input
                        :value="item.quantity"

                        :disabled="!!item.imei"

                        @change="
                            updateQty(
                                item,
                                $event
                            )
                        "

                        type="number"

                        min="1"

                        class="w-20 border rounded px-2 py-1 text-center disabled:bg-gray-100"
                    />

                </td>

                <!-- Giá -->

                <td class="p-2 text-right">

                    {{
                        format(item.price)
                    }}

                </td>

                <!-- Thành tiền -->

                <td class="p-2 text-right font-bold">

                    {{
                        format(
                            item.price *
                            item.quantity
                        )
                    }}

                </td>

                <!-- Hành động -->

                <td class="p-2 text-center">

                    <button
                        @click="emit('remove', index)"
                        class="text-red-600 hover:text-red-800"
                    >
                        ✕
                    </button>

                </td>

            </tr>

        </tbody>

    </table>

</template>