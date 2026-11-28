<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Network, Power, RefreshCw, RotateCw } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { useConfirm } from '@/Composables/useConfirm'

// Mạng & máy chủ: đổi IP, khởi động lại, tắt máy ngay trong Cài đặt (Ubuntu và Windows).
// Đổi IP máy chủ: quét mạng, hiện IP dùng được / không dùng được, kiểm tra IP nhập vào rồi đổi.
// Việc đổi chạy nền trên máy chủ (tự hoàn tác nếu mất mạng). Sau khi đổi, trang tự chuyển sang địa chỉ mới.
const confirmBox = useConfirm()
const loading = ref(false)
const error = ref('')
const info = ref(null)          // kết quả quét
const newIp = ref('')
const gateway = ref('')
const dns = ref('')
const showAdvanced = ref(false)
const phase = ref('idle')       // idle | changing | moved | failed | timeout
const powerPassword = ref('')
const powerError = ref('')
const powerPhase = ref('idle') // idle | restarting | shutdown
let powerTimer = null
let oldDownSince = 0
const resultMessage = ref('')
const targetOrigin = ref('')
let pollTimer = null
let pollStartedAt = 0

const toInt = ip => ip.split('.').reduce((n, part) => n * 256 + Number(part), 0)
const validIp = ip => /^(\d{1,3})(\.\d{1,3}){3}$/.test(ip) && ip.split('.').every(part => Number(part) <= 255)

const scan = async () => {
    loading.value = true
    error.value = ''
    try {
        const { data } = await window.axios.get(route('settings.network.scan'))
        info.value = data
        if (data.supported === false) error.value = data.message
        else if (data.error) error.value = data.error
        else if (data.status?.state === 'running' && Date.now() / 1000 - data.status.time < 300) { phase.value = 'changing'; startPolling(data.status.new_ip) }
        else announceLastResult(data.status)
    } catch (e) {
        error.value = e.response?.data?.message || 'Không quét được mạng lúc này.'
    } finally {
        loading.value = false
    }
}

// Vừa chuyển sang địa chỉ mới sau khi đổi IP: báo kết quả một lần.
const announceLastResult = (status) => {
    if (!status || Date.now() / 1000 - status.time > 180) return
    let seen = null
    try { seen = window.sessionStorage.getItem('qlbh.ip.announced') } catch { /* bỏ qua */ }
    if (seen === String(status.time)) return
    try { window.sessionStorage.setItem('qlbh.ip.announced', String(status.time)) } catch { /* bỏ qua */ }
    if (status.state === 'ok') toast.success(`Đổi IP thành công: máy chủ đang dùng ${status.new_ip || 'IP mới'}.`)
    else if (status.state === 'failed') toast.error(`Đổi IP không thành công: ${status.message || 'máy giữ nguyên IP cũ.'}`)
}

// Cho phép nhập chỉ số cuối (vd 210) hoặc đủ 4 số.
const fullIp = computed(() => {
    const value = newIp.value.trim()
    if (/^\d{1,3}$/.test(value) && info.value?.current_ip) return `${info.value.current_ip.split('.').slice(0, 3).join('.')}.${value}`
    return value
})

// Kiểm tra ngay trên trình duyệt theo kết quả quét; máy chủ kiểm tra lại lần nữa trước khi đổi.
const problem = computed(() => {
    const ip = fullIp.value
    const data = info.value
    if (!ip || !data) return ''
    if (!validIp(ip)) return 'IP chưa đúng định dạng (ví dụ ' + (data.suggest?.example || '192.168.100.210') + ').'
    if (ip === data.current_ip) return ''
    const reserved = (data.reserved || []).find(item => item.ip === ip)
    if (reserved) return { network: 'Đây là địa chỉ mạng, không dùng được.', broadcast: 'Đây là địa chỉ broadcast, không dùng được.', gateway: 'Đây là IP của router (gateway).' }[reserved.reason]
    const used = (data.used || []).find(item => item.ip === ip)
    if (used) return `Đang có thiết bị khác dùng (MAC ${used.mac}).`
    const n = toInt(ip)
    if (n < toInt(data.range_first) || n > toInt(data.range_last)) return `Nằm ngoài mạng ${data.network}/${data.prefix}. Chọn trong khoảng ${data.range_first} đến ${data.range_last}.`
    return ''
})
const canSubmit = computed(() => info.value && !error.value && phase.value === 'idle' && fullIp.value && fullIp.value !== info.value.current_ip && !problem.value)

const useIp = ip => { newIp.value = ip }

const submit = () => confirmBox.show({
    title: 'Đổi IP máy chủ',
    message: `Đổi IP từ ${info.value.current_ip} sang ${fullIp.value}? Kết nối hiện tại sẽ ngắt vài chục giây; các máy khác (điện thoại, máy tính tiền) phải mở lại bằng địa chỉ mới. Nếu mất mạng sau khi đổi, hệ thống tự quay về IP cũ.`,
    confirmText: 'Đổi IP',
    onConfirm: async () => {
        try {
            const { data } = await window.axios.post(route('settings.network.apply'), {
                ip: fullIp.value, gateway: gateway.value.trim() || null, dns: dns.value.trim() || null,
            })
            phase.value = 'changing'
            resultMessage.value = ''
            toast.info('Đã gửi yêu cầu đổi IP, vui lòng đợi...')
            startPolling(data.new_ip)
        } catch (e) {
            const errors = e.response?.data?.errors
            throw new Error(e.response?.data?.message || (errors ? Object.values(errors).flat().join(' ') : 'Không gửi được yêu cầu đổi IP.'))
        }
    },
})

// Trong lúc đổi: hỏi kết quả ở địa chỉ cũ; khi địa chỉ cũ không còn vào được thì chờ địa chỉ mới trả lời rồi chuyển sang.
// Nếu trình duyệt chưa tin chứng chỉ của địa chỉ mới (nên không "gọi thử" được), sau 20 giây hiện nút mở địa chỉ mới bằng tay.
const startPolling = (ip) => {
    const port = window.location.port ? `:${window.location.port}` : ''
    targetOrigin.value = `${window.location.protocol}//${ip}${port}`
    pollStartedAt = Date.now()
    oldDownSince = 0
    clearInterval(pollTimer)
    pollTimer = window.setInterval(poll, 3000)
}
const goToNewAddress = () => {
    stopPolling()
    toast.success(`Đổi IP thành công. Đang chuyển sang ${targetOrigin.value.replace(/^https?:\/\//, '')}...`)
    window.setTimeout(() => { window.location.href = `${targetOrigin.value}/settings?section=network` }, 1200)
}
const poll = async () => {
    if (Date.now() - pollStartedAt > 4 * 60 * 1000) {
        stopPolling(); phase.value = 'timeout'
        toast.warning('Chưa thấy máy chủ ở địa chỉ mới. Máy có thể đã tự quay về IP cũ.')
        return
    }
    let reachable = false
    try {
        const { data } = await window.axios.get(route('settings.network.status'), { timeout: 4000 })
        reachable = true
        oldDownSince = 0
        const status = data.status
        if (status?.state === 'failed') {
            stopPolling(); phase.value = 'failed'
            resultMessage.value = status.message || 'Không đổi được IP, máy giữ nguyên IP cũ.'
            toast.error('Đổi IP không thành công. Máy giữ nguyên IP cũ.')
            return
        }
        if (status?.state === 'ok') {
            if (status.new_ip && status.new_ip !== window.location.hostname) { goToNewAddress(); return }
            stopPolling(); phase.value = 'idle'
            toast.success('Đổi IP thành công.')
            scan()
            return
        }
    } catch { if (!oldDownSince) oldDownSince = Date.now() }
    if (reachable) return
    try {
        await fetch(`${targetOrigin.value}/login`, { mode: 'no-cors', cache: 'no-store', signal: AbortSignal.timeout(3000) })
        goToNewAddress()
    } catch {
        if (phase.value === 'changing' && oldDownSince && Date.now() - oldDownSince > 20000) {
            phase.value = 'moved'
            toast.success('Máy chủ đã đổi IP. Bấm "Mở địa chỉ mới" để tiếp tục.')
        }
    }
}
const stopPolling = () => { clearInterval(pollTimer); pollTimer = null }

// ---- Khởi động lại / tắt máy chủ ----
const power = (action) => {
    powerError.value = ''
    if (!powerPassword.value) { powerError.value = 'Nhập mật khẩu của bạn để xác nhận.'; return }
    const restart = action === 'restart'
    confirmBox.show({
        title: restart ? 'Khởi động lại máy chủ' : 'Tắt máy chủ',
        message: restart
            ? 'Máy chủ sẽ khởi động lại: phần mềm tạm ngừng khoảng 1-3 phút. Trang sẽ tự tải lại khi máy chủ sẵn sàng.'
            : 'Máy chủ sẽ TẮT HẲN: phần mềm ngừng hoạt động cho đến khi có người bấm nút nguồn trên máy chủ. Chắc chắn tắt?',
        confirmText: restart ? 'Khởi động lại' : 'Tắt máy',
        onConfirm: async () => {
            try {
                await window.axios.post(route('settings.network.power'), { action, password: powerPassword.value })
            } catch (e) {
                const message = e.response?.data?.errors?.password?.[0] || e.response?.data?.message || 'Không gửi được yêu cầu.'
                powerError.value = message
                toast.error(message)
                throw new Error(message)
            }
            powerPassword.value = ''
            if (restart) { powerPhase.value = 'restarting'; toast.info('Đang khởi động lại máy chủ...'); watchRestart() }
            else { powerPhase.value = 'shutdown'; toast.info('Máy chủ đang tắt. Muốn bật lại, bấm nút nguồn trên máy chủ.') }
        },
    })
}
const watchRestart = () => {
    const startedAt = Date.now()
    let wentDown = false
    clearInterval(powerTimer)
    powerTimer = window.setInterval(async () => {
        if (Date.now() - startedAt > 8 * 60 * 1000) { clearInterval(powerTimer); powerPhase.value = 'idle'; toast.warning('Chưa thấy máy chủ chạy lại. Kiểm tra máy chủ.'); return }
        try {
            await fetch(`${window.location.origin}/login`, { cache: 'no-store', signal: AbortSignal.timeout(3000) })
            // Phải thấy máy chủ ngừng trả lời rồi mới chờ nó chạy lại (tránh tải lại quá sớm khi chưa kịp tắt).
            if (wentDown || Date.now() - startedAt > 60 * 1000) { clearInterval(powerTimer); toast.success('Máy chủ đã chạy lại.'); window.setTimeout(() => window.location.reload(), 800) }
        } catch { wentDown = true }
    }, 3000)
}

const retry = () => { phase.value = 'idle'; resultMessage.value = ''; scan() }
onMounted(scan)
onBeforeUnmount(() => { stopPolling(); clearInterval(powerTimer) })
</script>

<template>
    <div class="space-y-4">
        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">Đang quét mạng để tìm IP đang được dùng (5-15 giây)...</div>

        <div v-else-if="error" class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p>{{ error }}</p>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-bold" @click="scan"><RefreshCw :size="13" /> Thử lại</button>
        </div>

        <template v-else-if="info">
            <!-- Đang đổi IP -->
            <div v-if="phase === 'changing'" class="space-y-2 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <p class="font-bold">Đang đổi IP sang {{ targetOrigin.replace(/^https?:\/\//, '') }}...</p>
                <p>Kết nối có thể ngắt vài chục giây. Trang sẽ tự chuyển sang địa chỉ mới khi máy chủ sẵn sàng (cần đăng nhập lại vì địa chỉ khác). Nếu mất mạng, máy tự quay về IP cũ.</p>
            </div>
            <div v-if="phase === 'moved'" class="space-y-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                <p class="font-bold">Đã đổi IP. Máy chủ đang dùng {{ targetOrigin.replace(/^https?:\/\//, '') }}.</p>
                <p>Nếu trình duyệt báo "không bảo mật", chọn Nâng cao → Tiếp tục. Cần đăng nhập lại ở địa chỉ mới.</p>
                <a :href="`${targetOrigin}/settings?section=network`" class="inline-block rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">Mở địa chỉ mới</a>
            </div>
            <div v-if="phase === 'failed'" class="space-y-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-bold">Không đổi được IP. Máy giữ nguyên cấu hình cũ.</p>
                <p>{{ resultMessage }}</p>
                <button type="button" class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-bold" @click="retry">Quét lại và thử IP khác</button>
            </div>
            <div v-if="phase === 'timeout'" class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-bold">Chưa thấy máy chủ trả lời ở địa chỉ mới.</p>
                <p>Thử mở trực tiếp <a class="font-bold underline" :href="targetOrigin">{{ targetOrigin }}</a>. Nếu vẫn không vào được, máy đã tự quay về IP cũ ({{ info.current_ip }}): mở lại địa chỉ đó và xem kết quả ở mục này.</p>
            </div>

            <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900"><Network :size="16" /> IP máy chủ hiện tại: <span class="font-mono">{{ info.current_ip }}</span></h2>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="info.is_static ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">{{ info.is_static ? 'IP tĩnh' : 'Tự động (DHCP) - nên cố định' }}</span>
                </div>
                <dl class="grid gap-x-4 gap-y-1 text-xs text-slate-600 sm:grid-cols-2">
                    <div>Mạng: <strong class="font-mono text-slate-800">{{ info.network }}/{{ info.prefix }}</strong> (card {{ info.interface }})</div>
                    <div>Router (gateway): <strong class="font-mono text-slate-800">{{ info.gateway }}</strong></div>
                    <div class="sm:col-span-2">Có thể đặt IP trong khoảng: <strong class="font-mono text-slate-800">{{ info.range_first }}</strong> đến <strong class="font-mono text-slate-800">{{ info.range_last }}</strong></div>
                </dl>
                <p v-if="info.status && info.status.state !== 'running' && phase === 'idle'" class="rounded-md px-2.5 py-1.5 text-xs" :class="info.status.state === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'">
                    Lần đổi IP gần nhất ({{ new Date(info.status.time * 1000).toLocaleString('vi-VN') }}): {{ info.status.state === 'ok' ? 'thành công' : 'không thành công' }}. {{ info.status.state === 'ok' ? '' : info.status.message }}
                </p>
            </section>

            <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-slate-900">Chọn IP mới</h3>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100" :disabled="phase === 'changing'" @click="scan"><RefreshCw :size="13" /> Quét lại</button>
                </div>

                <div v-if="info.suggest" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                    <span>Gợi ý: chọn trong khoảng <strong class="font-mono">{{ info.suggest.from }}</strong> đến <strong class="font-mono">{{ info.suggest.to }}</strong></span>
                    <button type="button" class="rounded-md bg-emerald-600 px-2.5 py-1 font-bold text-white hover:bg-emerald-700" @click="useIp(info.suggest.example)">Dùng {{ info.suggest.example }}</button>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <FloatingInput id="net-ip" v-model="newIp" label="IP mới (đủ 4 số hoặc chỉ số cuối)" :error="problem" :disabled="phase === 'changing'" />
                </div>

                <div>
                    <p class="mb-1 text-xs font-bold text-rose-700">Không thể chọn</p>
                    <div class="flex flex-wrap gap-1.5 text-[11px]">
                        <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-slate-700" title="Địa chỉ mạng">{{ info.reserved[0].ip }} · mạng</span>
                        <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-slate-700" title="Broadcast">{{ info.reserved[1].ip }} · broadcast</span>
                        <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-slate-700" title="Router">{{ info.reserved[2].ip }} · router</span>
                        <span v-for="item in info.used" :key="item.ip" class="rounded-md bg-rose-50 px-2 py-1 font-mono text-rose-800" :title="`Đang có thiết bị dùng, MAC ${item.mac}`">{{ item.ip }} · đang dùng</span>
                    </div>
                    <p v-if="!info.used.length" class="mt-1 text-[11px] text-slate-500">Không thấy thiết bị nào khác trong mạng.</p>
                </div>

                <div>
                    <p class="mb-1 text-xs font-bold text-emerald-700">Đang trống (bấm để chọn)</p>
                    <div class="flex flex-wrap gap-1.5 text-[11px]">
                        <button v-for="range in info.free_ranges" :key="range.from" type="button" class="rounded-md bg-emerald-50 px-2 py-1 font-mono text-emerald-800 hover:bg-emerald-100" @click="useIp(range.from)">
                            {{ range.from }}{{ range.count > 1 ? ` – ${range.to.split('.').pop()}` : '' }}
                        </button>
                    </div>
                    <p v-if="!info.free_ranges.length" class="mt-1 text-[11px] text-slate-500">Không còn IP trống trong dải đã quét.</p>
                </div>

                <p class="text-[11px] leading-relaxed text-slate-500">Máy đang tắt sẽ không hiện ở trên. Nên chọn IP ngoài dải DHCP của router để không bị cấp trùng.</p>

                <button type="button" class="text-xs font-bold text-slate-500 hover:text-slate-800" @click="showAdvanced = !showAdvanced">{{ showAdvanced ? 'Ẩn' : 'Hiện' }} tùy chọn nâng cao (gateway, DNS)</button>
                <div v-if="showAdvanced" class="grid gap-3 sm:grid-cols-2">
                    <FloatingInput id="net-gateway" v-model="gateway" :label="`Gateway (để trống = ${info.gateway})`" />
                    <FloatingInput id="net-dns" v-model="dns" label="DNS, cách nhau bằng dấu phẩy (để trống = mặc định)" />
                </div>

                <div class="flex justify-end pt-1">
                    <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50" :disabled="!canSubmit" @click="submit">Đổi IP</button>
                </div>
            </section>

            <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900"><Power :size="15" /> Điều khiển máy chủ</h3>
                <p v-if="powerPhase === 'restarting'" class="rounded-md bg-blue-50 px-2.5 py-1.5 text-xs text-blue-900">Đang khởi động lại... trang sẽ tự tải lại khi máy chủ sẵn sàng.</p>
                <p v-else-if="powerPhase === 'shutdown'" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-900">Máy chủ đang tắt. Muốn bật lại, bấm nút nguồn trên máy chủ.</p>
                <template v-else>
                    <FloatingInput id="power-password" v-model="powerPassword" secret label="Mật khẩu của bạn (để xác nhận)" :error="powerError" />
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" @click="power('restart')"><RotateCw :size="14" /> Khởi động lại</button>
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700" @click="power('shutdown')"><Power :size="14" /> Tắt máy</button>
                    </div>
                </template>
            </section>
        </template>
    </div>
</template>
