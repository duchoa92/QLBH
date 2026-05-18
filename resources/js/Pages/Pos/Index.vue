<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const imei = ref('');

const items = ref([]);

const customerPaid = ref(0);

const loading = ref(false);

/*
|--------------------------------------------------------------------------
| Scan IMEI
|--------------------------------------------------------------------------
*/

const scanImei = async () => {

    if (!imei.value) {
        return;
    }

    loading.value = true;

    try {

        const response = await axios.post(

            route('pos.scan-imei'),

            {
                imei: imei.value,
            }
        );

        const data = response.data;

        /*
        |--------------------------------------------------------------------------
        | tránh scan trùng
        |--------------------------------------------------------------------------
        */

        const exists = items.value.find(

            item => item.id === data.id
        );

        if (exists) {

            alert('IMEI đã có trong giỏ');

            imei.value = '';

            return;
        }

        items.value.push({

            id: data.id,

            product_id:
                data.product_id,

            imei:
                data.imei,

            name:
                data.product.name,

            price:
                Number(
                    data.product.price
                ),
        });

        imei.value = '';

    } catch (error) {

        alert(
            error.response?.data?.message
            ?? 'Có lỗi xảy ra'
        );

    } finally {

        loading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Tổng tiền
|--------------------------------------------------------------------------
*/

const total = computed(() => {

    return items.value.reduce(

        (sum, item) => {

            return sum + item.price;

        },

        0
    );
});

/*
|--------------------------------------------------------------------------
| Tiền thừa
|--------------------------------------------------------------------------
*/

const changeMoney = computed(() => {

    return customerPaid.value - total.value;
});

/*
|--------------------------------------------------------------------------
| Xóa sản phẩm
|--------------------------------------------------------------------------
*/

const removeItem = (index) => {

    items.value.splice(index, 1);
};

/*
|--------------------------------------------------------------------------
| Thanh toán
|--------------------------------------------------------------------------
*/

const checkout = async () => {

    if (!items.value.length) {

        alert('Chưa có sản phẩm');

        return;
    }

    if (
        Number(customerPaid.value)
        < total.value
    ) {

        alert('Khách đưa chưa đủ');

        return;
    }

    try {

        const response = await axios.post(

            route('pos.checkout'),

            {

                items: items.value,

                customer_paid:
                    customerPaid.value,
            }
        );

        // mở hóa đơn sau khi thanh toán thành công
        window.open(

            route(
                'pos.sale.show',
                response.data.sale_id
            ),

            '_blank'
        );

        items.value = [];

        customerPaid.value = 0;

        imei.value = '';

    } catch (error) {

        alert(
            error.response?.data?.message
            ?? 'Thanh toán thất bại'
        );
    }
};
</script>

<template>
    <div class="p-6">

        <h1 class="text-2xl font-bold mb-6">
            POS Bán Hàng
        </h1>

        <!-- scan imei -->
        <div class="mb-6">

            <input
                v-model="imei"
                @keyup.enter="scanImei"
                type="text"
                placeholder="Quét IMEI..."
                class="w-full border rounded p-3"
            >
        </div>

        <!-- danh sách sản phẩm -->
        <div class="border rounded overflow-hidden">

            <table class="w-full">

                <thead class="bg-gray-100">

                    <tr>

                        <th class="p-3 text-left">
                            Tên SP
                        </th>

                        <th class="p-3 text-left">
                            IMEI
                        </th>

                        <th class="p-3 text-right">
                            Giá
                        </th>

                        <th class="p-3 w-20">
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <tr
                        v-for="(item, index) in items"
                        :key="item.id"
                        class="border-t"
                    >

                        <td class="p-3">
                            {{ item.name }}
                        </td>

                        <td class="p-3">
                            {{ item.imei }}
                        </td>

                        <td class="p-3 text-right">
                            {{ item.price.toLocaleString() }}
                        </td>

                        <td class="p-3 text-center">

                            <button
                                @click="removeItem(index)"
                                class="text-red-500"
                            >
                                Xóa
                            </button>
                        </td>
                    </tr>

                    <tr v-if="!items.length">

                        <td
                            colspan="4"
                            class="p-5 text-center text-gray-500"
                        >
                            Chưa có sản phẩm
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- thanh toán -->
        <div class="mt-6 max-w-md ml-auto space-y-4">

            <div class="flex justify-between">

                <span>Tổng tiền:</span>

                <strong>
                    {{ total.toLocaleString() }}
                </strong>
            </div>

            <div>

                <label class="block mb-1">
                    Khách đưa
                </label>

                <input
                    v-model="customerPaid"
                    type="number"
                    class="w-full border rounded p-2"
                >
            </div>

            <div class="flex justify-between">

                <span>Tiền thừa:</span>

                <strong>
                    {{ changeMoney.toLocaleString() }}
                </strong>
            </div>

            <button
                @click="checkout"
                :disabled="loading"
                class="w-full bg-blue-600 text-white py-3 rounded"
            >
                Thanh toán
            </button>
        </div>
    </div>
</template>