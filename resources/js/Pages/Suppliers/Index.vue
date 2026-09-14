<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Eye, Plus, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'
import { openModal } from '@/Stores/modal'
import { useConfirm } from '@/Composables/useConfirm'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import DetailModal from '@/Components/UI/DetailModal.vue'
import SupplierForm from './Form.vue'

const props = defineProps({
    suppliers: Array,
    filters: Object,
})

const confirmBox = useConfirm()
const search = ref(props.filters?.search ?? '')
const detailSupplier = ref(null)

let searchTimeout = null

const searchSupplier = () => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('suppliers.index'),
            { search: search.value },
            {
                preserveState: true,
                replace: true,
            }
        )
    }, 250)
}

const reload = () => {
    router.reload({
        only: ['suppliers'],
        preserveScroll: true,
    })
}

const openCreate = () => {
    openModal(SupplierForm, {
        props: {
            title: 'Thêm nhà cung cấp',
        },
        onUpdated: reload,
    })
}

const openEdit = (supplier) => {
    openModal(SupplierForm, {
        props: {
            title: 'Sửa nhà cung cấp',
            supplier,
        },
        onUpdated: reload,
    })
}

const destroy = (supplier) => {
    confirmBox.show({
        title: 'Xác nhận xóa',
        message: `Xóa nhà cung cấp "${supplier.name}"?`,
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(route('suppliers.destroy', supplier.id), {
                preserveScroll: true,
                onSuccess: reload,
            })
        },
    })
}

const supplierRows = (supplier) => [
    { label: 'Mã', value: supplier.code },
    { label: 'Tên', value: supplier.name },
    { label: 'Điện thoại', value: supplier.phone },
    { label: 'Email', value: supplier.email },
    { label: 'Công nợ', value: `${formatMoney(supplier.debt_balance)} đ` },
    { label: 'Địa chỉ', value: supplier.address, full: true },
]
</script>

<template>
    <div class="space-y-4 p-6">
        <PageHeader
            title="Nhà cung cấp"
            description="Quản lý thông tin nhà cung cấp và công nợ"
        >
            <template #actions>
                <ActionButton @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Thêm mới
                </ActionButton>
            </template>
        </PageHeader>

        <DataPanel>
            <template #header>
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        @input="searchSupplier"
                        type="text"
                        placeholder="Tìm tên, điện thoại, email"
                        class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <th class="px-4 py-3 text-left">Mã</th>
                            <th class="px-4 py-3 text-left">Tên</th>
                            <th class="px-4 py-3 text-left">Điện thoại</th>
                            <th class="px-4 py-3 text-right">Công nợ</th>
                            <th class="w-32 px-4 py-3 text-center">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-medium text-slate-700">{{ supplier.code }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ supplier.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ supplier.phone || '-' }}</td>
                            <td
                                class="px-4 py-3 text-right font-semibold"
                                :class="supplier.debt_balance > 0 ? 'text-rose-600' : 'text-slate-500'"
                            >
                                {{ formatMoney(supplier.debt_balance) }} đ
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-center gap-1">
                                    <ActionButton
                                        variant="ghost"
                                        title="Xem chi tiết"
                                        @click="detailSupplier = supplier"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </ActionButton>

                                    <ActionButton
                                        variant="ghost"
                                        title="Sửa"
                                        @click="openEdit(supplier)"
                                    >
                                        <SquarePen class="h-4 w-4 text-blue-600" />
                                    </ActionButton>

                                    <ActionButton
                                        variant="ghost"
                                        title="Xóa"
                                        @click="destroy(supplier)"
                                    >
                                        <Trash2 class="h-4 w-4 text-red-500" />
                                    </ActionButton>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!suppliers.length">
                            <td
                                colspan="5"
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
            v-if="detailSupplier"
            title="Chi tiết nhà cung cấp"
            :rows="supplierRows(detailSupplier)"
            @close="detailSupplier = null"
        />
    </div>
</template>
