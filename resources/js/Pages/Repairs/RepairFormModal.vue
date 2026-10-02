<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { BrowserMultiFormatReader } from '@zxing/browser';

import CustomerAutocomplete from '@/Components/CustomerAutocomplete.vue';
import PatternLock from '@/Components/PatternLock.vue';
import FloatingInput from '@/Components/UI/FloatingInput.vue';
import FloatingSelect from '@/Components/UI/FloatingSelect.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import ActionButton from '@/Components/UI/ActionButton.vue';
import { toast } from 'vue-sonner';

const emit = defineEmits(['close', 'updated']);

const form = useForm({
    customer_id: null,
    customer_name: '',
    customer_phone: '',
    contact_phone: '',
    identity_card: '',
    device_name: '',
    imei: '',
    screen_password: '',
    screen_pattern: '',
    account_type: '',
    account_email: '',
    account_password: '',
    issue: [],
    repair_request: '',
    estimated_cost: '',
    accessories: [],
    note: '',
    images: [],
});

/*
|--------------------------------------------------------------------------
| Chọn khách hàng hiện hữu (Tự động điền dữ liệu như trang bán hàng)
|--------------------------------------------------------------------------
*/
const onSelectCustomer = (customer) => {
    if (!customer) {
        form.customer_id = null;
        form.customer_phone = '';
        form.identity_card = '';
        return;
    }
    form.customer_id = customer.id || null;
    form.customer_name = customer.full_name || customer.customer_name || customer.name || '';
    form.customer_phone = customer.phone || customer.customer_phone || '';
    form.identity_card = customer.cccd || customer.identity_card || '';
    form.contact_phone = customer.contact_phone || '';
    toast.success(`Đã lấy thông tin khách hàng: ${form.customer_name}`);
};

/*
|--------------------------------------------------------------------------
| Preview & Quản lý ảnh
|--------------------------------------------------------------------------
*/
const imagePreviews = ref([]);

const handleImages = (event) => {
    const files = Array.from(event.target.files);
    if (!files.length) return;

    form.images = [...form.images, ...files];
    const newPreviews = files.map((file) => URL.createObjectURL(file));
    imagePreviews.value = [...imagePreviews.value, ...newPreviews];
};

const removeImage = (index) => {
    URL.revokeObjectURL(imagePreviews.value[index]);
    form.images.splice(index, 1);
    imagePreviews.value.splice(index, 1);
};

/*
|--------------------------------------------------------------------------
| Scan IMEI bằng Camera
|--------------------------------------------------------------------------
*/
const videoRef = ref(null);
const scanning = ref(false);
const codeReader = new BrowserMultiFormatReader();

const startScan = async () => {
    try {
        scanning.value = true;
        const devices = await BrowserMultiFormatReader.listVideoInputDevices();
        const selectedDeviceId = devices[0]?.deviceId;

        if (!selectedDeviceId) {
            toast.error('Không tìm thấy camera');
            scanning.value = false;
            return;
        }

        codeReader.decodeFromVideoDevice(
            selectedDeviceId,
            videoRef.value,
            (result) => {
                if (result) {
                    form.imei = result.getText();
                    stopScan();
                }
            }
        );
    } catch (err) {
        console.error(err);
        toast.error('Không thể mở camera');
        scanning.value = false;
    }
};

const stopScan = () => {
    scanning.value = false;
    codeReader.reset();
};

/*
|--------------------------------------------------------------------------
| Gợi ý & Xử lý tình trạng máy (Issue tags)
|--------------------------------------------------------------------------
*/
const issueInput = ref('');
const issueSuggestions = ref([]);
const deviceSuggestions = ref([]);
const imeiSuggestions = ref([]);
const deviceFocused = ref(false);
const imeiFocused = ref(false);
const matchingDevices = computed(() => {
    const query = form.device_name.trim().toLocaleLowerCase();
    return query ? deviceSuggestions.value.filter((name) => name.toLocaleLowerCase().includes(query)).slice(0, 8) : [];
});
const matchingImeis = computed(() => {
    const query = form.imei.trim().toLocaleLowerCase();
    return query ? imeiSuggestions.value.filter((imei) => imei.toLocaleLowerCase().includes(query)).slice(0, 8) : [];
});
const filteredIssues = ref([]);

const accountTypeOptions = [
    { value: '', label: '-- Chọn loại tài khoản --' },
    { value: 'icloud', label: 'iCloud' },
    { value: 'google', label: 'Google' },
    { value: 'samsung', label: 'Samsung Account' },
    { value: 'xiaomi', label: 'Mi Account' },
    { value: 'other', label: 'Khác' },
];

onMounted(async () => {
    try {
        const response = await axios.get(route('repairs.suggestions'));
        issueSuggestions.value = response.data.issues ?? [];
        deviceSuggestions.value = response.data.devices ?? [];
        imeiSuggestions.value = response.data.imeis ?? [];
    } catch (error) {
        console.error(error);
    }
});

watch(issueInput, (value) => {
    if (!value) {
        filteredIssues.value = [];
        return;
    }

    if (value.endsWith(',')) {
        addIssue(value.replace(',', ''));
        return;
    }

    filteredIssues.value = issueSuggestions.value.filter(
        (item) =>
            item.toLowerCase().includes(value.toLowerCase()) &&
            !form.issue.includes(item)
    );
});

const selectIssue = (item) => {
    if (!form.issue.includes(item)) {
        form.issue.push(item);
    }
    issueInput.value = '';
    filteredIssues.value = [];
};

const addIssue = (value) => {
    value = value.trim();
    if (value && !form.issue.includes(value)) {
        form.issue.push(value);
    }
    issueInput.value = '';
    filteredIssues.value = [];
};

/*
|--------------------------------------------------------------------------
| Submit form
|--------------------------------------------------------------------------
*/
const submit = () => {
    form.post(route('repairs.store'), {
        forceFormData: true,
        onSuccess: () => {
            toast.success('Đã tiếp nhận máy sửa chữa');
            emit('updated');
            emit('close');
        },
    });
};
</script>

<template>
    <BaseModal
        title="Nhận máy sửa chữa"
        size="xl"
        @close="emit('close')"
    >
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 text-xs">
            <!-- CỘT TRÁI (THÔNG TIN CHÍNH) -->
            <div class="xl:col-span-2 space-y-4">
                <!-- KHÁCH HÀNG (LẤY TỪ HỆ THỐNG GIỐNG TRANG BÁN HÀNG) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Khách hàng
                        </h2>
                        <span class="text-[11px] text-slate-500">Tìm khách hàng hiện hữu hoặc nhập mới</span>
                    </div>

                    <div class="p-3.5 space-y-3">
                        <!-- Tìm kiếm khách hàng giống POS -->
                        <div>
                            <CustomerAutocomplete
                                v-model="form.customer_name"
                                @selected="onSelectCustomer"
                                placeholder="Gõ tên, số điện thoại hoặc CCCD..."
                            />
                            <p class="mt-1 text-[10px] text-slate-400">Khách mới: nhập tên và số điện thoại bên dưới.</p>
                            <div v-if="form.errors.customer_name" class="text-rose-500 text-[11px] mt-1">
                                {{ form.errors.customer_name }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                            <FloatingInput
                                v-model="form.customer_phone"
                                label="Số điện thoại *"
                                id="customer_phone"
                                :error="form.errors.customer_phone"
                                required
                            />

                            <FloatingInput
                                v-model="form.identity_card"
                                label="Số CCCD / CMND"
                                id="identity_card"
                                :error="form.errors.identity_card"
                            />

                            <FloatingInput
                                v-model="form.contact_phone"
                                label="SĐT liên hệ khác"
                                id="contact_phone"
                                :error="form.errors.contact_phone"
                            />
                        </div>
                    </div>
                </div>

                <!-- THÔNG TIN THIẾT BỊ -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/80">
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Thông tin thiết bị & Mật khẩu
                        </h2>
                    </div>

                    <div class="p-3.5 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            <div class="relative">
                                <FloatingInput
                                    v-model="form.device_name"
                                    label="Tên máy / Model *"
                                    id="device_name"
                                    :error="form.errors.device_name"
                                    required
                                    autocomplete="off"
                                    @focus="deviceFocused = true"
                                    @blur="deviceFocused = false"
                                />
                                <div v-if="deviceFocused && matchingDevices.length" class="absolute z-40 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                    <button v-for="device in matchingDevices" :key="device" type="button" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50" @mousedown.prevent="form.device_name = device; deviceFocused = false">{{ device }}</button>
                                </div>
                            </div>

                            <!-- IMEI & Quét camera -->
                            <div>
                                <div class="flex gap-1.5">
                                    <div class="relative flex-1">
                                        <FloatingInput
                                            v-model="form.imei"
                                            label="Số IMEI / Serial"
                                            id="imei"
                                            :error="form.errors.imei"
                                            autocomplete="off"
                                            @focus="imeiFocused = true"
                                            @blur="imeiFocused = false"
                                        />
                                        <div v-if="imeiFocused && matchingImeis.length" class="absolute z-40 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                            <button v-for="imei in matchingImeis" :key="imei" type="button" class="block w-full px-3 py-2 text-left font-mono text-xs text-slate-700 hover:bg-blue-50" @mousedown.prevent="form.imei = imei; imeiFocused = false">{{ imei }}</button>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="startScan"
                                        title="Quét IMEI từ Camera"
                                        class="px-3 rounded-lg bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800 transition flex items-center justify-center shrink-0"
                                    >
                                        📷
                                    </button>
                                </div>

                                <div v-if="scanning" class="mt-2 border rounded-xl overflow-hidden bg-black relative">
                                    <video ref="videoRef" class="w-full h-36 object-cover" />
                                    <button
                                        type="button"
                                        @click="stopScan"
                                        class="absolute top-2 right-2 bg-rose-600 text-white text-[10px] px-2 py-0.5 rounded"
                                    >
                                        Tắt
                                    </button>
                                </div>
                            </div>

                            <FloatingInput
                                v-model="form.screen_password"
                                label="Mật khẩu màn hình (PIN/Pass)"
                                id="screen_password"
                            />

                            <FloatingSelect
                                v-model="form.account_type"
                                label="Loại tài khoản lưu máy"
                                id="account_type"
                                :options="accountTypeOptions"
                            />

                            <FloatingInput
                                v-model="form.account_email"
                                label="Email / SĐT tài khoản"
                                id="account_email"
                            />

                            <FloatingInput
                                v-model="form.account_password"
                                label="Mật khẩu tài khoản"
                                id="account_password"
                            />
                        </div>

                        <!-- Vẽ khóa mẫu hình -->
                        <div class="pt-1">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1.5">
                                Mẫu hình mở khóa màn hình
                            </label>
                            <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50/50 p-2.5">
                                <PatternLock v-model="form.screen_pattern" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TÌNH TRẠNG & BÁO GIÁ -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/80">
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Tình trạng & Chi phí
                        </h2>
                    </div>

                    <div class="p-3.5 space-y-3">
                        <!-- Tình trạng máy (Tag + Autocomplete) -->
                        <div>
                            <div class="space-y-2">
                                <div v-if="form.issue.length > 0" class="flex flex-wrap gap-1.5 mb-2">
                                    <span
                                        v-for="(item, index) in form.issue"
                                        :key="index"
                                        class="bg-rose-50 text-rose-700 border border-rose-200 px-2.5 py-0.5 rounded-md flex items-center gap-1 text-xs font-medium"
                                    >
                                        {{ item }}
                                        <button
                                            type="button"
                                            class="hover:text-rose-900 font-bold ml-0.5"
                                            @click="form.issue.splice(index, 1)"
                                        >
                                            ×
                                        </button>
                                    </span>
                                </div>

                                <div class="relative">
                                    <FloatingInput
                                        v-model="issueInput"
                                        label="Nhập tình trạng máy rồi nhấn dấy , hoặc Enter"
                                        id="repair_issue_input"
                                        autocomplete="off"
                                        @keydown.enter.prevent="addIssue(issueInput)"
                                    />
                                    <div v-if="filteredIssues.length" class="absolute z-50 mt-1 max-h-40 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                                        <button
                                            v-for="item in filteredIssues"
                                            :key="item"
                                            type="button"
                                            class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                                            @mousedown.prevent="selectIssue(item)"
                                        >
                                            {{ item }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <FloatingInput
                            v-model="form.repair_request"
                            label="Yêu cầu sửa chữa cụ thể"
                            id="repair_request"
                            :error="form.errors.repair_request"
                        />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            <FloatingInput
                                v-model="form.estimated_cost"
                                type="number"
                                min="0"
                                step="1"
                                inputmode="numeric"
                                label="Chi phí dự kiến (VNĐ)"
                                id="estimated_cost"
                                :error="form.errors.estimated_cost"
                            />

                            <FloatingInput
                                :modelValue="form.accessories.join(', ')"
                                @update:modelValue="
                                    form.accessories = $event
                                        .split(',')
                                        .map((v) => v.trim())
                                        .filter(Boolean)
                                "
                                label="Phụ kiện kèm (Sạc, Ốp, SIM...)"
                                id="accessories"
                            />
                        </div>

                        <FloatingInput
                            v-model="form.note"
                            label="Ghi chú kỹ thuật"
                            id="note"
                            :error="form.errors.note"
                        />
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI (ẢNH NGOẠI QUAN & TÓM TẮT PHIẾU) -->
            <div class="space-y-4">
                <!-- TẢI ẢNH -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/80">
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Ảnh ngoại quan
                        </h2>
                    </div>

                    <div class="p-3.5 space-y-3">
                        <label class="flex flex-col items-center justify-center border-2 border-dashed border-slate-300 hover:border-blue-500 transition rounded-xl p-4 cursor-pointer bg-slate-50/50 hover:bg-blue-50/30">
                            <input
                                type="file"
                                multiple
                                accept="image/*"
                                class="hidden"
                                @change="handleImages"
                            >
                            <div class="text-2xl mb-1">📷</div>
                            <div class="text-xs font-semibold text-slate-700">Tải ảnh tình trạng</div>
                            <div class="text-[10px] text-slate-400">Chọn nhiều ảnh để tải lên</div>
                        </label>

                        <!-- Lưới hiển thị ảnh preview -->
                        <div v-if="imagePreviews.length > 0" class="grid grid-cols-2 gap-2">
                            <div
                                v-for="(image, index) in imagePreviews"
                                :key="index"
                                class="relative group aspect-square rounded-lg overflow-hidden border border-slate-200 bg-slate-100"
                            >
                                <img :src="image" class="w-full h-full object-cover" />
                                <button
                                    type="button"
                                    @click="removeImage(index)"
                                    class="absolute top-1 right-1 bg-rose-600 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition shadow"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TÓM TẮT PHIẾU -->
                <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl shadow-md p-4 text-white space-y-3">
                    <div class="border-b border-white/10 pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Xác nhận thông tin</h2>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between border-b border-white/10 pb-1.5">
                            <span class="text-slate-400">Khách hàng</span>
                            <span class="font-semibold text-white truncate max-w-[130px]">{{ form.customer_name || '---' }}</span>
                        </div>

                        <div class="flex items-center justify-between border-b border-white/10 pb-1.5">
                            <span class="text-slate-400">SĐT</span>
                            <span class="font-semibold text-white">{{ form.customer_phone || '---' }}</span>
                        </div>

                        <div class="flex items-center justify-between border-b border-white/10 pb-1.5">
                            <span class="text-slate-400">Thiết bị</span>
                            <span class="font-semibold text-white truncate max-w-[130px]">{{ form.device_name || '---' }}</span>
                        </div>

                        <div class="flex items-center justify-between border-b border-white/10 pb-1.5">
                            <span class="text-slate-400">IMEI</span>
                            <span class="font-mono text-white">{{ form.imei || '---' }}</span>
                        </div>

                        <div class="flex items-center justify-between pt-0.5">
                            <span class="text-slate-400">Số lỗi ghi nhận</span>
                            <span class="bg-blue-500/30 text-blue-300 border border-blue-400/30 px-2 py-0.5 rounded font-bold">
                                {{ form.issue.length }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2 pt-1">
                <ActionButton
                    variant="secondary"
                    @click="emit('close')"
                >
                    Hủy bỏ
                </ActionButton>

                <ActionButton
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Đang lưu phiếu...' : 'Lưu phiếu sửa chữa' }}
                </ActionButton>
            </div>
        </template>
    </BaseModal>
</template>
