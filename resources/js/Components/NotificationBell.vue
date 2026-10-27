<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { Bell, Trash2, CheckCheck, ExternalLink, AlertTriangle, Package, FileText, Wrench, SlidersHorizontal } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'

const isOpen = ref(false)
const filterMenuOpen = ref(false)
const bellRef = ref(null)
const notifications = ref([])
const activeFilter = ref('all')
const unreadCount = ref(0)
const loading = ref(false)
const loadFailed = ref(false)
const detailNotification = ref(null)
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
    const path = item.url ? new URL(item.url, window.location.origin).pathname : null
    if (item.url && path !== '/pos') router.visit(item.url)
    else detailNotification.value = item
}

const openNotificationTarget = () => {
    const url = detailNotification.value?.url
    detailNotification.value = null
    if (url) router.visit(url)
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
const filters = [
    { id: 'all', label: 'Tất cả' },
    { id: 'unread', label: 'Chưa đọc' },
    { id: 'read', label: 'Đã đọc' },
    { id: 'order', label: 'Đơn hàng' },
    { id: 'repair', label: 'Sửa chữa' },
    { id: 'system', label: 'Hệ thống' },
]
const activeFilterLabel = computed(() => filters.find(filter => filter.id === activeFilter.value)?.label || 'Tất cả')
const filterCount = (filterId) => {
    if (filterId === 'all') return notifications.value.length
    if (filterId === 'unread') return notifications.value.filter(item => !item.read).length
    if (filterId === 'read') return notifications.value.filter(item => item.read).length
    return notifications.value.filter(item => item.category === filterId).length
}
const filteredNotifications = computed(() => notifications.value.filter((item) => {
    if (activeFilter.value === 'unread') return !item.read
    if (activeFilter.value === 'read') return item.read
    if (['order', 'repair', 'system'].includes(activeFilter.value)) return item.category === activeFilter.value
    return true
}))
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
                class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-black text-white shadow-xs animate-[pulse_0.55s_ease-in-out_infinite]"
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
            <div class="mb-1 flex min-w-0 items-center gap-2 border-b border-slate-100 px-2 py-2">
                <div class="flex shrink-0 items-center gap-1.5">
                    <span class="font-extrabold text-slate-900 text-sm">Thông báo</span>
                    <span v-if="unreadCount > 0" class="rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-600">
                        {{ unreadLabel }}
                    </span>
                </div>
                <div class="relative min-w-0 flex-1">
                    <button
                        type="button"
                        aria-label="Lọc thông báo"
                        :aria-expanded="filterMenuOpen"
                        @click="filterMenuOpen = !filterMenuOpen"
                        class="flex h-8 w-full min-w-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-2 text-left text-xs font-semibold text-slate-700 transition hover:border-blue-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100"
                    >
                        <SlidersHorizontal :size="14" class="shrink-0 text-slate-500" />
                        <span class="min-w-0 flex-1 truncate">{{ activeFilterLabel }}</span>
                    </button>
                    <div v-if="filterMenuOpen" class="absolute left-0 top-full z-[60] mt-1 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                        <button
                            v-for="filter in filters"
                            :key="filter.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs font-medium transition"
                            :class="activeFilter === filter.id ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50'"
                            @click="activeFilter = filter.id; filterMenuOpen = false"
                        >
                            <span>{{ filter.label }}</span>
                            <span class="ml-3 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500">{{ filterCount(filter.id) }}</span>
                        </button>
                    </div>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    @click="markAllAsRead"
                    aria-label="Đánh dấu đã đọc"
                    title="Đánh dấu đã đọc"
                    class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-lg p-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 hover:text-blue-700 transition"
                >
                    <CheckCheck :size="16" />
                    <span class="hidden sm:inline">Đánh dấu đã đọc</span>
                </button>
            </div>

            <!-- DANH SÁCH ITEM -->
            <div class="max-h-80 overflow-y-auto space-y-1 pr-0.5">
                <div
                    v-for="item in filteredNotifications"
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
                        <p class="break-words font-bold leading-snug text-slate-900" :class="!item.read && 'text-blue-950'">
                            {{ item.title }}
                        </p>
                        <p class="mt-0.5 break-words text-slate-600 leading-snug line-clamp-2">
                            {{ item.message }}
                        </p>

                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <span class="text-[10px] font-medium text-slate-400">{{ item.time }}</span>
                            <!-- LINK CHI TIẾT -->
                            <span v-if="item.url" class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600">
                                <span>Xem chi tiết</span>
                                <ExternalLink :size="12" />
                            </span>
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
                <div v-else-if="!filteredNotifications.length" class="py-8 text-center text-slate-400 font-medium">
                    Không có thông báo phù hợp với bộ lọc
                </div>
            </div>
        </div>

        <BaseModal v-if="detailNotification" :title="detailNotification.title" size="sm" @close="detailNotification = null">
            <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ detailNotification.message || 'Không có nội dung chi tiết.' }}</p>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="detailNotification = null">Đóng</button>
                    <button v-if="detailNotification.url" type="button" class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700" @click="openNotificationTarget">Mở POS <ExternalLink :size="14" /></button>
                </div>
            </template>
        </BaseModal>
    </div>
</template>
