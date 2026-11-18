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
import { ScanFace, RefreshCw, User, UserRoundPlus, UsersRound } from 'lucide-vue-next'
import api from '@/Services/api'
import CreateCustomerModal from './CreateCustomerModal.vue'
import { customerService } from '../Services/customerService'
import { openModal } from '@/Stores/modal'
import CustomerFormModal from '@/Pages/Customers/CustomerFormModal.vue'
import CustomerIdCardScanner from '@/Components/CustomerIdCardScanner.vue'
import FaceSuggestionPanel from './FaceSuggestionPanel.vue'
import FaceDetectedModal from './FaceDetectedModal.vue'
import { usePage } from '@inertiajs/vue3'
import { useFaceSource } from '../Composables/useFaceSource'

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

// Nguồn nhận diện: đã bật camera cửa thì ưu tiên camera cửa (POS không mở camera, chỉ đọc "ai đang ở cửa");
// chỉ khi worker không chạy / camera cửa lỗi mới tự mở camera máy làm dự phòng (xem useFaceSource).
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
    queue: faceQueue,
    markServed: markFaceServed,
    statusText: faceStatusText,
    usingLocal: faceUsingLocal,
    doorMode: faceDoorMode,
    setDoorVideo: setFaceDoorVideo,
    doorVideoOk: faceDoorOk,
    attachPreview: attachFacePreview,
    toggle: toggleFace,
    switchCamera: switchFaceCamera,
    confirm: confirmFace,
    dismiss: dismissFace,
    dismissAll: dismissAllFaces,
} = useFaceSource({
    paused: () => !!props.customer || !!keyword.value.trim(),
    currentCustomerId: () => props.customer?.id || null,
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

// Hàng chờ khách (nhận diện): bấm khách quen để chọn, bấm khách chưa có dữ liệu để nhập thông tin.
const queueTarget = ref(null)
const openQueueItem = (item) => {
    if (props.customer) return
    if (item.kind === 'known') {
        confirmFace(item)
        return
    }
    queueTarget.value = item
    showCreateCustomerModal.value = true
}
const closeCreateModal = () => {
    showCreateCustomerModal.value = false
    queueTarget.value = null
}
// Gán lượt khách (và khuôn mặt nếu khách đồng ý) vào khách vừa tạo.
const linkQueueTarget = async (customer, consent) => {
    const target = queueTarget.value
    queueTarget.value = null
    if (!target) return
    if (target.visitId) {
        try {
            await api.post(`/api/customers/visits/${target.visitId}/link`, { customer_id: customer.id, consent: !!consent })
        } catch (error) {
            console.error(error)
        }
    } else if (consent && target.descriptor) {
        // Chưa kịp ghi lượt khách: vẫn lưu khuôn mặt cho khách vừa tạo (cùng cơ chế "xác nhận đúng người").
        api.post(`/api/customers/${customer.id}/face-feedback`, { descriptor: target.descriptor }).catch(() => {})
    }
    markFaceServed(target, customer)
}

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
    queueTarget.value = null
    if (identityPrefill.value) openFullCustomerModal(identityPrefill.value)
    else showCreateCustomerModal.value = true

    nextTick(() => {
        addRowOpen.value = false
        customers.value = []
    })
}


// Lưu KH
const saveCustomer = async (
    payload
) => {

    try {

        const { face_consent: faceConsent, ...data } = payload || {}

        const customer =

            await customerService.create(
                data
            )

        await linkQueueTarget(customer, faceConsent)

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

                linkQueueTarget(customer, true) // DEMO: mặc định đồng ý lưu khuôn mặt
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
                        v-if="faceActive && faceUsingLocal"
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

                    <!-- Camera cửa: vòng tròn xem trước ảnh cửa (cập nhật liên tục), viền đổi màu theo trạng thái nhận diện -->
                    <div
                        v-if="faceActive && faceDoorMode"
                        class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 ring-2"
                        :class="faceFailed || !faceDoorOk ? 'ring-rose-400' : facePreviewRing"
                        :title="faceStatusText || 'Camera cửa'"
                    >
                        <!-- Keep the video element mounted during negotiation. -->
                        <video :ref="setFaceDoorVideo" v-show="faceDoorOk" autoplay muted playsinline class="h-full w-full object-cover" aria-label="Camera cửa"></video>
                        <ScanFace v-show="!faceDoorOk" class="h-5 w-5" :class="faceFailed ? 'text-rose-500' : 'text-slate-400'" />
                    </div>

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

        <!-- Hàng chờ: những khách đứng sau trước camera (đã nhận ra hoặc chưa có dữ liệu) -->
        <div v-if="faceActive && faceQueue.length" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-2">
            <div class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                <UsersRound class="h-3.5 w-3.5" />
                Khách đang chờ ({{ faceQueue.length }})
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="item in faceQueue"
                    :key="item.trackId"
                    type="button"
                    :disabled="!!props.customer"
                    class="flex min-h-11 max-w-[11rem] shrink-0 items-center gap-2 rounded-lg border bg-white px-2 py-1 text-left transition active:scale-95 disabled:opacity-60"
                    :class="item.kind === 'known' ? 'border-emerald-200' : 'border-amber-300'"
                    :title="props.customer ? 'Hoàn tất đơn hiện tại trước' : (item.kind === 'known' ? 'Chọn khách này' : 'Khách chưa có dữ liệu — bấm để nhập thông tin')"
                    @click="openQueueItem(item)"
                >
                    <img v-if="item.thumb" :src="item.thumb" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
                    <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500"><User class="h-4 w-4" /></span>
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-semibold text-slate-800">{{ item.kind === 'known' ? item.customer.full_name : 'Khách mới' }}</span>
                        <span class="block truncate text-[11px]" :class="item.kind === 'known' ? 'text-emerald-600' : 'text-amber-600'">{{ item.kind === 'known' ? 'Đã nhận ra' : 'Chưa có dữ liệu' }}</span>
                    </span>
                </button>
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
    :keyword="queueTarget ? '' : keyword"
    :face-thumb="queueTarget?.thumb || ''"
    :ask-face-consent="!!queueTarget"
    @close="closeCreateModal"
    @save="saveCustomer"
    @full-detail="openFullCustomerModal"
/>

</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
