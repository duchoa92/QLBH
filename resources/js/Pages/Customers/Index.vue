<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Eye, Plus, Search, SquarePen } from 'lucide-vue-next'
import { openModal } from '@/Stores/modal'
import { formatMoney } from '@/utils/format'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import DetailModal from '@/Components/UI/DetailModal.vue'
import CustomerFormModal from './CustomerFormModal.vue'

const props = defineProps({
    customers: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')
const detailCustomer = ref(null)
let searchTimeout = null

watch(search, (value) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('customers.index'),
            { search: value },
            {
                preserveState: true,
                replace: true,
            }
        )
    }, 250)
})

const reload = () => {
    router.reload({
        only: ['customers'],
        preserveScroll: true,
    })
}

const openCreate = () => {
    openModal(CustomerFormModal, {
        props: {
            title: 'Thêm khách hàng',
        },
        onUpdated: reload,
    })
}

const openEdit = (customer) => {
    openModal(CustomerFormModal, {
        props: {
            title: 'Sửa khách hàng',
            customer,
        },
        onUpdated: reload,
    })
}

const customerRows = (customer) => [
    { label: 'Mã KH', value: customer.code },
    { label: 'Họ tên', value: customer.full_name },
    { label: 'Số điện thoại', value: customer.phone },
    { label: 'CCCD', value: customer.cccd },
    { label: 'Ngày sinh', value: customer.birthday },
    { label: 'Giới tính', value: customer.gender },
    { label: 'Điểm', value: customer.point_balance },
    { label: 'Công nợ', value: `${formatMoney(customer.debt_balance)} đ` },
    { label: 'Địa chỉ', value: customer.address, full: true },
    { label: 'Ghi chú', value: customer.note, full: true },
]
</script>

<template>
    <div class="space-y-4 p-6">
        <PageHeader
            title="Khách hàng"
            description="Thông tin liên hệ, điểm tích lũy và công nợ"
        >
            <template #actions>
                <ActionButton @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Thêm khách
                </ActionButton>
            </template>
        </PageHeader>

        <DataPanel>
            <template #header>
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm tên, SĐT..."
                        class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <th class="px-4 py-3 text-left">Mã KH</th>
                            <th class="px-4 py-3 text-left">Họ tên</th>
                            <th class="px-4 py-3 text-left">SĐT</th>
                            <th class="px-4 py-3 text-center">Điểm</th>
                            <th class="px-4 py-3 text-right">Công nợ</th>
                            <th class="px-4 py-3 text-center">Loại</th>
                            <th class="w-28 px-4 py-3 text-center">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in customers.data"
                            :key="item.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-medium text-slate-700">{{ item.code }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ item.full_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ item.phone || '-' }}</td>
                            <td class="px-4 py-3 text-center font-semibold text-blue-600">{{ item.point_balance }}</td>
                            <td
                                class="px-4 py-3 text-right font-semibold"
                                :class="item.debt_balance > 0 ? 'text-red-600' : 'text-green-600'"
                            >
                                {{ formatMoney(item.debt_balance) }} đ
                            </td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ item.customer_type }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-center gap-1">
                                    <ActionButton
                                        variant="ghost"
                                        title="Xem chi tiết"
                                        @click="detailCustomer = item"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </ActionButton>

                                    <ActionButton
                                        variant="ghost"
                                        title="Sửa"
                                        @click="openEdit(item)"
                                    >
                                        <SquarePen class="h-4 w-4 text-blue-600" />
                                    </ActionButton>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!customers.data.length">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-slate-400"
                            >
                                Không có dữ liệu
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DataPanel>

        <DetailModal
            v-if="detailCustomer"
            title="Chi tiết khách hàng"
            :rows="customerRows(detailCustomer)"
            @close="detailCustomer = null"
        />
    </div>
</template>
