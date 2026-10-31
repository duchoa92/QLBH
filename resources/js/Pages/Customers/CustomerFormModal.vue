<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import AdministrativeUnitAutocomplete from '@/Components/UI/AdministrativeUnitAutocomplete.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import api from '@/Services/api'
import CustomerIdCardScanner from '@/Components/CustomerIdCardScanner.vue'

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        default: 'Khách hàng',
    },
    // Khi dùng ở những nơi không thể điều hướng cả trang (ví dụ màn POS):
    // lưu qua API JSON thay vì Inertia form.post, rồi trả khách hàng vừa
    // tạo về qua sự kiện "updated" để nơi gọi tự chọn luôn khách đó.
    apiMode: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'updated'])
const provinces = ref([])
const wards = ref([])
const selectedProvinceCode = ref('')
const provincesLoading = ref(false)
const wardsLoading = ref(false)
const addressListMessage = ref('')
const phoneCheckLoading = ref(false)
const phoneCheckError = ref('')
const phoneDuplicates = ref([])
const phoneDuplicateCount = ref(0)
const phoneDuplicateModalOpen = ref(false)
const phoneCheckedKey = ref('')
const allowDuplicatePhone = ref('')
const cccdCheckLoading = ref(false)
const cccdCheckError = ref('')
const cccdDuplicates = ref([])
const cccdDuplicateCount = ref(0)
const cccdDuplicateModalOpen = ref(false)
const cccdCheckedKey = ref('')
let wardRequest = 0
let phoneCheckTimer = null
let phoneCheckRequest = 0
let phoneCheckPromise = null
let cccdCheckTimer = null
let cccdCheckRequest = 0
let cccdCheckPromise = null

const form = useForm({
    full_name: props.customer?.full_name || '',
    phone: props.customer?.phone || '',
    birthday: props.customer?.birthday || '',
    gender: props.customer?.gender || '',
    cccd: props.customer?.cccd || '',
    province: props.customer?.province || '',
    district: props.customer?.district || '',
    ward: props.customer?.ward || '',
    address: props.customer?.address || '',
    note: props.customer?.note || '',
})

const genderOptions = [
    { label: 'Chưa chọn', value: '' },
    { label: 'Nam', value: 'male' },
    { label: 'Nữ', value: 'female' },
    { label: 'Khác', value: 'other' },
]

const normalizeUnit = value => String(value || '').toLocaleLowerCase('vi').replace(/^(tỉnh|thành phố|tp\.?|thị xã|quận|huyện|phường|xã)\s+/i, '').trim()
const normalizePhone = value => String(value || '').replace(/\D/g, '')
    .replace(/^84(?=\d{9,10}$)/, '0')
const normalizeCccd = value => String(value || '').replace(/\D/g, '')
const selectedProvince = computed(() => provinces.value.find(item => item.code === selectedProvinceCode.value))
const duplicateCustomerNames = computed(() => phoneDuplicates.value.map(customer => customer.full_name).filter(Boolean).join(', '))
const duplicateCccdNames = computed(() => cccdDuplicates.value.map(customer => customer.full_name).filter(Boolean).join(', '))

const lookupPhone = async phone => {
    const request = ++phoneCheckRequest
    const key = normalizePhone(phone)
    phoneCheckLoading.value = true
    phoneCheckError.value = ''
    phoneCheckedKey.value = ''
    try {
        const { data } = await api.get('/api/customers/check-phone', {
            params: { phone: String(phone || '').trim() },
        })
        if (request !== phoneCheckRequest || key !== normalizePhone(form.phone)) return { stale: true }
        phoneDuplicates.value = data.customers || []
        phoneDuplicateCount.value = Number(data.total || phoneDuplicates.value.length)
        phoneDuplicateModalOpen.value = phoneDuplicates.value.length > 0
        phoneCheckedKey.value = key
        return { customers: phoneDuplicates.value }
    } catch (error) {
        if (request !== phoneCheckRequest) return { stale: true }
        phoneCheckError.value = error.response?.data?.message || 'Chưa kiểm tra được số điện thoại. Hãy thử lại.'
        return { error: true }
    } finally {
        if (request === phoneCheckRequest) phoneCheckLoading.value = false
    }
}

watch(() => form.phone, value => {
    if (props.customer?.id) return
    if (phoneCheckTimer) clearTimeout(phoneCheckTimer)
    phoneCheckTimer = null
    phoneCheckRequest++
    phoneCheckLoading.value = false
    phoneCheckError.value = ''
    phoneDuplicates.value = []
    phoneDuplicateCount.value = 0
    phoneDuplicateModalOpen.value = false
    phoneCheckedKey.value = ''
    allowDuplicatePhone.value = ''

    const digits = normalizePhone(value)
    if (digits.length < 7) return

    phoneCheckLoading.value = true
    phoneCheckTimer = setTimeout(() => {
        phoneCheckPromise = lookupPhone(String(value || '').trim())
    }, 450)
})

const checkPhoneBeforeSubmit = async () => {
    if (props.customer?.id) return true
    const phone = String(form.phone || '').trim()
    const key = normalizePhone(phone)
    if (key.length < 7) return true

    if (phoneCheckTimer) {
        clearTimeout(phoneCheckTimer)
        phoneCheckTimer = null
    }
    if (phoneCheckedKey.value !== key) {
        phoneCheckPromise = lookupPhone(phone)
    }
    const result = await phoneCheckPromise
    if (result?.error || result?.stale) return false
    if (phoneDuplicates.value.length && allowDuplicatePhone.value !== key) {
        phoneDuplicateModalOpen.value = true
        return false
    }
    return true
}

const closePhoneDuplicateModal = () => {
    phoneDuplicateModalOpen.value = false
}

const editPhone = () => {
    closePhoneDuplicateModal()
    focusPhone()
}

const createSeparateCustomer = () => {
    allowDuplicatePhone.value = normalizePhone(form.phone)
    closePhoneDuplicateModal()
}

const focusPhone = async () => {
    await nextTick()
    const input = document.getElementById('customer-phone')
    input?.focus()
    input?.select()
}

const retryPhoneCheck = () => {
    if (phoneCheckTimer) clearTimeout(phoneCheckTimer)
    phoneCheckTimer = null
    phoneCheckPromise = lookupPhone(String(form.phone || '').trim())
}

const lookupCccd = async value => {
    const request = ++cccdCheckRequest
    const key = normalizeCccd(value)
    cccdCheckLoading.value = true
    cccdCheckError.value = ''
    cccdCheckedKey.value = ''
    try {
        const { data } = await api.get('/api/customers/check-cccd', {
            params: { cccd: key, exclude_id: props.customer?.id },
        })
        if (request !== cccdCheckRequest || key !== normalizeCccd(form.cccd)) return { stale: true }
        cccdDuplicates.value = data.customers || []
        cccdDuplicateCount.value = Number(data.total || cccdDuplicates.value.length)
        cccdDuplicateModalOpen.value = cccdDuplicates.value.length > 0
        cccdCheckedKey.value = key
        return { customers: cccdDuplicates.value }
    } catch (error) {
        if (request !== cccdCheckRequest) return { stale: true }
        cccdCheckError.value = error.response?.data?.message || 'Chưa kiểm tra được CCCD. Hãy thử lại.'
        return { error: true }
    } finally {
        if (request === cccdCheckRequest) cccdCheckLoading.value = false
    }
}

watch(() => form.cccd, value => {
    if (cccdCheckTimer) clearTimeout(cccdCheckTimer)
    cccdCheckTimer = null
    cccdCheckRequest++
    cccdCheckLoading.value = false
    cccdCheckError.value = ''
    cccdDuplicates.value = []
    cccdDuplicateCount.value = 0
    cccdDuplicateModalOpen.value = false
    cccdCheckedKey.value = ''

    const key = normalizeCccd(value)
    if (![9, 12].includes(key.length)) return

    cccdCheckLoading.value = true
    cccdCheckTimer = setTimeout(() => {
        cccdCheckPromise = lookupCccd(key)
    }, 450)
})

const checkCccdBeforeSubmit = async () => {
    const key = normalizeCccd(form.cccd)
    if (![9, 12].includes(key.length)) return true

    if (cccdCheckTimer) {
        clearTimeout(cccdCheckTimer)
        cccdCheckTimer = null
    }
    if (cccdCheckedKey.value !== key) cccdCheckPromise = lookupCccd(key)
    const result = await cccdCheckPromise
    if (result?.error || result?.stale) return false
    if (cccdDuplicates.value.length) {
        cccdDuplicateModalOpen.value = true
        return false
    }
    return true
}

const retryCccdCheck = () => {
    if (cccdCheckTimer) clearTimeout(cccdCheckTimer)
    cccdCheckTimer = null
    cccdCheckPromise = lookupCccd(form.cccd)
}

const editCccd = async () => {
    cccdDuplicateModalOpen.value = false
    await nextTick()
    const input = document.getElementById('customer-cccd')
    input?.focus()
    input?.select()
}

const loadWards = async (provinceCode, keepWard = true) => {
    const request = ++wardRequest
    wards.value = []
    if (!provinceCode) return
    wardsLoading.value = true
    try {
        const { data } = await api.get(`/api/administrative-units/provinces/${provinceCode}/wards`)
        if (request !== wardRequest) return
        wards.value = data.data || []
        if (!keepWard && form.ward && !wards.value.some(item => normalizeUnit(item.name) === normalizeUnit(form.ward))) form.ward = ''
    } catch (error) {
        if (request === wardRequest) addressListMessage.value = error.response?.data?.message || 'Không tải được danh sách phường/xã.'
    } finally {
        if (request === wardRequest) wardsLoading.value = false
    }
}

const handleProvinceTyping = value => {
    if (selectedProvince.value && normalizeUnit(value) !== normalizeUnit(selectedProvince.value.name)) {
        selectedProvinceCode.value = ''
        wardRequest++
        wards.value = []
        wardsLoading.value = false
        form.district = ''
        form.ward = ''
    }
}

const selectProvince = async province => {
    selectedProvinceCode.value = province.code
    form.province = province.name
    form.district = ''
    form.ward = ''
    await loadWards(province.code, false)
    await nextTick()
    document.getElementById('customer-ward')?.focus()
}

const focusAddress = async () => {
    await nextTick()
    document.getElementById('customer-address-detail')?.focus()
}

const applyIdCard = async details => {
    for (const key of ['full_name', 'birthday', 'gender', 'cccd', 'address', 'province', 'district', 'ward']) {
        if (details[key]) form[key] = details[key]
    }
    if (details.street_address) form.address = details.street_address
    const province = provinces.value.find(item => normalizeUnit(item.name) === normalizeUnit(form.province))
    if (province) {
        selectedProvinceCode.value = province.code
        form.province = province.name
        await loadWards(province.code)
    } else {
        selectedProvinceCode.value = ''
    }
}
onMounted(async () => {
    provincesLoading.value = true
    try {
        const { data } = await api.get('/api/administrative-units/provinces')
        provinces.value = data.data || []
        const province = provinces.value.find(item => normalizeUnit(item.name) === normalizeUnit(form.province))
        if (province) {
            selectedProvinceCode.value = province.code
                form.province = province.name
            await loadWards(province.code)
        }
    } catch (error) {
        addressListMessage.value = error.response?.data?.message || 'Không tải được danh sách tỉnh/thành.'
    } finally {
        provincesLoading.value = false
    }
})

const submitViaApi = async () => {
    form.processing = true
    form.clearErrors()

    try {
        const { data } = await api.post('/api/customers', form.data())

        // Trả khách hàng vừa tạo về cho nơi gọi (ví dụ POS chọn luôn
        // khách này vào đơn), tái dùng đúng cơ chế "updated" đã có sẵn.
        emit('updated', data.data)
        emit('close')

    } catch (error) {

        if (error.response?.status === 422) {

            const errors = error.response.data.errors || {}

            form.errors = Object.fromEntries(
                Object.entries(errors).map(
                    ([field, messages]) => [field, messages[0]]
                )
            )

        } else {

            console.error(error)
        }

    } finally {

        form.processing = false
    }
}

const submit = async () => {
    if (!await checkPhoneBeforeSubmit()) return
    if (!await checkCccdBeforeSubmit()) return

    if (props.apiMode && !props.customer?.id) {
        submitViaApi()
        return
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated')
            emit('close')
        },
    }

    if (props.customer?.id) {
        form.put(route('customers.update', props.customer.id), options)
        return
    }

    form.post(route('customers.store'), options)
}
</script>

<template>
    <BaseModal
        :title="title"
        size="xl"
        @close="emit('close')"
    >
        <div class="grid gap-4 md:grid-cols-2">
            <FloatingInput
                v-model="form.full_name"
                label="Họ tên"
                :error="form.errors.full_name"
            />

            <FloatingInput
                v-model="form.phone"
                id="customer-phone"
                label="Số điện thoại"
                :error="form.errors.phone"
            />

            <div v-if="!props.customer?.id && phoneCheckLoading" class="-mt-3 text-xs text-slate-500 md:col-span-2">Đang kiểm tra số điện thoại…</div>
            <div v-else-if="!props.customer?.id && phoneCheckError" class="-mt-3 text-xs text-rose-700 md:col-span-2">
                {{ phoneCheckError }}
                <button type="button" class="ml-1 font-semibold underline" @click="retryPhoneCheck">Thử lại</button>
            </div>
            <div v-if="!props.customer?.id && phoneDuplicates.length && !phoneDuplicateModalOpen" class="-mt-3 text-xs md:col-span-2" :class="allowDuplicatePhone === normalizePhone(form.phone) ? 'text-emerald-800' : 'text-amber-800'">
                <span>{{ allowDuplicatePhone === normalizePhone(form.phone) ? 'Đã chọn tạo thông tin mới với SĐT này.' : 'SĐT đã tồn tại trong hệ thống.' }}</span>
                <button type="button" class="ml-1 font-semibold underline" @click="phoneDuplicateModalOpen = true">{{ allowDuplicatePhone === normalizePhone(form.phone) ? 'Thay đổi lựa chọn' : 'Chọn cách xử lý' }}</button>
            </div>

            <FloatingInput
                v-model="form.birthday"
                type="date"
                label="Ngày sinh"
                :error="form.errors.birthday"
            />

            <FloatingSelect
                v-model="form.gender"
                :options="genderOptions"
                option-label="label"
                option-value="value"
                label="Giới tính"
                :error="form.errors.gender"
            />

            <div class="relative">
                <FloatingInput v-model="form.cccd" id="customer-cccd" label="CCCD" placeholder-text="Quét QR CCCD để tự điền thông tin" :error="form.errors.cccd" input-class="pr-12" />
                <CustomerIdCardScanner class="absolute right-1 top-1/2 -translate-y-1/2 !h-8 !w-8" @read="applyIdCard" />
            </div>
            <div v-if="cccdCheckLoading" class="-mt-3 text-xs text-slate-500 md:col-span-2">Đang kiểm tra CCCD…</div>
            <div v-else-if="cccdCheckError" class="-mt-3 text-xs text-rose-700 md:col-span-2">
                {{ cccdCheckError }}
                <button type="button" class="ml-1 font-semibold underline" @click="retryCccdCheck">Thử lại</button>
            </div>

            <AdministrativeUnitAutocomplete
                    v-model="form.province"
                    id="customer-province"
                    :options="provinces"
                    label="Tỉnh / Thành"
                    :placeholder="provincesLoading ? 'Đang tải danh sách...' : 'Gõ để tìm tỉnh/thành'"
                    :loading="provincesLoading"
                    :error="form.errors.province"
                    @input="handleProvinceTyping"
                    @select="selectProvince"
            />

            <AdministrativeUnitAutocomplete
                v-model="form.ward"
                id="customer-ward"
                :options="wards"
                label="Phường / Xã"
                :placeholder="wardsLoading ? 'Đang tải danh sách...' : 'Gõ để tìm phường/xã'"
                :loading="wardsLoading"
                :error="form.errors.ward"
                @select="focusAddress"
            />

            <p v-if="addressListMessage" class="md:col-span-2 -mt-2 text-xs text-amber-700">{{ addressListMessage }} Bạn vẫn có thể nhập địa chỉ thủ công.</p>

            <FloatingInput
                v-model="form.address"
                id="customer-address-detail"
                class="md:col-span-2"
                label="Địa chỉ chi tiết"
                :error="form.errors.address"
            />

            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">
                    Ghi chú
                </label>
                <textarea
                    v-model="form.note"
                    rows="3"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                />
                <p
                    v-if="form.errors.note"
                    class="mt-1 text-sm text-red-500"
                >
                    {{ form.errors.note }}
                </p>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton
                    variant="secondary"
                    @click="emit('close')"
                >
                    Hủy
                </ActionButton>

                <ActionButton
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
                </ActionButton>
            </div>
        </template>
    </BaseModal>

    <Teleport to="body">
        <div v-if="!props.customer?.id && phoneDuplicateModalOpen" class="fixed inset-0 z-[1500] flex items-center justify-center bg-slate-950/55 p-3" @click.self="closePhoneDuplicateModal">
            <section class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="duplicate-customer-phone-title" @keydown.esc.stop.prevent="closePhoneDuplicateModal" tabindex="-1">
                <header class="flex items-start justify-between gap-3 border-b border-amber-100 bg-amber-50 px-4 py-3">
                    <div>
                        <h2 id="duplicate-customer-phone-title" class="text-sm font-bold text-amber-950">Số điện thoại đã tồn tại trong hệ thống</h2>
                    </div>
                    <button type="button" class="rounded-lg px-2 py-1 text-lg leading-none text-amber-700 hover:bg-amber-100" aria-label="Đóng" @click="closePhoneDuplicateModal">×</button>
                </header>
                <div class="p-4">
                    <p class="text-sm leading-relaxed text-slate-700">SĐT <strong>{{ form.phone }}</strong> đã tồn tại trong hệ thống với tên: <strong>{{ duplicateCustomerNames || 'Chưa có tên' }}</strong><template v-if="phoneDuplicateCount > phoneDuplicates.length"> và {{ phoneDuplicateCount - phoneDuplicates.length }} hồ sơ khác</template>.</p>
                </div>
                <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3">
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100" @click="editPhone">Đổi SĐT khác</button>
                    <button type="button" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700" @click="createSeparateCustomer">Tạo hồ sơ mới</button>
                </footer>
            </section>
        </div>
    </Teleport>

    <Teleport to="body">
        <div v-if="cccdDuplicateModalOpen" class="fixed inset-0 z-[1600] flex items-center justify-center bg-slate-950/60 p-3" @click.self="cccdDuplicateModalOpen = false">
            <section class="w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="duplicate-customer-cccd-title" @keydown.esc.stop.prevent="cccdDuplicateModalOpen = false" tabindex="-1">
                <header class="flex items-start justify-between gap-3 border-b border-rose-100 bg-rose-50 px-4 py-3">
                    <h2 id="duplicate-customer-cccd-title" class="text-sm font-bold text-rose-900">Không thể sử dụng số CCCD</h2>
                    <button type="button" class="-mr-1 -mt-1 rounded-lg px-2 py-1 text-lg leading-none text-rose-700 hover:bg-rose-100" aria-label="Đóng thông báo CCCD" @click="cccdDuplicateModalOpen = false">×</button>
                </header>
                <div class="space-y-2 p-4">
                    <p class="text-sm leading-relaxed text-slate-700">Số CCCD: <strong>{{ form.cccd }}</strong>, đã tồn tại với tên: <strong>{{ duplicateCccdNames || 'Chưa có tên' }}</strong><template v-if="cccdDuplicateCount > cccdDuplicates.length"> và {{ cccdDuplicateCount - cccdDuplicates.length }} hồ sơ khác</template>.</p>
                </div>
                <footer class="flex justify-end border-t border-slate-100 bg-slate-50 px-4 py-3">
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100" @click="editCccd">Sửa số CCCD</button>
                </footer>
            </section>
        </div>
    </Teleport>
</template>
