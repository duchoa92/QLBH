<script setup>
import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
    nextTick,
    watch ,
} from 'vue'
import CustomerDebtModal from './CustomerDebtModal.vue'
import { customerDebtService } from '../Services/customerDebtService'
import { useCustomerSearch } from '@/Modules/POS/Customer/Composables/useCustomerSearch.js'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { ScanFace, RefreshCw, User, UserRoundPlus } from 'lucide-vue-next'
import CreateCustomerModal from './CreateCustomerModal.vue'
import { customerService } from '../Services/customerService'
import { openModal } from '@/Stores/modal'
import CustomerFormModal from '@/Pages/Customers/CustomerFormModal.vue'
import CustomerIdCardScanner from '@/Components/CustomerIdCardScanner.vue'
import FaceSuggestionPanel from './FaceSuggestionPanel.vue'
import FaceDetectedModal from './FaceDetectedModal.vue'
import { usePage } from '@inertiajs/vue3'
import { useFaceCheckIn } from '../Composables/useFaceCheckIn'

const page = usePage()

// Cài đặt → Khách hàng: hiện hộp thông báo chi tiết thay cho phần hỏi trong ô khách hàng.
const faceModalOn = computed(() => !!page.props.settings?.face_notify_modal_enabled)
// Phần hỏi trong ô khách chỉ thay thế ô nhập khi KHÔNG dùng hộp thoại.
const inlineFaceAsk = computed(() => faceSuggestions.value.length > 0 && !faceModalOn.value)

const emit = defineEmits([
    'selected',
])

const {
    keyword,
    customers,
    identityPrefill,
    search,
    selectCustomer,
    onIdentityCardScanned,
    clearCustomer,
    activeIndex,
    setItemRef,
    onKeyDown,
    setActive,
} = useCustomerSearch(emit)

const props = defineProps({

    customer: {

        type: Object,

        default: null,
    },
})

// Nhận diện khuôn mặt nằm ngay trong ô nhập tên: bật/tắt bằng nút cạnh nút quét CCCD, khung quét hiện trong ô,
// bước hỏi xác nhận thay thế ô nhập khi đã nhận ra khách. Tạm dừng khi đã chọn khách hoặc nhân viên đang gõ tìm.
const {
    featureOn: faceFeatureOn,
    active: faceActive,
    failed: faceFailed,
    facing: faceFacing,
    switching: faceSwitching,
    tone: faceTone,
    suggestions: faceSuggestions,
    statusText: faceStatusText,
    attachPreview: attachFacePreview,
    toggle: toggleFace,
    switchCamera: switchFaceCamera,
    confirm: confirmFace,
    dismiss: dismissFace,
    dismissAll: dismissAllFaces,
} = useFaceCheckIn({
    paused: () => !!props.customer || !!keyword.value.trim(),
    onConfirm: customer => selectCustomer(customer),
})

const facePreviewRing = computed(() => ({
    scanning: 'ring-blue-400 animate-pulse',
    found: 'ring-emerald-500',
    error: 'ring-rose-400',
    idle: 'ring-slate-300',
}[faceTone.value] || 'ring-slate-300'))

// Chừa chỗ cho các nút bên phải (quét CCCD, bật camera) để chữ không bị đè. Khung camera nằm ngoài ô nhập.
const searchInputClass = computed(() => {
    return faceFeatureOn.value ? 'pr-[5.25rem]' : 'pr-12'
})

watch(
    () => props.customer,

    customer => {

        if (customer) {

            keyword.value =
                customer.full_name

            return
        }

        keyword.value = ''
    },

    {
        immediate: true,
    }
)

// Modal nợ
const showDebtModal = ref(false)

// Danh sách nợ
const debts = ref([])

// Tổng nợ
const debtTotal = ref(0)


// Mở modal nợ
const openDebtModal = async () => {

    try {

        if (!props.customer?.id) {

            return
        }

        const response =
            await customerDebtService.getDebts(
                props.customer.id
            )

        debts.value =
            response.data.debts

        debtTotal.value =
            response.data.total

        showDebtModal.value = true

    } catch (error) {

        console.error(error)
    }
}

// Mở modal tạo KH
const showCreateCustomerModal = ref(false)

// Tạo ref cho vùng tìm kiếm
const customerSearchRef = ref(null)

// Vùng danh sách kết quả (nằm ngoài ô nhập): bấm vào đây không tính là "bấm ra ngoài".
const resultsRef = ref(null)

// Nút "Thêm KH mới" nằm dưới danh sách kết quả: hiện khi đang gõ tìm (kể cả khi không có kết quả).
const addRowOpen = ref(false)
const onSearchInput = () => {
    addRowOpen.value = true
    search()
}
const showResults = computed(() => customers.value.length > 0 || (addRowOpen.value && !!keyword.value.trim()))
// Mở modal trước, rồi mới đóng danh sách (đóng trước sẽ gỡ nút khỏi trang giữa lúc đang bấm).
const startCreateCustomer = () => {
    if (identityPrefill.value) openFullCustomerModal(identityPrefill.value)
    else showCreateCustomerModal.value = true

    nextTick(() => {
        addRowOpen.value = false
        customers.value = []
    })
}


// Lưu KH
const saveCustomer = async (
    data
) => {

    try {

        const customer =

            await customerService.create(
                data
            )

        selectCustomer(
            customer
        )

        showCreateCustomerModal.value =
            false

    } catch (error) {

        console.error(error)
    }
}

// Mở modal "Tạo chi tiết" (dùng chung với trang Khách hàng)
const openFullCustomerModal = (
    prefill
) => {

    showCreateCustomerModal.value =
        false

    openModal(CustomerFormModal, {

        props: {

            title: 'Thêm khách hàng mới',

            apiMode: true,

            customer: {

                ...(prefill || identityPrefill.value || {}),
            },
        },

        onUpdated: (customer) => {

            if (customer) {

                selectCustomer(customer)
            }
        },
    })
}

// Thêm hàm click ngoài
const handleClickOutside = (event) => {

    if (
        customerSearchRef.value &&
        !customerSearchRef.value.contains(event.target) &&
        !resultsRef.value?.contains(event.target)
    ) {

        customers.value = []
        addRowOpen.value = false
    }
}


// Đăng ký sự kiện
onMounted(() => {

    document.addEventListener(
        'click',
        handleClickOutside
    )
})
onBeforeUnmount(() => {

    document.removeEventListener(
        'click',
        handleClickOutside
    )
})


</script>

<template>
    <div>
        <!-- <div class="mb-1 text-xs font-bold uppercase tracking-wide">
            Khách hàng
        </div> -->

        <div class="relative">
            <div 
                v-if="props.customer" 
                class="flex min-h-[44px] items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-1 ring-blue-100">
                        <User :size="15" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-xs font-semibold leading-tight text-slate-900" :title="props.customer.full_name">
                            {{ props.customer.full_name }}<span v-if="props.customer.phone" class="font-normal text-slate-400"> - {{ props.customer.phone }}</span>
                            <button
                                v-if="Number(props.customer.debt_balance) > 0"
                                type="button"
                                @click.stop="openDebtModal"
                                class="ml-1 whitespace-nowrap text-[11px] font-extrabold text-rose-600 hover:text-rose-700"
                            >
                                (Nợ: {{ $money(props.customer.debt_balance) }})
                            </button>
                        </span>
                </div>

                <!-- Xóa khách hàng -->
                <button 
                    type="button" 
                    @click="clearCustomer" 
                    class="shrink-0 rounded-lg px-1.5 py-1 text-xs font-semibold text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-700"
                    title="Đổi khách hàng"
                >
                    Đổi
                </button>
            </div>

            <div v-else>
                <!-- Bước hỏi xác nhận: thay thế ô nhập khi đã nhận ra khách bằng khuôn mặt -->
                <FaceSuggestionPanel
                    v-if="inlineFaceAsk"
                    :suggestions="faceSuggestions"
                    @confirm="confirmFace"
                    @dismiss="dismissFace"
                    @dismiss-all="dismissAllFaces"
                />

                <div v-show="!inlineFaceAsk" ref="customerSearchRef" class="flex items-center gap-2">
                    <!-- Khung camera nằm riêng bên trái ô nhập (không che chữ). Nhấp vào để đổi camera trước / sau. -->
                    <button
                        v-if="faceActive"
                        type="button"
                        class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 ring-2 transition active:scale-95 disabled:opacity-60"
                        :class="facePreviewRing"
                        :disabled="faceSwitching"
                        :title="faceFacing === 'user' ? 'Đang dùng camera trước, nhấp để đổi sang camera sau' : 'Đang dùng camera sau, nhấp để đổi sang camera trước'"
                        :aria-label="faceFacing === 'user' ? 'Đổi sang camera sau' : 'Đổi sang camera trước'"
                        @click="switchFaceCamera"
                    >
                        <span class="absolute inset-0 overflow-hidden rounded-full">
                            <video v-show="!faceFailed" :ref="attachFacePreview" class="h-full w-full object-cover" :class="faceFacing === 'user' ? '-scale-x-100' : ''" autoplay muted playsinline></video>
                            <span v-if="faceFailed" class="flex h-full w-full items-center justify-center"><ScanFace class="h-5 w-5 text-rose-500" /></span>
                        </span>
                        <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-white shadow ring-2 ring-white">
                            <RefreshCw class="h-3 w-3" />
                        </span>
                    </button>

                    <div class="relative min-w-0 flex-1">
                        <FloatingInput
                            v-model="keyword"
                            :placeholder-text="faceActive ? faceStatusText : 'Khách lẻ'"
                            :placeholder-class="faceActive && faceFailed ? '!text-rose-500' : ''"
                            @input="onSearchInput"
                            @keydown="onKeyDown"
                            type="text"
                            label="Nhập tên, SĐT hoặc CCCD"
                            :input-class="searchInputClass"
                        />

                        <CustomerIdCardScanner class="absolute right-1 top-1/2 -translate-y-1/2 !h-8 !w-8" @read="onIdentityCardScanned" @search="keyword = $event; onSearchInput()" />

                        <!-- Bật/tắt nhận diện khuôn mặt (chỉ hiện khi chủ cửa hàng đã bật tính năng trong Cài đặt) -->
                        <button
                            v-if="faceFeatureOn"
                            type="button"
                            role="switch"
                            :aria-checked="faceActive"
                            :title="faceActive ? 'Tắt nhận diện khách bằng camera' : 'Bật nhận diện khách bằng camera'"
                            class="absolute right-10 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg border transition"
                            :class="faceActive ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700' : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100'"
                            @click="toggleFace"
                        >
                            <ScanFace class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Danh sách KH + nút thêm mới nằm dưới cùng danh sách kết quả -->
                <div
                    v-if="showResults && !inlineFaceAsk"
                    ref="resultsRef"
                    class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg"
                >
                    <div v-if="customers.length" class="custom-scrollbar max-h-56 overflow-auto">
                        <button
                            v-for="(customer, index) in customers"
                            :ref="el => setItemRef(el, index)"
                            @mouseenter="setActive(index)"
                            :key="customer.id"
                            type="button"
                            @click="selectCustomer(customer)"
                            :class="[
                                'block w-full border-b border-slate-100 p-2.5 text-left last:border-b-0 transition-colors',
                                index === activeIndex ? 'bg-blue-50' : 'hover:bg-slate-50'
                            ]"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate text-sm font-semibold text-slate-900">
                                    {{ customer.full_name }}
                                </span>
                                <span
                                    v-if="Number(customer.debt_balance || 0) > 0"
                                    class="shrink-0 whitespace-nowrap rounded-full bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700"
                                >
                                    Nợ {{ $money(customer.debt_balance) }}
                                </span>
                            </div>
                            <div class="mt-0.5 text-xs text-slate-500">
                                {{ customer.phone }}
                            </div>
                        </button>
                    </div>

                    <button
                        v-if="keyword.trim()"
                        type="button"
                        class="flex min-h-11 w-full items-center gap-2 border-t border-slate-200 bg-cyan-50/60 px-3 py-2 text-left text-sm font-semibold text-cyan-700 transition-colors hover:bg-cyan-50 hover:text-cyan-900"
                        @mousedown.prevent
                        @click.stop="startCreateCustomer"
                    >
                        <UserRoundPlus class="h-4 w-4 shrink-0" />
                        <span class="min-w-0 truncate">Thêm khách hàng mới “{{ keyword.trim() }}”</span>
                    </button>
                </div>
            </div>            
        </div>

    </div>

<FaceDetectedModal
    v-if="faceModalOn && faceSuggestions.length && !props.customer"
    :suggestions="faceSuggestions"
    @confirm="confirmFace"
    @dismiss="dismissFace"
    @dismiss-all="dismissAllFaces"
/>

<CustomerDebtModal

    :show="showDebtModal"

    :debts="debts"

    :total="debtTotal"

    @close="
        showDebtModal = false
    "
/>

<CreateCustomerModal
    :show="showCreateCustomerModal"
    :keyword="keyword"
    @close="showCreateCustomerModal = false"
    @save="saveCustomer"
    @full-detail="openFullCustomerModal"
/>

</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
