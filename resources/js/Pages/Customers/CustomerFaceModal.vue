<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Camera, CircleAlert, ImagePlus, ScanFace, Trash2, Video, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import BaseModal from '@/Components/UI/BaseModal.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import api from '@/Services/api'
import {
    blobToCanvas, cameraErrorMessage, detectFace, detectStill, faceStatusMessage, loadFaceApi, makeThumbnail, startCamera, stopCamera,
} from '@/utils/faceRecognition'

/**
 * Đăng ký khuôn mặt từ nhiều nguồn cùng lúc: camera tại quầy, ảnh tải lên/chụp bằng điện thoại,
 * ảnh chụp từ camera an ninh. Các mẫu từ mọi nguồn gom chung (tối đa 5) rồi lưu một lần.
 * Chỉ vector đặc trưng được gửi lên máy chủ; ảnh chỉ dùng tạm trên trình duyệt.
 */
const props = defineProps({
    customer: { type: Object, required: true },
})
const emit = defineEmits(['close', 'updated'])

const MAX_SAMPLES = 5 // số ảnh/mẫu tối đa; mỗi ảnh tĩnh kèm thêm bản lật gương nên tối đa 10 vector
const MIN_VECTORS = 2 // 1 ảnh tĩnh (2 vector) hoặc 2 mẫu camera là đủ để lưu
const featureOn = computed(() => !!usePage().props.settings?.face_recognition_enabled)

const video = ref(null)
const fileInput = ref(null)
const cameraOn = ref(false)
const cameraStarting = ref(false)
const busy = ref(false)
const consent = ref(false)
const registered = ref(false)
const consentedAt = ref('')
const cctvConfigured = ref(false)
const samples = ref([]) // { id, source, descriptors[], thumb, lowQuality }
const message = ref('')
const messageTone = ref('info')
let stream = null
let nextId = 1

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
    } catch { /* vẫn cho đăng ký */ }
}

onMounted(() => {
    loadStatus()
    if (!featureOn.value) say('Tính năng nhận diện khuôn mặt đang tắt.', 'warn')
})
onBeforeUnmount(() => stopCamera(stream, video.value))

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
        stopCamera(stream, video.value)
        stream = null
        cameraOn.value = false
        return
    }
    cameraStarting.value = true
    try {
        await loadFaceApi()
        cameraOn.value = true
        await new Promise(resolve => setTimeout(resolve, 0)) // chờ thẻ video hiện ra
        stream = await startCamera(video.value)
        say('Đề nghị khách nhìn thẳng vào camera rồi bấm "Chụp mẫu".')
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

const fetchFromCctv = async () => {
    if (busy.value || isFull.value) return
    busy.value = true
    say('Đang lấy ảnh từ camera an ninh…')
    try {
        const response = await api.get('/api/customers/face-cctv-snapshot', { responseType: 'blob' })
        await addFromBlob(response.data, 'cctv', 'Ảnh camera an ninh')
        say(`Đã thêm mẫu từ camera an ninh (${samples.value.length}/${MAX_SAMPLES}).`, 'ok')
    } catch (error) {
        let text = error?.message
        const blob = error?.response?.data
        if (blob instanceof Blob) {
            try { text = JSON.parse(await blob.text()).message || text } catch { /* giữ nguyên */ }
        }
        say(text || 'Không lấy được ảnh từ camera an ninh.', 'error')
    } finally {
        busy.value = false
    }
}

/* ---------- Lưu / xóa ---------- */
const save = async () => {
    if (!canSave.value) return
    busy.value = true
    try {
        await api.post(`/api/customers/${props.customer.id}/face`, {
            consent: true,
            descriptors: vectors.value,
        })
        registered.value = true
        consentedAt.value = new Date().toISOString()
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
                <div class="grid gap-2 sm:grid-cols-3">
                    <button type="button" class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition" :class="cameraOn ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-700 hover:border-blue-300'" :disabled="cameraStarting" @click="toggleCamera">
                        <Video class="h-5 w-5 shrink-0" />
                        <span>{{ cameraStarting ? 'Đang mở camera…' : cameraOn ? 'Tắt camera' : 'Camera tại quầy' }}<small class="block text-[11px] font-normal text-slate-500">Chụp trực tiếp khách đang đứng</small></span>
                    </button>
                    <button type="button" class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:border-emerald-300 disabled:opacity-50" :disabled="busy || isFull" @click="fileInput.click()">
                        <ImagePlus class="h-5 w-5 shrink-0 text-emerald-600" />
                        <span>Tải ảnh lên / chụp<small class="block text-[11px] font-normal text-slate-500">Chọn nhiều ảnh, hoặc chụp bằng điện thoại</small></span>
                    </button>
                    <button type="button" class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:border-violet-300 disabled:opacity-50" :disabled="busy || isFull || !cctvConfigured" :title="cctvConfigured ? '' : 'Chưa cấu hình camera an ninh trong Thiết lập → Khách hàng'" @click="fetchFromCctv">
                        <Camera class="h-5 w-5 shrink-0 text-violet-600" />
                        <span>Camera an ninh<small class="block text-[11px] font-normal text-slate-500">{{ cctvConfigured ? 'Lấy ảnh chụp hiện tại' : 'Chưa cấu hình trong Thiết lập' }}</small></span>
                    </button>
                </div>
                <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onFilesPicked" />

                <div v-show="cameraOn" class="relative overflow-hidden rounded-2xl bg-slate-900">
                    <video ref="video" class="aspect-[4/3] max-h-72 w-full -scale-x-100 object-cover" playsinline muted></video>
                    <button type="button" class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-lg disabled:opacity-50" :disabled="busy || isFull" @click="captureFromCamera">Chụp mẫu</button>
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
            <p v-if="registered && consentedAt" class="text-xs text-slate-400">Đồng ý lúc {{ new Date(consentedAt).toLocaleString('vi-VN') }}. Lưu mẫu mới sẽ thay toàn bộ mẫu cũ.</p>
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
