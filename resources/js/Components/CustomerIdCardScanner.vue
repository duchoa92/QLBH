<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { BrowserQRCodeReader } from '@zxing/browser'
import { DecodeHintType } from '@zxing/library'
import { Camera, CameraOff, ImagePlus } from 'lucide-vue-next'
import { parseVietnameseIdCardQr } from '@/utils/vietnameseIdCard'

defineOptions({ inheritAttrs: false })

const emit = defineEmits(['read', 'search'])
const open = ref(false)
const video = ref(null)
const loading = ref(false)
const error = ref('')
const reader = new BrowserQRCodeReader(new Map([
    [DecodeHintType.TRY_HARDER, true],
]), { delayBetweenScanAttempts: 100, delayBetweenScanSuccess: 180 })
let controls = null
let stream = null
let session = 0
let scanTimer = null
let fallbackTimer = null
let detector = null

const stop = () => {
    session += 1
    loading.value = false
    try { controls?.stop() } catch (_) {}
    controls = null
    try { reader.reset() } catch (_) {}
    if (scanTimer) clearTimeout(scanTimer)
    scanTimer = null
    if (fallbackTimer) clearTimeout(fallbackTimer)
    fallbackTimer = null
    detector = null
    stream?.getTracks?.().forEach(track => { try { track.stop() } catch (_) {} })
    stream = null
    if (video.value) {
        video.value.pause()
        video.value.srcObject = null
    }
}

const handleQr = raw => {
    const details = parseVietnameseIdCardQr(raw)
    if (details) {
        emit('read', details)
        emit('search', details.cccd)
        stop()
        open.value = false
        return
    }

    const idNumber = String(raw || '').trim().replace(/\D/g, '')
    if (/^\d{9,12}$/.test(idNumber)) {
        emit('search', idNumber)
        stop()
        open.value = false
        return
    }

    error.value = 'Đã đọc được QR nhưng dữ liệu không đúng định dạng CCCD. Hãy thử quét lại mã QR ở mặt sau thẻ.'
}

const startZxingFallback = async currentSession => {
    if (currentSession !== session || controls) return
    detector = null
    if (scanTimer) clearTimeout(scanTimer)
    scanTimer = null
    if (fallbackTimer) clearTimeout(fallbackTimer)
    fallbackTimer = null

    try {
        controls = await reader.decodeFromVideoElement(video.value, result => {
            if (currentSession === session && result) handleQr(result.getText())
        })
    } catch (cause) {
        if (currentSession !== session) return
        error.value = cause?.message || 'Không khởi động được bộ đọc QR. Hãy thử lại hoặc chọn ảnh CCCD.'
        stop()
    }
}

const start = async () => {
    open.value = true
    error.value = ''
    loading.value = true
    const currentSession = ++session
    await nextTick()
    try {
        if (!globalThis.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            throw new Error('Camera cần HTTPS hoặc localhost. Hãy kiểm tra quyền camera của trình duyệt.')
        }
        const NativeDetector = globalThis.BarcodeDetector
        let supportsNativeQr = Boolean(NativeDetector)
        if (supportsNativeQr && NativeDetector.getSupportedFormats) {
            try { supportsNativeQr = (await NativeDetector.getSupportedFormats()).includes('qr_code') } catch (_) { supportsNativeQr = false }
        }
        if (supportsNativeQr) detector = new NativeDetector({ formats: ['qr_code'] })

        stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
                ...(supportsNativeQr ? { frameRate: { ideal: 30 } } : {}),
            },
        })
        if (currentSession !== session) {
            stream.getTracks().forEach(track => track.stop())
            return
        }
        video.value.srcObject = stream
        await video.value.play()
        const track = stream.getVideoTracks()[0]
        try {
            const capabilities = track.getCapabilities?.()
            const focus = capabilities?.focusMode?.includes('continuous') ? { focusMode: 'continuous' } : {}
            const maxZoom = capabilities?.zoom?.max
            const zoom = maxZoom > 1 ? { zoom: Math.min(1.5, maxZoom) } : {}
            if (Object.keys(focus).length || Object.keys(zoom).length) {
                await track.applyConstraints({ advanced: [{ ...focus, ...zoom }] })
            }
        } catch (_) {}

        if (detector) {
            const scan = async () => {
                if (currentSession !== session || !detector || !video.value || video.value.readyState < 2) return
                try {
                    const codes = await detector.detect(video.value)
                    if (currentSession === session && detector && codes[0]?.rawValue) {
                        handleQr(codes[0].rawValue)
                        return
                    }
                } catch (_) { /* Keep scanning if the current frame is not decodable. */ }
                if (currentSession === session && detector) scanTimer = setTimeout(scan, 100)
            }
            fallbackTimer = setTimeout(() => startZxingFallback(currentSession), 900)
            scan()
        } else {
            await startZxingFallback(currentSession)
        }
        loading.value = false
    } catch (cause) {
        if (currentSession !== session) return
        error.value = cause?.message || 'Không mở được camera. Hãy cho phép trình duyệt dùng camera rồi thử lại.'
        stop()
    }
}

const scanImage = async event => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    error.value = ''
    loading.value = true
    const imageUrl = URL.createObjectURL(file)
    try {
        const result = await reader.decodeFromImageUrl(imageUrl)
        handleQr(result.getText())
    } catch (_) {
        error.value = 'Không đọc được QR trong ảnh. Hãy chọn ảnh rõ, thấy trọn mã QR ở mặt sau CCCD.'
    } finally {
        URL.revokeObjectURL(imageUrl)
        loading.value = false
    }
}

const close = () => {
    stop()
    open.value = false
    error.value = ''
}

onBeforeUnmount(stop)
</script>

<template>
    <button v-bind="$attrs" type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100" title="Quét QR CCCD" aria-label="Quét QR CCCD" @click="start">
        <Camera class="h-4 w-4" />
    </button>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[1300] flex items-center justify-center bg-slate-950/70 p-3" @click.self="close">
            <section class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="cccd-qr-title">
                <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <h2 id="cccd-qr-title" class="font-bold text-slate-900">Quét QR trên CCCD</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Đưa mã QR ở mặt sau thẻ vào giữa khung hình</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Tắt camera" aria-label="Đóng quét CCCD" @click="close"><CameraOff class="h-5 w-5" /></button>
                </header>
                <div class="space-y-3 p-4">
                    <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-950">
                        <video ref="video" autoplay muted playsinline class="h-full w-full object-cover" />
                        <div class="pointer-events-none absolute inset-[10%] rounded-xl border-2 border-emerald-400 shadow-[0_0_0_999px_rgba(15,23,42,0.25)]" />
                    </div>
                    <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>
                    <p v-else class="text-center text-sm text-slate-600">{{ loading ? 'Đang mở camera…' : 'Đưa QR lại gần để chiếm phần lớn khung hình; giữ thẻ thẳng, đủ sáng và tránh lóa.' }}</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <label class="relative inline-flex cursor-pointer items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><ImagePlus class="h-4 w-4" />Chọn ảnh CCCD<input type="file" accept="image/*" class="absolute inset-0 cursor-pointer opacity-0" @change="scanImage" /></label>
                        <button v-if="error" type="button" class="rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50" @click="start">Quét lại</button>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="close">Đóng</button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>
