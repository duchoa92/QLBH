<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { Pencil, Plus, Power, Save, Trash2, X } from 'lucide-vue-next'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    settings: Object,
    can_update_bank_settings: Boolean,
    units: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    shop_name: props.settings.shop_name || '',
    currency_symbol: props.settings.currency_symbol || '₫',
    currency_format: props.settings.currency_format || 'vi-VN',
    app_locale: props.settings.app_locale || 'vi',
    allow_negative_stock: props.settings.allow_negative_stock || false,
    bank_bin: props.settings.bank_bin || '',
    bank_account: props.settings.bank_account || '',
    bank_account_name: props.settings.bank_account_name || '',
    bank_transfer_content: props.settings.bank_transfer_content || 'Thanh toan don hang',
})

const currencyFormatOptions = [
    { value: 'vi-VN', label: 'Việt Nam' },
    { value: 'en-US', label: 'US' },
]

const localeOptions = [
    { value: 'vi', label: 'Tiếng Việt' },
    { value: 'en', label: 'English' },
]

const bankLockedText = computed(() =>
    props.can_update_bank_settings
        ? 'Admin có thể cập nhật thông tin nhận chuyển khoản.'
        : 'Chỉ Admin được thay đổi thông tin nhận chuyển khoản.'
)

const editingUnitId = ref(null)

const unitForm = useForm({
    name: '',
    short_name: '',
    is_active: true,
})

const save = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
    })
}

const resetUnitForm = () => {
    editingUnitId.value = null
    unitForm.reset()
    unitForm.clearErrors()
    unitForm.name = ''
    unitForm.short_name = ''
    unitForm.is_active = true
}

const editUnit = (unit) => {
    editingUnitId.value = unit.id
    unitForm.clearErrors()
    unitForm.name = unit.name || ''
    unitForm.short_name = unit.short_name || ''
    unitForm.is_active = Boolean(unit.is_active)
}

const submitUnit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetUnitForm,
    }

    if (editingUnitId.value) {
        unitForm.put(route('units.update', editingUnitId.value), options)
        return
    }

    unitForm.post(route('units.store'), options)
}

const toggleUnitStatus = (unit) => {
    router.patch(route('units.toggleStatus', unit.id), {}, {
        preserveScroll: true,
    })
}

const deleteUnit = (unit) => {
    if (!window.confirm(`Xóa đơn vị "${unit.name}"?`)) {
        return
    }

    router.delete(route('units.destroy', unit.id), {
        preserveScroll: true,
    })
}

const unitDisplayName = (unit) =>
    unit?.short_name
        ? `${unit.name} (${unit.short_name})`
        : unit?.name
</script>

<template>
    <div class="space-y-5">
        <PageHeader
            title="Cài đặt hệ thống"
            description="Quản lý thông tin vận hành, định dạng tiền tệ và tài khoản nhận thanh toán."
        >
            <template #actions>
                <ActionButton
                    :disabled="form.processing"
                    @click="save"
                >
                    <Save :size="16" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu cài đặt' }}
                </ActionButton>
            </template>
        </PageHeader>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
            <div class="space-y-5">
                <DataPanel>
                    <template #header>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                Thông tin cửa hàng
                            </h2>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">
                                Tên cửa hàng và thiết lập hiển thị chung.
                            </p>
                        </div>
                    </template>

                    <div class="grid gap-4 p-4 md:grid-cols-2">
                        <FloatingInput
                            v-model="form.shop_name"
                            name="shop_name"
                            label="Tên cửa hàng"
                            :error="form.errors.shop_name"
                        />

                        <FloatingInput
                            v-model="form.currency_symbol"
                            name="currency_symbol"
                            label="Ký hiệu tiền"
                            :error="form.errors.currency_symbol"
                        />

                        <FloatingSelect
                            v-model="form.currency_format"
                            name="currency_format"
                            label="Định dạng tiền"
                            :options="currencyFormatOptions"
                        />

                        <FloatingSelect
                            v-model="form.app_locale"
                            name="app_locale"
                            label="Ngôn ngữ hệ thống"
                            :options="localeOptions"
                        />
                    </div>
                </DataPanel>

                <DataPanel>
                    <template #header>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                Kho hàng
                            </h2>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">
                                Các quy tắc ảnh hưởng đến xuất bán và tồn kho.
                            </p>
                        </div>
                    </template>

                    <div class="p-4">
                        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <span>
                                <span class="block text-sm font-bold text-slate-800">
                                    Cho phép tồn kho âm
                                </span>
                                <span class="mt-0.5 block text-xs font-medium text-slate-500">
                                    Dùng khi muốn cho phép bán trước rồi nhập bù sau.
                                </span>
                            </span>

                            <input
                                v-model="form.allow_negative_stock"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            >
                        </label>
                    </div>
                </DataPanel>

                <DataPanel>
                    <template #header>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">
                                    Đơn vị tính
                                </h2>
                                <p class="mt-0.5 text-xs font-medium text-slate-500">
                                    Thêm, sửa, xóa các đơn vị dùng cho sản phẩm như Cái, Chiếc, Bộ.
                                </p>
                            </div>
                        </div>
                    </template>

                    <div class="space-y-4 p-4">
                        <div
                            v-if="unitForm.hasErrors"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"
                        >
                            <div
                                v-for="message in Object.values(unitForm.errors)"
                                :key="message"
                            >
                                {{ message }}
                            </div>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_160px_120px]">
                            <FloatingInput
                                v-model="unitForm.name"
                                name="unit_name"
                                label="Tên đơn vị"
                                placeholder="Ví dụ: Thùng"
                                :error="unitForm.errors.name"
                            />

                            <FloatingInput
                                v-model="unitForm.short_name"
                                name="unit_short_name"
                                label="Viết tắt"
                                placeholder="hộp"
                                :error="unitForm.errors.short_name"
                            />

                            <ActionButton
                                class="h-[42px]"
                                :variant="editingUnitId ? 'info' : 'primary'"
                                :disabled="unitForm.processing"
                                @click="submitUnit"
                            >
                                <Save v-if="editingUnitId" :size="16" />
                                <Plus v-else :size="16" />
                                {{ editingUnitId ? 'Cập nhật' : 'Thêm' }}
                            </ActionButton>
                        </div>

                        <div
                            v-if="editingUnitId"
                            class="flex justify-end"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-slate-800"
                                @click="resetUnitForm"
                            >
                                <X :size="14" />
                                Hủy sửa
                            </button>
                        </div>

                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 text-xs font-bold uppercase text-slate-500">
                                        <th class="px-3 py-2 text-left">
                                            Đơn vị
                                        </th>
                                        <th class="w-28 px-3 py-2 text-left">
                                            Viết tắt
                                        </th>
                                        <th class="w-28 px-3 py-2 text-center">
                                            Trạng thái
                                        </th>
                                        <th class="w-28 px-3 py-2 text-center">
                                            Thao tác
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="unit in units"
                                        :key="unit.id"
                                        class="hover:bg-slate-50"
                                    >
                                        <td class="px-3 py-2 font-semibold text-slate-800">
                                            {{ unitDisplayName(unit) }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ unit.short_name || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span
                                                class="rounded-full px-2 py-1 text-xs font-bold"
                                                :class="unit.is_active
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-500'"
                                            >
                                                {{ unit.is_active ? 'Đang dùng' : 'Tạm tắt' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center justify-center gap-1">
                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50"
                                                    title="Sửa"
                                                    @click="editUnit(unit)"
                                                >
                                                    <Pencil :size="16" />
                                                </button>

                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-amber-600 hover:bg-amber-50"
                                                    title="Bật/tắt"
                                                    @click="toggleUnitStatus(unit)"
                                                >
                                                    <Power :size="16" />
                                                </button>

                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-red-600 hover:bg-red-50"
                                                    title="Xóa"
                                                    @click="deleteUnit(unit)"
                                                >
                                                    <Trash2 :size="16" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="!units.length">
                                        <td
                                            colspan="4"
                                            class="px-3 py-8 text-center text-sm font-medium text-slate-400"
                                        >
                                            Chưa có đơn vị tính nào
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                            Ví dụ: Cái, Chiếc, Bộ, Hộp. Hệ thống chỉ dùng đơn vị để hiển thị, số lượng nhập bán được tính trực tiếp.
                        </div>
                    </div>
                </DataPanel>
            </div>

            <DataPanel>
                <template #header>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                Tài khoản chuyển khoản
                            </h2>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">
                                {{ bankLockedText }}
                            </p>
                        </div>

                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-bold"
                            :class="can_update_bank_settings
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-red-50 text-red-700'"
                        >
                            {{ can_update_bank_settings ? 'Có quyền sửa' : 'Chỉ xem' }}
                        </span>
                    </div>
                </template>

                <div class="space-y-4 p-4">
                    <FloatingInput
                        v-model="form.bank_bin"
                        name="bank_bin"
                        label="Mã ngân hàng VietQR / BIN"
                        placeholder="Ví dụ: 970422"
                        :disabled="!can_update_bank_settings"
                        :error="form.errors.bank_bin"
                    />

                    <FloatingInput
                        v-model="form.bank_account"
                        name="bank_account"
                        label="Số tài khoản"
                        :disabled="!can_update_bank_settings"
                        :error="form.errors.bank_account"
                    />

                    <FloatingInput
                        v-model="form.bank_account_name"
                        name="bank_account_name"
                        label="Tên chủ tài khoản"
                        :disabled="!can_update_bank_settings"
                        :error="form.errors.bank_account_name"
                    />

                    <FloatingInput
                        v-model="form.bank_transfer_content"
                        name="bank_transfer_content"
                        label="Nội dung chuyển khoản mặc định"
                        :disabled="!can_update_bank_settings"
                        :error="form.errors.bank_transfer_content"
                    />
                </div>
            </DataPanel>
        </div>
    </div>
</template>
