<script setup>

const props = defineProps({

    sale: Object,
})

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

/*
|--------------------------------------------------------------------------
| Print auto
|--------------------------------------------------------------------------
*/

window.onload = () => {

    window.print()
}
</script>

<template>

    <div
        class="max-w-[320px] mx-auto p-3 text-sm"
    >

        <!-- SHOP -->

        <div class="text-center">

            <div class="text-xl font-bold">
                Đức Hòa
            </div>
            <div>
                <span class="font-italic">Điện Thoại - Máy Tính - Camera</span>
            </div>
            <div>
                Đ/c: Cầu Giớ - Vạn Xuân - Hưng Yên
            </div>

            <div>
                0906064789
            </div>

        </div>

        <!-- Divider -->

        <div class="border-t border-dashed my-3"></div>

        <!-- Info -->

        <div class="space-y-1">

            <div>
                Mã HD:
                {{ sale.code }}
            </div>

            <div>
                Ngày:
                {{ sale.created_at }}
            </div>

            <div>
                Thu ngân:
                {{ sale.user?.name }}
            </div>

            <div v-if="sale.customer">

                KH:
                {{ sale.customer.name }}

            </div>

        </div>

        <!-- Divider -->

        <div class="border-t border-dashed my-3"></div>

        <!-- Items -->

        <div
            v-for="item in sale.items"
            :key="item.id"
            class="mb-3"
        >

            <div class="font-medium">

                {{ item.product?.name }}

            </div>

            <div
                v-if="
                    item.product_imei_id
                "
                class="text-xs text-gray-600"
            >

                IMEI:
                {{
                    item.product_imei?.imei
                }}

            </div>

            <div
                class="flex justify-between"
            >

                <div>
                    {{
                        item.qty
                    }}
                    x
                    {{
                        format(item.price)
                    }}
                </div>

                <div>
                    {{
                        format(item.grand_total)
                    }}
                </div>

            </div>

        </div>

        <!-- Divider -->

        <div class="border-t border-dashed my-3"></div>

        <!-- Totals -->

        <div
            class="space-y-2"
        >

            <div
                class="flex justify-between"
            >

                <span>
                    Tạm tính
                </span>

                <span>
                    {{
                        format(
                            sale.subtotal
                        )
                    }}
                </span>

            </div>

            <div
                class="flex justify-between font-bold text-lg"
            >

                <span>
                    Tổng
                </span>

                <span>
                    {{
                        format(
                            sale.grand_total
                        )
                    }}
                </span>

            </div>

            <div
                class="flex justify-between"
            >

                <span>
                    Khách đưa
                </span>

                <span>
                    {{
                        format(
                            sale.paid_amount
                        )
                    }}
                </span>

            </div>

            <div
                class="flex justify-between"
            >

                <span>
                    Tiền thừa
                </span>

                <span>
                    {{
                        format(
                            sale.change_amount
                        )
                    }}
                </span>

            </div>

        </div>

        <!-- Divider -->

        <div class="border-t border-dashed my-3"></div>

        <!-- Footer -->

        <div class="text-center text-xs">

            Cảm ơn quý khách

        </div>

    </div>

</template>