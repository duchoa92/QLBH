<script setup>
import { formatCurrency, formatDateTime } from '@/utils/format'
import { imageUrl } from '@/utils/imageUrl'

const props = defineProps({

    imei: Object,

    repairs: {
        type: Array,
        default: () => [],
    },
})

const extraInfo = props.imei?.extra_info || {}

const hasExtraInfo = Boolean(
    extraInfo.note ||
    extraInfo.image_path
)

const extraInfoImageUrl = imageUrl(extraInfo.image_path)

const money = formatCurrency

const formatDate = (value) => {
    if (!value) {
        return '-'
    }

    return formatDateTime(value)
}

const saleCustomerName = (saleItem) =>
    saleItem.sale?.customer?.full_name ||
    saleItem.sale?.customer?.name ||
    '-'

</script>

<template>

    <div class="p-6">

        <h1 class="text-2xl font-bold mb-6">

            Chi tiết IMEI

        </h1>

        <div class="bg-white rounded shadow p-6">

            <div class="space-y-3">

                <p>

                    <strong>IMEI:</strong>

                    {{ imei.imei }}

                </p>

                <p>

                    <strong>Sản phẩm:</strong>

                    {{ imei.product?.name }}

                </p>

                <p>

                    <strong>Trạng thái:</strong>

                    {{ imei.status }}

                </p>

                <p>

                    <strong>Ngày bán:</strong>

                    {{ formatDate(imei.sold_at) }}

                </p>

                <div
                    v-if="hasExtraInfo"
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4"
                >
                    <div class="mb-2 font-semibold text-slate-900">
                        Thông tin thiết bị lưu kèm
                    </div>

                    <div class="space-y-2 text-sm">
                        <p v-if="extraInfo.note">
                            <strong>Thông tin:</strong>
                            {{ extraInfo.note }}
                        </p>

                        <a v-if="extraInfoImageUrl" :href="extraInfoImageUrl" target="_blank" class="block w-fit">
                            <img
                                :src="extraInfoImageUrl"
                                alt="Ảnh thiết bị"
                                class="h-32 w-32 rounded-lg border border-slate-200 object-cover"
                            />
                        </a>
                    </div>
                </div>

            </div>

            <div
                v-if="imei.histories && imei.histories.length"
                class="mt-8"
            >
                <h2 class="font-bold text-lg mb-4">
                    Lịch sử giá và nhập hàng
                </h2>

                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left">
                                Phiếu
                            </th>

                            <th class="text-left">
                                Loại
                            </th>

                            <th class="text-right">
                                Giá nhập
                            </th>

                            <th class="text-right">
                                Giá bán dự kiến
                            </th>

                            <th class="text-left">
                                Thời gian
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="history in imei.histories"
                            :key="history.id"
                            class="border-t"
                        >
                            <td class="py-2">
                                {{ history.meta?.import_code || history.note || '-' }}
                            </td>

                            <td class="py-2">
                                {{ history.type === 'price_update' ? 'Đổi giá bán' : 'Nhập hàng' }}
                            </td>

                            <td class="py-2 text-right">
                                {{ money(history.cost_price) }}
                            </td>

                            <td class="py-2 text-right">
                                {{ money(history.sell_price) }}
                            </td>

                            <td class="py-2">
                                {{ formatDate(history.happened_at || history.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    imei.sale_items &&
                    imei.sale_items.length
                "
                class="mt-8"
            >

                <h2 class="font-bold text-lg mb-4">

                    Lịch sử bán

                </h2>

                <table class="w-full">

                    <thead>

                        <tr>

                            <th class="text-left">
                                Hóa đơn
                            </th>

                            <th class="text-left">
                                Khách hàng
                            </th>

                            <th class="text-left">
                                Ngày bán
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr
                            v-for="
                                saleItem
                                in imei.sale_items
                            "
                            :key="saleItem.id"
                        >

                            <td>

                                {{
                                    saleItem.sale?.code
                                }}

                            </td>

                            <td>

                                {{
                                    saleCustomerName(saleItem)
                                }}

                            </td>

                            <td>

                                {{
                                    saleItem.sale
                                        ?.created_at
                                        ? formatDate(saleItem.sale.created_at)
                                        : '-'
                                }}

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <div
                v-if="repairs.length"
                class="mt-8"
            >
                <h2 class="font-bold text-lg mb-4">
                    Lịch sử sửa chữa
                </h2>

                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left">
                                Phiếu sửa
                            </th>

                            <th class="text-left">
                                Nội dung
                            </th>

                            <th class="text-right">
                                Chi phí
                            </th>

                            <th class="text-left">
                                Trạng thái
                            </th>

                            <th class="text-left">
                                Ngày nhận
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="repair in repairs"
                            :key="repair.id"
                            class="border-t"
                        >
                            <td class="py-2">
                                {{ repair.code }}
                            </td>

                            <td class="py-2">
                                {{ repair.repair_request || repair.note || '-' }}
                            </td>

                            <td class="py-2 text-right">
                                {{ money(repair.final_cost || repair.estimated_cost) }}
                            </td>

                            <td class="py-2">
                                {{ repair.status || '-' }}
                            </td>

                            <td class="py-2">
                                {{ formatDate(repair.received_at || repair.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</template>
