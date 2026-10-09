<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { Pencil, Plus, Power, Save, Trash2, X, Store, DatabaseBackup, ChevronRight, CreditCard, Boxes, Printer } from 'lucide-vue-next'
import BackupsIndex from '@/Pages/Backups/Index.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    settings: Object,
    can_update_bank_settings: Boolean,
    can_edit_settings: { type: Boolean, default: false },
    can_manage_payment_accounts: { type: Boolean, default: false },
    can_manage_inventory_settings: { type: Boolean, default: false },
    units: {
        type: Array,
        default: () => [],
    },
    can_manage_backups: Boolean,
    active_section: String,
    backups: { type: Array, default: () => [] },
    cloud_connections: { type: Array, default: () => [] },
    cloud_oauth: { type: Object, default: () => ({}) },
    schedule: { type: Object, default: () => ({}) },
    restore_preview: { type: Object, default: null },
})

const activeModal = ref(null)
watch(() => props.active_section, (section) => {
    if (section === 'backups' && props.can_manage_backups) activeModal.value = 'backups'
    else if (section === 'system' && props.can_edit_settings) activeModal.value = section
    else if (section === 'payments' && props.can_manage_payment_accounts) activeModal.value = section
    else if (section === 'inventory' && props.can_manage_inventory_settings) activeModal.value = section
    else if (section === 'printing' && props.can_edit_settings) activeModal.value = section
    else if (!section) activeModal.value = null
}, { immediate: true })

const openModal = (section) => {
    activeModal.value = section
    const url = `/settings?section=${section}`
    router.get(url, {}, { preserveState: true, preserveScroll: true, replace: true, only: ['active_section'] })
}

const closeModal = () => {
    activeModal.value = null
    router.get('/settings', {}, { preserveState: true, preserveScroll: true, replace: true, only: ['active_section'] })
}

const form = useForm({
    shop_name: props.settings.shop_name || '',
    currency_symbol: props.settings.currency_symbol || '₫',
    currency_format: props.settings.currency_format || 'vi-VN',
    app_locale: props.settings.app_locale || 'vi',
    app_timezone: props.settings.app_timezone || 'Asia/Ho_Chi_Minh',
    date_format: props.settings.date_format || 'd/m/Y',
    allow_negative_stock: props.settings.allow_negative_stock || false,
    print_paper_size: props.settings.print_paper_size || 'a4',
    print_orientation: props.settings.print_orientation || 'portrait',
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

const timezoneOptions = [
    { value: 'Asia/Ho_Chi_Minh', label: 'Việt Nam (UTC+7)' },
    { value: 'Asia/Bangkok', label: 'Bangkok (UTC+7)' },
    { value: 'UTC', label: 'UTC' },
]

const dateFormatOptions = [
    { value: 'd/m/Y', label: 'Ngày/Tháng/Năm' },
    { value: 'Y-m-d', label: 'Năm-Tháng-Ngày' },
    { value: 'm/d/Y', label: 'Tháng/Ngày/Năm' },
]

const printPaperOptions = [
    { value: 'a4', label: 'A4 (210 × 297 mm)' },
    { value: 'a5', label: 'A5 (148 × 210 mm)' },
    { value: '80mm', label: 'Máy in nhiệt 80 mm' },
    { value: '58mm', label: 'Máy in nhiệt 58 mm' },
]

const printOrientationOptions = [
    { value: 'portrait', label: 'Dọc' },
    { value: 'landscape', label: 'Ngang' },
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
        <PageHeader title="Thiết lập" description="Chọn nội dung bạn muốn cấu hình cho cửa hàng.">
            <template #actions>
                <span class="text-xs font-medium text-slate-500">Cài đặt được lưu riêng theo từng mục</span>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <button v-if="can_edit_settings" type="button" class="group grid min-h-[174px] grid-cols-3 grid-rows-[auto_1fr] items-start gap-x-3 gap-y-3 rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-blue-300 hover:shadow-md" @click="openModal('system')">
                <span class="col-span-3 flex min-w-0 items-center gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><Store :size="23" /></span><span class="min-w-0 flex-1 font-bold leading-6 text-slate-900">Thiết lập hệ thống</span><ChevronRight class="shrink-0 text-slate-400 transition group-hover:translate-x-1 group-hover:text-blue-600" :size="19" /></span>
                <span class="col-span-3 text-sm leading-5 text-slate-500">Tên cửa hàng, ngôn ngữ, tiền tệ, ngày giờ.</span>
            </button>
            <button v-if="can_manage_payment_accounts" type="button" class="group grid min-h-[174px] grid-cols-3 grid-rows-[auto_1fr] items-start gap-x-3 gap-y-3 rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-violet-300 hover:shadow-md" @click="openModal('payments')">
                <span class="col-span-3 flex min-w-0 items-center gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><CreditCard :size="23" /></span><span class="min-w-0 flex-1 font-bold leading-6 text-slate-900">Tài khoản thanh toán</span><ChevronRight class="shrink-0 text-slate-400 transition group-hover:translate-x-1 group-hover:text-violet-600" :size="19" /></span>
                <span class="col-span-3 text-sm leading-5 text-slate-500">Quản lý thông tin nhận chuyển khoản và VietQR.</span>
            </button>
            <button v-if="can_manage_inventory_settings" type="button" class="group grid min-h-[174px] grid-cols-3 grid-rows-[auto_1fr] items-start gap-x-3 gap-y-3 rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-amber-300 hover:shadow-md" @click="openModal('inventory')">
                <span class="col-span-3 flex min-w-0 items-center gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><Boxes :size="23" /></span><span class="min-w-0 flex-1 font-bold leading-6 text-slate-900">Hàng hóa và kho hàng</span><ChevronRight class="shrink-0 text-slate-400 transition group-hover:translate-x-1 group-hover:text-amber-600" :size="19" /></span>
                <span class="col-span-3 text-sm leading-5 text-slate-500">Quy tắc tồn kho và danh sách đơn vị tính.</span>
            </button>
            <button v-if="can_edit_settings" type="button" class="group grid min-h-[174px] grid-cols-3 grid-rows-[auto_1fr] items-start gap-x-3 gap-y-3 rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-sky-300 hover:shadow-md" @click="openModal('printing')">
                <span class="col-span-3 flex min-w-0 items-center gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700"><Printer :size="23" /></span><span class="min-w-0 flex-1 font-bold leading-6 text-slate-900">Bản in và khổ giấy</span><ChevronRight class="shrink-0 text-slate-400 transition group-hover:translate-x-1 group-hover:text-sky-600" :size="19" /></span>
                <span class="col-span-3 text-sm leading-5 text-slate-500">Cài khổ giấy và hướng in cho hóa đơn, phiếu sửa chữa.</span>
            </button>
            <button v-if="can_manage_backups" type="button" class="group grid min-h-[174px] grid-cols-3 grid-rows-[auto_1fr] items-start gap-x-3 gap-y-3 rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-emerald-300 hover:shadow-md" @click="openModal('backups')">
                <span class="col-span-3 flex min-w-0 items-center gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><DatabaseBackup :size="23" /></span><span class="min-w-0 flex-1 font-bold leading-6 text-slate-900">Sao lưu và khôi phục</span><ChevronRight class="shrink-0 text-slate-400 transition group-hover:translate-x-1 group-hover:text-emerald-600" :size="19" /></span>
                <span class="col-span-3 text-sm leading-5 text-slate-500">Tạo bản sao, đặt lịch tự động, kết nối đám mây và khôi phục dữ liệu.</span>
            </button>
        </div>

        <BaseModal
            v-if="(activeModal === 'system' && can_edit_settings) || (activeModal === 'payments' && can_manage_payment_accounts) || (activeModal === 'inventory' && can_manage_inventory_settings) || (activeModal === 'printing' && can_edit_settings)"
            :title="activeModal === 'system' ? 'Thiết lập hệ thống' : activeModal === 'payments' ? 'Tài khoản thanh toán' : activeModal === 'printing' ? 'Cài đặt bản in' : 'Hàng hóa và kho hàng'"
            size="lg"
            body-class="p-4 sm:p-5"
            @close="closeModal"
        >
        <p class="mb-4 text-sm text-slate-500">{{ activeModal === 'system' ? 'Cấu hình tên cửa hàng, ngôn ngữ, tiền tệ và ngày giờ.' : activeModal === 'payments' ? 'Cập nhật tài khoản nhận chuyển khoản và thông tin VietQR.' : activeModal === 'printing' ? 'Chọn khổ giấy và hướng in áp dụng cho hóa đơn, phiếu.' : 'Cấu hình quy tắc tồn kho và đơn vị tính.' }}</p>

        <div class="grid gap-5">
            <div class="space-y-5">
                <DataPanel v-if="activeModal === 'printing'">
                    <template #header>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Khổ giấy và hướng in</h2>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">Áp dụng thống nhất cho hóa đơn và phiếu in trong hệ thống.</p>
                        </div>
                    </template>
                    <div class="grid gap-4 p-4 sm:grid-cols-2">
                        <FloatingSelect v-model="form.print_paper_size" name="print_paper_size" label="Khổ giấy" :options="printPaperOptions" />
                        <FloatingSelect v-model="form.print_orientation" name="print_orientation" label="Hướng giấy" :options="printOrientationOptions" />
                    </div>
                </DataPanel>
                <DataPanel v-if="activeModal === 'system'">
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

                        <FloatingSelect v-model="form.app_timezone" name="app_timezone" label="Múi giờ hệ thống" :options="timezoneOptions" />
                        <FloatingSelect v-model="form.date_format" name="date_format" label="Định dạng ngày" :options="dateFormatOptions" />
                    </div>
                </DataPanel>

                <DataPanel v-if="activeModal === 'inventory'">
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

                <DataPanel v-if="activeModal === 'inventory'">
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

            <DataPanel v-if="activeModal === 'payments'">
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
        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton variant="secondary" @click="closeModal">Đóng</ActionButton>
                <ActionButton :disabled="form.processing" @click="save">
                    <Save :size="16" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu cài đặt' }}
                </ActionButton>
            </div>
        </template>
        </BaseModal>

        <BaseModal v-if="activeModal === 'backups' && can_manage_backups" title="Sao lưu và khôi phục" size="xl" body-class="bg-slate-50 p-4 sm:p-5" @close="closeModal">
            <BackupsIndex hide-header :backups="backups" :cloud_connections="cloud_connections" :cloud_oauth="cloud_oauth" :schedule="schedule" :restore_preview="restore_preview" />
        </BaseModal>
    </div>
</template>
