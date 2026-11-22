<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Cloud, Link2, Trash2 } from 'lucide-vue-next'
import SettingSwitch from './SettingSwitch.vue'
import SettingCollapse from './SettingCollapse.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { formatCloudError } from '@/utils/cloudErrors'

// Kết nối kho lưu trữ đám mây: bấm "Kết nối" → đăng nhập Google/Microsoft → quay lại đây. Bản sao lưu mới tự tải lên mọi kho đang bật.
const props = defineProps({
    connections: { type: Array, default: () => [] },
    oauth: { type: Object, default: () => ({}) },
})

const confirmBox = useConfirm()

const providers = [
    { key: 'google_drive', label: 'Google Drive', tone: 'bg-blue-50 text-blue-700' },
    { key: 'onedrive', label: 'OneDrive', tone: 'bg-sky-50 text-sky-700' },
]

const originMatches = (provider) => {
    const callback = props.oauth.redirect_uri?.[provider]
    if (!callback || typeof window === 'undefined') return false
    try { return new URL(callback).origin === window.location.origin } catch { return false }
}

// Lý do chưa thể kết nối (rỗng = dùng được).
const blockedReason = (provider) => {
    if (!props.oauth[provider]) return 'Máy chủ chưa cấu hình OAuth'
    if (!props.oauth.redirect_is_secure) return 'Cần địa chỉ HTTPS công khai'
    if (!originMatches(provider)) return 'Hãy mở hệ thống bằng đúng địa chỉ OAuth'
    return ''
}

const cards = computed(() => providers.map(item => ({
    ...item,
    reason: blockedReason(item.key),
    count: props.connections.filter(connection => connection.provider === item.key).length,
})))

const connect = (provider) => router.get(route('backups.cloud.oauth.start', { provider }), {}, { preserveScroll: true })
const toggle = (connection) => router.patch(route('backups.cloud-connections.toggle', { connection: connection.id }), {}, { preserveScroll: true })
const providerLabel = (provider) => providers.find(item => item.key === provider)?.label || provider
const remove = (connection) => confirmBox.show({
    title: 'Xóa kết nối đám mây',
    message: `Xóa kết nối “${connection.name}”? Các bản sao lưu đã có trên ${providerLabel(connection.provider)} vẫn được giữ nguyên.`,
    confirmText: 'Xóa kết nối',
    onConfirm: () => router.delete(route('backups.cloud-connections.destroy', { connection: connection.id }), { preserveScroll: true }),
})
const statusText = (connection) => connection.error
    ? formatCloudError(connection.error, connection.provider)
    : connection.enabled ? 'Đang tự tải bản sao lưu mới lên' : 'Đã tạm dừng'
</script>

<template>
    <div class="space-y-3">
        <SettingCollapse title="Thêm kết nối" description="Chọn dịch vụ rồi đăng nhập để cấp quyền. Bản sao lưu mới sẽ tự tải lên mọi kho đang bật.">
            <div class="grid gap-3 p-4 sm:grid-cols-2">
                <div v-for="card in cards" :key="card.key" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="card.tone"><Cloud :size="20" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-slate-900">{{ card.label }}</span>
                        <span class="block text-xs" :class="card.reason ? 'text-amber-700' : 'text-slate-500'">{{ card.reason || (card.count ? `${card.count} kết nối` : 'Chưa kết nối') }}</span>
                    </span>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="Boolean(card.reason)"
                        @click="connect(card.key)"
                    >
                        <Link2 :size="14" /> Kết nối
                    </button>
                </div>
            </div>
            <details v-if="cards.some(card => card.reason)" class="mx-4 mb-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                <summary class="cursor-pointer font-semibold text-slate-700">Chưa kết nối được? Xem cách cấu hình</summary>
                <ol class="mt-2 list-inside list-decimal space-y-1.5 leading-relaxed">
                    <li>Tạo ứng dụng OAuth trong Google Cloud Console (Google Drive) hoặc Microsoft Entra (OneDrive).</li>
                    <li>Thêm địa chỉ chuyển hướng:
                        <code v-for="card in cards" :key="card.key" class="mt-1 block break-all rounded bg-white px-2 py-1 text-[11px]">{{ card.label }}: {{ oauth.redirect_uri?.[card.key] || 'chưa tạo được' }}</code>
                    </li>
                    <li>Đặt Client ID / Secret vào cấu hình máy chủ (<code>GOOGLE_DRIVE_CLIENT_ID/SECRET</code>, <code>MICROSOFT_ONEDRIVE_CLIENT_ID/SECRET</code>) rồi chạy <code>php artisan config:clear</code>.</li>
                    <li>Mở hệ thống bằng đúng địa chỉ gốc ở trên và bấm “Kết nối”. OneDrive cần quyền Files.ReadWrite, User.Read, offline_access.</li>
                </ol>
            </details>
        </SettingCollapse>

        <SettingCollapse title="Kho đã kết nối" :description="connections.length ? `${connections.length} kho` : 'Chưa có kho nào.'">
            <p v-if="!connections.length" class="p-4 text-sm text-slate-500">Chưa có kết nối. Bấm “Kết nối” ở trên để thêm Google Drive hoặc OneDrive.</p>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="connection in connections" :key="connection.id" class="flex items-center gap-3 p-4">
                    <SettingSwitch
                        class="min-w-0 flex-1"
                        :model-value="Boolean(connection.enabled)"
                        :title="`${connection.name} · ${providerLabel(connection.provider)}`"
                        :description="statusText(connection)"
                        @update:model-value="toggle(connection)"
                    />
                    <button type="button" class="shrink-0 rounded-lg p-2 text-red-600 hover:bg-red-50" title="Xóa kết nối" aria-label="Xóa kết nối" @click="remove(connection)"><Trash2 :size="16" /></button>
                </li>
            </ul>
        </SettingCollapse>
    </div>
</template>
