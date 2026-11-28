<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { AlertTriangle, LayoutGrid, Search, X } from 'lucide-vue-next'
import { Link } from '@inertiajs/vue3'
import { diskAlertCollapsedLevel, setDiskAlertCollapsed } from '@/Stores/diskAlert'
import { collapsedAlerts, expandAlert } from '@/Stores/systemAlerts'
import { floatingActionsCount, floatingActionsTarget } from '@/Stores/floatingActions'
import { openGlobalSearchDialog } from '@/Stores/globalSearch'

/*
 * Nút nổi duy nhất của layout: bấm để mở bảng các nút chức năng của trang hiện tại
 * (ListToolbar / PageHeader / PageActions teleport vào `body`).
 * - Kéo thả được, thả ra tự hít vào cạnh trái/phải gần nhất và nhớ vị trí.
 * - Bảng tự mở lên/xuống và sang trái/phải tùy vị trí nút để không bị tràn màn hình.
 */
const SIZE = 48
const MARGIN = 12
const DRAG_THRESHOLD = 6
const STORAGE_KEY = 'floating-actions:position'

const page = usePage()
const open = ref(false)
const root = ref(null)
const body = ref(null)
const dragging = ref(false)
const viewport = reactive({ w: 0, h: 0 })
const pos = reactive({ x: 0, y: 0 })
let drag = null

const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max))
// Kích thước vùng nhìn tối thiểu: khi cửa sổ bị thu nhỏ/thu về thanh tác vụ, trình duyệt có thể báo 0x0 trong chốc lát.
const safeW = () => Math.max(viewport.w, SIZE + MARGIN * 2)
const safeH = () => Math.max(viewport.h, SIZE + MARGIN * 2)
const bounds = () => ({ maxX: safeW() - SIZE - MARGIN, maxY: safeH() - SIZE - MARGIN })

// Vị trí gốc (neo cạnh trái/phải + khoảng cách tới đáy) được giữ nguyên; vị trí hiển thị luôn tính lại từ neo này,
// nên thu nhỏ rồi phóng to cửa sổ nút vẫn về đúng chỗ cũ và không bao giờ nằm ngoài màn hình.
const anchor = reactive({ side: 'right', fromBottom: MARGIN + 4 })
const place = () => {
    const { maxX, maxY } = bounds()
    pos.x = anchor.side === 'left' ? MARGIN : maxX
    pos.y = clamp(safeH() - SIZE - anchor.fromBottom, MARGIN, maxY)
}
const savePosition = () => {
    try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ side: anchor.side, fromBottom: anchor.fromBottom })) } catch { /* Không ảnh hưởng chức năng */ }
}
const restorePosition = () => {
    try {
        const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null')
        if (saved?.side === 'left' || saved?.side === 'right') anchor.side = saved.side
        if (Number.isFinite(saved?.fromBottom)) anchor.fromBottom = Math.max(MARGIN, saved.fromBottom)
    } catch { /* dùng vị trí mặc định */ }
    place()
}
const syncViewport = () => {
    const w = window.innerWidth
    const h = window.innerHeight
    if (w > 0 && h > 0) { viewport.w = w; viewport.h = h }
    place()
}
const onResize = syncViewport
const onVisible = () => { if (!document.hidden) syncViewport() }
// Thả nút: hít vào cạnh gần nhất và lưu neo mới.
const commitDrag = () => {
    anchor.side = pos.x + SIZE / 2 < safeW() / 2 ? 'left' : 'right'
    anchor.fromBottom = Math.max(MARGIN, Math.round(safeH() - pos.y - SIZE))
    place()
    savePosition()
}

const onPointerDown = (event) => {
    if (event.button !== undefined && event.button !== 0) return
    event.currentTarget.setPointerCapture?.(event.pointerId)
    drag = { px: event.clientX, py: event.clientY, x: pos.x, y: pos.y, moved: false }
}
const onPointerMove = (event) => {
    if (!drag) return
    const dx = event.clientX - drag.px
    const dy = event.clientY - drag.py
    if (!drag.moved && Math.hypot(dx, dy) < DRAG_THRESHOLD) return
    if (!drag.moved) { drag.moved = true; dragging.value = true; open.value = false }
    const { maxX, maxY } = bounds()
    pos.x = clamp(drag.x + dx, MARGIN, maxX)
    pos.y = clamp(drag.y + dy, MARGIN, maxY)
}
const onPointerUp = () => {
    if (!drag) return
    const moved = drag.moved
    drag = null
    if (moved) {
        dragging.value = false
        commitDrag()
    } else {
        open.value = !open.value
    }
}
// Bàn phím (Enter/Space) sinh click với detail = 0; chuột/cảm ứng đã xử lý ở pointerup.
const onKeyboardClick = (event) => { if (event.detail === 0) open.value = !open.value }

const closeIfOutside = (event) => { if (open.value && !root.value?.contains(event.target)) open.value = false }
const closeOnEscape = (event) => { if (event.key === 'Escape') open.value = false }

onMounted(() => {
    viewport.w = window.innerWidth
    viewport.h = window.innerHeight
    restorePosition()
    floatingActionsTarget.value = body.value
    window.addEventListener('resize', onResize)
    window.addEventListener('orientationchange', onResize)
    window.addEventListener('focus', onResize)
    document.addEventListener('visibilitychange', onVisible)
    document.addEventListener('mousedown', closeIfOutside)
    document.addEventListener('keydown', closeOnEscape)
})
onBeforeUnmount(() => {
    floatingActionsTarget.value = null
    window.removeEventListener('resize', onResize)
    window.removeEventListener('orientationchange', onResize)
    window.removeEventListener('focus', onResize)
    document.removeEventListener('visibilitychange', onVisible)
    document.removeEventListener('mousedown', closeIfOutside)
    document.removeEventListener('keydown', closeOnEscape)
})
watch(() => page.url, () => { open.value = false })

// Bấm một nút chức năng thì đóng bảng (nút mở modal / đổi trang nên không cần giữ bảng).
const onPanelClick = (event) => {
    if (event.target instanceof Element && event.target.closest('button, a')) setTimeout(() => { open.value = false }, 0)
}
// Cảnh báo hệ thống đã ẩn (ổ đĩa, lịch chạy nền, mạng): gộp vào nút chức năng (nút đổi màu, nhấp nháy);
// trong bảng mỗi cảnh báo chỉ hiện MỘT dòng ngắn + nút xử lý + nút "Hiện lại" để đưa thanh cảnh báo trở lại.
const alerts = computed(() => {
    const list = []
    const health = page.props.diskHealth
    if (health && diskAlertCollapsedLevel.value === health.level) {
        list.push({ id: 'disk', level: health.level === 'danger' ? 'danger' : 'warning', text: `Ổ đĩa: ${health.messages?.[0] || 'sắp đầy'}`, link: route('backups.index'), linkText: health.backup_now ? 'Sao lưu' : 'Xem', restore: () => setDiskAlertCollapsed(null) })
    }
    const scheduler = page.props.schedulerHealth
    if (scheduler && collapsedAlerts.scheduler === (scheduler.backup_enabled ? 'backup' : 'plain')) {
        list.push({ id: 'scheduler', level: 'danger', text: scheduler.backup_enabled ? 'Lịch chạy nền dừng: sao lưu tự động không chạy' : 'Lịch chạy nền chưa hoạt động', link: scheduler.backup_enabled ? route('backups.index') : null, linkText: 'Sao lưu', restore: () => expandAlert('scheduler') })
    }
    const network = page.props.networkHealth
    if (network && collapsedAlerts.network === network.title) {
        list.push({ id: 'network', level: 'warning', text: network.title, link: route('settings.index', { section: 'network' }), linkText: 'Xử lý', restore: () => expandAlert('network') })
    }
    return list
})
const hasAlert = computed(() => alerts.value.length > 0)
const diskDanger = computed(() => alerts.value.some(item => item.level === 'danger'))
const openSearch = () => { open.value = false; openGlobalSearchDialog() }

const openUp = computed(() => pos.y + SIZE / 2 > safeH() / 2)
const alignRight = computed(() => pos.x + SIZE / 2 > safeW() / 2)
const panelClass = computed(() => [
    openUp.value ? 'bottom-full mb-2.5 origin-bottom' : 'top-full mt-2.5 origin-top',
    alignRight.value ? 'right-0' : 'left-0',
    alignRight.value ? (openUp.value ? 'origin-bottom-right' : 'origin-top-right') : (openUp.value ? 'origin-bottom-left' : 'origin-top-left'),
])
</script>

<template>
    <Transition name="fa-fade">
        <div v-if="open" class="fixed inset-0 z-[54] bg-slate-900/10 backdrop-blur-[1.5px] print:hidden" aria-hidden="true" />
    </Transition>

    <div
        ref="root"
        class="floating-actions fixed left-0 top-0 z-[55] print:hidden"
        :class="{ 'is-dragging': dragging }"
        :style="{ transform: `translate3d(${pos.x}px, ${pos.y}px, 0)`, width: `${SIZE}px`, height: `${SIZE}px` }"
    >
        <Transition name="fa-pop">
            <div
                v-show="open"
                class="floating-actions__panel absolute w-max min-w-[10.5rem] max-w-[min(20rem,calc(100vw-2rem))] rounded-2xl border border-slate-300 bg-white p-1.5 shadow-2xl shadow-slate-900/15"
                :class="panelClass"
                :style="{ '--fa-shift': openUp ? '10px' : '-10px' }"
                @click="onPanelClick"
            >
                <div v-if="alerts.length" class="floating-actions__alerts">
                    <div v-for="item in alerts" :key="item.id" class="floating-actions__alert" :class="item.level === 'danger' ? 'is-danger' : 'is-warning'" role="alert">
                        <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                        <span class="floating-actions__alert-text">{{ item.text }}</span>
                        <Link v-if="item.link" :href="item.link" class="floating-actions__alert-link">{{ item.linkText }}</Link>
                        <button type="button" class="floating-actions__alert-restore" title="Hiện lại thanh cảnh báo" @click.stop="item.restore()">Hiện lại</button>
                    </div>
                </div>
                <p v-if="floatingActionsCount" class="px-2 pb-1 pt-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Chức năng</p>
                <div ref="body" class="floating-actions__body flex flex-col gap-1" />
                <button type="button" class="floating-actions__search" @click.stop="openSearch">
                    <Search class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                    <span class="whitespace-nowrap text-left leading-tight">Tìm toàn hệ thống</span>
                </button>
            </div>
        </Transition>

        <button
            type="button"
            class="floating-actions__fab"
            :class="{ 'is-open': open, 'has-alert': hasAlert, 'is-danger': diskDanger }"
            :title="open ? 'Đóng' : (hasAlert ? (diskDanger ? 'Có cảnh báo nghiêm trọng — bấm để xem' : 'Có cảnh báo — bấm để xem') : 'Chức năng (kéo để di chuyển)')"
            :aria-expanded="open"
            aria-label="Chức năng của trang"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @click="onKeyboardClick"
        >
            <span class="floating-actions__ring" />
            <LayoutGrid class="floating-actions__icon floating-actions__icon--menu" />
            <X class="floating-actions__icon floating-actions__icon--close" />
            <span v-if="hasAlert && !open" class="floating-actions__badge" aria-hidden="true">{{ alerts.length }}</span>
        </button>
    </div>
</template>

<style scoped>
/* Vị trí: mượt khi hít vào cạnh, bám sát con trỏ khi đang kéo. */
.floating-actions { transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1); will-change: transform; }
.floating-actions.is-dragging { transition: none; }

.floating-actions__fab {
    position: relative; display: flex; height: 100%; width: 100%; align-items: center; justify-content: center;
    border-radius: 9999px; color: #fff; touch-action: none; cursor: grab; user-select: none;
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 10px 24px -6px rgba(5, 150, 105, 0.55), 0 2px 6px rgba(15, 23, 42, 0.18);
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.25s ease;
}
.floating-actions__fab:hover { transform: scale(1.06); }
.floating-actions__fab:active, .is-dragging .floating-actions__fab { cursor: grabbing; transform: scale(0.94); }
.floating-actions__fab.is-open { background: linear-gradient(135deg, #334155, #0f172a); box-shadow: 0 10px 24px -6px rgba(15, 23, 42, 0.5), 0 2px 6px rgba(15, 23, 42, 0.18); }
.floating-actions__ring {
    position: absolute; inset: 0; border-radius: 9999px; pointer-events: none;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45); animation: fa-ring 2.8s ease-out infinite;
}
.floating-actions__fab.is-open .floating-actions__ring, .is-dragging .floating-actions__ring { animation: none; }
.floating-actions__icon { position: absolute; height: 1.25rem; width: 1.25rem; transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s ease; }
.floating-actions__icon--close { opacity: 0; transform: rotate(-90deg) scale(0.4); }
.floating-actions__fab.is-open .floating-actions__icon--menu { opacity: 0; transform: rotate(90deg) scale(0.4); }
.floating-actions__fab.is-open .floating-actions__icon--close { opacity: 1; transform: rotate(0) scale(1); }

/* Bảng chức năng: bung ra từ nút, từng mục trượt vào lần lượt. */
.fa-pop-enter-active { transition: opacity 0.18s ease, transform 0.26s cubic-bezier(0.34, 1.56, 0.64, 1); }
.fa-pop-leave-active { transition: opacity 0.12s ease, transform 0.14s ease; }
.fa-pop-enter-from, .fa-pop-leave-to { opacity: 0; transform: translateY(var(--fa-shift, 0)) scale(0.82); }
.fa-fade-enter-active, .fa-fade-leave-active { transition: opacity 0.2s ease; }
.fa-fade-enter-from, .fa-fade-leave-to { opacity: 0; }

.floating-actions__body > :deep(*) { animation: fa-item-in 0.26s cubic-bezier(0.22, 1, 0.36, 1) both; }
.floating-actions__body > :deep(:nth-child(1)) { animation-delay: 0.04s; }
.floating-actions__body > :deep(:nth-child(2)) { animation-delay: 0.08s; }
.floating-actions__body > :deep(:nth-child(3)) { animation-delay: 0.12s; }
.floating-actions__body > :deep(:nth-child(4)) { animation-delay: 0.16s; }
.floating-actions__body > :deep(:nth-child(n + 5)) { animation-delay: 0.2s; }

/* Nút của trang: gọn, đồng nhất, rộng hết bảng, bỏ chiều cao/đổ bóng riêng của từng trang. */
.floating-actions__body :deep(button),
.floating-actions__body :deep(a) {
    display: flex; width: 100%; height: auto !important; min-height: 2.25rem; align-items: center; justify-content: flex-start;
    gap: 0.5rem; padding: 0.4rem 0.75rem; border-radius: 0.75rem; font-size: 0.75rem; line-height: 1.1rem; font-weight: 600;
    box-shadow: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.floating-actions__body :deep(svg) { height: 0.95rem; width: 0.95rem; flex-shrink: 0; }
.floating-actions__body > :deep(div) { display: flex; width: 100%; flex-direction: column; align-items: stretch; gap: 0.25rem; }

.floating-actions__search {
    margin-top: 0.375rem; display: flex; width: 100%; align-items: center; gap: 0.5rem;
    border: 1px solid rgb(203 213 225); border-radius: 0.75rem; padding: 0.5rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: rgb(71 85 105); transition: background 0.15s ease;
}
.floating-actions__search:last-child { margin-bottom: 0; }
.floating-actions__search:hover { background: rgb(241 245 249); }

@keyframes fa-item-in { from { opacity: 0; transform: translateX(8px) scale(0.96); } to { opacity: 1; transform: none; } }
@keyframes fa-ring { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45); } 70%, 100% { box-shadow: 0 0 0 14px rgba(16, 185, 129, 0); } }
@media (prefers-reduced-motion: reduce) {
    .floating-actions, .floating-actions__fab, .floating-actions__icon { transition: none; }
    .floating-actions__ring, .floating-actions__body > :deep(*) { animation: none; }
}

/* Có cảnh báo ổ đĩa: nút đổi sang màu cam/đỏ, vòng sáng nhấp nháy và có dấu cảnh báo ở góc. */
.floating-actions__fab.has-alert:not(.is-open) { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 10px 24px -6px rgba(217, 119, 6, 0.6), 0 2px 6px rgba(15, 23, 42, 0.18); }
.floating-actions__fab.has-alert.is-danger:not(.is-open) { background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 10px 24px -6px rgba(185, 28, 28, 0.6), 0 2px 6px rgba(15, 23, 42, 0.18); }
.floating-actions__fab.has-alert:not(.is-open) .floating-actions__ring { animation: fa-ring-warn 1.2s ease-out infinite; }
.floating-actions__fab.has-alert.is-danger:not(.is-open) .floating-actions__ring { animation-name: fa-ring-danger; }
.floating-actions__fab.has-alert:not(.is-open) .floating-actions__icon--menu { animation: fa-blink 1.2s ease-in-out infinite; }
.floating-actions__badge {
    position: absolute; right: -4px; top: -4px; display: flex; height: 1.25rem; width: 1.25rem; align-items: center; justify-content: center;
    border-radius: 9999px; background: #fff; color: #dc2626; font-size: 0.6875rem; font-weight: 800; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35); animation: fa-blink 1.2s ease-in-out infinite;
}
.is-danger .floating-actions__badge { color: #b91c1c; }
.floating-actions__alerts { margin-bottom: 0.375rem; display: flex; flex-direction: column; gap: 0.25rem; }
.floating-actions__alert { display: flex; align-items: center; gap: 0.375rem; border-radius: 0.625rem; border: 1px solid; padding: 0.3rem 0.5rem; font-size: 0.6875rem; line-height: 1rem; white-space: normal; }
.floating-actions__alert.is-warning { border-color: #fcd34d; background: #fffbeb; color: #78350f; }
.floating-actions__alert.is-danger { border-color: #fca5a5; background: #fef2f2; color: #991b1b; }
.floating-actions__alert-text { min-width: 0; flex: 1; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.floating-actions__alert-link, .floating-actions__alert-restore { flex-shrink: 0; border-radius: 0.375rem; padding: 0.125rem 0.4rem; font-weight: 700; font-size: 0.625rem; background: rgba(0, 0, 0, 0.07); color: inherit; }
.floating-actions__alert-link { color: #fff; background: #d97706; }
.is-danger .floating-actions__alert-link { background: #dc2626; }
@keyframes fa-ring-warn { 0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); } 70%, 100% { box-shadow: 0 0 0 16px rgba(245, 158, 11, 0); } }
@keyframes fa-ring-danger { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.65); } 70%, 100% { box-shadow: 0 0 0 16px rgba(239, 68, 68, 0); } }
@keyframes fa-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
</style>