<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ScanFace } from 'lucide-vue-next'
import api from '@/Services/api'
import { cameraErrorMessage, detectFace, loadFaceApi, startCamera, stopCamera } from '@/utils/faceRecognition'

/**
 * Tự nhận ra khách đã đăng ký khuôn mặt khi họ đứng trước camera POS.
 * Chỉ GỢI Ý: nhân viên bấm "Đúng" thì mới chọn khách, tránh ghi nhầm công nợ/bảo hành.
 * Bật/tắt được và nhớ lựa chọn theo từng máy.
 */
const props = defineProps({
    // Đã có khách trong đơn thì tạm dừng nhận diện.
    paused: { type: Boolean, default: false },
})
const emit = defineEmits(['confirm'])

const STORAGE_KEY = 'pos.faceCheckIn'
const FRAME_INTERVAL = 700
const STABLE_FRAMES = 3
const SUGGESTION_TTL = 20000
const DISMISS_COOLDOWN = 60000

const readEnabled = () => { try { return localStorage.getItem(STORAGE_KEY) === '1' } catch { return false } }
// Tính năng có được chủ cửa hàng bật trong Cài đặt không (mặc định tắt).
const featureOn = computed(() => !!usePage().props.settings?.face_recognition_enabled)
const enabled = ref(readEnabled())
const video = ref(null)
const status = ref('')
const suggestion = ref(null)
let suggestionDescriptor = null // vector của lần nhận diện đang gợi ý, dùng để tự học khi nhân viên xác nhận
const failed = ref(false)

let stream = null
let loop = null
let busy = false
let stable = 0
let lastRequestAt = 0
let suggestionTimer = null
const dismissed = new Map() // customerId -> thời điểm bỏ qua

const statusText = computed(() => {
    if (failed.value) return status.value
    if (!enabled.value) return 'Nhận diện khách bằng camera: tắt'
    if (props.paused) return 'Đã chọn khách'
    return status.value || 'Đang chờ khách…'
})

const clearSuggestion = () => { suggestion.value = null; suggestionDescriptor = null; clearTimeout(suggestionTimer) }

const tick = async () => {
    if (busy || document.hidden || props.paused || suggestion.value || !video.value) return
    busy = true
    try {
        const result = await detectFace(video.value)
        if (result.status !== 'ok') {
            stable = 0
            status.value = result.status === 'multiple' ? 'Có nhiều người trước camera' : 'Đang chờ khách…'
            return
        }

        stable += 1
        if (stable < STABLE_FRAMES || Date.now() - lastRequestAt < 3000) return

        lastRequestAt = Date.now()
        stable = 0
        status.value = 'Đang nhận diện…'
        const { data } = await api.post('/api/customers/face-match', { descriptor: result.descriptor })

        if (!data.matched || !data.customer) {
            status.value = 'Khách chưa đăng ký khuôn mặt'
            return
        }

        const skippedAt = dismissed.get(data.customer.id)
        if (skippedAt && Date.now() - skippedAt < DISMISS_COOLDOWN) {
            status.value = 'Đang chờ khách…'
            return
        }

        suggestion.value = data.customer
        suggestionDescriptor = result.descriptor
        status.value = 'Đã nhận ra khách'
        clearTimeout(suggestionTimer)
        suggestionTimer = setTimeout(clearSuggestion, SUGGESTION_TTL)
    } catch (error) {
        // Lỗi mạng tạm thời thì thử lại ở khung hình sau, không làm phiền nhân viên.
        if (!error?.response) status.value = 'Đang chờ khách…'
    } finally {
        busy = false
    }
}

const start = async () => {
    await nextTick()
    if (!featureOn.value || !video.value) return
    failed.value = false
    status.value = 'Đang mở camera…'
    try {
        await loadFaceApi()
        stream = await startCamera(video.value)
        status.value = 'Đang chờ khách…'
        loop = setInterval(tick, FRAME_INTERVAL)
    } catch (error) {
        failed.value = true
        status.value = cameraErrorMessage(error)
        stop()
    }
}

const stop = () => {
    clearInterval(loop)
    loop = null
    stopCamera(stream, video.value)
    stream = null
    stable = 0
    clearSuggestion()
}

const toggle = () => {
    enabled.value = !enabled.value
    try { localStorage.setItem(STORAGE_KEY, enabled.value ? '1' : '0') } catch { /* bỏ qua */ }
}

watch(enabled, (value) => { value ? start() : stop() })
watch(featureOn, (value) => { value && enabled.value ? start() : stop() })

const confirm = () => {
    const customer = suggestion.value
    const descriptor = suggestionDescriptor
    clearSuggestion()
    if (!customer) return
    emit('confirm', customer)
    // Nhân viên xác nhận đúng người: lưu thêm mẫu này để lần sau nhận chính xác hơn (máy chủ tự kiểm tra và giới hạn số mẫu).
    if (descriptor) api.post(`/api/customers/${customer.id}/face-feedback`, { descriptor }).catch(() => {})
}

const dismiss = () => {
    if (suggestion.value) dismissed.set(suggestion.value.id, Date.now())
    clearSuggestion()
}

onMounted(() => { if (enabled.value) start() })
onBeforeUnmount(stop)
</script>

<template>
    <div v-if="featureOn" class="mt-1.5">
        <div class="flex items-center gap-2 text-[11px] text-slate-500">
            <span class="relative flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100">
                <video v-show="enabled && !failed" ref="video" class="absolute inset-0 h-full w-full -scale-x-100 object-cover" playsinline muted></video>
                <ScanFace v-if="!enabled || failed" class="h-3.5 w-3.5 text-slate-400" />
            </span>
            <span class="min-w-0 flex-1 truncate" :class="failed ? 'text-rose-600' : ''" :title="statusText">{{ statusText }}</span>
            <button
                type="button"
                role="switch"
                :aria-checked="enabled"
                class="relative h-5 w-9 shrink-0 rounded-full transition-colors"
                :class="enabled ? 'bg-blue-600' : 'bg-slate-300'"
                title="Bật/tắt nhận diện khách bằng camera"
                @click="toggle"
            >
                <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all" :class="enabled ? 'left-[18px]' : 'left-0.5'"></span>
            </button>
        </div>

        <div v-if="suggestion" class="mt-1.5 flex items-center gap-2 rounded-xl bg-blue-50 px-2.5 py-1.5 ring-1 ring-blue-200">
            <div class="min-w-0 flex-1 text-xs leading-tight text-slate-700">
                Có phải <strong class="text-slate-900">{{ suggestion.full_name }}</strong>
                <span v-if="suggestion.phone" class="text-slate-500"> ({{ suggestion.phone }})</span>
                <strong v-if="Number(suggestion.debt_balance) > 0" class="ml-1 whitespace-nowrap text-rose-600">Nợ {{ $money(suggestion.debt_balance) }}</strong>?
            </div>
            <button type="button" class="rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-blue-700" @click="confirm">Đúng</button>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-white" @click="dismiss">Không</button>
        </div>
    </div>
</template>
