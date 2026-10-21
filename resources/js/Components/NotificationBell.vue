<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { Bell, Trash2, CheckCheck, ExternalLink, AlertTriangle, Package, FileText, Wrench } from 'lucide-vue-next'

const isOpen = ref(false)
const bellRef = ref(null)
const notifications = ref([])
const unreadCount = ref(0)
const loading = ref(false)
const loadFailed = ref(false)
let refreshTimer
let requestPending = false

const loadNotifications = async ({ notifyError = false } = {}) => {
    if (requestPending) return
    requestPending = true
    loading.value = true
    try {
        const { data } = await axios.get('/notifications')
        if (!Array.isArray(data.notifications)) throw new Error('Invalid notifications response')
        notifications.value = data.notifications
        unreadCount.value = Number(data.unread_count || 0)
        loadFailed.value = false
    } catch {
        loadFailed.value = true
        if (notifyError) toast.error('Không thể tải thông báo hệ thống')
    } finally {
        requestPending = false
        loading.value = false
    }
}

const toggleDropdown = () => {
    isOpen.value = !isOpen.value
    if (isOpen.value) loadNotifications({ notifyError: true })
}

const markAsRead = async (item) => {
    if (!item.read) {
        item.read = true
        unreadCount.value = Math.max(0, unreadCount.value - 1)
        try {
            await axios.patch(`/notifications/${encodeURIComponent(item.id)}/read`)
        } catch {
            item.read = false
            unreadCount.value += 1
            toast.error('Không thể cập nhật trạng thái thông báo')
        }
    }
}

const markAllAsRead = async () => {
    try {
        await axios.patch('/notifications/read-all')
        notifications.value.forEach((item) => { item.read = true })
        unreadCount.value = 0
    } catch {
        toast.error('Không thể đánh dấu đã đọc')
    }
}

const openNotification = (item) => {
    isOpen.value = false
    markAsRead(item)
    if (item.url) router.visit(item.url)
}

const removeNotification = async (id) => {
    const item = notifications.value.find((notification) => notification.id === id)
    try {
        await axios.delete(`/notifications/${encodeURIComponent(id)}`)
        notifications.value = notifications.value.filter((notification) => notification.id !== id)
        if (item && !item.read) unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch {
        toast.error('Không thể xóa thông báo')
    }
}

const unreadLabel = computed(() => unreadCount.value > 9 ? '9+' : unreadCount.value)

const getIcon = (type) => {
    if (type === 'repair') return Wrench
    if (type === 'warning') return AlertTriangle
    if (type === 'sale') return FileText
    return Package
}

// Đồng bộ định kỳ để chuông nhận sự kiện mới từ máy chủ.
const refresh = () => {
    if (!document.hidden) loadNotifications()
}

// Tắt dropdown khi click ngoài
const handleClickOutside = (e) => {
    if (bellRef.value && !bellRef.value.contains(e.target)) {
        isOpen.value = false
    }
}

onMounted(() => {
    loadNotifications()
    refreshTimer = window.setInterval(refresh, 60000)
    document.addEventListener('mousedown', handleClickOutside)
})
onBeforeUnmount(() => {
    window.clearInterval(refreshTimer)
    document.removeEventListener('mousedown', handleClickOutside)
})
</script>

<template>
    <div ref="bellRef" class="relative shrink-0">
        <!-- NÚT CHUÔNG THÔNG BÁO -->
        <button
            type="button"
            @click="toggleDropdown"
            class="relative flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 active:scale-95 transition shrink-0"
            title="Thông báo"
        >
            <Bell :size="18" />

            <!-- BADGE SỐ LƯỢNG CHƯA ĐỌC -->
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-black text-white shadow-xs animate-pulse"
            >
                {{ unreadLabel }}
            </span>
        </button>

        <!-- DROPDOWN DANH SÁCH THÔNG BÁO -->
        <div
            v-if="isOpen"
            class="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white p-2 shadow-2xl border border-slate-100 z-50 text-xs animate-in fade-in slide-in-from-top-2 duration-150"
        >
            <!-- HEADER DROPDOWN -->
            <div class="flex items-center justify-between px-3 py-2 border-b border-slate-100 mb-1">
                <div class="flex items-center gap-2">
                    <span class="font-extrabold text-slate-900 text-sm">Thông báo</span>
                    <span v-if="unreadCount > 0" class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-600">
                        {{ unreadCount }} mới
                    </span>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    @click="markAllAsRead"
                    class="flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-700 hover:underline transition"
                >
                    <CheckCheck :size="14" />
                    <span>Đánh dấu đã đọc</span>
                </button>
            </div>

            <!-- DANH SÁCH ITEM -->
            <div class="max-h-80 overflow-y-auto space-y-1 pr-0.5">
                <div
                    v-for="item in notifications"
                    :key="item.id"
                    @click="openNotification(item)"
                    class="group relative flex items-start gap-3 rounded-xl p-2.5 transition-all cursor-pointer"
                    :class="item.read ? 'bg-white hover:bg-slate-50' : 'bg-blue-50/50 hover:bg-blue-50'"
                >
                    <!-- ICON LOẠI -->
                    <div
                        class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg shadow-xs"
                        :class="[
                            item.type === 'warning' ? 'bg-amber-100 text-amber-600' :
                            item.type === 'repair' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'
                        ]"
                    >
                        <component :is="getIcon(item.type)" :size="16" />
                    </div>

                    <!-- NỘI DUNG -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-1">
                            <p class="font-bold text-slate-900 truncate" :class="!item.read && 'text-blue-950'">
                                {{ item.title }}
                            </p>
                            <span class="text-[10px] font-medium text-slate-400 shrink-0">{{ item.time }}</span>
                        </div>
                        <p class="mt-0.5 text-slate-600 leading-snug line-clamp-2">
                            {{ item.message }}
                        </p>

                        <!-- LINK CHI TIẾT -->
                        <div v-if="item.url" class="mt-1.5 flex items-center gap-1 text-[11px] font-bold text-blue-600">
                                <span>Xem chi tiết</span>
                                <ExternalLink :size="12" />
                        </div>
                    </div>

                    <!-- NÚT XÓA / ĐÁNH DẤU -->
                    <button
                        type="button"
                        @click.stop="removeNotification(item.id)"
                        class="opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-rose-600 transition"
                        title="Xóa thông báo"
                    >
                        <Trash2 :size="13" />
                    </button>
                </div>

                <div v-if="loading && !notifications.length" class="py-8 text-center text-slate-400 font-medium">
                    Đang tải thông báo...
                </div>
                <div v-else-if="loadFailed && !notifications.length" class="py-6 text-center text-slate-500">
                    <p>Chưa tải được thông báo.</p>
                    <button type="button" class="mt-2 font-semibold text-emerald-700 hover:underline" @click.stop="loadNotifications({ notifyError: true })">Thử lại</button>
                </div>
                <div v-else-if="!notifications.length" class="py-8 text-center text-slate-400 font-medium">
                    Không có thông báo nào
                </div>
            </div>
        </div>
    </div>
</template>
