<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

// Input IMEI
const imeiInput = ref('');

// Giỏ hàng
const cart = ref([]);

// Trạng thái loading khi quét IMEI
const loading = ref(false);

// Tính tổng tiền
const total = computed(() => {

    return cart.value.reduce(

        (sum, item) => {

            return sum + Number(item.price);

        },

        0
    );
});

// Quét IMEI
const scanImei = async () => {

    if (! imeiInput.value) {
        return;
    }

    loading.value = true;

    try {

        const response = await axios.post(

            route('pos.scan-imei'),

            {
                imei: imeiInput.value,
            }
        );

        const exists = cart.value.find(

            item => item.id === response.data.id
        );

        if (exists) {

            alert('IMEI đã có trong giỏ');

            imeiInput.value = '';

            return;
        }

        cart.value.push({

            id: response.data.id,

            product_id:
                response.data.product_id,

            imei: response.data.imei,

            product_name:
                response.data.product.name,

            price:
                response.data.product.price,
        });

        imeiInput.value = '';

    } catch (error) {

        alert(
            error.response.data.message
        );
    }

    loading.value = false;
};

const removeItem = (index) => {

    cart.value.splice(index, 1);
};

// Thanh toán
const customerPaid = ref(0);

const checkout = async () => {

    try {

        await axios.post(

            route('pos.checkout'),

            {
                items: cart.value,

                customer_paid:
                    customerPaid.value,
            }
        );

        alert('Thanh toán thành công');

        cart.value = [];

        customerPaid.value = 0;

    } catch (error) {

        alert(
            error.response.data.message
        );
    }
};



</script>

<template>

    <div class="p-6">

        <h1 class="text-3xl font-bold mb-6">
            POS Bán Hàng
        </h1>

        <div
            class="bg-white rounded shadow p-4 mb-6"
        >

            <label class="block mb-2">
                Quét IMEI
            </label>

            <input
                v-model="imeiInput"
                @keyup.enter="scanImei"
                type="text"
                autofocus
                class="w-full border rounded p-3 text-lg"
                placeholder="Quét IMEI..."
            >

        </div>

        <div
            class="bg-white rounded shadow overflow-hidden"
        >

            <table class="w-full">

                <thead class="bg-gray-100">

                    <tr>

                        <th class="p-3 border">
                            IMEI
                        </th>

                        <th class="p-3 border">
                            Sản phẩm
                        </th>

                        <th class="p-3 border">
                            Giá
                        </th>

                        <th class="p-3 border">
                            #
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="
                            (item, index)
                            in cart
                        "
                        :key="item.id"
                    >

                        <td class="p-3 border">
                            {{ item.imei }}
                        </td>

                        <td class="p-3 border">
                            {{ item.product_name }}
                        </td>

                        <td class="p-3 border">
                            {{
                                Number(
                                    item.price
                                ).toLocaleString()
                            }}
                        </td>

                        <td class="p-3 border">

                            <button
                                @click="
                                    removeItem(index)
                                "
                                class="text-red-600"
                            >
                                Xóa
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <!-- Tổng tiền -->
        <div
            class="mt-6 text-right"
        >

            <div class="text-2xl font-bold">

                Tổng tiền:

                {{
                    total.toLocaleString()
                }}

                đ

            </div>

        </div>

        <!-- Nhập tiền khách đưa -->
        <div class="mt-4">

            <label class="block mb-2">
                Tiền khách đưa
            </label>

            <input
                v-model="customerPaid"
                type="number"
                class="border rounded p-3 w-full"
            >

        </div>
<!-- Tiền thừa -->
        <div class="mt-4 text-xl">

            Tiền thừa:

            {{
                (
                    customerPaid - total
                ).toLocaleString()
            }}

            đ

        </div>

        <button
            @click="checkout"
            class="mt-6 bg-green-600 text-white px-6 py-3 rounded"
        >
            Thanh toán
        </button>

    </div>

</template>