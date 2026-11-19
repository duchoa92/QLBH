import { onBeforeUnmount, ref, watch } from 'vue'
import api from '@/Services/api'

const waitForIce = peer => new Promise(resolve => {
    if (peer.iceGatheringState === 'complete') return resolve()
    // WHEP needs a candidate in the non-trickle SDP, but waiting for every
    // adapter/VPN candidate makes a LAN camera take 8+ seconds to open. Send
    // as soon as the first usable host candidate is appended to the SDP.
    const timeout = setTimeout(done, 2500)
    const check = () => { if (peer.iceGatheringState === 'complete') done() }
    const candidate = event => { if (event.candidate) setTimeout(done, 120) }
    function done() { clearTimeout(timeout); peer.removeEventListener('icegatheringstatechange', check); peer.removeEventListener('icecandidate', candidate); resolve() }
    peer.addEventListener('icegatheringstatechange', check)
    peer.addEventListener('icecandidate', candidate)
})

/** MediaMTX/WHEP player and AI metadata overlay. */
export const useCameraIntelligenceWebrtc = (enabled, { camera = 'door-1', facesInterval = 250, sourceKey = null, webrtcUrl = null } = {}) => {
    const ok = ref(false), loading = ref(true), error = ref(''), faces = ref([]), videoElement = ref(null)
    let peer = null, facesTimer = null, retryTimer = null, frameTimer = null, retryAttempts = 0, generation = 0, lastFacesAt = 0, lastFacesChangedAt = 0
    const currentCamera = () => typeof camera === 'function' ? camera() : (camera?.value ?? camera)
    const currentWebrtcUrl = () => typeof webrtcUrl === 'function' ? webrtcUrl() : (webrtcUrl?.value ?? webrtcUrl)
    const setVideo = element => { videoElement.value = element; if (element?.srcObject) element.play?.().catch(() => {}) }
    const stop = () => { generation += 1; clearInterval(facesTimer); clearTimeout(retryTimer); clearTimeout(frameTimer); peer?.close(); peer = null; facesTimer = null; frameTimer = null; lastFacesAt = 0; lastFacesChangedAt = 0; if (videoElement.value) videoElement.value.srcObject = null; ok.value = false; loading.value = true; error.value = ''; faces.value = [] }
    const retry = () => { clearTimeout(retryTimer); retryTimer = setTimeout(() => { if (enabled.value) start() }, Math.min(10000, 800 * (2 ** Math.min(retryAttempts++, 3)))) }
    const getFaces = async (run, selectedCamera) => {
        try {
            const { data } = await api.get('/api/customers/camera-intelligence/faces', { params: { camera: selectedCamera } })
            if (run !== generation) return
            const at = Number(data?.at || 0)
            if (at && at !== lastFacesAt) { lastFacesAt = at; lastFacesChangedAt = Date.now() }
            // The endpoint can return its cached last value while the worker
            // is delayed. Never keep an old named overlay on a new video frame.
            faces.value = at && Date.now() - lastFacesChangedAt <= 1200 ? (data?.faces || []) : []
        } catch { /* Keep the latest valid frame only until its short TTL expires. */ }
    }
    const start = async () => {
        stop(); const run = generation
        const selectedCamera = currentCamera()
        if (!selectedCamera) return
        try {
            const live = await api.get('/api/customers/camera-intelligence/live', { params: { camera: selectedCamera } })
            if (!live.data?.ready) throw new Error(live.data?.message || 'Dịch vụ camera chưa sẵn sàng.')
            const connection = new RTCPeerConnection(); peer = connection; connection.addTransceiver('video', { direction: 'recvonly' })
            connection.ontrack = event => {
                if (run !== generation) return
                const video = videoElement.value
                if (!video) return
                video.srcObject = event.streams?.[0] || new MediaStream([event.track])
                video.play?.().catch(() => {})
                // Receiving an SDP track is not proof that ICE media packets can
                // arrive. Only mark the player ready once the first decoded frame
                // is visible; otherwise retry instead of leaving a black canvas.
                video.onloadeddata = () => {
                    if (run !== generation) return
                    clearTimeout(frameTimer); ok.value = true; loading.value = false; error.value = ''; retryAttempts = 0
                }
                clearTimeout(frameTimer)
                frameTimer = setTimeout(() => {
                    if (run === generation && !ok.value) {
                        loading.value = false
                        error.value = 'WebRTC đã kết nối nhưng chưa nhận được hình. Đang thử kết nối lại…'
                        retry()
                    }
                }, 7000)
            }
            connection.onconnectionstatechange = () => { if (run === generation && ['failed', 'closed', 'disconnected'].includes(connection.connectionState)) { ok.value = false; loading.value = false; error.value = 'Kết nối camera gián đoạn, đang tự kết nối lại…'; retry() } }
            await connection.setLocalDescription(await connection.createOffer()); await waitForIce(connection)
            const directUrl = currentWebrtcUrl()
            const endpoint = directUrl
                ? `${String(directUrl).replace(/\/$/, '')}/${encodeURIComponent(selectedCamera)}-view/whep`
                : `/api/customers/camera-intelligence/webrtc-offer?camera=${encodeURIComponent(selectedCamera)}`
            // Direct MediaMTX lives on a different port. Do not use the shared
            // Axios instance here: it appends Laravel's XSRF/X-Requested-With
            // headers, MediaMTX correctly rejects that CORS preflight and the
            // browser never sends the SDP POST. Native fetch sends only the
            // WHEP headers that MediaMTX explicitly allows.
            let answer
            if (directUrl) {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { Accept: 'application/sdp', 'Content-Type': 'application/sdp' },
                    body: connection.localDescription.sdp,
                    credentials: 'omit',
                })
                if (!response.ok) throw new Error(`MediaMTX trả về ${response.status}.`)
                answer = { data: await response.text() }
            } else {
                answer = await api.post(endpoint, connection.localDescription.sdp, { headers: { Accept: 'application/sdp', 'Content-Type': 'application/sdp' }, responseType: 'text' })
            }
            if (run !== generation) return; await connection.setRemoteDescription({ type: 'answer', sdp: answer.data }); facesTimer = setInterval(() => getFaces(run, selectedCamera), facesInterval); getFaces(run, selectedCamera)
        } catch (cause) { if (run !== generation) return; loading.value = false; error.value = cause?.response?.data?.message || cause?.message || 'Không mở được luồng camera.'; retry() }
    }
    watch([enabled, () => sourceKey?.value ?? sourceKey, currentCamera], ([value]) => value ? start() : stop(), { immediate: true }); onBeforeUnmount(stop)
    return { ok, loading, error, faces, setVideo }
}
