import mitt from 'mitt'
import api from '@/Services/api'

/**
 * Đồng bộ thời gian thực nhẹ giữa máy chủ và mọi trang đang mở.
 *
 * - Máy chủ giữ "số phiên bản" cho từng nhóm dữ liệu (products, sales, repairs...), tăng lên mỗi khi
 *   có INSERT/UPDATE/DELETE vào các bảng liên quan (App\Support\DataVersion), bất kể ai thực hiện.
 * - Trình duyệt chỉ hỏi /api/data-versions. Nhóm nào đổi số thì phát sự kiện, các trang đăng ký nhóm đó
 *   (useAutoRefresh / useLiveList) tự tải lại phần dữ liệu của mình, ngầm.
 * - Tạm dừng khi tab ẩn; quay lại tab là kiểm tra ngay. Máy rảnh lâu thì hỏi thưa hơn.
 */
// Đồng bộ theo phiên bản không cần chạy sát từng vài giây: mỗi thay đổi đã có
// một số phiên bản riêng. Nhịp 10 giây vẫn đủ gần thời gian thực, nhưng giảm
// đáng kể request nền trên tất cả trang đang đăng nhập.
const POLL_MS = 10000
const IDLE_POLL_MS = 30000
const IDLE_AFTER_MS = 5 * 60 * 1000
const MAX_BACKOFF_MS = 30000
// Khi cơ chế phiên bản không chạy (chưa migrate, chưa có thay đổi nào được ghi nhận), cứ chừng này ms lại
// báo mọi trang tự nạp lại ngầm một lần để dữ liệu vẫn được cập nhật.
const FALLBACK_MS = 15000

const emitter = mitt()
const state = { versions: null, started: false, timer: null, inflight: false, again: false, failures: 0, lastActivity: Date.now(), lastFallback: Date.now() }

const nextDelay = () => {
    if (state.failures) return Math.min(MAX_BACKOFF_MS, POLL_MS * 2 ** state.failures)
    return Date.now() - state.lastActivity > IDLE_AFTER_MS ? IDLE_POLL_MS : POLL_MS
}

const schedule = (delay = nextDelay()) => {
    clearTimeout(state.timer)
    state.timer = null
    if (!state.started || document.hidden) return
    state.timer = setTimeout(tick, delay)
}

const applyVersions = (next) => {
    if (!next || typeof next !== 'object') return
    if (state.versions) {
        const changed = Object.keys(next).filter(channel => next[channel] !== state.versions[channel])
        state.versions = { ...next }
        if (changed.length) emitter.emit('changed', changed)
    } else {
        state.versions = { ...next }
    }
}

// Gỡ lỗi: gõ window.__liveSync trong Console để xem trạng thái (versions, started, failures).
if (typeof window !== 'undefined') window.__liveSync = state

export const pollNow = async () => {
    if (!state.started) return
    if (state.inflight) { state.again = true; return }
    state.inflight = true
    try {
        // `_` + no-cache: tránh trình duyệt/service worker trả bản cũ đã lưu.
        const { data } = await api.get('/api/data-versions', {
            params: { _: Date.now() },
            headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' },
        })
        state.failures = 0
        const versions = data?.versions && !Array.isArray(data.versions) ? data.versions : {}
        applyVersions(versions)
        // Chỉ dùng lưới an toàn khi cơ chế phiên bản chưa sẵn sàng. Khi đã có
        // phiên bản, phát '*' định kỳ sẽ làm mọi trang tải lại dữ liệu dù không
        // hề có thay đổi.
        const degraded = data?.ready === false || Object.keys(versions).length === 0
        if (degraded && Date.now() - state.lastFallback >= FALLBACK_MS) {
            state.lastFallback = Date.now()
            emitter.emit('changed', ['*'])
        }
    } catch (error) {
        const status = error?.response?.status
        if (status === 401 || status === 419) { stopLiveSync(); return }
        state.failures = Math.min(state.failures + 1, 4)
    } finally {
        state.inflight = false
        if (state.again) { state.again = false; pollNow() }
    }
}

async function tick() {
    state.timer = null
    if (!state.started || document.hidden) return
    await pollNow()
    schedule()
}

const onVisible = () => {
    if (!state.started || document.hidden) return
    clearTimeout(state.timer)
    state.timer = null
    pollNow().finally(() => schedule())
}

// Chính tab này vừa lưu dữ liệu: kiểm tra ngay (đợi chút cho máy chủ commit xong).
const onLocalChange = () => { setTimeout(() => { pollNow() }, 250) }
const onActivity = () => { state.lastActivity = Date.now() }

let listenersInstalled = false
const installListeners = () => {
    if (listenersInstalled || typeof window === 'undefined') return
    listenersInstalled = true
    document.addEventListener('visibilitychange', onVisible)
    window.addEventListener('focus', onVisible)
    window.addEventListener('online', onVisible)
    window.addEventListener('reference-data:changed', onLocalChange)
    ;['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(name =>
        window.addEventListener(name, onActivity, { passive: true }))
}

/** Mốc so sánh lấy từ trang vừa dựng. Chỉ tiến lên, không lùi, để tải lại một phần không sinh làm mới giả. */
export const adoptPageVersions = (page) => {
    const fromPage = page?.props?.data_versions
    if (!fromPage || typeof fromPage !== 'object' || Array.isArray(fromPage)) return
    const merged = { ...(state.versions || {}) }
    Object.entries(fromPage).forEach(([channel, version]) => {
        if (!(channel in merged) || version > merged[channel]) merged[channel] = version
    })
    state.versions = merged
}

export const startLiveSync = (page) => {
    if (typeof window === 'undefined') return
    if (!page?.props?.auth?.user) { stopLiveSync(); return }

    adoptPageVersions(page)
    installListeners()
    if (state.started) return
    state.started = true
    state.failures = 0
    pollNow().finally(() => schedule())
}

export const stopLiveSync = () => {
    state.started = false
    clearTimeout(state.timer)
    state.timer = null
    state.versions = null
}

/** Đăng ký nhận thông báo khi các nhóm dữ liệu đổi. channels rỗng/null = mọi nhóm. Trả về hàm hủy. */
export const onLiveChange = (channels, callback) => {
    const wanted = Array.isArray(channels) && channels.length ? channels : null
    const handler = (changed) => {
        if (!wanted || changed.includes('*') || changed.some(channel => wanted.includes(channel))) callback(changed)
    }
    emitter.on('changed', handler)
    return () => emitter.off('changed', handler)
}
