<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import CustomerAutocomplete from '@/Components/CustomerAutocomplete.vue'
import PatternLock from '@/Components/PatternLock.vue'
import { toast } from 'vue-sonner'

const props = defineProps({ repair: { type: Object, required: true } })
const emit = defineEmits(['close', 'updated'])

const form = useForm({
    customer_id: props.repair.customer?.id || null,
    customer_name: props.repair.customer?.name || '',
    customer_phone: props.repair.customer?.phone || '',
    identity_card: props.repair.customer?.identity_card || '',
    contact_phone: props.repair.contact_phone || '',
    device_name: props.repair.device_name || '',
    imei: props.repair.imei || '',
    serial: props.repair.serial || '',
    screen_password: props.repair.screen_password || '',
    screen_pattern: props.repair.screen_pattern || '',
    account_type: props.repair.account_type || '',
    account_email: props.repair.account_email || '',
    account_password: props.repair.account_password || '',
    issue: props.repair.issue || [],
    accessories: props.repair.accessories || [],
    repair_request: props.repair.repair_request || '',
    note: props.repair.note || '',
    estimated_cost: props.repair.estimated_cost ?? '',
    images: [],
})
const issueText = ref(Array.isArray(form.issue) ? form.issue.join(', ') : '')
const accessoriesText = ref(Array.isArray(form.accessories) ? form.accessories.join(', ') : '')

const onSelectCustomer = (customer) => {
    if (!customer) {
        form.customer_id = null
        form.customer_phone = ''
        form.identity_card = ''
        return
    }
    form.customer_id = customer.id || null
    form.customer_name = customer.full_name || customer.name || ''
    form.customer_phone = customer.phone || ''
    form.identity_card = customer.cccd || customer.identity_card || ''
}

const submit = () => {
    form.issue = issueText.value.split(',').map((item) => item.trim()).filter(Boolean)
    form.accessories = accessoriesText.value.split(',').map((item) => item.trim()).filter(Boolean)
    // PHP does not reliably parse multipart PUT requests. Use Laravel's
    // method spoofing so uploaded images and the other fields arrive together.
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('repairs.update', props.repair.id), {
        forceFormData: true,
        onSuccess: () => {
            toast.success('Đã cập nhật phiếu sửa chữa')
            emit('updated')
            emit('close')
        },
    })
}
</script>

<template>
    <BaseModal :title="`Sửa phiếu ${repair.code}`" size="lg" @close="emit('close')">
        <form id="repair-edit-form" class="space-y-3" @submit.prevent="submit">
            <section class="space-y-2 rounded-xl border border-slate-200 p-3">
                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Khách hàng</h3>
                <CustomerAutocomplete v-model="form.customer_name" :initial-customer="repair.customer" @selected="onSelectCustomer" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <FloatingInput v-model="form.customer_name" label="Tên khách hàng" id="edit_repair_customer_name" :error="form.errors.customer_name" />
                    <FloatingInput v-model="form.customer_phone" label="Số điện thoại" id="edit_repair_customer_phone" :error="form.errors.customer_phone" />
                    <FloatingInput v-model="form.identity_card" label="CCCD / CMND" id="edit_repair_identity_card" :error="form.errors.identity_card" />
                    <FloatingInput v-model="form.contact_phone" label="SĐT liên hệ khác" id="edit_repair_contact_phone" :error="form.errors.contact_phone" />
                </div>
            </section>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FloatingInput v-model="form.device_name" label="Thiết bị" id="edit_repair_device" :error="form.errors.device_name" />
                <FloatingInput v-model="form.imei" label="IMEI / Serial" id="edit_repair_imei" :error="form.errors.imei" />
                <FloatingInput v-model="form.serial" label="Số Serial (nếu khác IMEI)" id="edit_repair_serial" :error="form.errors.serial" />
                <FloatingInput v-model="issueText" label="Triệu chứng (ngăn cách bằng dấu phẩy)" id="edit_repair_issue" :error="form.errors.issue" />
                <FloatingInput v-model="form.estimated_cost" type="number" min="0" step="1" label="Chi phí dự kiến (VNĐ)" id="edit_repair_estimated_cost" :error="form.errors.estimated_cost" />
                <FloatingInput v-model="form.screen_password" label="Mật khẩu màn hình" id="edit_repair_screen_password" :error="form.errors.screen_password" />
                <FloatingSelect v-model="form.account_type" label="Loại tài khoản" id="edit_repair_account_type" :options="[{value:'',label:'Không có tài khoản'},{value:'icloud',label:'iCloud'},{value:'google',label:'Google'},{value:'samsung',label:'Samsung Account'},{value:'xiaomi',label:'Mi Account'},{value:'other',label:'Khác'}]" :error="form.errors.account_type" />
                <FloatingInput v-model="form.account_email" label="Email / SĐT tài khoản" id="edit_repair_account_email" :error="form.errors.account_email" />
                <FloatingInput v-model="form.account_password" label="Mật khẩu tài khoản" id="edit_repair_account_password" :error="form.errors.account_password" />
            </div>
            <section class="rounded-xl border border-slate-200 p-3">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Mẫu hình mở khóa</h3>
                <PatternLock v-model="form.screen_pattern" />
            </section>
            <FloatingInput v-model="form.repair_request" label="Yêu cầu sửa chữa" id="edit_repair_request" :error="form.errors.repair_request" />
            <FloatingInput v-model="form.note" label="Ghi chú" id="edit_repair_note" :error="form.errors.note" />
            <FloatingInput v-model="accessoriesText" label="Phụ kiện kèm theo (ngăn cách bằng dấu phẩy)" id="edit_repair_accessories" :error="form.errors.accessories" />
            <label class="block rounded-lg border border-dashed border-slate-300 p-3 text-sm text-slate-600">
                Tải ảnh bổ sung
                <input class="mt-2 block w-full text-xs" type="file" accept="image/*" multiple @change="form.images = Array.from($event.target.files || [])" />
                <span v-if="form.images.length" class="mt-1 block text-xs text-slate-500">Đã chọn {{ form.images.length }} ảnh</span>
            </label>

        </form>
        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton variant="secondary" @click="emit('close')">Hủy</ActionButton>
                <ActionButton type="submit" form="repair-edit-form" :disabled="form.processing">{{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}</ActionButton>
            </div>
        </template>
    </BaseModal>
</template>
