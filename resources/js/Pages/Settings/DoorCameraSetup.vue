<script setup>
// Hướng dẫn camera stack go2rtc + AI; tự điền cấu hình và cập nhật trạng thái dịch vụ.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Check, Copy, RefreshCw } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'

const props = defineProps({
    info: { type: Object, required: true },
})

const confirmBox = useConfirm()

const apiUrl = computed(() => props.info.api_url || '')
const apiKey = ref(String(props.info.key || ''))
const status = ref({
    online: !!props.info.online,
    camera_ok: !!props.info.camera_ok,
    message: props.info.message || null,
    camera_configured: !!props.info.camera_configured,
    seconds_ago: props.info.seconds_ago ?? null,
    go2rtc: props.info.go2rtc || { ready: false, message: 'Chưa kiểm tra go2rtc.' },
    server_os: props.info.server_os || 'Windows',
})

const showKey = ref(false)
const copied = ref(false)
const regenerating = ref(false)
let pollTimer = null
let copiedTimer = null

const refreshStatus = async () => {
    try {
        const { data } = await window.axios.get(route('settings.door-camera.status'))
        status.value = { ...status.value, ...data }
    } catch {
        // Mất mạng tạm thời: giữ trạng thái cũ, lần hỏi sau sẽ cập nhật.
    }
}

onMounted(() => { pollTimer = window.setInterval(refreshStatus, 4000) })
onBeforeUnmount(() => {
    window.clearInterval(pollTimer)
    window.clearTimeout(copiedTimer)
})

const shownKey = computed(() => (showKey.value ? apiKey.value : `${apiKey.value.slice(0, 4)}••••••••••••••••`))
const buildCommand = (key) => [
    'cd camera-ai',
    'npm ci',
    `npm run setup -- --url ${apiUrl.value} --key ${key}${/^https:/i.test(apiUrl.value) ? ' --trust-cert' : ''}`,
].join('\n')
const displayCommand = computed(() => buildCommand(shownKey.value))
const stackCommand = computed(() => status.value.server_os === 'Linux'
    ? 'cd deploy/go2rtc && sudo docker compose up -d --build'
    : '.\\deploy\\camera-stack\\install-windows.ps1')

const copyText = async text => {
    try {
        await navigator.clipboard.writeText(text)
    } catch {
        const area = document.createElement('textarea')
        area.value = text
        area.style.position = 'fixed'
        area.style.opacity = '0'
        document.body.appendChild(area)
        area.select()
        document.execCommand('copy')
        document.body.removeChild(area)
    }
    copied.value = true
    window.clearTimeout(copiedTimer)
    copiedTimer = window.setTimeout(() => { copied.value = false }, 2000)
}
const copyCommand = () => copyText(buildCommand(apiKey.value))
const copyStackCommand = () => copyText(stackCommand.value)

const regenerateKey = () => {
    confirmBox.show({
        title: 'Tạo khóa mới cho Camera AI',
        message: 'Khóa cũ sẽ hết hiệu lực ngay. Camera AI sẽ kết nối lại sau khi bạn chạy lại lệnh cấu hình bên dưới với khóa mới.',
        confirmText: 'Tạo khóa mới',
        onConfirm: async () => {
            regenerating.value = true
            try {
                const { data } = await window.axios.post(route('settings.door-camera.key'))
                apiKey.value = String(data.key || '')
                showKey.value = false
            } finally {
                regenerating.value = false
            }
        },
    })
}

const badge = computed(() => {
    if (!status.value.online) {
        return { text: 'Chưa kết nối', classes: 'bg-slate-200 text-slate-700' }
    }
    if (!status.value.camera_ok) {
        return { text: 'AI chạy · camera lỗi', classes: 'bg-amber-100 text-amber-800' }
    }
    return { text: 'AI đang chạy', classes: 'bg-emerald-100 text-emerald-800' }
})
</script>

<template>
    <div class="space-y-3 rounded-lg border border-indigo-200 bg-indigo-50/60 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-slate-800">Thiết lập camera trực tiếp và nhận diện AI</h3>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold" :class="badge.classes">
                <span class="h-1.5 w-1.5 rounded-full" :class="status.online && status.camera_ok ? 'bg-emerald-500' : status.online ? 'bg-amber-500' : 'bg-slate-400'"></span>
                {{ badge.text }}
            </span>
        </div>

        <p v-if="status.online && status.message" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-900">
            Camera báo: {{ status.message }}
        </p>

        <div class="flex flex-wrap items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-2 text-xs">
            <span class="font-semibold text-slate-700">Video WebRTC:</span>
            <span class="rounded-full px-2 py-0.5 font-semibold" :class="status.go2rtc?.ready ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                {{ status.go2rtc?.ready ? 'go2rtc đã kết nối' : 'Cần khởi động go2rtc' }}
            </span>
            <span v-if="status.go2rtc?.message" class="text-slate-500">{{ status.go2rtc.message }}</span>
        </div>

        <ol class="space-y-2.5 text-xs leading-relaxed text-slate-700">
            <li>
                <strong>1. Nhập camera:</strong> điền địa chỉ <strong>RTSP</strong> và tài khoản/mật khẩu nếu có ở mục camera bên dưới rồi bấm <strong>Lưu</strong>. Trên Windows, cần Node.js 22 và bản go2rtc QLBH tại <code>deploy/go2rtc/go2rtc.exe</code>; chỉ cần FFmpeg trong <code>PATH</code> nếu go2rtc liên tục không tạo được JPEG từ luồng H.264/H.265. Trên Linux cần Docker Engine + Compose.
                <span v-if="!status.camera_configured" class="font-semibold text-amber-700"> (Hiện chưa lưu địa chỉ camera nào.)</span>
            </li>
            <li>
                <strong>2. Cấu hình Camera AI một lần:</strong> chạy các lệnh sau trong PowerShell/Terminal. Chúng cài thư viện và lưu khóa API; AI sẽ được camera stack khởi chạy nền ở bước tiếp theo:
                <pre class="mt-1.5 overflow-x-auto rounded-md bg-slate-900 p-2.5 text-[11px] leading-5 text-slate-100">{{ displayCommand }}</pre>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" @click="copyCommand">
                        <component :is="copied ? Check : Copy" :size="14" />
                        {{ copied ? 'Đã chép' : 'Sao chép lệnh cấu hình' }}
                    </button>
                    <button type="button" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="showKey = !showKey">
                        {{ showKey ? 'Ẩn khóa' : 'Hiện khóa' }}
                    </button>
                    <button type="button" :disabled="regenerating" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60" @click="regenerateKey">
                        <RefreshCw :size="13" :class="{ 'animate-spin': regenerating }" /> Tạo khóa mới
                    </button>
                </div>
            </li>
            <li>
                <strong>3. Cài camera stack một lần:</strong> chạy lệnh dưới từ thư mục gốc. go2rtc tự quản lý và khởi động lại Camera AI; Windows có một tác vụ nền, Linux có một container Compose:
                <pre class="mt-1.5 overflow-x-auto rounded-md bg-slate-900 p-2.5 text-[11px] leading-5 text-slate-100">{{ stackCommand }}</pre>
                <button type="button" class="mt-1 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="copyStackCommand">
                    <component :is="copied ? Check : Copy" :size="14" /> {{ copied ? 'Đã chép lệnh' : 'Sao chép lệnh cài stack' }}
                </button>
            </li>
            <li>
                <strong>4. Hoàn tất:</strong> chờ trạng thái Camera AI và go2rtc chuyển xanh. Sau đó POS và Thống kê phát video trực tiếp, nhãn nhận diện hiển thị trên hình.
            </li>
        </ol>

        <p class="text-[11px] leading-relaxed text-slate-500">
            Mở TCP và UDP cổng <code>8555</code> để máy khác xem. Nếu truy cập qua mạng khác, khai báo IP máy chủ trong <code>deploy/go2rtc/go2rtc.yaml</code> mục <code>webrtc.candidates</code>. Khóa Camera AI do hệ thống tự tạo. Đổi thông tin camera ở đây, AI tự cập nhật mà không cần cài lại. Log nằm tại <code>storage/logs/camera-stack/go2rtc.log</code>; gỡ tự khởi động trên Windows bằng <code>deploy/camera-stack/uninstall-windows.ps1</code>. Trên Linux dùng <code>docker compose ps</code> trong <code>deploy/go2rtc</code>.
        </p>
    </div>
</template>
