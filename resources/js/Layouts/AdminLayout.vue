<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Menu, X } from 'lucide-vue-next'

import Sidebar from '@/Components/Sidebar.vue'
import ConfirmBox from '@/Components/ConfirmBox.vue'
import ModalRoot from '@/Components/ModalRoot.vue'

const sidebarOpen = ref(false)
const isDesktop = ref(false)

// Trang thai collapse
const sidebarCollapsed = ref(
    JSON.parse(localStorage.getItem('sidebar-collapsed') || 'false')
)

const effectiveSidebarCollapsed = computed(() =>
    isDesktop.value && sidebarCollapsed.value
)

const page = usePage()

const currentTitle = computed(() => {
    const path = page.url.split('?')[0]

    if (path.startsWith('/pos')) return 'POS bán hàng'
    if (path.startsWith('/products')) return 'Sản phẩm'
    if (path.startsWith('/product-imeis') || path.startsWith('/imeis')) return 'IMEI'
    if (path.startsWith('/categories')) return 'Danh mục'
    if (path.startsWith('/brands')) return 'Thương hiệu'
    if (path.startsWith('/customers')) return 'Khách hàng'
    if (path.startsWith('/sales')) return 'Hóa đơn'
    if (path.startsWith('/repairs')) return 'Sửa chữa'
    if (path.startsWith('/users')) return 'Nhân viên'
    if (path.startsWith('/profile')) return 'Tài khoản'

    return 'Dashboard'
})

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem(
        'sidebar-collapsed',
        JSON.stringify(sidebarCollapsed.value)
    )
}

const updateViewport = () => {
    isDesktop.value = window.matchMedia('(min-width: 1024px)').matches
    if (isDesktop.value) {
        sidebarOpen.value = false
    }
}

onMounted(() => {
    updateViewport()
    window.addEventListener('resize', updateViewport)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateViewport)
})
</script>

<template>
<div class="h-screen w-screen overflow-hidden bg-white text-slate-900 flex flex-col">

    <!-- MOBILE OVERLAY -->
    <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"
        @click="sidebarOpen = false"
    ></div>

    <!-- ====== LAYOUT FLEX ====== -->
    <div class="flex h-full w-full overflow-hidden">

        <!-- SIDEBAR WRAPPER -->
        <div
            class="fixed inset-y-0 left-0 z-40 h-screen transition-all duration-300 lg:static shrink-0"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                effectiveSidebarCollapsed ? 'lg:w-[70px]' : 'w-[280px] lg:w-[240px]'
            ]"
        >
            <Sidebar
                :collapsed="effectiveSidebarCollapsed"
                @navigate="sidebarOpen = false"
                @toggle="toggleSidebar"
            />
        </div>

        <!-- MAIN CONTENT AREA -->
        <div class="flex-1 flex flex-col h-full min-w-0 overflow-hidden">

            <!-- HEADER -->
            <header class="shrink-0 border-b border-slate-200 bg-white/95 backdrop-blur z-20">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">

                    <!-- LEFT -->
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 lg:hidden"
                            @click="sidebarOpen = !sidebarOpen"
                            aria-label="Mở menu"
                        >
                            <X v-if="sidebarOpen" :size="20" />
                            <Menu v-else :size="20" />
                        </button>

                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Hệ thống quản lý
                            </div>
                            <h1 class="text-lg font-black text-slate-950">
                                {{ currentTitle }}
                            </h1>
                        </div>
                    </div>

                    <!-- RIGHT -->
                    <div class="flex items-center gap-2">
                        <Link
                            href="/pos"
                            class="hidden rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700 sm:inline-flex"
                        >
                            Mở POS
                        </Link>

                        <Link
                            href="/profile"
                            class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            {{ page.props.auth?.user?.name || 'Tài khoản' }}
                        </Link>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100"
                        >
                            Đăng xuất
                        </Link>
                    </div>
                </div>
            </header>

            <!-- MAIN CONTENT (CHỈ CUỘN Ở ĐÂY) -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                <div class="mx-auto max-w-[1600px]">
                    <slot />
                </div>
            </main>

        </div>
    </div>
</div>

<ConfirmBox />
<ModalRoot />
</template>