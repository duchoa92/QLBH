import { onBeforeUnmount, ref, watch } from 'vue'
import api from '@/Services/api'

const waitForIce = peer => new Promise(resolve => {
    if (peer.iceGatheringState === 'complete') return resolve()
    const timeout = setTimeout(done, 1800)
    const check = () => { if (peer.iceGatheringState === 'complete') done() }
    function done() { clearTimeout(timeout); peer.removeEventListener('icegatheringstatechange', check); resolve() }
    peer.addEventListener('icegatheringstatechange', check)
})

/** MediaMTX/WHEP player and AI metadata overlay; no go2rtc API is used. */
export const useCameraIntelligenceWebrtc = (enabled, { camera = 'door-1', facesInterval = 350, sourceKey = null } = {}) => {
    const ok = ref(false), loading = ref(true), error = ref(''), faces = ref([]), videoElement = ref(null)
    let peer = null, facesTimer = null, retryTimer = null, frameTimer = null, retryAttempts = 0, generation = 0
    const setVideo = element => { videoElement.value = element; if (element?.srcObject) element.play?.().catch(() => {}) }
    const stop = () => { generation += 1; clearInterval(facesTimer); clearTimeout(retryTimer); clearTimeout(frameTimer); peer?.close(); peer = null; facesTimer = null; frameTimer = null; if (videoElement.value) videoElement.value.srcObject = null; ok.value = false; loading.value = true; error.value = ''; faces.value = [] }
    const retry = () => { clearTimeout(retryTimer); retryTimer = setTimeout(() => { if (enabled.value) start() }, Math.min(10000, 800 * (2 ** Math.min(retryAttempts++, 3)))) }
    const getFaces = async run => { try { const { data } = await api.get('/api/customers/camera-intelligence/faces', { params: { camera } }); if (run === generation) faces.value = data?.faces || [] } catch {} }
    const start = async () => {
        stop(); const run = generation
        try {
            const live = await api.get('/api/customers/camera-intelligence/live', { params: { camera } })
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
            const answer = await api.post(`/api/customers/camera-intelligence/webrtc-offer?camera=${encodeURIComponent(camera)}`, connection.localDescription.sdp, { headers: { Accept: 'application/sdp', 'Content-Type': 'application/sdp' }, responseType: 'text' })
            if (run !== generation) return; await connection.setRemoteDescription({ type: 'answer', sdp: answer.data }); facesTimer = setInterval(() => getFaces(run), facesInterval); getFaces(run)
        } catch (cause) { if (run !== generation) return; loading.value = false; error.value = cause?.response?.data?.message || cause?.message || 'Không mở được luồng camera.'; retry() }
    }
    watch([enabled, () => sourceKey?.value ?? sourceKey], ([value]) => value ? start() : stop(), { immediate: true }); onBeforeUnmount(stop)
    return { ok, loading, error, faces, setVideo }
}
