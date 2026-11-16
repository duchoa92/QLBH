import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import api from '@/Services/api'
import { cameraErrorMessage, cropThumbnail, detectFaces, loadFaceModels, startCamera, stopCamera } from '@/utils/faceRecognition'

/**
 * Tự nhận ra khách đã đăng ký khuôn mặt khi họ đứng trước camera POS — nhiều người cùng lúc cũng được.
 * Chỉ GỢI Ý: nhân viên bấm "Đúng" thì mới chọn khách, tránh ghi nhầm công nợ/bảo hành.
 *
 * Cách làm để vừa nhanh vừa chính xác kể cả khi ảnh mờ:
 *  - Vòng quét chạy liên tục (không chờ mốc cố định), chỉ tăng tốc khi có mặt mới cần nhận ra.
 *  - Mỗi khuôn mặt được theo dõi qua các khung hình; giữ lại vài khung NÉT NHẤT rồi gửi cùng lúc, máy chủ lấy
 *    trung bình các khoảng cách tốt nhất nên một khung mờ không làm sai kết quả.
 *  - Khuôn mặt rõ thì chỉ cần 2 khung là hỏi ngay; khuôn mặt mờ thì chờ thêm khung nét hơn (tối đa ~2 giây).
 *  - Nhiều khuôn mặt được gửi trong 1 lần gọi, kết quả hiện thành danh sách để nhân viên chọn đúng người đang thanh toán.
 */
const STORAGE_KEY = 'pos.faceCheckIn'
const FAST_DELAY = 120 // ms giữa 2 lượt khi đang có mặt cần nhận ra
const IDLE_DELAY = 350 // ms khi không có ai trước camera
const RESOLVED_DELAY = 600 // ms khi mọi khuôn mặt đã có kết quả (đỡ tốn máy)
const TRACK_TTL = 1500 // mặt biến mất quá thời gian này thì bỏ theo dõi
const MAX_SAMPLES = 4 // số khung hình nét nhất giữ lại cho mỗi khuôn mặt
const SEND_SAMPLES = 3 // số khung gửi lên máy chủ cho mỗi khuôn mặt
const BLUR_FLOOR = 10 // dưới mức này coi là quá mờ: chờ thêm khung nét hơn
const BLUR_MAX_WAIT = 1800 // chờ tối đa bấy nhiêu ms rồi vẫn gửi dù mờ
const UNKNOWN_RETRY = 6000 // khách chưa đăng ký: thử lại sau (phòng khi lần trước ánh sáng xấu)
const AMBIGUOUS_RETRY = 2500
const SUGGESTION_TTL = 25000
const DISMISS_COOLDOWN = 60000
const SUGGESTION_RETRY = 8000 // gợi ý hết hạn mà chưa ai bấm: nhắc lại sau

const FACING_KEY = 'pos.faceCheckIn.facing'
const readFacing = () => { try { return localStorage.getItem(FACING_KEY) === 'environment' ? 'environment' : 'user' } catch { return 'user' } }
const readEnabled = (key) => { try { return localStorage.getItem(key) === '1' } catch { return false } }

export const useFaceCheckIn = ({ paused = () => false, onConfirm = () => {}, currentCustomerId = () => null, storageKey = STORAGE_KEY, onVisit = () => {} } = {}) => {
    const page = usePage()
    // Tính năng có được chủ cửa hàng bật trong Cài đặt không (mặc định tắt).
    const featureOn = computed(() => !!page.props.settings?.face_recognition_enabled)
    const enabled = ref(readEnabled(storageKey))
    const active = computed(() => featureOn.value && enabled.value)
    // Ghi nhận lượt khách ra vào (Cài đặt → Khách hàng): mặc định tắt.
    const trackingOn = computed(() => !!page.props.settings?.face_visit_tracking_enabled)
    const failed = ref(false)
    const facing = ref(readFacing()) // 'user' = camera trước, 'environment' = camera sau
    const switching = ref(false)
    const status = ref('')
    const tone = ref('idle') // idle | scanning | found | error
    const suggestions = ref([])
    const liveFaces = ref([]) // khuôn mặt đang thấy trong khung hình (vẽ khung bắt mặt trên trang thống kê)
    const queue = ref([]) // khách đang đứng chờ (đã nhận ra nhưng chưa được chọn, hoặc chưa có dữ liệu)

    let stream = null
    let analysis = null // <video> ẩn dùng để phân tích, tồn tại độc lập với giao diện
    let previewEl = null
    let running = false
    let busy = false
    let timer = null
    let suggestionTimer = null
    let tracks = []
    let nextTrackId = 1
    const dismissed = new Map() // customerId -> thời điểm bỏ qua
    let servedId = null // khách vừa được chọn cho đơn hiện tại

    const statusText = computed(() => {
        if (failed.value) return status.value
        if (!active.value) return ''
        if (paused()) return 'Đã có khách'
        return status.value || 'Đang chờ khách…'
    })

    /* ---------------- hiển thị xem trước trong ô nhập ---------------- */
    const attachPreview = (element) => {
        previewEl = element
        if (element && stream) {
            element.srcObject = stream
            element.play?.().catch(() => {})
        }
    }

    const createAnalysisVideo = () => {
        const element = document.createElement('video')
        element.muted = true
        element.setAttribute('playsinline', '')
        Object.assign(element.style, { position: 'fixed', left: '-9999px', top: '0', width: '2px', height: '2px', opacity: '0', pointerEvents: 'none' })
        document.body.appendChild(element)

        return element
    }

    /* ---------------- theo dõi khuôn mặt qua các khung hình ---------------- */
    const centerOf = box => ({ x: box.x + box.width / 2, y: box.y + box.height / 2 })

    const nearestTrack = (box, used) => {
        const center = centerOf(box)
        let best = null
        let bestDistance = Infinity
        for (const track of tracks) {
            if (used.has(track.id)) continue
            const other = centerOf(track.box)
            const distance = Math.hypot(center.x - other.x, center.y - other.y)
            const limit = 0.7 * Math.max(track.box.width, track.box.height)
            if (distance < limit && distance < bestDistance) {
                best = track
                bestDistance = distance
            }
        }

        return best
    }

    const bestSharpness = track => track.samples.reduce((max, sample) => Math.max(max, sample.sharpness), 0)

    const addSample = (track, face) => {
        const isBest = !track.samples.length || face.sharpness > bestSharpness(track)
        track.samples.push({ descriptor: face.descriptor, sharpness: face.sharpness })
        track.samples.sort((a, b) => b.sharpness - a.sharpness)
        track.samples.length = Math.min(track.samples.length, MAX_SAMPLES)
        if (isBest) {
            try { track.thumb = cropThumbnail(analysis, face.box) } catch { /* bỏ qua, chỉ là ảnh minh họa */ }
        }
    }

    /* ---------------- ghi nhận lượt khách ra vào ---------------- */
    const sendLeft = (track) => {
        if (!track.visitId) {
            if (track.visitSent) track.leftPending = true // đang gửi dở: gửi "đã rời" ngay khi có mã
            return
        }
        if (track.leftSent) return
        track.leftSent = true
        api.post(`/api/customers/visits/${track.visitId}/left`).catch(() => {})
    }

    const recordVisit = async (track) => {
        if (!trackingOn.value || track.visitId || track.visitSent) return
        const payload = { thumb: track.thumb || null }
        if (track.match) payload.customer_id = track.match.customer.id
        else if (track.samples[0]?.descriptor) payload.descriptor = track.samples[0].descriptor
        else return

        track.visitSent = true
        try {
            const { data } = await api.post('/api/customers/visits', payload)
            if (data?.recorded) {
                track.visitId = data.id
                if (track.leftPending) sendLeft(track)
                refreshQueue()
                onVisit(data)
            }
        } catch {
            track.visitSent = false // lần nhận diện sau sẽ thử lại
        }
    }

    const updateTracks = (faces, now) => {
        const used = new Set()
        for (const face of faces) {
            let track = nearestTrack(face.box, used)
            if (!track) {
                track = { id: nextTrackId++, box: face.box, firstSeen: now, lastSeen: now, hits: 0, samples: [], state: 'collecting', retryAt: 0, match: null, thumb: '' }
                tracks.push(track)
            }
            used.add(track.id)
            track.box = face.box
            track.lastSeen = now
            track.hits += 1

            // Mặt đã có kết quả thì không cần thêm mẫu; mặt đang chờ thử lại bắt đầu thu mẫu mới khi tới giờ.
            if (track.served) continue
            if (track.state === 'waiting' && now >= track.retryAt) {
                track.state = 'collecting'
                track.samples = []
                track.hits = 1
                track.firstSeen = now
            }
            if (track.state === 'collecting') addSample(track, face)
        }
        const gone = tracks.filter(track => now - track.lastSeen >= TRACK_TTL)
        if (gone.length) {
            gone.forEach(sendLeft)
            tracks = tracks.filter(track => now - track.lastSeen < TRACK_TTL)
        }
    }

    const isReady = (track, now) => {
        if (track.state !== 'collecting' || track.hits < 2 || !track.samples.length) return false

        // Khuôn mặt rõ thì hỏi ngay; mặt mờ thì chờ thêm khung nét hơn (hoặc hết thời gian chờ).
        return bestSharpness(track) >= BLUR_FLOOR || now - track.firstSeen >= BLUR_MAX_WAIT
    }

    /* ---------------- gợi ý ---------------- */
    const clearSuggestions = () => {
        suggestions.value = []
        clearTimeout(suggestionTimer)
    }

    const buildSuggestions = (now) => {
        // Đang phục vụ khách khác thì không bật gợi ý; những người này nằm trong hàng chờ.
        if (paused()) {
            suggestions.value = []
            clearTimeout(suggestionTimer)
            refreshQueue(now)
            return
        }
        const list = []
        const seen = new Set()
        const matched = tracks
            .filter(track => track.state === 'matched' && track.match && !track.served)
            .sort((a, b) => b.box.width * b.box.height - a.box.width * a.box.height) // người gần camera nhất đứng đầu

        for (const track of matched) {
            const customer = track.match.customer
            const skippedAt = dismissed.get(customer.id)
            if (skippedAt && now - skippedAt < DISMISS_COOLDOWN) {
                track.state = 'waiting'
                track.retryAt = skippedAt + DISMISS_COOLDOWN
                continue
            }
            if (seen.has(customer.id) || customer.id === currentCustomerId()) continue
            seen.add(customer.id)
            list.push({
                trackId: track.id,
                customer,
                confidence: track.match.confidence,
                thumb: track.thumb,
                descriptor: track.samples[0]?.descriptor || null,
            })
        }

        suggestions.value = list
        clearTimeout(suggestionTimer)
        if (list.length) {
            tone.value = 'found'
            status.value = list.length > 1 ? `Nhận ra ${list.length} khách` : 'Đã nhận ra khách'
            suggestionTimer = setTimeout(() => {
                // Hết thời gian mà chưa ai xác nhận: bỏ gợi ý, người đó chuyển xuống hàng chờ và được nhắc lại sau.
                const retryAt = Date.now() + SUGGESTION_RETRY
                suggestions.value.forEach((item) => {
                    const track = tracks.find(other => other.id === item.trackId)
                    if (track) { track.state = 'waiting'; track.retryAt = retryAt }
                })
                clearSuggestions()
                refreshQueue()
            }, SUGGESTION_TTL)
        }
        refreshQueue(now)
    }

    /* ---------------- hàng chờ ---------------- */
    const refreshQueue = (now = Date.now()) => {
        const inSuggestion = new Set(suggestions.value.map(item => item.trackId))
        const list = []
        const knownIds = new Set()
        const ordered = [...tracks].sort((a, b) => b.box.width * b.box.height - a.box.width * a.box.height)
        for (const track of ordered) {
            if (track.served || inSuggestion.has(track.id)) continue
            const base = { trackId: track.id, thumb: track.thumb, descriptor: track.samples[0]?.descriptor || null, visitId: track.visitId || null }
            if (track.match && (track.state === 'matched' || track.state === 'waiting')) {
                const customer = track.match.customer
                const skippedAt = dismissed.get(customer.id)
                if (skippedAt && now - skippedAt < DISMISS_COOLDOWN) continue
                if (customer.id === currentCustomerId() || knownIds.has(customer.id)) continue
                knownIds.add(customer.id)
                list.push({ ...base, kind: 'known', customer, confidence: track.match.confidence })
            } else if (!track.match && track.unregistered && track.state === 'waiting') {
                list.push({ ...base, kind: 'unknown', customer: null })
            }
        }
        const signature = items => items.map(item => `${item.trackId}:${item.kind}:${item.customer?.id || ''}:${item.visitId || ''}:${item.thumb ? 1 : 0}`).join('|')
        if (signature(list) !== signature(queue.value)) queue.value = list
    }

    // Khách chưa có dữ liệu vừa được nhập thông tin: gỡ khỏi hàng chờ, không hỏi lại.
    const markServed = (item, customer = null) => {
        const track = tracks.find(other => other.id === item?.trackId)
        if (track) {
            track.served = true
            if (customer) track.match = { customer, confidence: 1 }
        }
        if (customer?.id) dismissed.set(customer.id, Date.now())
        refreshQueue()
    }

    const resetTracks = () => {
        tracks.forEach(sendLeft)
        tracks = []
        queue.value = []
    }

    const resolveTracks = async (now) => {
        const ready = tracks.filter(track => isReady(track, now)).slice(0, 6)
        if (!ready.length) return

        ready.forEach((track) => { track.state = 'querying' })
        try {
            const { data } = await api.post('/api/customers/face-match', {
                faces: ready.map(track => ({
                    key: track.id,
                    descriptors: track.samples.slice(0, SEND_SAMPLES).map(sample => sample.descriptor),
                })),
            })

            const results = new Map((data.results || []).map(result => [Number(result.key), result]))
            for (const track of ready) {
                const result = results.get(track.id)
                if (result?.matched && result.customer) {
                    track.state = 'matched'
                    track.match = { customer: result.customer, confidence: result.confidence }
                    track.unregistered = false
                } else {
                    track.match = null
                    track.state = 'waiting'
                    track.retryAt = now + (result?.ambiguous ? AMBIGUOUS_RETRY : UNKNOWN_RETRY)
                    track.unregistered = !result?.ambiguous
                }
            }
        } catch (error) {
            // Lỗi mạng tạm thời thì thử lại ở lượt sau, không làm phiền nhân viên.
            ready.forEach((track) => { track.state = 'collecting' })
            throw error
        }

        ready.forEach((track) => {
            if (track.match || track.unregistered) recordVisit(track)
        })
        buildSuggestions(now)
    }

    const publishLive = (now) => {
        const width = analysis?.videoWidth || 0
        const height = analysis?.videoHeight || 0
        if (!width || !height) { liveFaces.value = []; return }
        liveFaces.value = tracks
            .filter(track => now - track.lastSeen < TRACK_TTL)
            .map((track) => {
                const known = track.match && (track.state === 'matched' || track.state === 'waiting' || track.served)
                const unknown = !known && track.unregistered && track.state === 'waiting'
                return {
                    id: track.id,
                    x: track.box.x / width,
                    y: track.box.y / height,
                    w: track.box.width / width,
                    h: track.box.height / height,
                    kind: known ? 'known' : (unknown ? 'unknown' : 'scanning'),
                    label: known ? track.match.customer.full_name : (unknown ? `Khách mới${track.visitId ? ` #${track.visitId}` : ''}` : 'Đang nhận diện…'),
                    thumb: track.thumb,
                }
            })
    }

    const describeStatus = (tooSmall) => {
        // Đang hiện gợi ý thì giữ nguyên lời nhắn "Đã nhận ra khách".
        if (suggestions.value.length) return

        const live = tracks.filter(track => Date.now() - track.lastSeen < TRACK_TTL)
        if (!live.length) {
            status.value = tooSmall ? 'Mời khách lại gần camera hơn' : 'Đang chờ khách…'
            tone.value = 'idle'
            return
        }

        const working = live.filter(track => track.state === 'collecting' || track.state === 'querying')
        if (working.length) {
            const blurry = working.some(track => track.samples.length && bestSharpness(track) < BLUR_FLOOR)
            status.value = live.length > 1 ? `Đang nhận diện ${live.length} người…` : 'Đang nhận diện…'
            if (blurry) status.value += ' (ảnh hơi mờ)'
            tone.value = 'scanning'

            return
        }

        const unregistered = live.filter(track => track.state === 'waiting' && track.unregistered)
        if (unregistered.length) {
            status.value = live.length > 1 ? `${unregistered.length} người chưa đăng ký khuôn mặt` : 'Khách chưa đăng ký khuôn mặt'
            tone.value = 'idle'

            return
        }

        status.value = 'Đang chờ khách…'
        tone.value = 'idle'
    }

    /* ---------------- vòng quét ---------------- */
    const schedule = (delay) => {
        clearTimeout(timer)
        if (running) timer = setTimeout(cycle, delay)
    }

    const cycle = async () => {
        if (!running) return
        if (busy) return schedule(200)
        if (document.hidden || !analysis || analysis.readyState < 2) return schedule(400)

        busy = true
        const startedAt = performance.now()
        let delay = FAST_DELAY
        try {
            const { faces, tooSmall } = await detectFaces(analysis)
            const now = Date.now()
            updateTracks(faces, now)
            await resolveTracks(now)
            describeStatus(tooSmall)
            refreshQueue(now)
            publishLive(now)

            if (!faces.length) delay = IDLE_DELAY
            else if (tracks.every(track => track.state === 'waiting' || track.state === 'matched')) delay = RESOLVED_DELAY
        } catch (error) {
            delay = error?.response ? 1500 : 500
        } finally {
            busy = false
        }

        schedule(Math.max(0, delay - (performance.now() - startedAt)))
    }

    const start = async () => {
        if (!featureOn.value || running) return
        failed.value = false
        status.value = 'Đang mở camera…'
        tone.value = 'scanning'
        try {
            await loadFaceModels()
            analysis = createAnalysisVideo()
            stream = await startCamera(analysis, facing.value)
            if (previewEl) attachPreview(previewEl)
            status.value = 'Đang chờ khách…'
            tone.value = 'idle'
            running = true
            schedule(0)
        } catch (error) {
            failed.value = true
            tone.value = 'error'
            status.value = cameraErrorMessage(error)
            stop()
        }
    }

    const stop = () => {
        running = false
        clearTimeout(timer)
        stopCamera(stream, analysis)
        stream = null
        analysis?.remove()
        analysis = null
        if (previewEl) previewEl.srcObject = null
        resetTracks()
        liveFaces.value = []
        clearSuggestions()
    }

    // Nhấp vào khung camera: đổi qua lại camera trước / sau (khởi động lại luồng với hướng mới).
    const switchCamera = async () => {
        if (!active.value || switching.value) return
        switching.value = true
        facing.value = facing.value === 'user' ? 'environment' : 'user'
        try { localStorage.setItem(FACING_KEY, facing.value) } catch { /* bỏ qua */ }
        try {
            stop()
            await start()
        } finally {
            switching.value = false
        }
    }

    const toggle = () => {
        enabled.value = !enabled.value
        try { localStorage.setItem(storageKey, enabled.value ? '1' : '0') } catch { /* bỏ qua */ }
    }

    /* ---------------- thao tác của nhân viên ---------------- */
    const confirm = (suggestion) => {
        if (!suggestion) return
        clearSuggestions()
        dismissed.set(suggestion.customer.id, Date.now())
        const served = tracks.find(track => track.id === suggestion.trackId)
        if (served) served.served = true
        onConfirm(suggestion.customer)
        // Nhân viên xác nhận đúng người: lưu thêm mẫu này để lần sau nhận chính xác hơn (máy chủ tự kiểm tra và giới hạn số mẫu).
        if (suggestion.descriptor) api.post(`/api/customers/${suggestion.customer.id}/face-feedback`, { descriptor: suggestion.descriptor }).catch(() => {})
        // Những người còn lại trước camera giữ nguyên trong hàng chờ cho đơn kế tiếp.
        refreshQueue()
    }

    const dismiss = (suggestion) => {
        if (!suggestion) return
        dismissed.set(suggestion.customer.id, Date.now())
        suggestions.value = suggestions.value.filter(item => item.trackId !== suggestion.trackId)
        const track = tracks.find(item => item.id === suggestion.trackId)
        if (track) {
            track.state = 'waiting'
            track.retryAt = Date.now() + DISMISS_COOLDOWN
        }
        if (!suggestions.value.length) {
            clearTimeout(suggestionTimer)
            status.value = 'Đang chờ khách…'
            tone.value = 'idle'
        }
        refreshQueue()
    }

    const dismissAll = () => {
        [...suggestions.value].forEach(dismiss)
    }

    watch(active, (value) => { value ? start() : stop() }, { immediate: true })

    // Đơn trước thanh toán xong (khách được bỏ chọn): nhận lại những người đang đứng trước camera.
    watch(currentCustomerId, (id) => { if (id) servedId = id })
    watch(() => paused(), (value, previous) => {
        if (previous && !value) {
            // Người vừa thanh toán có thể vẫn đứng đó: không hỏi lại ngay. Những người còn lại được gợi ý tiếp.
            if (servedId) dismissed.set(servedId, Date.now())
            servedId = null
            buildSuggestions(Date.now())
        }
    })

    onBeforeUnmount(stop)

    return {
        featureOn,
        enabled,
        active,
        failed,
        facing,
        switching,
        tone,
        suggestions,
        queue,
        liveFaces,
        statusText,
        attachPreview,
        toggle,
        switchCamera,
        confirm,
        dismiss,
        dismissAll,
        markServed,
    }
}
