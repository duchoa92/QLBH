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
| Khách hàng & Tìm kiếm
|--------------------------------------------------------------------------
*/
const selectedCustomer = ref(null);
const searchQuery = ref('');

const onSelectCustomer = (customer) => {
    if (!customer) {
        clearCustomer();
        return;
    }
    selectedCustomer.value = customer;
    form.customer_id = customer.id || null;
    form.customer_name = customer.full_name || customer.customer_name || customer.name || '';
    form.customer_phone = customer.phone || customer.customer_phone || '';
    form.identity_card = customer.cccd || customer.identity_card || '';
    form.contact_phone = customer.contact_phone || form.customer_phone || '';
    searchQuery.value = '';
    toast.success(`Đã chọn khách hàng: ${form.customer_name}`);
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    searchQuery.value = '';
    form.customer_id = null;
    form.customer_name = '';
    form.customer_phone = '';
    form.contact_phone = '';
    form.identity_card = '';
};

/*
|--------------------------------------------------------------------------
| Trạng thái Bảo mật (Ẩn/Hiện)
|--------------------------------------------------------------------------
*/
const showSecurity = ref(false);

watch(showSecurity, (val) => {
    if (!val) {
        form.screen_password = '';
        form.screen_pattern = '';
        form.account_type = '';
        form.account_email = '';
        form.account_password = '';
    }
});

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
        title="Tiếp nhận máy sửa chữa"
        size="lg"
        @close="emit('close')"
    >
        <!-- BỐ CỤC ĐƯỢC ÉP CHIỀU RỘNG CHUẨN ĐỂ KHÔNG BỊ TRÀN -->
        <div class="max-w-xl mx-auto space-y-3 text-xs p-1">
            
            <!-- 1. TÌM KIẾM KHÁCH HÀNG -->
            <div class="space-y-1">
                <div 
                    v-if="selectedCustomer" 
                    class="flex items-center justify-between gap-2 px-3.5 py-2 bg-blue-50/70 border border-blue-200/90 rounded-xl transition"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <div class="truncate leading-tight flex items-center gap-1.5 flex-wrap">
                            <span class="font-bold text-slate-800 text-xs">{{ form.customer_name }}</span>
                            <span v-if="form.customer_phone" class="text-blue-600 text-xs font-medium">
                                - {{ form.customer_phone }}
                            </span>
                        </div>
                    </div>

                    <button 
                        type="button" 
                        @click="clearCustomer" 
                        class="text-blue-400 hover:text-rose-600 p-1 transition shrink-0"
                        title="Bỏ chọn khách hàng"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-else class="relative">
                    <CustomerAutocomplete
                        v-model="searchQuery"
                        @selected="onSelectCustomer"
                        placeholder="Tìm khách hàng theo tên, SĐT, mã hoặc CCCD"
                    />
                </div>

                <p v-if="form.errors.customer_name" class="text-rose-500 text-[11px] mt-1 font-medium">
                    {{ form.errors.customer_name }}
                </p>
            </div>

            <!-- 2. THÔNG TIN THIẾT BỊ & BẢO MẬT -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-3.5 py-2 border-b border-slate-100 bg-slate-50/70">
                    <h2 class="text-[11px] font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Thông tin thiết bị
                    </h2>
                </div>

                <div class="p-3 space-y-2.5">
                    <!-- Tên máy & IMEI -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
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
                            <div v-if="deviceFocused && matchingDevices.length" class="absolute left-0 right-0 top-full z-[100] mt-1 max-h-40 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                <button 
                                    v-for="device in matchingDevices" 
                                    :key="device" 
                                    type="button" 
                                    class="block w-full px-3 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition" 
                                    @mousedown.prevent="form.device_name = device; deviceFocused = false"
                                >
                                    {{ device }}
                                </button>
                            </div>
                        </div>

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
                                    <div v-if="imeiFocused && matchingImeis.length" class="absolute left-0 right-0 top-full z-[100] mt-1 max-h-40 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                        <button 
                                            v-for="imei in matchingImeis" 
                                            :key="imei" 
                                            type="button" 
                                            class="block w-full px-3 py-1.5 text-left font-mono text-xs text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition" 
                                            @mousedown.prevent="form.imei = imei; imeiFocused = false"
                                        >
                                            {{ imei }}
                                        </button>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="startScan"
                                    title="Quét IMEI từ Camera"
                                    class="px-3 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200 hover:bg-indigo-100 active:bg-indigo-200 transition flex items-center justify-center shrink-0"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h0.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </button>
                            </div>

                            <div v-if="scanning" class="mt-2 border rounded-xl overflow-hidden bg-slate-900 relative">
                                <video ref="videoRef" class="w-full h-32 object-cover" />
                                <button
                                    type="button"
                                    @click="stopScan"
                                    class="absolute top-2 right-2 bg-rose-600 text-white text-[10px] px-2 py-0.5 rounded-lg shadow hover:bg-rose-700 transition"
                                >
                                    Tắt camera
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TOGGLE BẢO MẬT -->
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer select-none p-2 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-100/70 transition">
                            <input 
                                type="checkbox" 
                                v-model="showSecurity" 
                                class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 cursor-pointer"
                            />
                            <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Khai báo mật khẩu / Khóa màn hình
                            </span>
                        </label>
                    </div>

                    <!-- KHU VỰC BẢO MẬT -->
                    <div v-if="showSecurity" class="space-y-2.5 pt-2 border-t border-slate-100">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <FloatingInput
                                v-model="form.screen_password"
                                label="Mật khẩu màn hình"
                                id="screen_password"
                            />

                            <FloatingSelect
                                v-model="form.account_type"
                                label="Tài khoản máy"
                                id="account_type"
                                :options="accountTypeOptions"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" v-if="form.account_type">
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

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                Mẫu hình khóa màn hình
                            </label>
                            <div class="flex justify-center rounded-xl border border-slate-200/80 bg-slate-50/50 p-2">
                                <PatternLock v-model="form.screen_pattern" />
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 3. TÌNH TRẠNG & YÊU CẦU -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-3.5 py-2 border-b border-slate-100 bg-slate-50/70">
                    <h2 class="text-[11px] font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Tình trạng & Yêu cầu
                    </h2>
                </div>

                <div class="p-3 space-y-2.5">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">
                            Lỗi máy ghi nhận
                        </label>
                        <div v-if="form.issue.length > 0" class="flex flex-wrap gap-1.5 mb-2">
                            <span
                                v-for="(item, index) in form.issue"
                                :key="index"
                                class="bg-rose-50 text-rose-700 border border-rose-200/80 px-2 py-0.5 rounded-lg flex items-center gap-1 text-[11px] font-medium"
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
                                label="Nhập lỗi máy (ấn Enter hoặc dấu ,)"
                                id="repair_issue_input"
                                autocomplete="off"
                                @keydown.enter.prevent="addIssue(issueInput)"
                            />
                            <div v-if="filteredIssues.length" class="absolute left-0 right-0 top-full z-[100] mt-1 max-h-36 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
                                <button
                                    v-for="item in filteredIssues"
                                    :key="item"
                                    type="button"
                                    class="block w-full px-3 py-1.5 text-left text-xs text-slate-700 hover:bg-rose-50 hover:text-rose-700 transition"
                                    @mousedown.prevent="selectIssue(item)"
                                >
                                    {{ item }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <FloatingInput
                            v-model="form.repair_request"
                            label="Yêu cầu sửa chữa"
                            id="repair_request"
                            :error="form.errors.repair_request"
                        />

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
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <FloatingInput
                            :modelValue="form.accessories.join(', ')"
                            @update:modelValue="
                                form.accessories = $event
                                    .split(',')
                                    .map((v) => v.trim())
                                    .filter(Boolean)
                            "
                            label="Phụ kiện kèm theo"
                            id="accessories"
                        />

                        <FloatingInput
                            v-model="form.note"
                            label="Ghi chú kỹ thuật"
                            id="note"
                            :error="form.errors.note"
                        />
                    </div>

                    <!-- ẢNH NGOẠI QUAN MÁY -->
                    <div class="pt-1">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="text-[11px] font-semibold text-slate-700">Ảnh ngoại quan máy</span>
                            <label class="cursor-pointer text-indigo-600 hover:text-indigo-700 text-xs font-semibold flex items-center gap-1">
                                <input type="file" multiple accept="image/*" class="hidden" @change="handleImages" />
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Thêm ảnh
                            </label>
                        </div>

                        <div v-if="imagePreviews.length > 0" class="grid grid-cols-6 gap-1.5">
                            <div
                                v-for="(image, index) in imagePreviews"
                                :key="index"
                                class="relative group aspect-square rounded-lg overflow-hidden border border-slate-200 bg-slate-100"
                            >
                                <img :src="image" class="w-full h-full object-cover" />
                                <button
                                    type="button"
                                    @click="removeImage(index)"
                                    class="absolute top-1 right-1 bg-rose-600 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition shadow"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. TÓM TẮT PHIẾU -->
            <div class="bg-slate-900 rounded-2xl shadow-md p-3 text-white space-y-1.5 border border-slate-800">
                <div class="border-b border-slate-800 pb-1 flex justify-between items-center">
                    <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-300">Tóm tắt tiếp nhận</h2>
                    <span class="bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[10px] px-2 py-0.5 rounded-full font-medium">Phiếu mới</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                    <div class="flex items-center justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400">Khách:</span>
                        <span class="font-semibold text-slate-100 truncate max-w-[120px]">{{ form.customer_name || '---' }}</span>
                    </div>

                    <div class="flex items-center justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400">SĐT:</span>
                        <span class="font-semibold text-slate-100">{{ form.customer_phone || '---' }}</span>
                    </div>

                    <div class="flex items-center justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400">Thiết bị:</span>
                        <span class="font-semibold text-slate-100 truncate max-w-[120px]">{{ form.device_name || '---' }}</span>
                    </div>

                    <div class="flex items-center justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400">Số lỗi:</span>
                        <span class="bg-rose-500/20 text-rose-300 border border-rose-500/30 px-2 py-0.5 rounded-full font-bold text-[10px]">
                            {{ form.issue.length }} lỗi
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- FOOTER ACTIONS -->
        <template #footer>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <ActionButton variant="secondary" @click="emit('close')">
                    Hủy bỏ
                </ActionButton>

                <ActionButton :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Đang tạo phiếu...' : 'Lưu phiếu sửa chữa' }}
                </ActionButton>
            </div>
        </template>
    </BaseModal>
</template>