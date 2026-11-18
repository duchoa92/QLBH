<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Camera, CircleAlert, ImagePlus, LoaderCircle, ScanFace, Trash2, Video, X, Zap } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import BaseModal from '@/Components/UI/BaseModal.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import api from '@/Services/api'
import {
    blobToCanvas, cameraErrorMessage, detectFace, detectStill, faceStatusMessage, loadFaceModels, makeThumbnail, startCamera, stopCamera,
} from '@/utils/faceRecognition'

/**
 * Đăng ký khuôn mặt từ nhiều nguồn cùng lúc: camera tại quầy, ảnh tải lên/chụp bằng điện thoại,
 * ảnh chụp từ camera an ninh. Các mẫu từ mọi nguồn gom chung (tối đa 5) rồi lưu một lần.
 * Chỉ vector đặc trưng được gửi lên máy chủ; ảnh chỉ dùng tạm trên trình duyệt.
 *
 * Camera tại quầy và camera an ninh TỰ QUÉT: thấy đúng một khuôn mặt rõ nét, giữ yên đủ lâu thì tự chụp mẫu, rồi nhắc đổi
 * góc mặt cho mẫu kế tiếp, đến khi đủ số mẫu thì tự dừng. Không cần bấm tay (vẫn có nút "Chụp ngay" dự phòng).
 */
const props = defineProps({
    customer: { type: Object, required: true },
})
const emit = defineEmits(['close', 'updated'])

const MAX_SAMPLES = 5 // số ảnh/mẫu tối đa; mỗi ảnh tĩnh kèm thêm bản lật gương nên tối đa 10 vector
const AUTO_STEADY_FRAMES = 2 // số lần dò liên tiếp thấy mặt rõ nét thì mới tự chụp (tránh chụp lúc đang quay đầu)
const AUTO_GAP_MS = 1500 // nghỉ giữa hai lần tự chụp để khách kịp đổi góc mặt
const AUTO_BLUR_FLOOR = 10 // độ nét tối thiểu của vùng mặt (cùng ngưỡng với nhận diện ở POS)
const CCTV_GAP_MS = 3500 // camera an ninh: giới hạn máy chủ 20 yêu cầu/phút nên không quét dày hơn
const CCTV_MAX_MISSES = 25 // quét liên tiếp từng đó lần mà không có mặt dùng được thì tự dừng
const POSES = ['Nhìn thẳng vào camera', 'Xoay mặt nhẹ sang trái', 'Xoay mặt nhẹ sang phải', 'Ngẩng mặt nhẹ', 'Cúi mặt nhẹ']
const MIN_VECTORS = 2 // 1 ảnh tĩnh (2 vector) hoặc 2 mẫu camera là đủ để lưu
const featureOn = computed(() => !!usePage().props.settings?.face_recognition_enabled)

const video = ref(null)
const fileInput = ref(null)
const cameraOn = ref(false)
const cameraStarting = ref(false)
const busy = ref(false)
const consent = ref(true) // DEMO: mặc định đồng ý, sửa lại thành false khi chạy thật
const registered = ref(false)
const consentedAt = ref('')
const cctvConfigured = ref(false)
const profileEngines = ref({ 'human-v1': 0, 'sface-v1': 0 })
const sfaceEnrollment = ref({ state: 'idle' })
const sfaceEnrolling = ref(false)
const samples = ref([]) // { id, source, descriptors[], thumb, lowQuality }
const autoCapture = ref(true) // camera tại quầy: tự quét và tự chụp
const scanState = ref('idle') // idle | none | multiple | small | blurry | hold: màu viền khung camera
const flash = ref(false)
const cctvScanning = ref(false)
const message = ref('')
const messageTone = ref('info')
let stream = null
let nextId = 1
let sfaceStatusTimer = null

const say = (text, tone = 'info') => { message.value = text; messageTone.value = tone }
const toneClass = { info: 'text-slate-600', warn: 'text-amber-600', error: 'text-rose-600', ok: 'text-emerald-600' }
const sourceLabel = { camera: 'Camera', photo: 'Ảnh', cctv: 'An ninh' }
const sourceBadge = { camera: 'bg-blue-600', photo: 'bg-emerald-600', cctv: 'bg-violet-600' }
const isFull = computed(() => samples.value.length >= MAX_SAMPLES)
const vectors = computed(() => samples.value.flatMap(sample => sample.descriptors))
const canSave = computed(() => featureOn.value && consent.value && vectors.value.length >= MIN_VECTORS && !busy.value)

const loadStatus = async () => {
    try {
        const { data } = await api.get(`/api/customers/${props.customer.id}/face`)
        registered.value = !!data.registered
        consentedAt.value = data.consented_at || ''
        cctvConfigured.value = !!data.cctv_configured
        profileEngines.value = data.engines || { 'human-v1': 0, 'sface-v1': 0 }
        sfaceEnrollment.value = data.sface_enrollment || { state: 'idle' }
        if (sfaceEnrolling.value && sfaceEnrollment.value.state === 'completed') {
            sfaceEnrolling.value = false
            clearInterval(sfaceStatusTimer)
            say(`Đã lưu ${sfaceEnrollment.value.samples || 0} mẫu YuNet + SFace từ camera cửa.`, 'ok')
            toast.success('Đã thêm mẫu SFace để kiểm chứng.')
        } else if (sfaceEnrolling.value && sfaceEnrollment.value.state === 'idle') {
            sfaceEnrolling.value = false
            clearInterval(sfaceStatusTimer)
            say('Lấy mẫu SFace đã hết hạn. Hãy để một người nhìn camera rồi thử lại.', 'warn')
        }
    } catch { /* vẫn cho đăng ký */ }
}

onMounted(() => {
    loadStatus()
    if (!featureOn.value) say('Tính năng nhận diện khuôn mặt đang tắt.', 'warn')
})
onBeforeUnmount(() => {
    stopAuto()
    stopCctvScan()
    clearInterval(sfaceStatusTimer)
    stopCamera(stream, video.value)
})

const wait = ms => new Promise(resolve => setTimeout(resolve, ms))

const addSample = (source, result, media) => {
    samples.value.push({
        id: nextId++,
        source,
        // Ảnh tĩnh có thêm vector lật gương; camera trực tiếp chỉ có 1 vector.
        descriptors: [result.descriptor, result.descriptorFlip].filter(Boolean),
        thumb: makeThumbnail(media),
        // Mặt nhỏ trong ảnh (thường gặp ở camera an ninh) vẫn dùng được nhưng kém chính xác hơn.
        lowQuality: result.ratio < 0.2,
    })
}

const removeSample = (id) => { samples.value = samples.value.filter(sample => sample.id !== id) }

/* ---------- Nguồn 1: camera tại quầy ---------- */
const toggleCamera = async () => {
    if (cameraOn.value) {
        stopAuto()
        stopCamera(stream, video.value)
        stream = null
        cameraOn.value = false
        return
    }
    cameraStarting.value = true
    try {
        await loadFaceModels()
        cameraOn.value = true
        await new Promise(resolve => setTimeout(resolve, 0)) // chờ thẻ video hiện ra
        stream = await startCamera(video.value)
        if (autoCapture.value && !isFull.value) startAuto()
        else say('Đề nghị khách nhìn thẳng vào camera rồi bấm "Chụp ngay".')
    } catch (error) {
        cameraOn.value = false
        say(cameraErrorMessage(error), 'error')
    } finally {
        cameraStarting.value = false
    }
}

const captureFromCamera = async () => {
    if (!cameraOn.value || busy.value || isFull.value) return
    busy.value = true
    try {
        for (let attempt = 0; attempt < 8; attempt++) {
            const result = await detectFace(video.value, { minFaceRatio: 0.28 })
            if (result.status === 'ok') {
                addSample('camera', result, video.value)
                say(`Đã thêm mẫu từ camera (${samples.value.length}/${MAX_SAMPLES}). Xoay mặt nhẹ rồi chụp thêm.`, 'ok')
                return
            }
            say({ ...faceStatusMessage, small: 'Hãy lại gần camera hơn.' }[result.status], 'warn')
            await wait(400)
        }
        say('Không chụp được. Kiểm tra ánh sáng và vị trí đứng.', 'error')
    } finally {
        busy.value = false
    }
}

/* ---------- Tự quét + tự chụp bằng camera tại quầy ---------- */
let autoTimer = null
let autoRunning = false
let steady = 0
let lastCaptureAt = 0

const nextPose = () => POSES[Math.min(samples.value.length, POSES.length - 1)]
const autoHint = computed(() => (cameraOn.value && autoCapture.value && !isFull.value ? nextPose() : ''))

const flashOnce = () => {
    flash.value = true
    setTimeout(() => { flash.value = false }, 180)
}

const stopAuto = () => {
    autoRunning = false
    clearTimeout(autoTimer)
    autoTimer = null
    steady = 0
    scanState.value = 'idle'
}

const autoTick = async () => {
    if (!autoRunning) return
    let delay = 350
    try {
        if (document.hidden || !video.value || video.value.readyState < 2 || busy.value) {
            delay = 400
        } else {
            const result = await detectFace(video.value, { minFaceRatio: 0.28 })
            if (!autoRunning) return
            const now = Date.now()
            if (result.status !== 'ok') {
                steady = 0
                scanState.value = result.status
                say({
                    none: 'Chưa thấy khuôn mặt. Đứng vào giữa khung hình.',
                    multiple: 'Chỉ để một người trong khung hình.',
                    small: 'Hãy lại gần camera hơn.',
                }[result.status] || 'Đang tìm khuôn mặt…', 'warn')
            } else if (result.sharpness < AUTO_BLUR_FLOOR) {
                steady = 0
                scanState.value = 'blurry'
                say('Ảnh hơi mờ, giữ yên mặt hoặc thêm ánh sáng.', 'warn')
            } else if (now - lastCaptureAt < AUTO_GAP_MS) {
                scanState.value = 'hold'
                say(`Đã chụp ${samples.value.length}/${MAX_SAMPLES}. Tiếp theo: ${nextPose().toLowerCase()}.`, 'ok')
            } else {
                steady += 1
                scanState.value = 'hold'
                if (steady >= AUTO_STEADY_FRAMES) {
                    steady = 0
                    lastCaptureAt = now
                    addSample('camera', result, video.value)
                    flashOnce()
                    if (isFull.value) {
                        stopAuto()
                        say(`Đã đủ ${MAX_SAMPLES} mẫu. Bấm "Lưu" để hoàn tất.`, 'ok')
                        return
                    }
                    say(`Đã chụp ${samples.value.length}/${MAX_SAMPLES}. Tiếp theo: ${nextPose().toLowerCase()}.`, 'ok')
                } else {
                    say('Giữ yên, đang chụp…')
                }
            }
        }
    } catch {
        delay = 800 // lỗi dò tạm thời: thử lại, không làm gián đoạn
    }
    if (autoRunning) autoTimer = setTimeout(autoTick, delay)
}

const startAuto = () => {
    if (autoRunning || !cameraOn.value || isFull.value) return
    autoRunning = true
    steady = 0
    lastCaptureAt = Date.now() - AUTO_GAP_MS // lần đầu chụp ngay khi mặt đã rõ
    say(`${nextPose()}. Hệ thống tự chụp khi mặt rõ nét.`)
    autoTimer = setTimeout(autoTick, 200)
}

watch(autoCapture, (on) => { on ? startAuto() : (stopAuto(), cameraOn.value && say('Đã tắt tự động. Bấm "Chụp ngay" để chụp từng mẫu.')) })
// Xóa bớt mẫu khi đã đủ thì quét tiếp; đủ mẫu thì tự dừng cả hai chế độ.
watch(isFull, (full) => {
    if (full) { stopAuto(); stopCctvScan() } else if (cameraOn.value && autoCapture.value) startAuto()
})

/* ---------- Nguồn 2 & 3: ảnh tải lên, ảnh camera an ninh ---------- */
const addFromBlob = async (blob, source, label) => {
    const canvas = await blobToCanvas(blob)
    // Ảnh tĩnh: dò mặt kỹ hơn, tự phóng to mặt nhỏ, kèm bản lật gương. Mặt nhỏ vẫn nhận nhưng đánh dấu chất lượng thấp.
    const result = await detectStill(canvas)
    if (result.status !== 'ok') throw new Error(`${label}: ${faceStatusMessage[result.status]}`)
    addSample(source, result, canvas)
}

const onFilesPicked = async (event) => {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    if (!files.length || busy.value) return
    busy.value = true
    let added = 0
    const problems = []
    try {
        for (const file of files) {
            if (isFull.value) { problems.push('Đã đủ số mẫu tối đa.'); break }
            try {
                await addFromBlob(file, 'photo', file.name)
                added += 1
            } catch (error) {
                problems.push(error?.message || `${file.name}: không đọc được ảnh.`)
            }
        }
        if (added) say(`Đã thêm ${added} mẫu từ ảnh.${problems.length ? ' ' + problems[0] : ''}`, problems.length ? 'warn' : 'ok')
        else say(problems[0] || 'Không thêm được mẫu nào.', 'error')
    } finally {
        busy.value = false
    }
}

const readRequestError = async (error) => {
    let text = error?.message
    const blob = error?.response?.data
    if (blob instanceof Blob) {
        try { text = JSON.parse(await blob.text()).message || text } catch { /* giữ nguyên */ }
    }
    return text
}

// Camera an ninh: bấm một lần là tự lấy ảnh liên tục, thấy đúng một khuôn mặt dùng được thì tự thêm mẫu, đủ mẫu thì dừng.
let cctvToken = 0
const stopCctvScan = () => { cctvToken += 1; cctvScanning.value = false }

/* ---------- Nguồn 4: YuNet + SFace trực tiếp từ Camera cửa ---------- */
const startSfaceEnrollment = async () => {
    if (busy.value || sfaceEnrolling.value) return
    if (!consent.value) { say('Cần xác nhận khách đã đồng ý trước khi lấy mẫu SFace.', 'warn'); return }
    busy.value = true
    try {
        await api.post(`/api/customers/${props.customer.id}/face-sface-enrollment`, { consent: true })
        sfaceEnrolling.value = true
        sfaceEnrollment.value = { state: 'pending' }
        say('Đang lấy 3 mẫu YuNet + SFace từ Camera cửa. Mời một khách đứng nhìn camera trong vài giây.', 'info')
        clearInterval(sfaceStatusTimer)
        sfaceStatusTimer = setInterval(loadStatus, 1200)
    } catch (error) {
        say(error?.response?.data?.message || 'Không thể bắt đầu lấy mẫu SFace từ Camera cửa.', 'error')
    } finally {
        busy.value = false
    }
}

const toggleCctvScan = async () => {
    if (cctvScanning.value) {
        stopCctvScan()
        say('Đã dừng quét camera an ninh.')
        return
    }
    if (busy.value || isFull.value) return
    const token = ++cctvToken
    cctvScanning.value = true
    let misses = 0
    let requestErrors = 0
    say('Đang quét camera an ninh… Khách đứng trước camera, hệ thống tự chụp mẫu.')

    while (token === cctvToken && !isFull.value) {
        let delay = CCTV_GAP_MS
        try {
            const response = await api.get('/api/customers/face-cctv-snapshot', { responseType: 'blob' })
            if (token !== cctvToken) break
            requestErrors = 0
            await addFromBlob(response.data, 'cctv', 'Ảnh camera an ninh')
            misses = 0
            say(isFull.value
                ? `Đã đủ ${MAX_SAMPLES} mẫu. Bấm "Lưu" để hoàn tất.`
                : `Đã tự chụp mẫu từ camera an ninh (${samples.value.length}/${MAX_SAMPLES}). Mời khách ${nextPose().toLowerCase()}.`, 'ok')
        } catch (error) {
            if (token !== cctvToken) break
            const text = await readRequestError(error)
            if (error?.response) {
                // Lỗi từ máy chủ/camera (chưa cấu hình, camera không trả ảnh...): thử lại vài lần rồi dừng hẳn.
                requestErrors += 1
                if (error.response.status === 429) delay = 6000
                else if (requestErrors >= 3) { say(text || 'Không lấy được ảnh từ camera an ninh.', 'error'); break }
            } else {
                misses += 1
                if (misses >= CCTV_MAX_MISSES) { say('Không thấy khuôn mặt dùng được nên đã dừng quét. Bấm lại để quét tiếp.', 'warn'); break }
            }
            if (token === cctvToken) say(`${text || 'Chưa lấy được mặt.'} Đang quét tiếp…`, 'warn')
        }
        if (token === cctvToken) await wait(delay)
    }
    if (token === cctvToken) cctvScanning.value = false
}

/* ---------- Lưu / xóa ---------- */
const save = async () => {
    if (!canSave.value) return
    stopAuto()
    stopCctvScan()
    busy.value = true
    try {
        await api.post(`/api/customers/${props.customer.id}/face`, {
            consent: true,
            descriptors: vectors.value,
        })
        registered.value = true
        consentedAt.value = new Date().toISOString()
        // Đã lưu xong: tắt camera để không tự chụp thêm mẫu mới khi danh sách mẫu vừa được làm trống.
        if (cameraOn.value) { stopCamera(stream, video.value); stream = null; cameraOn.value = false }
        samples.value = []
        say('Đã lưu dữ liệu nhận diện cho khách.', 'ok')
        toast.success('Đã đăng ký khuôn mặt khách hàng.')
        emit('updated')
    } catch (error) {
        const errors = error?.response?.data?.errors
        say(errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || 'Không lưu được dữ liệu.'), 'error')
    } finally {
        busy.value = false
    }
}

const remove = async () => {
    if (busy.value) return
    busy.value = true
    try {
        await api.delete(`/api/customers/${props.customer.id}/face`)
        registered.value = false
        consentedAt.value = ''
        say('Đã xóa dữ liệu nhận diện của khách.', 'ok')
        toast.success('Đã xóa dữ liệu khuôn mặt.')
        emit('updated')
    } catch (error) {
        say(error?.response?.data?.message || 'Không xóa được dữ liệu.', 'error')
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <BaseModal title="Đăng ký khuôn mặt" size="lg" @close="emit('close')">
        <div class="space-y-3">
            <div class="text-sm">
                <span class="font-bold text-slate-900">{{ customer.full_name }}</span>
                <span v-if="customer.phone" class="text-slate-500"> · {{ customer.phone }}</span>
                <span v-if="registered" class="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Đã đăng ký</span>
            </div>

            <div v-if="!featureOn" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                Tính năng nhận diện khuôn mặt đang <strong>tắt</strong>. Hãy bật tại
                <Link href="/settings?section=loyalty" class="font-bold underline">Thiết lập → Khách hàng</Link>
                rồi quay lại đăng ký. Bạn vẫn xóa được dữ liệu đã lưu.
            </div>

            <template v-if="featureOn">
                <!-- Chọn nguồn: dùng riêng từng nguồn hoặc kết hợp nhiều nguồn -->
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <button type="button" class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition" :class="cameraOn ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-700 hover:border-blue-300'" :disabled="cameraStarting" @click="toggleCamera">
                        <Video class="h-5 w-5 shrink-0" />
                        <span>{{ cameraStarting ? 'Đang mở camera…' : cameraOn ? 'Tắt camera' : 'Camera tại quầy' }}<small class="block text-[11px] font-normal text-slate-500">Tự quét và tự chụp khách đang đứng</small></span>
                    </button>
                    <button type="button" class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:border-emerald-300 disabled:opacity-50" :disabled="busy || isFull" @click="fileInput.click()">
                        <ImagePlus class="h-5 w-5 shrink-0 text-emerald-600" />
                        <span>Tải ảnh lên / chụp<small class="block text-[11px] font-normal text-slate-500">Chọn nhiều ảnh, hoặc chụp bằng điện thoại</small></span>
                    </button>
                    <button type="button" class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition disabled:opacity-50" :class="cctvScanning ? 'border-violet-300 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-700 hover:border-violet-300'" :disabled="(busy || isFull || !cctvConfigured) && !cctvScanning" :title="cctvConfigured ? '' : 'Chưa cấu hình camera an ninh trong Thiết lập → Khách hàng'" @click="toggleCctvScan">
                        <LoaderCircle v-if="cctvScanning" class="h-5 w-5 shrink-0 animate-spin" />
                        <Camera v-else class="h-5 w-5 shrink-0 text-violet-600" />
                        <span>{{ cctvScanning ? 'Đang quét… bấm để dừng' : 'Camera an ninh' }}<small class="block text-[11px] font-normal text-slate-500">{{ cctvConfigured ? 'Tự quét và tự chụp khi thấy khách' : 'Chưa cấu hình trong Thiết lập' }}</small></span>
                    </button>
                    <button type="button" class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition disabled:opacity-50" :class="sfaceEnrolling ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-700 hover:border-indigo-300'" :disabled="busy || sfaceEnrolling || !cctvConfigured" :title="cctvConfigured ? '' : 'Cần cấu hình và bật Camera cửa trước'" @click="startSfaceEnrollment">
                        <LoaderCircle v-if="sfaceEnrolling" class="h-5 w-5 shrink-0 animate-spin" />
                        <ScanFace v-else class="h-5 w-5 shrink-0 text-indigo-600" />
                        <span>{{ sfaceEnrolling ? 'Đang lấy mẫu SFace…' : 'YuNet + SFace' }}<small class="block text-[11px] font-normal text-slate-500">Lấy 3 mẫu riêng từ Camera cửa</small></span>
                    </button>
                </div>
                <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onFilesPicked" />

                <div v-show="cameraOn" class="relative overflow-hidden rounded-2xl bg-slate-900 ring-4 transition" :class="{ 'ring-emerald-400': scanState === 'hold', 'ring-amber-400': ['none', 'multiple', 'small', 'blurry'].includes(scanState), 'ring-transparent': scanState === 'idle' }">
                    <video ref="video" class="aspect-[4/3] max-h-72 w-full -scale-x-100 object-cover" playsinline muted></video>
                    <div v-if="flash" class="pointer-events-none absolute inset-0 bg-white/70"></div>
                    <span v-if="autoHint" class="pointer-events-none absolute left-1/2 top-3 max-w-[90%] -translate-x-1/2 truncate rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">{{ autoHint }} · {{ samples.length }}/{{ MAX_SAMPLES }}</span>
                    <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-xs font-bold shadow-lg" :class="autoCapture ? 'bg-emerald-500 text-white' : 'bg-white/90 text-slate-700'" :aria-pressed="autoCapture" @click="autoCapture = !autoCapture"><Zap class="h-3.5 w-3.5" />Tự động: {{ autoCapture ? 'Bật' : 'Tắt' }}</button>
                        <button type="button" class="rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-lg disabled:opacity-50" :disabled="busy || isFull" @click="captureFromCamera">Chụp ngay</button>
                    </div>
                </div>

                <!-- Các mẫu đã thu thập từ mọi nguồn -->
                <div>
                    <div class="mb-1.5 flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-700">Mẫu đã thu thập: {{ samples.length }}/{{ MAX_SAMPLES }}</span>
                        <span class="text-slate-400">1 ảnh rõ mặt là đủ; thêm ảnh nhiều góc sẽ nhận chính xác hơn</span>
                    </div>
                    <div v-if="samples.length" class="flex flex-wrap gap-2">
                        <div v-for="sample in samples" :key="sample.id" class="relative">
                            <img :src="sample.thumb" alt="" class="h-20 w-20 rounded-xl object-cover ring-1 ring-slate-200" />
                            <span class="absolute bottom-1 left-1 rounded px-1.5 py-0.5 text-[10px] font-bold text-white" :class="sourceBadge[sample.source]">{{ sourceLabel[sample.source] }}</span>
                            <span v-if="sample.lowQuality" class="absolute right-1 top-1 rounded-full bg-amber-500 p-0.5 text-white" title="Khuôn mặt nhỏ trong ảnh, độ chính xác thấp hơn"><CircleAlert class="h-3.5 w-3.5" /></span>
                            <button type="button" class="absolute -right-1.5 -top-1.5 rounded-full bg-slate-700 p-0.5 text-white shadow hover:bg-rose-600" aria-label="Bỏ mẫu này" @click="removeSample(sample.id)"><X class="h-3.5 w-3.5" /></button>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-400">Chưa có mẫu nào. Chọn một hoặc nhiều nguồn ở trên để thêm.</div>
                </div>
            </template>

            <p class="min-h-[1.25rem] text-sm font-medium" :class="toneClass[messageTone]">{{ message }}</p>

            <label v-if="featureOn" class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs leading-snug text-amber-900">
                <input v-model="consent" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-amber-300 text-blue-600" />
                <span>
                    Khách <strong>đã đồng ý</strong> cho cửa hàng lưu dữ liệu nhận diện khuôn mặt để nhận ra khách khi đến mua hàng
                    (áp dụng cho cả ảnh tải lên và ảnh từ camera an ninh). Hệ thống chỉ lưu dữ liệu đặc trưng, <strong>không lưu ảnh</strong>.
                    Khách có thể yêu cầu xóa bất cứ lúc nào.
                </span>
            </label>
            <p v-if="registered && consentedAt" class="text-xs text-slate-400">Đồng ý lúc {{ new Date(consentedAt).toLocaleString('vi-VN') }}. Lưu mẫu Human mới chỉ thay mẫu Human; mẫu SFace được giữ riêng để kiểm chứng.</p>
            <p v-if="registered && consentedAt" class="text-xs text-slate-400">Mẫu đang có: Human {{ profileEngines['human-v1'] || 0 }} · YuNet + SFace {{ profileEngines['sface-v1'] || 0 }}. Hai nhóm được lưu và so khớp riêng.</p>
        </div>

        <template #footer>
            <div class="flex w-full flex-wrap items-center justify-between gap-2">
                <button v-if="registered" type="button" :disabled="busy" class="inline-flex items-center gap-1 rounded-xl px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50" @click="remove">
                    <Trash2 class="h-4 w-4" />Xóa dữ liệu khuôn mặt
                </button>
                <span v-else></span>
                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="emit('close')">Đóng</button>
                    <ActionButton :disabled="!canSave" @click="save">
                        <ScanFace class="h-4 w-4" />{{ busy ? 'Đang xử lý…' : `Lưu ${samples.length} mẫu` }}
                    </ActionButton>
                </div>
            </div>
        </template>
    </BaseModal>
</template>
