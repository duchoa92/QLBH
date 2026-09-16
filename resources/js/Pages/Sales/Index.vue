<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Eye, Search } from 'lucide-vue-next'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatDateTime, formatMoney } from '@/utils/format'

const props = defineProps({
    sales: Object,
    filters: Object,
})

const search = ref(props.filters.search ?? '')
const detailSale = ref(null)
let searchTimeout = null

watch(search, value => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('sales.index'),
            { search: value },
            {
                preserveState: true,
                replace: true,
            }
        )
    }, 250)
})

const saleTotal = (sale) =>
    sale.grand_total ?? sale.total_amount ?? sale.subtotal ?? 0

const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Cái'

    return `${Number.isInteger(qty) ? qty : qty.toLocaleString('vi-VN')} ${unit}`
}
</script>

<template>
    <div class="space-y-4 p-6">
        <PageHeader
            title="Hóa đơn bán hàng"
            description="Tra cứu hóa đơn, sản phẩm và IMEI đã bán"
        />

        <DataPanel>
            <template #header>
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm mã HD hoặc IMEI..."
                        class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    >
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <th class="px-4 py-3 text-left">Mã HD</th>
                            <th class="px-4 py-3 text-right">Tổng tiền</th>
                            <th class="px-4 py-3 text-left">Ngày</th>
                            <th class="w-24 px-4 py-3 text-center">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="sale in sales.data"
                            :key="sale.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ sale.code }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                {{ formatMoney(saleTotal(sale)) }} đ
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ formatDateTime(sale.created_at) }}</td>
                            <td class="px-4 py-3 text-center">
                                <ActionButton
                                    variant="ghost"
                                    title="Xem chi tiết"
                                    @click="detailSale = sale"
                                >
                                    <Eye class="h-4 w-4" />
                                </ActionButton>
                            </td>
                        </tr>

                        <tr v-if="!sales.data.length">
                            <td
                                colspan="4"
                                class="px-4 py-12 text-center text-slate-400"
                            >
                                Không có dữ liệu
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DataPanel>

        <BaseModal
            v-if="detailSale"
            title="Chi tiết hóa đơn"
            size="xl"
            @close="detailSale = null"
        >
            <div class="space-y-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-xs font-semibold uppercase text-slate-400">Mã HD</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ detailSale.code }}</div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-xs font-semibold uppercase text-slate-400">Ngày</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ formatDateTime(detailSale.created_at) }}</div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-xs font-semibold uppercase text-slate-400">Tổng tiền</div>
                        <div class="mt-1 font-semibold text-emerald-600">{{ formatMoney(saleTotal(detailSale)) }} đ</div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[700px] text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                <th class="px-3 py-2 text-left">Sản phẩm</th>
                                <th class="px-3 py-2 text-left">IMEI</th>
                                <th class="px-3 py-2 text-center">SL</th>
                                <th class="px-3 py-2 text-right">Đơn giá</th>
                                <th class="px-3 py-2 text-right">Thành tiền</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in detailSale.items"
                                :key="item.id"
                            >
                                <td class="px-3 py-2 font-medium text-slate-900">
                                    {{ item.product?.name || '-' }}
                                </td>
                                <td class="px-3 py-2 font-mono text-xs text-slate-600">
                                    {{ item.product_imei?.imei ?? '-' }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="font-semibold text-slate-800">
                                        {{ saleQuantityText(item) }}
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(item.unit_price) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ formatMoney(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
