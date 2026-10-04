<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
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
    intake_type: 'repair',
    warranty_source_type: '',
    warranty_source_id: null,
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
const customerDeviceSuggestions = ref([]);
const customerDeviceLoading = ref(false);
const selectedDevice = ref(null);
const isNewDevice = ref(false);
const selectedWarranty = ref(null);
let customerDeviceRequest = 0;

const displayWarrantyDate = (value) => value
    ? new Date(value).toLocaleDateString('vi-VN')
    : 'Không có hạn';

watch(searchQuery, (value) => {
    if (!selectedCustomer.value) form.customer_name = value.trim();
});

const loadCustomerDeviceSuggestions = async (customerId) => {
    const requestId = ++customerDeviceRequest;
    customerDeviceSuggestions.value = [];
    if (!customerId) {
        customerDeviceLoading.value = false;
        return;
    }
    customerDeviceLoading.value = true;
    try {
        const { data } = await axios.get(route('repairs.customer-devices', customerId));
        if (requestId === customerDeviceRequest) customerDeviceSuggestions.value = data.devices || [];
    } catch {
        if (requestId === customerDeviceRequest) customerDeviceSuggestions.value = [];
    } finally {
        if (requestId === customerDeviceRequest) customerDeviceLoading.value = false;
    }
};

const onSelectCustomer = (customer) => {
    if (!customer) {
        selectedCustomer.value = null;
        form.customer_id = null;
        clearSelectedDevice();
        loadCustomerDeviceSuggestions(null);
        return;
    }
    selectedCustomer.value = customer;
    form.customer_id = customer.id || null;
    form.customer_name = customer.full_name || customer.customer_name || customer.name || '';
    form.customer_phone = customer.phone || customer.customer_phone || '';
    form.identity_card = customer.cccd || customer.identity_card || '';
    form.contact_phone = customer.contact_phone || form.customer_phone || '';
    clearSelectedDevice();
    searchQuery.value = '';
    loadCustomerDeviceSuggestions(customer.id);
};

const clearCustomer = () => {
    clearSelectedDevice();
    selectedCustomer.value = null;
    searchQuery.value = '';
    form.customer_id = null;
    form.customer_name = '';
    form.customer_phone = '';
    form.contact_phone = '';
    form.identity_card = '';
    loadCustomerDeviceSuggestions(null);
};

/*
|--------------------------------------------------------------------------
| Trạng thái Bảo mật (Ẩn/Hiện)
|--------------------------------------------------------------------------
*/
const lockType = ref('');
const lockTypeOptions = [
    { value: '', label: '-- Chọn kiểu khóa --' },
    { value: 'pin', label: 'Mã PIN' },
    { value: 'password', label: 'Mật khẩu' },
    { value: 'pattern', label: 'Mẫu hình' },
];

watch(lockType, (value) => {
    if (applyingHistoryDevice.value) return;
    if (value !== 'pattern') form.screen_pattern = '';
    if (!['pin', 'password'].includes(value)) form.screen_password = '';
});
watch(() => form.account_type, (value) => {
    if (!value && !applyingHistoryDevice.value) {
        form.account_email = '';
        form.account_password = '';
    }
});

const applyingHistoryDevice = ref(false);
const selectCustomerDevice = async (device) => {
    applyingHistoryDevice.value = true;
    selectedDevice.value = { name: device.device_name || '', imei: device.imei || device.serial || '', source: device.source };
    isNewDevice.value = false;
    deviceSearchQuery.value = '';
    form.device_name = device.device_name || '';
    form.imei = device.imei || device.serial || '';
    form.screen_password = device.screen_password || '';
    form.screen_pattern = device.screen_pattern || '';
    form.account_type = device.account_type || '';
    form.account_email = device.account_email || '';
    form.account_password = device.account_password || '';
    form.issue = Array.isArray(device.issue) ? [...device.issue] : (device.issue ? [device.issue] : []);
    form.repair_request = device.repair_request || '';
    form.accessories = Array.isArray(device.accessories) ? [...device.accessories] : [];
    form.estimated_cost = device.estimated_cost || '';
    form.note = device.note || '';
    const eligibleWarranty = device.warranty?.active ? device.warranty : null;
    selectedWarranty.value = eligibleWarranty;
    form.warranty_source_type = eligibleWarranty?.source_type || '';
    form.warranty_source_id = eligibleWarranty?.source_id || null;

    lockType.value = device.screen_pattern ? 'pattern' : (device.screen_password ? 'password' : '');
    deviceFocused.value = false;
    newImeiConfirmed.value = false;
    await nextTick();
    applyingHistoryDevice.value = false;
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
        await nextTick();
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
                    lookupAfterScan = true;
                    form.imei = result.getText().trim();
                    deviceSearchQuery.value = form.imei;
                    deviceFocused.value = true;
                    searchDeviceCatalog(deviceSearchQuery.value);
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
const deviceSearchQuery = ref('');
const deviceFocused = ref(false);
const deviceSearching = ref(false);
const newImeiConfirmed = ref(false);
let lookupAfterScan = false;
let deviceSearchTimer;
let deviceSearchSequence = 0;

const combineDeviceSuggestions = (data) => {
    const fromDevices = (data.devices || []).map((item) => ({ ...item, name: item.name || '' }));
    const fromImeis = (data.imeis || []).map((item) => ({ ...item, name: item.name || '' }));
    return [...fromDevices, ...fromImeis]
        .filter((item) => item.name || item.imei)
        .filter((item, index, items) => items.findIndex((candidate) => candidate.name === item.name && candidate.imei === item.imei) === index)
        .slice(0, 12);
};

const searchDeviceCatalog = (value) => {
    clearTimeout(deviceSearchTimer);
    const keyword = value.trim();
    const sequence = ++deviceSearchSequence;
    deviceSuggestions.value = [];
    if (keyword.length < 2) {
        deviceSearching.value = false;
        return;
    }
    deviceSearching.value = true;
    deviceSearchTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('repairs.suggestions'), { params: { keyword } });
            if (sequence !== deviceSearchSequence) return;
            deviceSuggestions.value = combineDeviceSuggestions(data);
            if (lookupAfterScan) {
                lookupAfterScan = false;
                const match = deviceSuggestions.value.find((item) => item.imei?.toLocaleLowerCase() === keyword.toLocaleLowerCase());
                if (match) {
                    selectDevice(match);
                    newImeiConfirmed.value = false;
                } else {
                    newImeiConfirmed.value = true;
                    toast.info('IMEI chưa có trong hệ thống, sẽ được ghi nhận cho máy mới.');
                }
            }
        } catch {
            if (sequence === deviceSearchSequence) {
                deviceSuggestions.value = [];
                if (lookupAfterScan) {
                    lookupAfterScan = false;
                    newImeiConfirmed.value = true;
                    toast.info('Không tìm thấy IMEI trong hệ thống, sẽ ghi nhận cho máy mới.');
                }
            }
        } finally {
            if (sequence === deviceSearchSequence) deviceSearching.value = false;
        }
    }, 220);
};

watch(deviceSearchQuery, (value) => {
    newImeiConfirmed.value = false;
    if (deviceFocused.value) searchDeviceCatalog(value || '');
});

const selectDevice = (item) => {
    form.device_name = item.name || '';
    form.imei = item.imei || '';
    selectedDevice.value = { ...item, name: form.device_name, imei: form.imei };
    isNewDevice.value = false;
    selectedWarranty.value = null;
    form.warranty_source_type = '';
    form.warranty_source_id = null;
    deviceSearchQuery.value = '';
    newImeiConfirmed.value = false;
    deviceFocused.value = false;
};

const useNewDevice = () => {
    const value = deviceSearchQuery.value.trim();
    if (!value) return;
    const scannedImei = newImeiConfirmed.value && form.imei === value;
    form.device_name = scannedImei ? '' : value;
    form.imei = scannedImei ? value : '';
    selectedDevice.value = { name: form.device_name || 'Máy mới', imei: form.imei, source: 'Máy mới' };
    isNewDevice.value = true;
    selectedWarranty.value = null;
    form.warranty_source_type = '';
    form.warranty_source_id = null;
    newImeiConfirmed.value = false;
    deviceSearchQuery.value = '';
    deviceFocused.value = false;
};

const clearSelectedDevice = () => {
    selectedDevice.value = null;
    isNewDevice.value = false;
    form.device_name = '';
    form.imei = '';
    form.screen_password = '';
    form.screen_pattern = '';
    form.account_type = '';
    form.account_email = '';
    form.account_password = '';
    form.issue = [];
    form.repair_request = '';
    form.accessories = [];
    form.estimated_cost = '';
    form.note = '';
    selectedWarranty.value = null;
    form.warranty_source_type = '';
    form.warranty_source_id = null;
    lockType.value = '';
    newImeiConfirmed.value = false;
};

const handleDeviceSearchEnter = () => useNewDevice();

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
        size="xl"
        body-class="px-4 pb-4 pt-2"
        @close="emit('close')"
    >
        <div class="grid grid-cols-1 items-start gap-3 text-xs lg:grid-cols-12 lg:gap-4">
        <div class="space-y-3 lg:col-span-8">
            
            <!-- 1. TÌM KIẾM KHÁCH HÀNG -->
            <section class="overflow-visible rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between gap-2 rounded-t-xl border-b border-slate-100 bg-slate-50 px-3.5 py-2.5">
                    <h2 class="text-[11px] font-bold uppercase tracking-wide text-slate-800">♙ Khách hàng</h2>
                    <span class="text-[10px] text-slate-500">Tìm khách hàng hiện hữu hoặc nhập mới</span>
                </header>
                <div class="space-y-2 p-3">
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
                            <span v-if="Number(selectedCustomer.debt_balance || 0) > 0" class="text-rose-600 text-[11px] font-semibold">
                                · Nợ {{ Number(selectedCustomer.debt_balance).toLocaleString('vi-VN') }} đ
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
            </section>

            <!-- 2. THÔNG TIN THIẾT BỊ & BẢO MẬT -->
            <div class="relative z-20 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-visible" :class="{ 'z-40': deviceFocused }">
                <div class="px-3.5 py-2 border-b border-slate-100 bg-slate-50/70">
                    <h2 class="text-[11px] font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Thông tin thiết bị
                    </h2>
                </div>

                <div class="p-3 space-y-2.5">
                    <div v-if="selectedWarranty" class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-2.5 text-[11px] text-emerald-900">
                        <strong>Thiết bị có căn cứ bảo hành.</strong> Hệ thống sẽ xác minh lại khi hoàn tất sửa; lúc đó chọn sửa dịch vụ hoặc bảo hành.
                        <div class="mt-1 font-semibold">Căn cứ: {{ selectedWarranty.invoice_code || selectedDevice?.source }} · đến {{ displayWarrantyDate(selectedWarranty.expires_at) }}</div>
                    </div>
                    <div v-else class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-[11px] text-slate-600">
                        Có thể tiếp nhận máy trước; loại sửa dịch vụ hay bảo hành sẽ được chốt sau khi kiểm tra.
                    </div>

                    <div v-if="selectedCustomer" class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-2.5">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-600">Thiết bị khách đã mua / từng sửa</p>
                            <span v-if="customerDeviceLoading" class="text-[10px] text-slate-400">Đang tải...</span>
                        </div>
                        <div v-if="customerDeviceSuggestions.length" class="flex gap-2 overflow-x-auto pb-0.5">
                            <button
                                v-for="device in customerDeviceSuggestions"
                                :key="device.key"
                                type="button"
                                class="min-w-[190px] max-w-[250px] rounded-lg border border-indigo-100 bg-white px-2.5 py-2 text-left transition hover:border-indigo-300 hover:bg-indigo-50"
                                @click="selectCustomerDevice(device)"
                            >
                                <span class="block truncate text-[11px] font-semibold text-slate-800">{{ device.device_name }}</span>
                                <span class="mt-0.5 block truncate text-[10px] text-slate-500">{{ device.source }}<template v-if="device.imei || device.serial"> · {{ device.imei || device.serial }}</template></span>
                                <span v-if="device.warranty" class="mt-1 block text-[10px] font-semibold" :class="device.warranty.active ? 'text-emerald-700' : 'text-rose-600'">
                                    {{ device.warranty.voided_at ? 'BH đã vô hiệu' : device.warranty.active ? `Còn BH đến ${displayWarrantyDate(device.warranty.expires_at)}` : `Hết BH ${displayWarrantyDate(device.warranty.expires_at)}` }}
                                </span>
                                <span v-else class="mt-1 block text-[10px] text-slate-400">Không có hạn BH khách hàng</span>
                            </button>
                        </div>
                        <p v-else-if="!customerDeviceLoading" class="text-[10px] text-slate-500">Chưa có thiết bị trong lịch sử. Có thể tìm hoặc nhập máy mới bên dưới.</p>
                    </div>

                    <div v-if="selectedDevice" class="rounded-xl border border-blue-200 bg-blue-50/70 px-3.5 py-2.5 transition">
                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-2">
                            <div v-if="form.device_name" class="flex min-w-0 items-center gap-2.5">
                                <svg class="h-4 w-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <div class="min-w-0 truncate text-xs leading-tight">
                                    <span class="font-bold text-slate-800">{{ form.device_name }}</span>
                                    <span v-if="!isNewDevice && selectedDevice.source" class="ml-1.5 text-slate-500">· {{ selectedDevice.source }}</span>
                                </div>
                            </div>
                            <FloatingInput v-else v-model="form.device_name" label="Tên máy / Model *" id="device_name" :error="form.errors.device_name" required />
                            <FloatingInput v-model="form.imei" label="Số IMEI / Serial" id="imei" :error="form.errors.imei" />
                            <button type="button" @click="clearSelectedDevice" class="shrink-0 p-1 text-blue-400 transition hover:text-rose-600" title="Bỏ chọn máy" aria-label="Bỏ chọn máy">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <p v-if="newImeiConfirmed" class="mt-1 text-[10px] text-emerald-700">IMEI mới sẽ được lưu cùng phiếu tiếp nhận.</p>
                    </div>

                    <div v-else class="relative">
                        <div class="flex items-center gap-1.5">
                            <div class="relative min-w-0 flex-1">
                                <FloatingInput
                                    v-model="deviceSearchQuery"
                                    label="Tìm máy theo tên hoặc IMEI / Serial"
                                    id="device_search"
                                    autocomplete="off"
                                    @keydown.enter.prevent="handleDeviceSearchEnter"
                                    @focus="deviceFocused = true; searchDeviceCatalog(deviceSearchQuery)"
                                    @blur="deviceFocused = false"
                                />
                                <div v-if="deviceFocused && deviceSearchQuery.trim().length >= 2" class="absolute left-0 right-0 top-full z-[200] mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                    <button v-for="device in deviceSuggestions" :key="`${device.name}-${device.imei || ''}-${device.source}`" type="button" class="block w-full border-b border-slate-50 px-3 py-2 text-left text-xs last:border-0 hover:bg-blue-50" @mousedown.prevent="selectDevice(device)">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="truncate font-semibold text-slate-800">{{ device.name || 'Chưa có tên máy' }}</span>
                                            <span v-if="device.imei" class="shrink-0 font-mono text-[10px] text-indigo-700">{{ device.imei }}</span>
                                        </span>
                                        <span class="text-[10px] text-slate-500">{{ device.source }}</span>
                                    </button>
                                    <p v-if="deviceSearching" class="px-3 py-2 text-[10px] text-slate-400">Đang tìm theo tên máy và IMEI...</p>
                                    <button v-if="!deviceSearching" type="button" class="block w-full border-t border-slate-100 px-3 py-2 text-left text-xs font-semibold text-blue-700 hover:bg-blue-50" @mousedown.prevent="useNewDevice">
                                        + Dùng tên máy này, nhập IMEI riêng
                                    </button>
                                    <p v-if="!deviceSearching && !deviceSuggestions.length" class="px-3 py-1 text-[10px] text-slate-400">Chưa tìm thấy máy phù hợp. Có thể dùng thông tin mới bên trên.</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="startScan"
                                title="Quét IMEI từ Camera"
                                class="flex shrink-0 items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 px-3 text-indigo-600 transition hover:bg-indigo-100 active:bg-indigo-200"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h0.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </button>
                        </div>
                        <div v-if="scanning" class="relative mt-2 overflow-hidden rounded-xl border bg-slate-900">
                            <video ref="videoRef" class="h-32 w-full object-cover" />
                            <button type="button" @click="stopScan" class="absolute right-2 top-2 rounded-lg bg-rose-600 px-2 py-0.5 text-[10px] text-white shadow transition hover:bg-rose-700">Tắt camera</button>
                        </div>
                    </div>

                    <p v-if="form.errors.device_name && !selectedDevice" class="text-[11px] font-medium text-rose-500">{{ form.errors.device_name }}</p>
                    <div class="grid grid-cols-1 gap-2.5 border-t border-slate-100 pt-2.5 sm:grid-cols-2">
                        <FloatingSelect v-model="lockType" label="Kiểu khóa màn hình" id="repair_lock_type" :options="lockTypeOptions" />
                        <FloatingInput v-if="['pin', 'password'].includes(lockType)" v-model="form.screen_password" :type="lockType === 'password' ? 'password' : 'text'" :inputmode="lockType === 'pin' ? 'numeric' : 'text'" :label="lockType === 'pin' ? 'Mã PIN màn hình' : 'Mật khẩu màn hình'" id="repair_screen_password" />
                        <FloatingSelect v-model="form.account_type" label="Loại tài khoản máy" id="repair_account_type" :options="accountTypeOptions" />
                        <FloatingInput v-if="form.account_type" v-model="form.account_email" label="Email / SĐT tài khoản" id="repair_account_email" />
                        <FloatingInput v-if="form.account_type" v-model="form.account_password" type="password" label="Mật khẩu tài khoản" id="repair_account_password" />
                        <div v-if="lockType === 'pattern'" class="sm:col-span-2">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-600">Vẽ mẫu hình khóa màn hình</label>
                            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-2"><PatternLock v-model="form.screen_pattern" /></div>
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

                </div>
            </div>

        </div>

        <aside class="space-y-3 lg:col-span-4">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 bg-slate-50 px-3.5 py-2.5">
                    <h2 class="text-[11px] font-bold uppercase tracking-wide text-slate-800">▧ Ảnh ngoại quan</h2>
                </header>
                <div class="p-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                        <input type="file" multiple accept="image/*" class="hidden" @change="handleImages" />
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7a2 2 0 012-2h2l1.2 1.5H18a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V7z" />
                            <circle cx="12" cy="13" r="3" stroke-width="1.8" />
                        </svg>
                        <span>Chọn ảnh</span>
                    </label>
                    <span class="ml-2 text-[10px] text-slate-400">{{ imagePreviews.length ? `${imagePreviews.length} ảnh đã chọn` : 'Có thể chọn nhiều ảnh' }}</span>
                    <div v-if="imagePreviews.length" class="mt-2 grid grid-cols-4 gap-1.5">
                        <div v-for="(image, index) in imagePreviews" :key="index" class="group relative aspect-square overflow-hidden rounded-lg border border-slate-200">
                            <img :src="image" alt="Ảnh ngoại quan máy" class="h-full w-full object-cover" />
                            <button type="button" @click="removeImage(index)" class="absolute right-1 top-1 rounded-full bg-rose-600 p-1 text-white opacity-0 shadow group-hover:opacity-100" aria-label="Xóa ảnh">×</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4. TÓM TẮT PHIẾU -->
            <div class="space-y-1.5 rounded-xl border border-slate-800 bg-slate-900 p-3.5 text-white shadow-md">
                <div class="border-b border-slate-800 pb-1 flex justify-between items-center">
                    <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-300">Tóm tắt tiếp nhận</h2>
                    <span class="bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[10px] px-2 py-0.5 rounded-full font-medium">Phiếu mới</span>
                </div>

                <div class="space-y-1 text-[11px]">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-800 py-1.5">
                        <span class="text-slate-400">Khách:</span>
                        <span class="font-semibold text-slate-100 truncate max-w-[120px]">{{ form.customer_name || '---' }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-2 border-b border-slate-800 py-1.5">
                        <span class="text-slate-400">SĐT:</span>
                        <span class="font-semibold text-slate-100">{{ form.customer_phone || '---' }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-2 border-b border-slate-800 py-1.5">
                        <span class="text-slate-400">Thiết bị:</span>
                        <span class="font-semibold text-slate-100 truncate max-w-[120px]">{{ form.device_name || '---' }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-2 border-b border-slate-800 py-1.5">
                        <span class="text-slate-400">IMEI:</span>
                        <span class="max-w-[65%] truncate text-right font-mono font-semibold text-slate-100">{{ form.imei || '---' }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-2 py-1.5">
                        <span class="text-slate-400">Số lỗi:</span>
                        <span class="bg-rose-500/20 text-rose-300 border border-rose-500/30 px-2 py-0.5 rounded-full font-bold text-[10px]">
                            {{ form.issue.length }} lỗi
                        </span>
                    </div>
                </div>
            </div>

        </aside>
        </div>

        <!-- FOOTER ACTIONS -->
        <template #footer>
            <div class="flex items-center justify-end gap-2">
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
