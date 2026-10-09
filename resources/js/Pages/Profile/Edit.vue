<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { KeyRound, UserRound } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: '' },
})

const page = usePage()
const user = computed(() => page.props.auth?.user || {})
const roles = computed(() => page.props.auth?.roles || [])
const avatarUrl = computed(() => user.value.avatar ? `/storage/${user.value.avatar}` : null)
</script>

<template>
    <Head title="Thiết lập cá nhân" />

    <main class="mx-auto w-full max-w-6xl space-y-4 p-3 sm:p-5 lg:p-6">
        <PageHeader title="Thiết lập cá nhân" description="Quản lý thông tin hiển thị và bảo mật tài khoản của bạn." />

        <section class="flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-lg font-bold text-slate-600 ring-1 ring-slate-200">
                <img v-if="avatarUrl" :src="avatarUrl" alt="Ảnh đại diện" class="h-full w-full object-cover">
                <span v-else>{{ (user.name || 'N').charAt(0).toUpperCase() }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-base font-bold text-slate-900">{{ user.name || 'Nhân viên' }}</h2>
                <p class="mt-0.5 text-sm text-slate-500">Tên đăng nhập: <span class="font-semibold text-slate-700">{{ user.username || '—' }}</span></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span v-for="role in roles" :key="role" class="inline-flex items-center gap-1.5 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"><UserRound class="h-3.5 w-3.5" />{{ role }}</span>
            </div>
        </section>

        <div class="grid items-start gap-4 lg:grid-cols-2">
            <DataPanel>
                <UpdateProfileInformationForm :must-verify-email="props.mustVerifyEmail" :status="props.status" />
            </DataPanel>
            <DataPanel>
                <UpdatePasswordForm />
            </DataPanel>
        </div>

        <p class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600">
            <KeyRound class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
            Tên đăng nhập được dùng để đăng nhập hệ thống. Riêng tài khoản Super Admin chính luôn giữ tên đăng nhập <strong>admin</strong>.
        </p>
    </main>
</template>
