<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { BarcodeFormat, BrowserMultiFormatReader } from '@zxing/browser'
import { DecodeHintType } from '@zxing/library'
import { Camera, CameraOff } from 'lucide-vue-next'

defineOptions({ inheritAttrs: false })

const emit = defineEmits(['scanned'])
const open = ref(false)
const video = ref(null)
const scanning = ref(false)
const cameraReady = ref(false)
const error = ref('')
let controls = null
let reader = null
let activeStream = null
let scanSession = 0

const resultIsInsideScanArea = (result) => {
    const points = result?.getResultPoints?.() || []
    const element = video.value
    const videoWidth = element?.videoWidth
    const videoHeight = element?.videoHeight
    const frame = element?.getBoundingClientRect()

    if (!points.length || !videoWidth || !videoHeight || !frame?.width || !frame?.height) return false

    // Match the square guide to the visible video when object-fit: cover crops
    // the camera frame (common on phone cameras).
    const scale = Math.max(frame.width / videoWidth, frame.height / videoHeight)
    const cropX = (videoWidth * scale - frame.width) / 2
    const cropY = (videoHeight * scale - frame.height) / 2
    const boundary = frame.width * 0.12
    const minX = boundary
    const maxX = frame.width - boundary
    const minY = boundary
    const maxY = frame.height - boundary

    return points.every(point => {
        const x = point.getX() * scale - cropX
        const y = point.getY() * scale - cropY
        return x >= minX && x <= maxX && y >= minY && y <= maxY
    })
}

const stop = () => {
    // Invalidate a pending getUserMedia request too (for example, if the modal
    // is closed while the browser is still asking for camera permission).
    scanSession += 1
    scanning.value = false
    cameraReady.value = false
    try { controls?.stop() } catch (_) {}
    controls = null
    try { reader?.reset() } catch (_) {}
    const element = video.value
    const stream = activeStream || element?.srcObject
    activeStream = null
    stream?.getTracks?.().forEach(track => {
        try { track.stop() } catch (_) {}
    })
    if (element) {
        element.pause()
        element.srcObject = null
    }
}

const start = async () => {
    open.value = true
    if (scanning.value) return
    error.value = ''
    scanning.value = true
    const session = ++scanSession
    await nextTick()
    try {
        if (!globalThis.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            throw new Error('Camera cần HTTPS hoặc localhost. Hãy mở trang bằng HTTPS và cho phép quyền camera.')
        }
        const hints = new Map([
            [DecodeHintType.TRY_HARDER, true],
            [DecodeHintType.POSSIBLE_FORMATS, [
                BarcodeFormat.EAN_13,
                BarcodeFormat.EAN_8,
                BarcodeFormat.UPC_A,
                BarcodeFormat.UPC_E,
                BarcodeFormat.CODE_128,
                BarcodeFormat.CODE_39,
                BarcodeFormat.CODE_93,
                BarcodeFormat.ITF,
                BarcodeFormat.CODABAR,
                BarcodeFormat.QR_CODE,
                BarcodeFormat.DATA_MATRIX,
                BarcodeFormat.PDF_417,
            ]],
        ])
        reader = new BrowserMultiFormatReader(hints, { delayBetweenScanAttempts: 70, delayBetweenScanSuccess: 250 })
        controls = await reader.decodeFromConstraints({
            audio: false,
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        }, video.value, result => {
            const code = result?.getText?.().trim()
            if (!code || !resultIsInsideScanArea(result)) return
            stop()
            open.value = false
            emit('scanned', code)
        })
        activeStream = video.value?.srcObject || null
        if (session !== scanSession || !open.value) {
            stop()
            return
        }
        await video.value?.play()
        if (session !== scanSession || !open.value) {
            stop()
            return
        }
        cameraReady.value = true
    } catch (cause) {
        error.value = cause?.message || 'Không mở được camera. Hãy kiểm tra quyền truy cập camera.'
        stop()
    }
}

const close = () => { stop(); open.value = false }
onBeforeUnmount(stop)
</script>

<template>
    <button v-bind="$attrs" type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100" title="Quét mã bằng camera" aria-label="Quét mã bằng camera" @click="start">
        <Camera class="h-4 w-4" />
    </button>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/70 p-4" @click.self="close">
            <section class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <h2 class="font-semibold text-slate-800">Quét mã sản phẩm</h2>
                    <button type="button" class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100" title="Tắt camera" aria-label="Tắt camera và đóng cửa sổ quét" @click="close"><CameraOff class="h-4 w-4" /><span>Tắt camera</span></button>
                </header>
                <div class="p-4">
                    <div class="relative mx-auto aspect-square w-[78vw] max-w-[460px] overflow-hidden rounded-lg bg-black">
                        <video ref="video" class="absolute inset-0 h-full w-full object-cover" autoplay muted playsinline webkit-playsinline />
                        <div class="absolute left-1/2 top-1/2 aspect-square w-[76%] -translate-x-1/2 -translate-y-1/2 rounded-xl border-2 border-emerald-400 shadow-[0_0_0_9999px_rgba(0,0,0,0.42)]">
                            <span class="absolute inset-x-2 top-1/2 border-t border-dashed border-emerald-200/90" />
                        </div>
                    </div>
                    <p v-if="error" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                    <p v-else class="mt-3 text-center text-sm text-slate-600">{{ cameraReady ? 'Đưa mã vào trong ô vuông để quét.' : 'Đang mở camera…' }}</p>
                    <button v-if="error" type="button" class="mt-3 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white" @click="start">Thử lại</button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
