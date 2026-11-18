<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Copy, RefreshCw } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'

const props = defineProps({ info: { type: Object, required: true } })
const confirmBox = useConfirm()
const status = ref({ ...props.info })
const copied = ref(false)
const regenerating = ref(false)
let pollTimer = null
let copiedTimer = null
const refreshStatus = async () => { try { const { data } = await window.axios.get(route('settings.door-camera.status')); status.value = { ...status.value, ...data } } catch {} }
onMounted(() => { pollTimer = window.setInterval(refreshStatus, 4000) })
onBeforeUnmount(() => { clearInterval(pollTimer); clearTimeout(copiedTimer) })
const installCommand = computed(() => status.value.server_os === 'Linux'
    ? 'cd deploy/camera-platform && sudo docker compose up -d --build'
    : '.\\deploy\\camera-platform\\install-windows.ps1')
const copyText = async text => {
    try { await navigator.clipboard.writeText(text) } catch { const area = document.createElement('textarea'); area.value = text; document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove() }
    copied.value = true; clearTimeout(copiedTimer); copiedTimer = setTimeout(() => { copied.value = false }, 2000)
}
const regenerateKey = () => confirmBox.show({ title: 'Tạo khóa mới cho Camera Intelligence', message: 'Khóa cũ hết hiệu lực ngay. Hệ thống sẽ tự nạp khóa mới và làm mới dịch vụ camera.', confirmText: 'Tạo khóa mới', onConfirm: async () => { regenerating.value = true; try { await window.axios.post(route('settings.door-camera.key')) } finally { regenerating.value = false } } })
const cameraConfigured = computed(() => Boolean(status.value.camera_configured))
const badge = computed(() => !cameraConfigured.value
    ? { text: 'Chưa khai báo RTSP', cls: 'bg-amber-100 text-amber-800' }
    : !status.value.online ? { text: 'Chưa kết nối', cls: 'bg-slate-200 text-slate-700' }
        : !status.value.camera_ok ? { text: 'AI đang kiểm tra camera', cls: 'bg-amber-100 text-amber-800' }
            : { text: 'AI đang chạy', cls: 'bg-emerald-100 text-emerald-800' })
</script>

<template>
    <div class="space-y-3 rounded-lg border border-indigo-200 bg-indigo-50/60 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-slate-800">Camera Platform: MediaMTX + Camera Intelligence</h3>
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="badge.cls">{{ badge.text }}</span>
        </div>
        <div v-if="!cameraConfigured" class="rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs leading-relaxed text-amber-900">
            <strong>Chưa có luồng camera để AI đọc.</strong> Cuộn lên phần <strong>Cấu hình camera</strong>, nhập RTSP chính và sub-stream H.264, rồi bấm <strong>Lưu cài đặt</strong> ở cuối hộp này. Hệ thống sẽ tự áp dụng cấu hình mới.
        </div>
        <p v-if="status.message" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-900">{{ status.message }}</p>
        <ol class="space-y-2.5 text-xs leading-relaxed text-slate-700">
            <li><strong>1. Khai báo camera:</strong> nhập và lưu hai RTSP ở phần <strong>Cấu hình camera</strong> ngay phía trên. AI và MediaMTX cùng dùng sub-stream H.264, nên hình đang xem và kết quả nhận diện là cùng một khung hình; không chuyển mã luồng trên máy chủ.</li>
            <li>
                <strong>2. Cài tự động lần đầu:</strong> sau khi đã lưu RTSP ở bước 1, mở PowerShell tại thư mục gốc QLBH và chạy đúng một lệnh bên dưới. Script tự tạo môi trường Python riêng, cài thư viện, tải/kiểm tra YuNet + SFace và đăng ký dịch vụ nền. Từ lần sau, chỉ cần bấm <strong>Lưu cài đặt</strong>: RTSP, AI và video WebRTC sẽ tự làm mới.
                <pre class="mt-1.5 overflow-x-auto rounded-md bg-slate-900 p-2.5 text-[11px] leading-5 text-slate-100">{{ installCommand }}</pre>
                <div class="mt-1.5 flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white" @click="copyText(installCommand)"><Copy :size="14" />{{ copied ? 'Đã chép' : 'Sao chép lệnh cài' }}</button>
                    <button type="button" :disabled="regenerating" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold" @click="regenerateKey"><RefreshCw :size="13" :class="{ 'animate-spin': regenerating }" />Tạo khóa mới</button>
                </div>
            </li>
            <li><strong>3. Điều kiện duy nhất ngoài QLBH:</strong> Windows cần Python 64-bit 3.10–3.14. FFmpeg bản LGPL được script tải vào thư mục runtime riêng của QLBH, không cần cài hay sửa <code>PATH</code> hệ thống.</li>
            <li><strong>4. Hoạt động nền:</strong> MediaMTX phát WebRTC; Camera Intelligence chạy độc lập 24/7, tự gửi heartbeat, nhãn và lượt khách về QLBH kể cả khi không mở trình duyệt.</li>
        </ol>
        <p class="text-[11px] text-slate-500">Mở UDP/TCP 8189 và TCP 8889 cho WebRTC trên LAN. TCP 8189 là đường dự phòng khi mạng chặn UDP. Trên Ubuntu ưu tiên Docker Compose; trên Windows script cài native service.</p>
    </div>
</template>
