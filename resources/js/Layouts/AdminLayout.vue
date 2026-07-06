<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Sidebar from '@/Components/Sidebar.vue'
import Modal from '@/Components/Modal.vue'

const sidebarOpen = ref(false)

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

const page = usePage()

</script>

<template>

    <div class="min-h-screen bg-slate-100 text-slate-900">

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"
            @click="sidebarOpen = false"
        />

        <div
            class="fixed inset-y-0 left-0 z-40 w-72 transform transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <Sidebar @navigate="sidebarOpen = false" />
        </div>

        <div class="min-h-screen lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="Mở menu"
                        >
                            <span class="text-xl font-bold leading-none">
                                =
                            </span>
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

                    <div class="flex items-center gap-2">
                        <Link
                            href="/pos"
                            class="hidden rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 sm:inline-flex"
                        >
                            Mở POS
                        </Link>

                        <Link
                            href="/profile"
                            class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ page.props.auth?.user?.name || 'Tài khoản' }}
                        </Link>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                        >
                            Đăng xuất
                        </Link>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <div class="mx-auto max-w-[1600px]">
                    <slot />
                </div>
            </main>

        </div>
    </div>

     <div>

        <!-- GLOBAL MODAL -->
        <Modal />
    </div>

</template>
