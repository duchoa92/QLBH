<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const modules = [
    {
        title: 'POS ban hang',
        description: 'Mo man hinh ban hang, quet barcode va thanh toan.',
        href: '/pos',
        tone: 'bg-blue-600 text-white',
        action: 'Ban hang ngay',
    },
    {
        title: 'San pham',
        description: 'Quan ly hang hoa, ton kho, IMEI va gia ban.',
        href: '/products',
        tone: 'bg-white text-slate-900',
        action: 'Xem kho hang',
    },
    {
        title: 'Khach hang',
        description: 'Tra cuu thong tin, diem tich luy va cong no.',
        href: '/customers',
        tone: 'bg-white text-slate-900',
        action: 'Quan ly khach',
    },
    {
        title: 'Sua chua',
        description: 'Theo doi phieu sua, trang thai va lich su xu ly.',
        href: '/repairs',
        tone: 'bg-white text-slate-900',
        action: 'Xem phieu sua',
    },
]

const stats = [
    {
        label: 'Ban hang',
        value: 'POS',
    },
    {
        label: 'Kho hang',
        value: 'San pham',
    },
    {
        label: 'Cham soc',
        value: 'Khach hang',
    },
    {
        label: 'Dich vu',
        value: 'Sua chua',
    },
]
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>

        <div class="space-y-6">

            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">

                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

                    <div>

                        <div class="text-sm font-bold uppercase tracking-wide text-blue-600">
                            Tong quan he thong
                        </div>

                        <h2 class="mt-2 text-3xl font-black text-slate-950">
                            Quan ly ban hang tap trung
                        </h2>

                        <p class="mt-2 max-w-2xl text-slate-600">
                            Cac module ban hang, kho, khach hang, hoa don va sua chua da duoc gom trong mot menu quan tri thong nhat.
                        </p>

                    </div>

                    <Link
                        href="/pos"
                        class="inline-flex h-12 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-blue-700"
                    >
                        Mo POS
                    </Link>

                </div>

            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

                <div
                    v-for="item in stats"
                    :key="item.label"
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        {{ item.label }}
                    </div>

                    <div class="mt-2 text-2xl font-black text-slate-950">
                        {{ item.value }}
                    </div>
                </div>

            </section>

            <section class="grid gap-4 xl:grid-cols-4">

                <Link
                    v-for="module in modules"
                    :key="module.href"
                    :href="module.href"
                    class="rounded-lg border border-slate-200 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    :class="module.tone"
                >

                    <h3 class="text-lg font-black">
                        {{ module.title }}
                    </h3>

                    <p
                        class="mt-2 min-h-[48px] text-sm"
                        :class="module.tone.includes('bg-blue')
                            ? 'text-blue-50'
                            : 'text-slate-600'"
                    >
                        {{ module.description }}
                    </p>

                    <div
                        class="mt-4 text-sm font-bold"
                        :class="module.tone.includes('bg-blue')
                            ? 'text-white'
                            : 'text-blue-600'"
                    >
                        {{ module.action }}
                    </div>

                </Link>

            </section>

        </div>

    </AuthenticatedLayout>
</template>
