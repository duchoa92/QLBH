<script setup>
// Hướng dẫn cài worker camera cửa ngay trong Cài đặt: khóa tự sinh, lệnh cài có sẵn thông tin, trạng thái worker.
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
    'cd door-worker',
    'npm install',
    `npm run setup -- --url ${apiUrl.value} --key ${key}`,
    'npm start',
].join('\n')
const displayCommand = computed(() => buildCommand(shownKey.value))

const copyCommand = async () => {
    const text = buildCommand(apiKey.value)
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

const regenerateKey = () => {
    confirmBox.show({
        title: 'Tạo khóa mới cho worker',
        message: 'Khóa cũ sẽ hết hiệu lực ngay. Worker đang chạy sẽ bị từ chối cho tới khi bạn chạy lại lệnh cài với khóa mới.',
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
        return { text: 'Worker chạy · camera lỗi', classes: 'bg-amber-100 text-amber-800' }
    }
    return { text: 'Worker đang chạy', classes: 'bg-emerald-100 text-emerald-800' }
})
</script>

<template>
    <div class="space-y-3 rounded-lg border border-indigo-200 bg-indigo-50/60 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-slate-800">Cài worker camera cửa</h3>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold" :class="badge.classes">
                <span class="h-1.5 w-1.5 rounded-full" :class="status.online && status.camera_ok ? 'bg-emerald-500' : status.online ? 'bg-amber-500' : 'bg-slate-400'"></span>
                {{ badge.text }}
            </span>
        </div>

        <p v-if="status.online && status.message" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-900">
            Camera báo: {{ status.message }}
        </p>

        <ol class="space-y-2.5 text-xs leading-relaxed text-slate-700">
            <li>
                <strong>1. Nhập camera:</strong> điền địa chỉ <strong>RTSP</strong> (hoặc ảnh chụp http) cùng tài khoản, mật khẩu nếu có ở mục camera bên dưới rồi bấm <strong>Lưu</strong>. Camera RTSP cần cài <code>ffmpeg</code> trên máy chạy worker (Ubuntu: <code>sudo apt install ffmpeg</code>).
                <span v-if="!status.camera_configured" class="font-semibold text-amber-700"> (Hiện chưa lưu địa chỉ camera nào.)</span>
            </li>
            <li>
                <strong>2. Cài worker:</strong> trên máy chạy nền (cùng mạng với camera), mở cửa sổ lệnh tại thư mục dự án và chạy:
                <pre class="mt-1.5 overflow-x-auto rounded-md bg-slate-900 p-2.5 text-[11px] leading-5 text-slate-100">{{ displayCommand }}</pre>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" @click="copyCommand">
                        <component :is="copied ? Check : Copy" :size="14" />
                        {{ copied ? 'Đã chép' : 'Sao chép lệnh' }}
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
                <strong>3. Chờ vài giây:</strong> khi nhãn trên đổi thành <strong>Worker đang chạy</strong> là xong. Từ đó POS không mở camera nữa mà hiện khách đang đứng ở cửa.
            </li>
        </ol>

        <p class="text-[11px] leading-relaxed text-slate-500">
            Khóa do hệ thống tự tạo, không cần sửa file cấu hình. Worker chạy ở máy khác máy chủ này thì thay địa chỉ <code>{{ apiUrl }}</code> bằng địa chỉ máy chủ mà máy đó truy cập được.
            Đổi thông tin camera ở đây về sau, worker tự cập nhật trong khoảng 10 giây, không cần cài lại.
            Lệnh dùng cho cả Windows và Ubuntu; chạy nền lâu dài trên Ubuntu xem hướng dẫn trong <code>door-worker/README.md</code>.
        </p>
    </div>
</template>
