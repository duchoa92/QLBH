import { onBeforeUnmount, ref, watch } from 'vue'
import api from '@/Services/api'

const waitForIceGathering = (peer, timeout = 1800) => new Promise(resolve => {
    if (peer.iceGatheringState === 'complete') return resolve()
    let timer
    const finish = () => {
        clearTimeout(timer)
        peer.removeEventListener('icegatheringstatechange', check)
        resolve()
    }
    const check = () => { if (peer.iceGatheringState === 'complete') finish() }
    peer.addEventListener('icegatheringstatechange', check)
    timer = setTimeout(finish, timeout)
})

/** Camera RTSP qua go2rtc/WebRTC, metadata nhận diện được vẽ riêng trên video. */
export const useDoorWebrtc = (enabled, { facesInterval = 500, sourceKey = null } = {}) => {
    const ok = ref(false)
    const loading = ref(true)
    const error = ref('')
    const faces = ref([])
    const videoElement = ref(null)
    let peer = null
    let facesTimer = null
    let generation = 0

    const setVideo = element => {
        videoElement.value = element
        if (element && peer?.remoteDescription && !element.srcObject && peer.remoteDescription) {
            const stream = peer.getReceivers().map(receiver => receiver.track).filter(Boolean)
            if (stream.length) element.srcObject = new MediaStream(stream)
            element.play?.().catch(() => {})
        }
    }

    const stop = () => {
        generation += 1
        clearInterval(facesTimer)
        facesTimer = null
        peer?.close()
        peer = null
        if (videoElement.value) videoElement.value.srcObject = null
        ok.value = false
        loading.value = true
        error.value = ''
        faces.value = []
    }

    const pollFaces = async run => {
        try {
            const response = await api.get('/api/customers/door-camera/live-faces')
            if (run === generation) faces.value = Array.isArray(response.data?.faces) ? response.data.faces : []
        } catch {
            // Video tiếp tục chạy nếu metadata tạm thời chưa lấy được.
        }
    }

    const start = async () => {
        stop()
        const run = generation
        try {
            const live = await api.get('/api/customers/door-camera/live')
            if (run !== generation) return
            if (!live.data?.ready) throw new Error(live.data?.message || 'Luồng camera chưa sẵn sàng.')

            const connection = new RTCPeerConnection()
            peer = connection
            connection.addTransceiver('video', { direction: 'recvonly' })
            connection.ontrack = event => {
                if (run !== generation) return
                const video = videoElement.value
                if (video) {
                    video.srcObject = event.streams?.[0] || new MediaStream([event.track])
                    video.play?.().catch(() => {})
                }
                ok.value = true
                loading.value = false
                error.value = ''
            }
            connection.onconnectionstatechange = () => {
                if (run !== generation) return
                if (connection.connectionState === 'failed' || connection.connectionState === 'closed') {
                    ok.value = false
                    loading.value = false
                    error.value = 'Không tạo được kết nối WebRTC. Kiểm tra mạng và cổng WebRTC của go2rtc.'
                }
            }

            const offer = await connection.createOffer()
            await connection.setLocalDescription(offer)
            await waitForIceGathering(connection)
            if (run !== generation) return

            const answer = await api.post('/api/customers/door-camera/webrtc-offer', connection.localDescription.sdp, {
                headers: { Accept: 'application/sdp', 'Content-Type': 'application/sdp' },
                responseType: 'text',
            })
            if (run !== generation) return
            await connection.setRemoteDescription({ type: 'answer', sdp: answer.data })
            facesTimer = setInterval(() => pollFaces(run), facesInterval)
            pollFaces(run)
            setTimeout(() => {
                if (run === generation && !ok.value && !error.value) {
                    loading.value = false
                    error.value = 'go2rtc đã nhận kết nối nhưng chưa gửi video. Kiểm tra codec camera (khuyến nghị H.264).'
                }
            }, 8000)
        } catch (cause) {
            if (run !== generation) return
            loading.value = false
            let responseData = cause?.response?.data
            if (typeof responseData === 'string') {
                try { responseData = JSON.parse(responseData) } catch { /* proxy có thể trả text/HTML thay JSON */ }
            }
            error.value = responseData?.message || cause?.message || 'Không mở được luồng camera.'
        }
    }

    // Khi Cài đặt đổi RTSP/tài khoản, nguồn go2rtc đổi theo. Peer WebRTC cũ không tự chuyển nguồn,
    // vì vậy đóng peer cũ và tạo lại kết nối mà không cần người dùng tải lại trang.
    watch([enabled, () => sourceKey?.value ?? sourceKey], ([value]) => { value ? start() : stop() }, { immediate: true })
    onBeforeUnmount(stop)

    return { ok, loading, error, faces, setVideo }
}
