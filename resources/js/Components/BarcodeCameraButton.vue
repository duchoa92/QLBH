<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { scanImageData, setModuleArgs } from '@undecaf/zbar-wasm'
import { Camera, CameraOff, Flashlight, FlashlightOff } from 'lucide-vue-next'
import zbarWasmUrl from '@undecaf/zbar-wasm/dist/zbar.wasm?url'

defineOptions({ inheritAttrs: false })

setModuleArgs({ locateFile: filename => filename === 'zbar.wasm' ? zbarWasmUrl : filename })

const emit = defineEmits(['scanned'])
const open = ref(false)
const video = ref(null)
const photoInput = ref(null)
const galleryInput = ref(null)
const scanning = ref(false)
const cameraReady = ref(false)
const cameraSources = ref([])
const cameraSourcePickerOpen = ref(false)
const selectedCameraId = ref('')
const cameraWasManuallySelected = ref(false)
const readingPhoto = ref(false)
const torchAvailable = ref(false)
const torchOn = ref(false)
const zoomAvailable = ref(false)
const zoomMin = ref(1)
const zoomMax = ref(1)
const zoomLevel = ref(1)
const error = ref('')
let activeStream = null
let nativeScanTimer = null
let nativeFallbackTimer = null
let nativeScanBusy = false
let nativeFallbackStarted = false
let zbarScanTimer = null
let zbarScanBusy = false
let scanSession = 0

const withTimeout = (promise, message, timeout = 15000) => {
    let timer
    return Promise.race([
        promise,
        new Promise((_, reject) => { timer = setTimeout(() => reject(new Error(message)), timeout) }),
    ]).finally(() => clearTimeout(timer))
}

const acceptResult = (result, session) => {
    const code = result?.getText?.().trim()
    if (!code || session !== scanSession || !open.value) return
    stop()
    open.value = false
    emit('scanned', code)
}

const stop = () => {
    // Invalidate a pending getUserMedia request too (for example, if the modal
    // is closed while the browser is still asking for camera permission).
    scanSession += 1
    scanning.value = false
    cameraReady.value = false
    torchAvailable.value = false
    torchOn.value = false
    zoomAvailable.value = false
    zoomMin.value = 1
    zoomMax.value = 1
    zoomLevel.value = 1
    if (nativeScanTimer) clearTimeout(nativeScanTimer)
    if (nativeFallbackTimer) clearTimeout(nativeFallbackTimer)
    if (zbarScanTimer) clearTimeout(zbarScanTimer)
    nativeScanTimer = null
    nativeFallbackTimer = null
    zbarScanTimer = null
    nativeScanBusy = false
    nativeFallbackStarted = false
    zbarScanBusy = false
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

const scanVideoWithZBar = async (session) => {
    if (session !== scanSession || !open.value) return
    const element = video.value
    if (!zbarScanBusy && element?.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA && element.videoWidth && element.videoHeight) {
        zbarScanBusy = true
        try {
            const scale = Math.min(1, 1600 / Math.max(element.videoWidth, element.videoHeight))
            const canvas = document.createElement('canvas')
            canvas.width = Math.max(1, Math.round(element.videoWidth * scale))
            canvas.height = Math.max(1, Math.round(element.videoHeight * scale))
            const context = canvas.getContext('2d', { willReadFrequently: true })
            if (!context) throw new Error('canvas-unavailable')
            context.drawImage(element, 0, 0, canvas.width, canvas.height)
            const symbols = await withTimeout(scanImageData(context.getImageData(0, 0, canvas.width, canvas.height)), 'scan-timeout', 8000)
            const symbol = symbols.find(item => item.decode?.('utf-8')?.trim())
            if (symbol) {
                acceptResult({ getText: () => symbol.decode('utf-8').trim() }, session)
                return
            }
        } catch (_) {
            // Skip a frame when the device decoder cannot process it.
        } finally {
            zbarScanBusy = false
        }
    }
    if (session === scanSession && open.value) {
        zbarScanTimer = setTimeout(() => scanVideoWithZBar(session), 140)
    }
}

const configureCameraTrack = async (track) => {
    if (!track?.applyConstraints) return

    const capabilities = track.getCapabilities?.()
    const focusModes = capabilities?.focusMode || []
    torchAvailable.value = Boolean(capabilities?.torch)
    if (capabilities?.zoom && Number.isFinite(capabilities.zoom.max)) {
        zoomMin.value = capabilities.zoom.min ?? 1
        zoomMax.value = Math.min(capabilities.zoom.max, 4)
        zoomLevel.value = zoomMin.value
        zoomAvailable.value = zoomMax.value > zoomMin.value
    }

    if (focusModes.includes('continuous')) {
        try {
            await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] })
        } catch (_) {
            // Some Android camera implementations expose but reject focus controls.
        }
    }
}

const cameraConstraints = (deviceId = '') => ({
    audio: false,
    video: {
        ...(deviceId
            ? { deviceId: { exact: deviceId } }
            : { facingMode: { ideal: 'environment' } }),
        width: { min: 640, ideal: 1920 },
        height: { min: 480, ideal: 1080 },
        frameRate: { ideal: 30, max: 30 },
    },
})

const getTrackCapabilities = (track) => {
    try { return track?.getCapabilities?.() || {} } catch (_) { return {} }
}

const openPreferredCamera = async () => {
    if (cameraWasManuallySelected.value && selectedCameraId.value) {
        return navigator.mediaDevices.getUserMedia(cameraConstraints(selectedCameraId.value))
    }

    let stream = await navigator.mediaDevices.getUserMedia(cameraConstraints())
    if (!navigator.mediaDevices?.enumerateDevices) return stream

    let devices = []
    try {
        devices = (await navigator.mediaDevices.enumerateDevices()).filter(device => device.kind === 'videoinput')
        cameraSources.value = devices.map((device, index) => ({
            deviceId: device.deviceId,
            label: device.label || `Camera ${index + 1}`,
        }))
    } catch (_) {
        return stream
    }

    const defaultTrack = stream.getVideoTracks?.()[0]
    const defaultId = defaultTrack?.getSettings?.().deviceId || ''
    const defaultCapabilities = getTrackCapabilities(defaultTrack)
    if (defaultCapabilities.torch || devices.length < 2) {
        if (defaultId) selectedCameraId.value = defaultId
        return stream
    }

    const alternativeCameras = devices.filter(device =>
        device.deviceId && device.deviceId !== defaultId && !/front|user/i.test(device.label || ''),
    )

    for (const device of alternativeCameras) {
        stream.getTracks?.().forEach(track => track.stop())
        stream = null
        try {
            const candidate = await navigator.mediaDevices.getUserMedia(cameraConstraints(device.deviceId))
            const track = candidate.getVideoTracks?.()[0]
            const facingMode = track?.getSettings?.().facingMode
            if (facingMode && facingMode !== 'environment') {
                candidate.getTracks?.().forEach(item => item.stop())
                continue
            }
            if (getTrackCapabilities(track).torch) {
                selectedCameraId.value = device.deviceId
                return candidate
            }
            candidate.getTracks?.().forEach(item => item.stop())
        } catch (_) {
            // A browser may list a lens but refuse to open it directly.
        }
    }

    return navigator.mediaDevices.getUserMedia(cameraConstraints(defaultId))
}

const loadCameraSources = async (activeTrack) => {
    if (!navigator.mediaDevices?.enumerateDevices) return
    try {
        const devices = await navigator.mediaDevices.enumerateDevices()
        cameraSources.value = devices
            .filter(device => device.kind === 'videoinput')
            .map((device, index) => ({
                deviceId: device.deviceId,
                label: device.label || `Camera ${index + 1}`,
            }))
        const activeId = activeTrack?.getSettings?.().deviceId
        if (activeId && cameraSources.value.some(camera => camera.deviceId === activeId)) {
            selectedCameraId.value = activeId
        }
    } catch (_) {
        cameraSources.value = []
    }
}

const changeCameraSource = (deviceId = selectedCameraId.value) => {
    if (!deviceId) return
    cameraWasManuallySelected.value = true
    cameraSourcePickerOpen.value = false
    selectedCameraId.value = deviceId
    stop()
    start()
}

const focusCamera = async () => {
    const track = activeStream?.getVideoTracks?.()[0]
    const focusModes = track?.getCapabilities?.().focusMode || []
    if (!track?.applyConstraints) return

    try {
        if (focusModes.includes('single-shot')) {
            await track.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] })
        } else if (focusModes.includes('continuous')) {
            // Reapplying continuous focus nudges Android camera drivers to refocus.
            await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] })
        }
        if (focusModes.includes('single-shot') && focusModes.includes('continuous')) {
            setTimeout(() => {
                if (scanning.value) track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {})
            }, 700)
        }
    } catch (_) {
        // Tap-to-focus is optional and depends on browser camera support.
    }
}

const setZoom = async (event) => {
    const track = activeStream?.getVideoTracks?.()[0]
    const next = Number(event.target.value)
    if (!track?.applyConstraints || !Number.isFinite(next)) return
    try {
        await track.applyConstraints({ advanced: [{ zoom: next }] })
        zoomLevel.value = next
    } catch (_) {
        zoomAvailable.value = false
    }
}

const toggleTorch = async () => {
    const track = activeStream?.getVideoTracks?.()[0]
    if (!track?.applyConstraints || !torchAvailable.value) return
    const next = !torchOn.value
    try {
        await track.applyConstraints({ advanced: [{ torch: next }] })
        torchOn.value = next
    } catch (_) {
        torchAvailable.value = false
    }
}

const openPhoneCamera = () => {
    error.value = ''
    stop()
}

const handlePickerCancel = () => {
    setTimeout(() => {
        if (open.value && !scanning.value) start()
    }, 250)
}

const readCapturedPhoto = async (event) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) {
        if (event.target === photoInput.value) handlePickerCancel()
        return
    }

    // Keep the live camera running while iOS opens the Photos picker. Releasing
    // getUserMedia before the native picker appears can make iOS minimize the tab.
    if (event.target === galleryInput.value) stop()
    error.value = ''
    readingPhoto.value = true
    const session = scanSession
    const imageUrl = URL.createObjectURL(file)
    let bitmap = null
    try {
        await nextTick()
        await new Promise(resolve => requestAnimationFrame(() => resolve()))

        const maxImageSize = 1800
        if (typeof globalThis.createImageBitmap === 'function') {
            try {
                bitmap = await withTimeout(globalThis.createImageBitmap(file, {
                    resizeWidth: maxImageSize,
                    resizeHeight: maxImageSize,
                    resizeQuality: 'high',
                }), 'image-timeout', 10000)
            } catch (_) {
                // Fall back to an HTML image on browsers without bitmap resizing support.
            }
        }

        if (typeof globalThis.BarcodeDetector === 'function' && bitmap) {
            try {
                const formats = await globalThis.BarcodeDetector.getSupportedFormats()
                const imageFormats = [
                    'aztec', 'code_128', 'code_39', 'code_93', 'codabar',
                    'data_matrix', 'ean_13', 'ean_8', 'itf', 'pdf417',
                    'qr_code', 'upc_a', 'upc_e',
                ].filter(format => formats.includes(format))
                if (imageFormats.length) {
                    const [result] = await withTimeout(
                        new globalThis.BarcodeDetector({ formats: imageFormats }).detect(bitmap),
                        'scan-timeout',
                        6000,
                    )
                    if (result?.rawValue?.trim()) {
                        acceptResult({ getText: () => result.rawValue.trim() }, session)
                        return
                    }
                }
            } catch (_) {
                // Use the bundled ZBar decoder if the browser decoder cannot read the photo.
            }
        }

        const canvas = document.createElement('canvas')
        if (bitmap) {
            canvas.width = bitmap.width
            canvas.height = bitmap.height
        } else {
            const image = new Image()
            image.src = imageUrl
            await withTimeout(image.decode(), 'image-timeout', 10000)
            const scale = Math.min(1, maxImageSize / Math.max(image.naturalWidth, image.naturalHeight))
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale))
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale))
            const context = canvas.getContext('2d', { willReadFrequently: true })
            if (!context) throw new Error('canvas-unavailable')
            context.drawImage(image, 0, 0, canvas.width, canvas.height)
        }
        const context = canvas.getContext('2d', { willReadFrequently: true })
        if (!context) throw new Error('canvas-unavailable')
        if (bitmap) context.drawImage(bitmap, 0, 0, canvas.width, canvas.height)

        const symbols = await withTimeout(
            scanImageData(context.getImageData(0, 0, canvas.width, canvas.height)),
            'scan-timeout',
            8000,
        )
        const symbol = symbols.find(item => item.decode?.('utf-8')?.trim())
        if (!symbol) throw new Error('barcode-not-found')
        acceptResult({ getText: () => symbol.decode('utf-8').trim() }, session)
    } catch (_) {
        if (session === scanSession && open.value) {
            error.value = 'Không đọc được mã trong ảnh. Hãy thử ảnh rõ hơn hoặc chọn nguồn camera khác.'
        }
    } finally {
        bitmap?.close?.()
        URL.revokeObjectURL(imageUrl)
        readingPhoto.value = false
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
        if (typeof globalThis.BarcodeDetector === 'function') {
            let supportedFormats = []
            try {
                supportedFormats = await globalThis.BarcodeDetector.getSupportedFormats()
            } catch (_) {
                // Fall back to ZBar WebAssembly when the native API is unavailable.
            }
            const productFormats = [
                'aztec', 'code_128', 'code_39', 'code_93', 'codabar',
                'data_matrix', 'ean_13', 'ean_8', 'itf', 'pdf417',
                'qr_code', 'upc_a', 'upc_e',
            ].filter(format => supportedFormats.includes(format))

            if (productFormats.length) {
                const detector = new globalThis.BarcodeDetector({ formats: productFormats })
                activeStream = await openPreferredCamera()
                if (session !== scanSession || !open.value) {
                    stop()
                    return
                }

                video.value.srcObject = activeStream
                await video.value.play()
                const videoTrack = activeStream.getVideoTracks?.()[0]
                await loadCameraSources(videoTrack)
                await configureCameraTrack(videoTrack)
                if (session !== scanSession || !open.value) {
                    stop()
                    return
                }

                cameraReady.value = true
                nativeFallbackTimer = setTimeout(async () => {
                    if (session !== scanSession || !open.value || nativeFallbackStarted) return
                    nativeFallbackStarted = true
                    if (nativeScanTimer) clearTimeout(nativeScanTimer)
                    nativeScanTimer = null
                    scanVideoWithZBar(session)
                }, 1800)

                const scanWithNativeDetector = async () => {
                    if (session !== scanSession || !open.value || nativeFallbackStarted) return
                    if (!nativeScanBusy && video.value?.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                        nativeScanBusy = true
                        try {
                            const results = await detector.detect(video.value)
                            const code = results.find(result => result.rawValue?.trim())?.rawValue?.trim()
                            if (code && session === scanSession && open.value) {
                                acceptResult({ getText: () => code }, session)
                                return
                            }
                        } catch (_) {
                            // A frame can be undecodable while focus or exposure adjusts.
                        } finally {
                            nativeScanBusy = false
                        }
                    }
                    if (session === scanSession && open.value && !nativeFallbackStarted) {
                        nativeScanTimer = setTimeout(scanWithNativeDetector, 100)
                    }
                }
                scanWithNativeDetector()
                return
            }
        }

        activeStream = await openPreferredCamera()
        if (session !== scanSession || !open.value) {
            stop()
            return
        }
        video.value.srcObject = activeStream
        await video.value.play()
        const videoTrack = activeStream?.getVideoTracks?.()[0]
        await loadCameraSources(videoTrack)
        await configureCameraTrack(videoTrack)
        if (session !== scanSession || !open.value) {
            stop()
            return
        }
        cameraReady.value = true
        scanVideoWithZBar(session)
    } catch (cause) {
        error.value = cause?.message || 'Không mở được camera. Hãy kiểm tra quyền truy cập camera.'
        stop()
    }
}

const close = () => {
    stop()
    open.value = false
}
onBeforeUnmount(() => {
    stop()
})
</script>

<template>
    <button v-bind="$attrs" type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100" title="Quét mã bằng camera" aria-label="Quét mã bằng camera" @click="start">
        <Camera class="h-4 w-4" />
    </button>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[1200] flex items-center justify-center overflow-y-auto bg-black/70 p-3 sm:p-4" @click.self="close">
            <section class="my-auto max-h-[calc(100dvh-1.5rem)] w-full max-w-xl overflow-y-auto rounded-xl bg-white shadow-2xl sm:max-h-[calc(100dvh-2rem)]">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <h2 class="font-semibold text-slate-800">Quét mã sản phẩm</h2>
                    <button type="button" class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100" title="Tắt camera" aria-label="Tắt camera và đóng cửa sổ quét" @click="close"><CameraOff class="h-4 w-4" /><span>Tắt camera</span></button>
                </header>
                <div class="p-3 sm:p-4">
                    <div v-if="cameraReady && cameraSources.length > 1" class="mb-3">
                        <button type="button" class="text-sm text-slate-600 underline decoration-dotted underline-offset-4" :aria-expanded="cameraSourcePickerOpen" @click="cameraSourcePickerOpen = !cameraSourcePickerOpen">
                            Camera bị mờ? Chọn camera khác
                        </button>
                    </div>
                    <div class="relative mx-auto aspect-[4/3] w-[min(94vw,68dvh,520px)] max-w-full overflow-hidden rounded-lg bg-black">
                        <video ref="video" class="absolute inset-0 h-full w-full object-cover" autoplay muted playsinline webkit-playsinline @click="focusCamera" />
                        <div class="pointer-events-none absolute inset-[5%] rounded-lg border-2 border-emerald-400/90">
                            <span class="absolute inset-x-1 top-1/2 border-t border-dashed border-emerald-200/90" />
                        </div>
                    </div>
                    <div v-if="cameraReady && torchAvailable" class="mt-2 flex justify-center">
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700" @click="toggleTorch">
                            <component :is="torchOn ? FlashlightOff : Flashlight" class="h-4 w-4" />{{ torchOn ? 'Tắt đèn' : 'Bật đèn' }}
                        </button>
                    </div>
                    <label v-if="cameraReady && zoomAvailable" class="mt-2 flex items-center gap-3 text-sm text-slate-600">
                        <span>Zoom {{ zoomLevel.toFixed(1) }}×</span>
                        <input type="range" class="min-w-0 flex-1 accent-emerald-600" :min="zoomMin" :max="zoomMax" step="0.1" :value="zoomLevel" @input="setZoom" />
                    </label>
                    <p v-if="readingPhoto" class="mt-3 text-center text-sm text-slate-600">Đang đọc ảnh…</p>
                    <p v-else-if="error" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                    <p v-else class="mt-3 text-center text-sm text-slate-600">{{ cameraReady ? 'Đưa mã vào giữa khung hình. Chạm vào mã để lấy nét.' : scanning ? 'Đang mở camera…' : 'Chụp ảnh bằng camera điện thoại ở nút bên dưới.' }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <label class="relative inline-flex w-full cursor-pointer items-center justify-center gap-2 overflow-hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm font-semibold text-emerald-800">
                            <Camera class="h-4 w-4" />Chụp ảnh để quét
                            <input ref="photoInput" type="file" accept="image/*" capture="environment" aria-label="Chụp ảnh để quét" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" @click="openPhoneCamera" @change="readCapturedPhoto" @cancel="handlePickerCancel" />
                        </label>
                        <label class="relative inline-flex w-full cursor-pointer items-center justify-center gap-2 overflow-hidden rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700">
                            Chọn ảnh trong máy
                            <input ref="galleryInput" type="file" accept="image/*" aria-label="Chọn ảnh trong máy" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" @change="readCapturedPhoto" />
                        </label>
                    </div>
                    <button v-if="error" type="button" class="mt-3 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white" @click="start">Thử lại</button>
                </div>
            </section>
        </div>
    </Teleport>
    <Teleport to="body">
        <div v-if="open && cameraSourcePickerOpen" class="fixed inset-0 z-[1300] flex items-center justify-center bg-black/45 p-4" @click.self="cameraSourcePickerOpen = false">
            <section class="w-full max-w-sm rounded-xl bg-white p-4 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="camera-source-title">
                <div class="mb-3 flex items-center justify-between">
                    <h3 id="camera-source-title" class="font-semibold text-slate-800">Chọn nguồn camera</h3>
                    <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" @click="cameraSourcePickerOpen = false">Đóng</button>
                </div>
                <div class="grid max-h-[55dvh] gap-1 overflow-y-auto">
                    <button
                        v-for="(camera, index) in cameraSources"
                        :key="camera.deviceId"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-3 text-left text-sm"
                        :class="selectedCameraId === camera.deviceId ? 'bg-emerald-100 font-semibold text-emerald-800' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
                        @click="changeCameraSource(camera.deviceId)"
                    >
                        <span>{{ camera.label || `Camera ${index + 1}` }}</span>
                        <span v-if="selectedCameraId === camera.deviceId" aria-label="Đang chọn">✓</span>
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
