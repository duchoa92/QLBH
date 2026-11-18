<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Check, Copy, RefreshCw } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'

const props = defineProps({ info: { type: Object, required: true } })
const confirmBox = useConfirm()
const apiUrl = computed(() => props.info.api_url || '')
const apiKey = ref(String(props.info.key || ''))
const status = ref({ ...props.info })
const showKey = ref(false)
const copied = ref(false)
const regenerating = ref(false)
let pollTimer = null
let copiedTimer = null
const refreshStatus = async () => { try { const { data } = await window.axios.get(route('settings.door-camera.status')); status.value = { ...status.value, ...data } } catch {} }
onMounted(() => { pollTimer = window.setInterval(refreshStatus, 4000) })
onBeforeUnmount(() => { clearInterval(pollTimer); clearTimeout(copiedTimer) })
const shownKey = computed(() => showKey.value ? apiKey.value : `${apiKey.value.slice(0, 4)}••••••••••••••••`)
const configCommand = key => [
    'cd camera-intelligence',
    'py -3.10 -m venv .venv',
    '.\\.venv\\Scripts\\python -m pip install -r requirements.txt',
    'Copy-Item config.example.yaml config.yaml',
    `# Mở config.yaml, đặt server.event_url = ${apiUrl.value} và server.camera_key = ${key}`,
    '.\\.venv\\Scripts\\python -m app.main --config config.yaml',
].join('\n')
const platformCommand = computed(() => status.value.server_os === 'Linux'
    ? 'cd deploy/camera-platform && sudo docker compose up -d --build'
    : '.\\deploy\\camera-platform\\install-windows.ps1')
const copyText = async text => {
    try { await navigator.clipboard.writeText(text) } catch { const area = document.createElement('textarea'); area.value = text; document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove() }
    copied.value = true; clearTimeout(copiedTimer); copiedTimer = setTimeout(() => { copied.value = false }, 2000)
}
const regenerateKey = () => confirmBox.show({ title: 'Tạo khóa mới cho Camera Intelligence', message: 'Khóa cũ hết hiệu lực ngay; hãy cập nhật config.yaml của dịch vụ.', confirmText: 'Tạo khóa mới', onConfirm: async () => { regenerating.value = true; try { const { data } = await window.axios.post(route('settings.door-camera.key')); apiKey.value = String(data.key || ''); showKey.value = false } finally { regenerating.value = false } } })
const badge = computed(() => !status.value.online ? { text: 'Chưa kết nối', cls: 'bg-slate-200 text-slate-700' } : !status.value.camera_ok ? { text: 'AI đang lỗi', cls: 'bg-amber-100 text-amber-800' } : { text: 'AI đang chạy', cls: 'bg-emerald-100 text-emerald-800' })
</script>

<template>
    <div class="space-y-3 rounded-lg border border-indigo-200 bg-indigo-50/60 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-slate-800">Camera Platform: MediaMTX + Camera Intelligence</h3>
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="badge.cls">{{ badge.text }}</span>
        </div>
        <p v-if="status.message" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-900">{{ status.message }}</p>
        <ol class="space-y-2.5 text-xs leading-relaxed text-slate-700">
            <li><strong>1. Khai báo camera:</strong> nhập RTSP chính cho AI ở phần bên dưới. Với mỗi camera, MediaMTX phát một sub-stream H.264 riêng cho WebRTC; không chuyển mã luồng xem trên máy chủ.</li>
            <li>
                <strong>2. Cài Camera Intelligence:</strong> chạy một lần, rồi cập nhật <code>config.yaml</code> với RTSP từng camera và khóa hiển thị bên dưới.
                <pre class="mt-1.5 overflow-x-auto rounded-md bg-slate-900 p-2.5 text-[11px] leading-5 text-slate-100">{{ configCommand(shownKey) }}</pre>
                <div class="mt-1.5 flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white" @click="copyText(configCommand(apiKey))"><component :is="copied ? Check : Copy" :size="14" />{{ copied ? 'Đã chép' : 'Sao chép cấu hình' }}</button>
                    <button type="button" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold" @click="showKey = !showKey">{{ showKey ? 'Ẩn khóa' : 'Hiện khóa' }}</button>
                    <button type="button" :disabled="regenerating" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold" @click="regenerateKey"><RefreshCw :size="13" :class="{ 'animate-spin': regenerating }" />Tạo khóa mới</button>
                </div>
            </li>
            <li><strong>3. Cài MediaMTX:</strong> <pre class="mt-1.5 overflow-x-auto rounded-md bg-slate-900 p-2.5 text-[11px] leading-5 text-slate-100">{{ platformCommand }}</pre></li>
            <li><strong>4. Hoạt động nền:</strong> MediaMTX phát WebRTC; Camera Intelligence chạy độc lập 24/7, tự gửi heartbeat, nhãn và lượt khách về QLBH kể cả khi không mở trình duyệt.</li>
        </ol>
        <p class="text-[11px] text-slate-500">Mở UDP/TCP 8189 và TCP 8889 cho WebRTC trên LAN. TCP 8189 là đường dự phòng khi mạng chặn UDP. Trên Ubuntu ưu tiên Docker Compose; trên Windows script cài native service.</p>
    </div>
</template>
