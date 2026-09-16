<script setup>

defineOptions({
    // Trang hóa đơn dùng để in trực tiếp — không bọc layout admin (sidebar/topbar)
    layout: null,
})

const props = defineProps({

    sale: Object,
})

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Cái'

    return `${Number.isInteger(qty) ? qty : qty.toLocaleString('vi-VN')} ${unit}`
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
                {{ sale.customer.full_name }}

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
                <span
                    v-if="item.variant?.attributes"
                    class="block text-[11px] font-normal text-slate-600"
                >
                    {{ Object.values(item.variant.attributes).filter(Boolean).join(' / ') }}
                </span>

            </div>
            <!--Hiện giảm giá-->
            <div
                v-if="item.discount_value > 0"
                class="text-xs text-red-600"
            >
                Giảm:

                <span
                    v-if="
                        item.discount_type === 'percent'
                    "
                >
                    {{ item.discount_value }}%
                </span>

                <span v-else>
                    {{ $money(item.discount_value) }}đ
                </span>

            </div>

            <div
                v-if="
                    item.gifts &&
                    item.gifts.length
                "
                class="mt-1 text-xs text-green-700"
            >

                <div
                    v-for="gift in item.gifts"
                    :key="gift.id"
                >
                    🎁 {{ gift.product?.name }}
                    x{{ gift.quantity }}
                </div>

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
                    <div>
                        {{ saleQuantityText(item) }}
                        x
                        {{ $money(item.unit_price) }}
                    </div>
                </div>

                <div>
                    {{ $money(item.subtotal) }}
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
                        $money(
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
                        $money(
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
                        $money(
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
                        $money(
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
