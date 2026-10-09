<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash/debounce';
import { BrowserMultiFormatReader } from '@zxing/browser';

import PatternLock from '@/Components/PatternLock.vue';
import FloatingInput from '@/Components/UI/FloatingInput.vue';
import FloatingSelect from '@/Components/UI/FloatingSelect.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import ActionButton from '@/Components/UI/ActionButton.vue';
import RepairProgressModal from './RepairProgressModal.vue';
import RepairStatusProgress from './RepairStatusProgress.vue';
import { toast } from 'vue-sonner';
import { formatCurrency, formatDate } from '@/utils/format';

const emit = defineEmits(['close', 'updated']);
const props = defineProps({ initialRepair: { type: Object, default: null }, startAt: { type: String, default: 'repair' }, onCompleted: Function, onPay: Function });
const step = ref(props.initialRepair ? props.startAt : 'intake');
const createdRepair = ref(props.initialRepair || null);
const progressModalRef = ref(null);
const workflowStage = ref('progress');
const canOpenComplete = computed(() => createdRepair.value?.status === 'repairing'
    && (workflowStage.value === 'complete' || createdRepair.value?.waiting_mode === 'repair_now'));
const initial = props.initialRepair;
const searchLookupRef = ref(null);
const issueLookupRef = ref(null);
const securityExpanded = ref(Boolean(initial?.screen_password || initial?.screen_pattern || initial?.account_type || initial?.account_email || initial?.account_password));
const lightboxImage = ref(null);

const form = useForm({
    customer_id: initial?.customer?.id || null,
    customer_name: initial?.customer?.name || '',
    customer_phone: initial?.customer?.phone || '',
    contact_phone: initial?.contact_phone || '',
    identity_card: initial?.customer?.identity_card || '',
    warranty_source_type: initial?.warranty_source_type || '',
    warranty_source_id: initial?.warranty_source_id || null,
    device_name: initial?.device_name || '',
    imei: initial?.imei || '',
    screen_password: initial?.screen_password || '',
    screen_pattern: initial?.screen_pattern || '',
    account_type: initial?.account_type || '',
    account_email: initial?.account_email || '',
    account_password: initial?.account_password || '',
    issue: Array.isArray(initial?.issue) ? [...initial.issue] : [],
    repair_request: initial?.repair_request || '',
    estimated_cost: initial?.estimated_cost ?? '',
    accessories: Array.isArray(initial?.accessories) ? [...initial.accessories] : [],
    note: initial?.note || '',
    images: [],
});

/*
|--------------------------------------------------------------------------
| Khách hàng & Tìm kiếm
|--------------------------------------------------------------------------
*/
const selectedCustomer = ref(initial?.customer ? {
    id: initial.customer.id,
    full_name: initial.customer.name,
    phone: initial.customer.phone,
    cccd: initial.customer.identity_card,
    debt_balance: initial.customer.debt_balance,
} : null);
const searchQuery = ref('');
const customerDeviceSuggestions = ref([]);
const customerDeviceLoading = ref(false);
const selectedDevice = ref(initial ? { name: initial.device_name, imei: initial.imei, source: 'Phiếu hiện tại' } : null);
const isNewDevice = ref(false);
const selectedWarranty = ref(initial?.warranty_source_type ? {
    source_type: initial.warranty_source_type,
    source_id: initial.warranty_source_id,
    expires_at: initial.warranty_expires_at,
    active: initial.warranty_status === 'eligible',
    invoice_code: initial.warranty_source_type,
} : null);
let customerDeviceRequest = 0;

const displayWarrantyDate = (value) => value ? formatDate(value) : 'Không có hạn';

const warrantyForDeviceSuggestion = (item) => {
    const customerId = Number(form.customer_id || selectedCustomer.value?.id || 0);
    if (!customerId) return null;
    const identifier = String(item.imei || '').trim().toLocaleLowerCase();
    if (!identifier) return null;
    const matches = customerDeviceSuggestions.value.filter((device) =>
        [device.imei, device.serial]
            .filter(Boolean)
            .some((value) => String(value).trim().toLocaleLowerCase() === identifier)
    );
    const customerWarranty = matches.find((device) =>
        device.warranty?.active && !device.warranty?.voided_at && device.warranty?.expires_at
    )?.warranty;
    if (customerWarranty) return { status: 'active', warranty: customerWarranty };

    const catalogWarranty = item.warranty;
    const warrantyOwnerId = catalogWarranty?.sale_customer_id ?? catalogWarranty?.source_customer_id;
    if (
        warrantyOwnerId
        && Number(warrantyOwnerId) === customerId
        && catalogWarranty?.active
        && !catalogWarranty?.voided_at
        && catalogWarranty?.expires_at
    ) {
        return { status: 'active', warranty: catalogWarranty };
    }
    return null;
};

const typedImeiWarranty = computed(() => {
    if (!form.customer_id) return null;
    const identifier = deviceSearchQuery.value.trim();
    if (!identifier || /\s/.test(identifier)) return null;
    const matchingCatalogDevices = deviceSuggestions.value.filter((device) =>
        String(device.imei || '').trim().toLocaleLowerCase() === identifier.toLocaleLowerCase()
    );
    const catalogMatch = matchingCatalogDevices.find((device) => device.warranty) || matchingCatalogDevices[0];
    if (catalogMatch?.warranty) return warrantyForDeviceSuggestion(catalogMatch);
    if (identifier.length < 8) return null;
    return warrantyForDeviceSuggestion({ imei: identifier });
});

const isSelectedCustomerActiveWarranty = (purchaser) =>
    Number(form.customer_id || 0) === Number(purchaser?.id || 0)
    && Boolean(purchaser?.warranty_active)
    && Boolean(purchaser?.warranty_expires_at)
    && !purchaser?.warranty_voided_at;

const selectedDeviceWarranty = computed(() => {
    if (!selectedDevice.value?.imei) return null;
    const catalogMatch = deviceSuggestions.value.find((device) =>
        String(device.imei || '').trim().toLocaleLowerCase() === String(selectedDevice.value.imei).trim().toLocaleLowerCase()
        && device.warranty
    );
    return warrantyForDeviceSuggestion(catalogMatch || selectedDevice.value);
});

const loadCustomerDeviceSuggestions = async (customerId) => {
    const requestId = ++customerDeviceRequest;
    customerDeviceSuggestions.value = [];
    if (!customerId) {
        customerDeviceLoading.value = false;
        return [];
    }
    customerDeviceLoading.value = true;
    try {
        const { data } = await axios.get(route('repairs.customer-devices', customerId));
        const devices = data.devices || [];
        if (requestId === customerDeviceRequest) customerDeviceSuggestions.value = devices;
        return devices;
    } catch {
        if (requestId === customerDeviceRequest) customerDeviceSuggestions.value = [];
        return [];
    } finally {
        if (requestId === customerDeviceRequest) customerDeviceLoading.value = false;
    }
};

const onSelectCustomer = (customer) => {
    if (!customer) {
        selectedCustomer.value = null;
        form.customer_id = null;
        return loadCustomerDeviceSuggestions(null);
    }
    const existingDevice = selectedDevice.value;
    selectedCustomer.value = customer;
    form.customer_id = customer.id || null;
    form.customer_name = customer.full_name || customer.customer_name || customer.name || '';
    form.customer_phone = customer.phone || customer.customer_phone || '';
    form.identity_card = customer.cccd || customer.identity_card || '';
    form.contact_phone = customer.contact_phone || form.customer_phone || '';
    searchQuery.value = '';
    deviceSearchQuery.value = '';
    // Keep the lookup focused so typing again immediately reopens suggestions.
    deviceFocused.value = true;
    const historyPromise = loadCustomerDeviceSuggestions(customer.id);
    if (!existingDevice) {
        return historyPromise;
    }

    return historyPromise.then(async (history) => {
        const identifier = String(existingDevice.imei || existingDevice.serial || '').trim().toLocaleLowerCase();
        const matchingHistory = history.find((candidate) =>
            [candidate.imei, candidate.serial]
                .filter(Boolean)
                .some((value) => String(value).trim().toLocaleLowerCase() === identifier)
        );
        if (matchingHistory) {
            await applyDeviceSelection({
                ...existingDevice,
                name: existingDevice.name || matchingHistory.device_name,
                imei: existingDevice.imei || existingDevice.serial,
                customer,
                device_data: matchingHistory,
                warranty: matchingHistory.warranty || null,
            });
        } else {
            // A different customer may bring this device in; never carry another customer's saved credentials over.
            form.screen_password = '';
            form.screen_pattern = '';
            form.account_type = '';
            form.account_email = '';
            form.account_password = '';
            lockType.value = '';
            existingDevice.customer = customer;
            existingDevice.device_data = {};
            existingDevice.warranty = null;
            selectedDevice.value = { ...existingDevice };
            selectedWarranty.value = null;
            form.warranty_source_type = '';
            form.warranty_source_id = null;
        }
        return history;
    });
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    searchQuery.value = '';
    form.customer_id = null;
    form.customer_name = '';
    form.customer_phone = '';
    form.contact_phone = '';
    form.identity_card = '';
    loadCustomerDeviceSuggestions(null);
};

const useNewCustomer = () => {
    const name = searchQuery.value.trim();
    if (!name) return;
    selectedCustomer.value = { id: null, full_name: name, phone: '', cccd: '', debt_balance: 0, is_new: true };
    form.customer_id = null;
    form.customer_name = name;
    form.customer_phone = '';
    form.contact_phone = '';
    form.identity_card = '';
    searchQuery.value = '';
    deviceSearchQuery.value = '';
    customerDeviceSuggestions.value = [];
    // The popup closes because the query is cleared, while the input remains reusable.
    deviceFocused.value = true;
};

/*
|--------------------------------------------------------------------------
| Trạng thái Bảo mật (Ẩn/Hiện)
|--------------------------------------------------------------------------
*/
const lockType = ref(form.screen_pattern ? 'pattern' : (form.screen_password ? 'password' : ''));
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
const applyDeviceSelection = async (device) => {
    applyingHistoryDevice.value = true;
    if (device.customer?.id && Number(device.customer.id) !== Number(form.customer_id)) {
        onSelectCustomer(device.customer);
    }
    const name = device.name || device.device_name || '';
    const imei = device.imei || device.serial || '';
    if (selectedDevice.value && (String(selectedDevice.value.imei || '').toLowerCase() !== String(imei || '').toLowerCase() || String(selectedDevice.value.name || selectedDevice.value.device_name || '').toLowerCase() !== String(name || '').toLowerCase())) {
        clearImagePreviews();
    }
    const details = device.device_data || device;
    selectedDevice.value = { ...device, name, imei, source: device.source };
    isNewDevice.value = false;
    deviceSearchQuery.value = '';
    form.device_name = name;
    form.imei = imei;
    form.screen_password = details.screen_password || '';
    form.screen_pattern = details.screen_pattern || '';
    form.account_type = details.account_type || ((details.account_email || details.account_password) ? 'other' : '');
    form.account_email = details.account_email || '';
    form.account_password = details.account_password || '';
    form.issue = Array.isArray(details.issue) ? [...details.issue] : (details.issue ? [details.issue] : []);
    form.repair_request = details.repair_request || '';
    form.accessories = Array.isArray(details.accessories) ? [...details.accessories] : [];
    form.estimated_cost = details.estimated_cost || '';
    form.note = details.note || '';
    securityExpanded.value = Boolean(details.screen_password || details.screen_pattern || details.account_type || details.account_email || details.account_password);
    const warrantyOwnerId = device.warranty?.sale_customer_id ?? device.warranty?.source_customer_id ?? null;
    const warrantyBelongsToCustomer = !warrantyOwnerId || Number(warrantyOwnerId) === Number(form.customer_id);
    const eligibleWarranty = device.warranty?.active && warrantyBelongsToCustomer ? device.warranty : null;
    selectedWarranty.value = eligibleWarranty;
    form.warranty_source_type = eligibleWarranty?.source_type || '';
    form.warranty_source_id = eligibleWarranty?.source_id || null;

    lockType.value = details.screen_pattern ? 'pattern' : (details.screen_password ? 'password' : '');
    deviceFocused.value = true;
    newImeiConfirmed.value = false;
    await nextTick();
    applyingHistoryDevice.value = false;
};

const selectCustomerDevice = (device) => applyDeviceSelection(device);

/*
|--------------------------------------------------------------------------
| Preview & Quản lý ảnh
|--------------------------------------------------------------------------
*/
const imagePreviews = ref([]);
const revokeImagePreviews = () => {
    imagePreviews.value.forEach((url) => URL.revokeObjectURL(url));
    imagePreviews.value = [];
    lightboxImage.value = null;
};
const clearImagePreviews = () => {
    revokeImagePreviews();
    form.images = [];
};

const handleImages = (event) => {
    const files = Array.from(event.target.files);
    event.target.value = '';
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
const customerSuggestions = ref([]);
const deviceSearchQuery = searchQuery;
const normalizedDeviceSearch = computed(() => deviceSearchQuery.value.trim().toLocaleLowerCase());
const matchingDeviceNames = computed(() => deviceSuggestions.value.filter((device) =>
    device.name && String(device.name).toLocaleLowerCase().includes(normalizedDeviceSearch.value)
));
const matchingImeis = computed(() => deviceSuggestions.value.filter((device) =>
    device.imei && String(device.imei).toLocaleLowerCase().includes(normalizedDeviceSearch.value)
));
const deviceFocused = ref(false);
const deviceSearching = ref(false);
const newImeiConfirmed = ref(false);
let lookupAfterScan = false;
let deviceSearchSequence = 0;

const combineDeviceSuggestions = (data) => {
    const fromDevices = (data.devices || []).map((item) => ({ ...item, name: item.name || '' }));
    const fromImeis = (data.imeis || []).map((item) => ({ ...item, name: item.name || '' }));
    const namesWithImei = new Set(fromImeis.filter((item) => item.imei).map((item) => String(item.name).trim().toLocaleLowerCase()));
    const suggestions = new Map();
    [...fromDevices.filter((item) => item.imei || !namesWithImei.has(String(item.name).trim().toLocaleLowerCase())), ...fromImeis]
        .filter((item) => item.name || item.imei)
        .forEach((item) => {
            const key = `${String(item.name).trim().toLocaleLowerCase()}|${String(item.imei || '').trim().toLocaleLowerCase()}`;
            const previous = suggestions.get(key);
            const purchasers = [...(previous?.purchasers || []), ...(item.purchasers || [])]
                .filter((purchaser, index, all) => {
                    const identity = purchaser?.sale_item_id ?? purchaser?.id;
                    return identity && all.findIndex((candidate) => (candidate?.sale_item_id ?? candidate?.id) === identity) === index;
                });
            suggestions.set(key, previous
                ? { ...previous, ...item, customer: item.customer || previous.customer, purchasers, warranty: item.warranty || previous.warranty }
                : { ...item, purchasers: item.purchasers || [] });
        });
    return [...suggestions.values()].slice(0, 12);
};

const runDeviceSearch = debounce(async (keyword, sequence) => {
    try {
        const { data } = await axios.get(route('repairs.suggestions'), {
            params: { keyword, exclude_repair_id: props.initialRepair?.id || undefined },
        });
        if (sequence !== deviceSearchSequence) return;
        deviceSuggestions.value = combineDeviceSuggestions(data);
        customerSuggestions.value = data.customers || [];
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
            customerSuggestions.value = [];
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

const searchDeviceCatalog = (value) => {
    const keyword = value.trim();
    const sequence = ++deviceSearchSequence;
    deviceSuggestions.value = [];
    customerSuggestions.value = [];
    if (keyword.length < 2) {
        runDeviceSearch.cancel();
        deviceSearching.value = false;
        return;
    }
    deviceSearching.value = true;
    runDeviceSearch(keyword, sequence);
};

watch(deviceSearchQuery, (value) => {
    newImeiConfirmed.value = false;
    if (deviceFocused.value) searchDeviceCatalog(value || '');
});

const activeRepairForImei = (imei) => {
    const identifier = String(imei || '').trim().toLocaleLowerCase();
    if (!identifier) return null;
    return deviceSuggestions.value.find((device) =>
        String(device.imei || device.serial || '').trim().toLocaleLowerCase() === identifier
    )?.active_repair || null;
};

const isImeiBlocked = (imei) => {
    const activeRepair = activeRepairForImei(imei);
    return Boolean(activeRepair && Number(activeRepair.id) !== Number(props.initialRepair?.id || 0));
};

const selectDevice = (item) => {
    if (isImeiBlocked(item.imei || item.serial)) {
        const activeRepair = activeRepairForImei(item.imei || item.serial);
        toast.error(`IMEI này đang có phiếu ${activeRepair.code} chưa hoàn tất.`);
        return;
    }
    const matchingWarrantyDevice = deviceSuggestions.value.find((device) =>
        String(device.imei || '').trim().toLocaleLowerCase() === String(item.imei || '').trim().toLocaleLowerCase()
        && device.warranty
    );
    let selectedItem = !item.warranty && matchingWarrantyDevice
        ? {
            ...item,
            warranty: matchingWarrantyDevice.warranty,
            customer: item.customer || matchingWarrantyDevice.customer,
            device_data: item.device_data || matchingWarrantyDevice.device_data,
            source_date: item.source_date || matchingWarrantyDevice.source_date,
            purchase_info: item.purchase_info || matchingWarrantyDevice.purchase_info,
        }
        : item;

    // The person bringing the device in can differ from its previous buyer.
    // Keep the customer already chosen for this repair and only reuse that customer's device history.
    const currentCustomer = selectedCustomer.value || (form.customer_name.trim() ? {
        id: form.customer_id,
        full_name: form.customer_name,
        phone: form.customer_phone,
        cccd: form.identity_card,
    } : null);
    if (!currentCustomer) {
        // If a single customer is associated with this result, load them into the form.
        // Keep credentials out of the form when the IMEI has no unambiguous customer.
        if (!selectedItem.customer?.id) selectedItem = { ...selectedItem, device_data: {} };
    } else if (Number(currentCustomer.id || 0) !== Number(item.customer?.id || 0)) {
        const identifier = String(item.imei || item.serial || '').trim().toLocaleLowerCase();
        const customerHistory = currentCustomer.id && customerDeviceSuggestions.value.find((candidate) =>
            [candidate.imei, candidate.serial]
                .filter(Boolean)
                .some((value) => String(value).trim().toLocaleLowerCase() === identifier)
        );
        selectedItem = {
            ...selectedItem,
            ...(customerHistory || {}),
            name: item.name || customerHistory?.device_name || '',
            imei: item.imei || item.serial,
            customer: currentCustomer,
            device_data: customerHistory || {},
            warranty: customerHistory?.warranty || selectedItem.warranty,
        };
    }
    return applyDeviceSelection(selectedItem);
};

const selectDeviceForPurchaser = async (device, purchaser) => {
    if (isImeiBlocked(device.imei || device.serial)) {
        const activeRepair = activeRepairForImei(device.imei || device.serial);
        toast.error(`IMEI này đang có phiếu ${activeRepair.code} chưa hoàn tất.`);
        return;
    }
    const history = await onSelectCustomer(purchaser);
    const identifier = String(device.imei || '').trim().toLocaleLowerCase();
    const customerDevice = history.find((candidate) =>
        [candidate.imei, candidate.serial]
            .filter(Boolean)
            .some((value) => String(value).trim().toLocaleLowerCase() === identifier)
    );
    const selected = {
        ...device,
        ...(customerDevice || {}),
        name: device.name || customerDevice?.device_name || '',
        imei: device.imei || device.serial,
        customer: purchaser,
        source: device.source,
        device_data: customerDevice || {},
        warranty: isSelectedCustomerActiveWarranty(purchaser) ? {
            source_type: 'sale_item',
            source_id: purchaser.sale_item_id,
            sale_customer_id: purchaser.id,
            invoice_code: purchaser.invoice_code,
            expires_at: purchaser.warranty_expires_at,
            active: true,
        } : (customerDevice?.warranty?.active ? customerDevice.warranty : null),
    };
    selectDevice(selected);
};

const selectDeviceForCurrentCustomer = async (device) => {
    if (isImeiBlocked(device.imei || device.serial)) {
        const activeRepair = activeRepairForImei(device.imei || device.serial);
        toast.error(`IMEI này đang có phiếu ${activeRepair.code} chưa hoàn tất.`);
        return;
    }
    const customer = selectedCustomer.value;
    const history = customer?.id ? await loadCustomerDeviceSuggestions(customer.id) : [];
    const identifier = String(device.imei || device.serial || '').trim().toLocaleLowerCase();
    const customerDevice = history.find((candidate) =>
        [candidate.imei, candidate.serial]
            .filter(Boolean)
            .some((value) => String(value).trim().toLocaleLowerCase() === identifier)
    );
    selectDevice({
        ...device,
        ...(customerDevice || {}),
        name: device.name || customerDevice?.device_name || '',
        imei: device.imei || device.serial,
        customer: customer || null,
        device_data: customerDevice || {},
        warranty: customerDevice?.warranty || device.warranty,
    });
};

const prepareNewDevice = (name, imei) => {
    clearImagePreviews();
    form.device_name = name;
    form.imei = imei;
    selectedDevice.value = { name: name || 'Máy mới', imei, source: 'Thiết bị mới' };
    isNewDevice.value = true;
    selectedWarranty.value = null;
    form.warranty_source_type = '';
    form.warranty_source_id = null;
    form.screen_password = '';
    form.screen_pattern = '';
    form.account_type = '';
    form.account_email = '';
    form.account_password = '';
    lockType.value = '';
    newImeiConfirmed.value = false;
    deviceSearchQuery.value = '';
    deviceFocused.value = false;
};

const addDeviceByName = () => {
    const name = deviceSearchQuery.value.trim();
    if (name) prepareNewDevice(name, '');
};

const addDeviceByImei = () => {
    const imei = deviceSearchQuery.value.trim();
    if (!imei) return;
    if (isImeiBlocked(imei)) {
        const activeRepair = activeRepairForImei(imei);
        toast.error(`IMEI này đang có phiếu ${activeRepair.code} chưa hoàn tất.`);
        return;
    }
    prepareNewDevice('', imei);
};

const useNewDevice = () => {
    const value = deviceSearchQuery.value.trim();
    if (!value) return;
    if ((newImeiConfirmed.value && form.imei === value) || /^\d{6,20}$/.test(value)) {
        addDeviceByImei();
    } else {
        addDeviceByName();
    }
};

const clearSelectedDevice = () => {
    clearImagePreviews();
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

const handleDeviceSearchEnter = () => {
    if (deviceSearching.value) return;
    const value = deviceSearchQuery.value.trim();
    const exactImei = deviceSuggestions.value.find((item) => String(item.imei || '').trim().toLocaleLowerCase() === value.toLocaleLowerCase());
    if (exactImei) {
        selectDevice(exactImei);
        return;
    }
    if (customerSuggestions.value.length === 1 && !deviceSuggestions.value.length) {
        onSelectCustomer(customerSuggestions.value[0]);
        return;
    }
    if (customerSuggestions.value.length || deviceSuggestions.value.length) return;
    if (/^\d{6,20}$/.test(value)) useNewDevice();
};

const filteredIssues = ref([]);

const accountTypeOptions = [
    { value: '', label: '-- Chọn loại tài khoản --' },
    { value: 'icloud', label: 'iCloud' },
    { value: 'google', label: 'Google' },
    { value: 'samsung', label: 'Samsung Account' },
    { value: 'xiaomi', label: 'Mi Account' },
    { value: 'other', label: 'Khác' },
];

const handleDocumentPointerDown = (event) => {
    if (!searchLookupRef.value?.contains(event.target)) deviceFocused.value = false;
    if (!issueLookupRef.value?.contains(event.target)) filteredIssues.value = [];
};

onMounted(async () => {
    document.addEventListener('pointerdown', handleDocumentPointerDown);
    try {
        const response = await axios.get(route('repairs.suggestions'));
        issueSuggestions.value = response.data.issues ?? [];
    } catch (error) {
        console.error(error);
    }
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', handleDocumentPointerDown);
    deviceSearchSequence += 1;
    runDeviceSearch.cancel();
    revokeImagePreviews();
    if (scanning.value) {
        try {
            stopScan();
        } catch (error) {
            console.warn('Không thể dừng camera khi đóng phiếu sửa chữa', error);
        }
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
    const isEditing = Boolean(createdRepair.value?.id);
    const onSuccess = (page) => {
        if (isEditing) {
            const updated = page.props.repairs?.data?.find((item) => Number(item.id) === Number(createdRepair.value.id));
            if (updated) createdRepair.value = updated;
            else {
                createdRepair.value = {
                    ...createdRepair.value,
                    customer: {
                        ...createdRepair.value.customer,
                        id: form.customer_id,
                        name: form.customer_name,
                        phone: form.customer_phone,
                        identity_card: form.identity_card,
                    },
                    contact_phone: form.contact_phone,
                    device_name: form.device_name,
                    imei: form.imei,
                    screen_password: form.screen_password,
                    screen_pattern: form.screen_pattern,
                    account_type: form.account_type,
                    account_email: form.account_email,
                    account_password: form.account_password,
                    issue: [...form.issue],
                    repair_request: form.repair_request,
                    estimated_cost: form.estimated_cost,
                    accessories: [...form.accessories],
                    note: form.note,
                };
            }
            toast.success('Đã cập nhật thông tin phiếu');
            step.value = 'repair';
            emit('updated');
            return;
        }

        const responseUrl = new URL(page.url, window.location.origin);
        const repairId = Number(page.props.createdRepairId || page.props.flash?.createdRepairId || responseUrl.searchParams.get('created_repair_id') || 0);
        const repair = page.props.repairs?.data?.find((item) => Number(item.id) === repairId);
        if (!repair) {
            toast.error('Phiếu đã lưu nhưng chưa tải được dữ liệu bước sửa. Vui lòng tải lại danh sách rồi mở phiếu vừa tạo.');
            emit('updated');
            return;
        }
        toast.success('Đã tiếp nhận máy sửa chữa');
        createdRepair.value = repair;
        step.value = 'repair';
        emit('updated');
    };

    const options = {
        forceFormData: true,
        onSuccess,
    };

    if (isEditing) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('repairs.update', createdRepair.value.id), options);
    } else {
        form.transform((data) => data).post(route('repairs.store'), options);
    }
};

const onProgressUpdated = (repair) => {
    if (repair) createdRepair.value = repair;
    emit('updated');
};

const onWorkflowStageChange = (stage) => {
    workflowStage.value = stage;
};

const selectWorkflowStep = (status) => {
    if (status === 'pending') {
        if (step.value === 'repair' && progressModalRef.value?.hasUnsavedChanges
            && !window.confirm('Bạn có thay đổi tiến trình chưa lưu. Quay lại và bỏ các thay đổi này?')) return;
        step.value = 'intake';
        return;
    }
    if (!createdRepair.value) return;
    if (step.value === 'intake' && form.isDirty) {
        submit();
        return;
    }
    step.value = 'repair';
    if (status === 'done') {
        if (createdRepair.value.status === 'done') {
            progressModalRef.value?.openProgressStep();
            return;
        }
        if (progressModalRef.value?.hasUnsavedChanges
            && !window.confirm('Bạn có thay đổi tiến trình chưa lưu. Tiếp tục sang bước hoàn tất và bỏ các thay đổi này?')) return;
        progressModalRef.value?.openCompletionStep();
    } else if (status === 'repairing' || status === 'returned') {
        if (status === 'returned') progressModalRef.value?.openHistory();
        else progressModalRef.value?.openProgressStep();
    }
};
</script>

<template>
    <BaseModal
        :title="step === 'intake' ? (createdRepair ? `Sửa thông tin phiếu · ${createdRepair.code}` : 'Tiếp nhận và sửa chữa') : `Quy trình sửa chữa · ${createdRepair?.code || ''}`"
        size="xl"
        body-class="px-4 pb-4"
        @close="emit('close')"
    >
        <div class="sticky top-0 z-40 -mx-4 mb-2 border-b border-slate-200 bg-slate-50/95 px-3 py-1.5 shadow-sm backdrop-blur">
            <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm">
            <RepairStatusProgress
                class="min-w-0"
                :status="createdRepair?.status || 'pending'"
                :waiting-for-parts="createdRepair?.waiting_for_parts || false"
                :waiting-mode="createdRepair?.waiting_mode || 'repair_now'"
                :can-open-repair="Boolean(createdRepair)"
                :can-open-complete="canOpenComplete"
                :workflow-stage="workflowStage"
                @select="selectWorkflowStep"
            />
            <div class="mt-1 flex items-center justify-between gap-3 border-t border-slate-100 pt-1">
                <div v-if="step === 'repair' && createdRepair" class="grid min-w-0 flex-1 grid-cols-2 gap-x-3 gap-y-0.5 text-[10px] sm:grid-cols-4">
                    <div class="min-w-0 truncate"><span class="text-slate-500">Khách</span><strong class="ml-1 text-slate-800">{{ createdRepair.customer?.name || createdRepair.customer_name || 'Khách lẻ' }}</strong></div>
                    <div class="min-w-0 truncate"><span class="text-slate-500">SĐT</span><strong class="ml-1 text-slate-800">{{ createdRepair.customer?.phone || createdRepair.contact_phone || '—' }}</strong></div>
                    <div class="min-w-0 truncate"><span class="text-slate-500">Máy</span><strong class="ml-1 text-slate-800">{{ createdRepair.device_name || '—' }}</strong></div>
                    <div class="min-w-0 truncate"><span class="text-slate-500">IMEI</span><strong class="ml-1 font-mono text-indigo-700">{{ createdRepair.imei || createdRepair.serial || '—' }}</strong></div>
                </div>
            <button v-if="step === 'repair' && createdRepair" type="button" class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700" @click="progressModalRef?.openHistory()">
                🕘 Lịch sử sửa
            </button>
            </div>
            </div>
        </div>
        <RepairProgressModal
            v-if="step === 'repair' && createdRepair"
            ref="progressModalRef"
            :key="createdRepair.id"
            :repair="createdRepair"
            embedded
            @close="emit('close')"
            @updated="onProgressUpdated"
            @stage-change="onWorkflowStageChange"
            @completed="props.onCompleted?.($event)"
            @pay="props.onPay?.($event)"
        />
        <template v-else>
        <div class="grid grid-cols-1 items-start gap-3 text-xs lg:grid-cols-12 lg:gap-4">
        <div class="space-y-3 lg:col-span-8">
            
            <!-- Tra cứu nhanh gọn, dùng lại được sau khi đã chọn kết quả -->
            <div ref="searchLookupRef" class="relative z-50 px-1">
                <div class="space-y-2">
                    <div class="relative flex items-center gap-1.5">
                        <div class="relative min-w-0 flex-1">
                            <FloatingInput v-model="deviceSearchQuery" label="Tra cứu khách hàng, tên máy, IMEI / Serial" id="repair_unified_search" autocomplete="off" @keydown.enter.prevent="handleDeviceSearchEnter" @focus="deviceFocused = true; searchDeviceCatalog(deviceSearchQuery)" />
                            <div v-if="deviceFocused && deviceSearchQuery.trim().length >= 2" class="absolute left-0 right-0 top-full z-[200] mt-1 flex max-h-80 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/5">
                                <div class="min-h-0 flex-1 overflow-y-auto py-1.5">
                                <p v-if="customerSuggestions.length" class="sticky top-0 z-10 flex items-center gap-2 border-b border-blue-100 bg-blue-50/95 px-3.5 py-2 text-[10px] font-extrabold uppercase tracking-wider text-blue-800"><span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>Khách hàng<span class="rounded-full bg-white px-1.5 py-0.5 text-[9px] text-blue-700">{{ customerSuggestions.length }}</span></p>
                                <button v-for="customer in customerSuggestions" :key="`customer-${customer.id}`" type="button" class="block w-full border-b border-slate-100 px-3.5 py-2.5 text-left transition hover:bg-blue-50" @pointerdown.prevent="onSelectCustomer(customer)">
                                    <span class="flex items-center justify-between gap-2"><span class="truncate text-sm font-bold text-slate-900">{{ customer.full_name }} <span class="text-[11px] font-medium text-slate-500">· {{ customer.phone || 'Chưa có SĐT' }}</span></span><span v-if="Number(customer.debt_balance || 0) > 0" class="shrink-0 rounded-full border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700">Nợ {{ $money(customer.debt_balance) }}</span></span>
                                    <span class="mt-0.5 block text-[10px] text-slate-500">{{ customer.code || customer.cccd || 'Chọn làm khách tiếp nhận' }}</span>
                                </button>
                                <p v-if="matchingDeviceNames.length" class="sticky top-0 z-10 flex items-center gap-2 border-y border-indigo-100 bg-indigo-50/95 px-3.5 py-2 text-[10px] font-extrabold uppercase tracking-wider text-indigo-800"><span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>Tên máy<span class="rounded-full bg-white px-1.5 py-0.5 text-[9px] text-indigo-700">{{ matchingDeviceNames.length }}</span></p>
                                <div v-for="device in matchingDeviceNames" :key="`model-${device.name}-${device.imei || ''}-${device.source}`" class="border-b border-slate-200 last:border-0">
                                    <button v-for="purchaser in (device.purchasers || [])" :key="`model-buyer-${device.imei}-${purchaser.sale_item_id}`" type="button" class="group block w-full border-l-2 border-transparent px-3.5 py-2.5 text-left transition hover:border-indigo-500 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-55" :disabled="isImeiBlocked(device.imei)" @pointerdown.prevent="selectDeviceForPurchaser(device, purchaser)">
                                        <span class="flex items-center justify-between gap-2"><span class="truncate text-sm font-extrabold text-slate-900">{{ device.name }} <span v-if="device.imei" class="ml-1 rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-indigo-700">{{ device.imei }}</span></span><span class="shrink-0 text-[9px] font-semibold text-slate-400">{{ device.source }}</span></span>
                                        <span class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 pl-3 text-[11px]"><span class="font-bold text-blue-800">{{ purchaser.full_name }}</span><span class="text-slate-500">đã mua ngày</span><span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-700">{{ displayWarrantyDate(purchaser.purchased_at) }}</span><span v-if="purchaser.warranty_expires_at" class="rounded border px-1.5 py-0.5 font-bold" :class="purchaser.warranty_active ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'">{{ purchaser.warranty_active ? 'BH đến' : 'Hết BH' }} {{ displayWarrantyDate(purchaser.warranty_expires_at) }}</span><span v-if="purchaser.warranty_voided_at" class="rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 font-bold text-rose-700">BH đã vô hiệu</span></span>
                                        <span v-if="isImeiBlocked(device.imei)" class="ml-3 mt-1 inline-flex rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-800">Đang xử lý · {{ activeRepairForImei(device.imei)?.code }}</span>
                                        <span v-if="device.in_stock" class="ml-3 mt-1.5 inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-100 px-2 py-1 text-[10px] font-extrabold text-amber-900">⚠ IMEI hiện còn trong kho · chưa bán</span>
                                    </button>
                                    <button v-if="!(device.purchasers || []).length" type="button" class="block w-full border-l-2 border-transparent px-3.5 py-2.5 text-left transition hover:border-indigo-500 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-55" :disabled="isImeiBlocked(device.imei)" @pointerdown.prevent="selectDevice(device)">
                                        <span class="flex items-center justify-between gap-2"><span class="truncate text-sm font-extrabold text-slate-900">{{ device.name }} <span v-if="device.imei" class="ml-1 rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-indigo-700">{{ device.imei }}</span></span><span v-if="device.in_stock" class="rounded-md border border-amber-300 bg-amber-100 px-2 py-1 text-[10px] font-extrabold text-amber-900">⚠ Còn trong kho</span></span>
                                        <span class="mt-1 block pl-3 text-[11px] text-slate-600"><template v-if="device.customer?.full_name"><strong class="text-blue-800">{{ device.customer.full_name }}</strong> · </template>{{ device.source }}<template v-if="device.source_date"> · {{ displayWarrantyDate(device.source_date) }}</template><strong v-if="isImeiBlocked(device.imei)" class="ml-2 text-rose-700">· Đang xử lý {{ activeRepairForImei(device.imei)?.code }}</strong></span>
                                    </button>
                                </div>
                                <p v-if="matchingImeis.length" class="sticky top-0 z-10 flex items-center gap-2 border-y border-violet-100 bg-violet-50/95 px-3.5 py-2 text-[10px] font-extrabold uppercase tracking-wider text-violet-800"><span class="h-1.5 w-1.5 rounded-full bg-violet-600"></span>IMEI / Serial<span class="rounded-full bg-white px-1.5 py-0.5 text-[9px] text-violet-700">{{ matchingImeis.length }}</span></p>
                                <div v-for="device in matchingImeis" :key="`imei-${device.name}-${device.imei}-${device.source}`" class="border-b border-slate-200 px-1.5 py-1 last:border-0">
                                    <button v-for="purchaser in (device.purchasers || [])" :key="`${device.imei}-${purchaser.sale_item_id}`" type="button" class="block w-full rounded-lg border-l-2 border-transparent px-2.5 py-2 text-left transition hover:border-violet-500 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-55" :disabled="isImeiBlocked(device.imei)" @pointerdown.prevent="selectDeviceForPurchaser(device, purchaser)">
                                        <span class="block text-sm font-extrabold text-slate-900">{{ device.name || 'Chưa có tên máy' }} <span class="ml-1 rounded-md bg-violet-100 px-1.5 py-0.5 font-mono text-[11px] text-violet-800">{{ device.imei }}</span></span>
                                        <span class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 pl-3 text-[11px]"><span class="font-bold text-blue-800">{{ purchaser.full_name }}</span><span class="text-slate-500">đã mua ngày</span><span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-700">{{ displayWarrantyDate(purchaser.purchased_at) }}</span><span v-if="purchaser.warranty_expires_at" class="rounded border px-1.5 py-0.5 font-bold" :class="purchaser.warranty_active ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'">{{ purchaser.warranty_active ? 'BH đến' : 'Hết BH' }} {{ displayWarrantyDate(purchaser.warranty_expires_at) }}</span><span v-if="purchaser.warranty_voided_at" class="rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 font-bold text-rose-700">BH đã vô hiệu</span></span>
                                        <span v-if="isImeiBlocked(device.imei)" class="ml-3 mt-1 inline-flex rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-800">Đang xử lý · {{ activeRepairForImei(device.imei)?.code }}</span>
                                    </button>
                                    <button v-if="!(device.purchasers || []).length" type="button" class="flex w-full items-center justify-between gap-2 rounded-lg border-l-2 border-transparent px-2.5 py-2 text-left transition hover:border-violet-500 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-55" :disabled="isImeiBlocked(device.imei)" @pointerdown.prevent="selectDevice(device)">
                                        <span class="min-w-0"><span class="block truncate text-sm font-extrabold text-slate-900">{{ device.name || 'Thiết bị chưa có tên' }}</span><span class="mt-0.5 block font-mono text-[11px] font-bold text-violet-800">{{ device.imei }}</span></span>
                                        <span v-if="device.in_stock" class="shrink-0 rounded-md border border-amber-300 bg-amber-100 px-2 py-1 text-[10px] font-extrabold text-amber-900">⚠ Còn trong kho</span>
                                        <span v-else class="shrink-0 text-[10px] font-medium text-slate-500">{{ device.source }}</span>
                                    </button>
                                </div>
                                <p v-if="deviceSearching" class="px-3 py-2 text-[10px] text-slate-400">Đang tìm khách hàng và thiết bị...</p>
                                <p v-else-if="!customerSuggestions.length && !matchingDeviceNames.length && !matchingImeis.length" class="px-3 py-2 text-[10px] text-slate-500">Chưa có kết quả phù hợp. Chọn mục muốn tạo mới ở bên dưới.</p>
                                </div>
                                <div v-if="!deviceSearching" class="grid shrink-0 grid-cols-1 gap-1.5 border-t border-slate-200 bg-slate-50 p-2 sm:grid-cols-3">
                                    <button type="button" class="rounded-xl border border-blue-200 bg-white px-2.5 py-2 text-left text-[10px] font-bold text-blue-800 shadow-sm transition hover:border-blue-400 hover:bg-blue-50" @pointerdown.prevent="useNewCustomer"><span class="block text-[9px] font-semibold uppercase tracking-wide text-blue-500">Tạo mới</span><span class="mt-0.5 block truncate">+ Khách “{{ deviceSearchQuery }}”</span></button>
                                    <button type="button" class="rounded-xl border border-indigo-200 bg-white px-2.5 py-2 text-left text-[10px] font-bold text-indigo-800 shadow-sm transition hover:border-indigo-400 hover:bg-indigo-50" @pointerdown.prevent="addDeviceByName"><span class="block text-[9px] font-semibold uppercase tracking-wide text-indigo-500">Tạo mới</span><span class="mt-0.5 block truncate">+ Thiết bị “{{ deviceSearchQuery }}”</span></button>
                                    <button type="button" class="rounded-xl border border-violet-200 bg-white px-2.5 py-2 text-left text-[10px] font-bold text-violet-800 shadow-sm transition hover:border-violet-400 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-55" :disabled="isImeiBlocked(deviceSearchQuery)" @pointerdown.prevent="addDeviceByImei"><span class="block text-[9px] font-semibold uppercase tracking-wide text-violet-500">{{ isImeiBlocked(deviceSearchQuery) ? 'Đang có phiếu' : 'Tạo mới' }}</span><span class="mt-0.5 block truncate">+ IMEI “{{ deviceSearchQuery }}”</span></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" @click="startScan" title="Quét IMEI từ Camera" class="flex shrink-0 items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 px-3 text-indigo-600 transition hover:bg-indigo-100 active:bg-indigo-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h0.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89L18 7h1a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                    <div v-if="scanning" class="relative overflow-hidden rounded-xl border bg-slate-900"><video ref="videoRef" class="h-32 w-full object-cover"/><button type="button" @click="stopScan" class="absolute right-2 top-2 rounded-lg bg-rose-600 px-2 py-0.5 text-[10px] text-white">Tắt camera</button></div>
                    <div v-if="isImeiBlocked(deviceSearchQuery)" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[10px] font-bold text-rose-800">
                        IMEI này đang được xử lý trong phiếu {{ activeRepairForImei(deviceSearchQuery)?.code }}. Không thể tạo phiếu mới.
                    </div>
                    <div v-if="typedImeiWarranty" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold text-emerald-800">
                        Khách đang chọn còn bảo hành đến {{ displayWarrantyDate(typedImeiWarranty.warranty.expires_at) }}
                    </div>
                </div>
            </div>

            <section class="overflow-visible rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between gap-2 rounded-t-xl border-b border-slate-100 bg-slate-50 px-3.5 py-2.5">
                    <h2 class="text-[11px] font-bold uppercase tracking-wide text-slate-800">♙ Thông tin khách hàng</h2>
                    <div v-if="selectedCustomer" class="flex items-center gap-2 text-[10px]">
                        <span v-if="Number(selectedCustomer.debt_balance || 0) > 0" class="font-semibold text-rose-600">Nợ {{ $money(selectedCustomer.debt_balance) }}</span>
                        <button type="button" class="text-slate-400 hover:text-rose-600" @click="clearCustomer">Bỏ chọn</button>
                    </div>
                </header>
                <div class="grid grid-cols-1 gap-2.5 p-3 sm:grid-cols-2">
                    <FloatingInput v-model="form.customer_name" label="Tên khách hàng *" id="repair_customer_name" :error="form.errors.customer_name" required />
                    <FloatingInput v-model="form.customer_phone" label="Số điện thoại" id="repair_customer_phone" :error="form.errors.customer_phone" />
                    <FloatingInput v-model="form.contact_phone" label="SĐT liên hệ khác" id="repair_contact_phone" :error="form.errors.contact_phone" />
                    <FloatingInput v-model="form.identity_card" label="CCCD / CMND" id="repair_identity_card" :error="form.errors.identity_card" />
                </div>
            </section>

            <!-- 3. THÔNG TIN THIẾT BỊ & BẢO MẬT -->
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

                    <div v-if="selectedCustomer && !selectedDevice" class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-2.5">
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
                                <span v-if="device.warranty?.active && !device.warranty?.voided_at" class="mt-1 block text-[10px] font-bold text-emerald-700">
                                    Còn bảo hành đến {{ displayWarrantyDate(device.warranty.expires_at) }}
                                </span>
                            </button>
                        </div>
                        <p v-else-if="!customerDeviceLoading" class="text-[10px] text-slate-500">Chưa có thiết bị trong lịch sử. Có thể tìm hoặc nhập máy mới bên dưới.</p>
                    </div>

                    <div v-if="selectedDevice" class="flex items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50/70 px-3 py-2 text-[10px]">
                        <span class="truncate font-semibold text-blue-800">{{ selectedDevice.source || (isNewDevice ? 'Thiết bị mới' : 'Đã chọn thiết bị') }}</span>
                        <button type="button" @click="clearSelectedDevice" class="shrink-0 text-blue-500 hover:text-rose-600" title="Bỏ chọn thiết bị">Bỏ chọn</button>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <FloatingInput v-model="form.device_name" label="Tên máy / Model *" id="device_name" :error="form.errors.device_name" required />
                        <FloatingInput v-model="form.imei" label="Số IMEI / Serial" id="imei" :error="form.errors.imei" />
                    </div>
                    <p v-if="newImeiConfirmed" class="text-[10px] text-emerald-700">IMEI chưa có trong hệ thống; bạn có thể nhập tên và các thông tin hiện tại của máy.</p>
                    <p v-if="form.errors.device_name && !selectedDevice" class="text-[11px] font-medium text-rose-500">{{ form.errors.device_name }}</p>
                    <div class="border-t border-slate-100 pt-2.5">
                        <button type="button" class="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left transition hover:bg-slate-50" :aria-expanded="securityExpanded" @click="securityExpanded = !securityExpanded">
                            <span><strong class="block text-[11px] font-bold text-slate-700">Mật khẩu & tài khoản máy</strong><small class="text-[10px] text-slate-500">{{ securityExpanded ? 'Thu gọn thông tin bảo mật' : 'Mở rộng khi cần nhập hoặc xem thông tin' }}</small></span>
                            <span class="text-slate-400">{{ securityExpanded ? '−' : '+' }}</span>
                        </button>
                    </div>
                    <div v-if="securityExpanded" class="space-y-3">
                        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-2.5">
                            <FloatingSelect v-model="lockType" label="Kiểu khóa màn hình" id="repair_lock_type" :options="lockTypeOptions" />
                            <FloatingInput v-if="['pin', 'password'].includes(lockType)" v-model="form.screen_password" :type="lockType === 'password' ? 'password' : 'text'" :inputmode="lockType === 'pin' ? 'numeric' : 'text'" :label="lockType === 'pin' ? 'Mã PIN màn hình' : 'Mật khẩu màn hình'" id="repair_screen_password" />
                            <FloatingSelect v-model="form.account_type" label="Loại tài khoản máy" id="repair_account_type" :options="accountTypeOptions" />
                            <FloatingInput v-if="form.account_type" v-model="form.account_email" label="Email / SĐT tài khoản" id="repair_account_email" />
                            <FloatingInput v-if="form.account_type" v-model="form.account_password" type="password" label="Mật khẩu tài khoản" id="repair_account_password" />
                        </div>
                        <div v-if="lockType === 'pattern'" class="flex flex-col items-center border-t border-slate-100 pt-3 text-center">
                            <label class="mb-2 block text-[11px] font-semibold text-slate-600">Vẽ mẫu hình khóa màn hình</label>
                            <div class="inline-flex justify-center rounded-xl border border-slate-200 bg-slate-50/70 p-2"><PatternLock v-model="form.screen_pattern" /></div>
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

                        <div ref="issueLookupRef" class="relative">
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
                                    @pointerdown.prevent="selectIssue(item)"
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
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-dashed border-blue-300 bg-blue-50/50 p-2.5">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-700">Ghi nhận ngoại quan</p>
                        <p class="mt-0.5 text-[10px] text-slate-500">Chọn nhiều ảnh hoặc thêm ảnh vào danh sách hiện tại.</p>
                    </div>
                    <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <input type="file" multiple accept="image/*" class="hidden" @change="handleImages" />
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7a2 2 0 012-2h2l1.2 1.5H18a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V7z" />
                            <circle cx="12" cy="13" r="3" stroke-width="1.8" />
                        </svg>
                        <span>{{ imagePreviews.length ? 'Thêm ảnh' : 'Chọn ảnh' }}</span>
                    </label>
                    </div>
                    <div v-if="imagePreviews.length" class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <div v-for="(image, index) in imagePreviews" :key="index" class="group relative aspect-square overflow-hidden rounded-lg border border-slate-200">
                            <img :src="image" alt="Ảnh ngoại quan máy" class="h-full w-full cursor-zoom-in object-cover" @click="lightboxImage = image" />
                            <button type="button" @click.stop="removeImage(index)" class="absolute right-1 top-1 rounded-full bg-rose-600 p-1 text-white opacity-0 shadow group-hover:opacity-100" aria-label="Xóa ảnh">×</button>
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

        <div v-if="lightboxImage" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/90 p-6" @click.self="lightboxImage = null">
            <button type="button" class="absolute right-5 top-5 rounded-full bg-white/15 px-3 py-2 text-xl text-white hover:bg-white/25" aria-label="Đóng ảnh xem trước" @click="lightboxImage = null">×</button>
            <img :src="lightboxImage" alt="Ảnh ngoại quan phóng to" class="max-h-full max-w-full rounded-lg object-contain shadow-2xl" @click.stop />
        </div>

        <!-- FOOTER ACTIONS -->
        </template>
        <template #footer>
            <div v-if="step === 'intake'" class="flex items-center justify-end gap-2">
                <ActionButton variant="secondary" @click="emit('close')">
                    Hủy bỏ
                </ActionButton>

                <ActionButton :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Đang lưu...' : createdRepair ? 'Lưu thay đổi' : 'Tiếp tục' }}
                </ActionButton>
            </div>
            <div v-else class="flex w-full items-center justify-between gap-2">
                <button v-if="progressModalRef?.footerShowCancel" type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50" :disabled="progressModalRef?.footerBusy" @click="progressModalRef.cancelRepair()">Hủy phiếu</button>
                <span v-else />
                <div class="flex items-center gap-2">
                    <ActionButton v-if="progressModalRef?.footerShowBack" variant="secondary" @click="progressModalRef.returnToProgress()">Quay lại tiến trình</ActionButton>
                    <ActionButton v-if="!progressModalRef?.footerShowBack && ['pending', 'repairing', 'done'].includes(createdRepair?.status)" variant="secondary" @click="step = 'intake'">Quay lại để sửa</ActionButton>
                    <ActionButton variant="secondary" @click="progressModalRef?.closeProgress()">Đóng</ActionButton>
                    <ActionButton v-if="progressModalRef?.footerShowSave" type="button" :disabled="progressModalRef?.footerBusy" @click="progressModalRef.saveProgress()">{{ progressModalRef?.footerActionLabel }}</ActionButton>
                </div>
            </div>
        </template>
    </BaseModal>
</template>
