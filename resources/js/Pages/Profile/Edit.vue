<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { KeyRound, ShieldCheck, UserCheck, UserRound } from 'lucide-vue-next'
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

    <main class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <PageHeader 
            title="Thiết lập cá nhân" 
            description="Quản lý thông tin hồ sơ hiển thị, email liên hệ và cài đặt bảo mật tài khoản." 
        />

        <!-- Profile Overview Banner -->
        <section class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition-all sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <!-- Avatar wrapper -->
                    <div class="relative shrink-0">
                        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 text-xl font-bold text-white shadow-md shadow-indigo-100 ring-4 ring-slate-50 sm:h-20 sm:w-20 sm:text-2xl">
                            <img v-if="avatarUrl" :src="avatarUrl" alt="Ảnh đại diện" class="h-full w-full object-cover">
                            <span v-else>{{ (user.name || 'N').charAt(0).toUpperCase() }}</span>
                        </div>
                        <span class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white ring-2 ring-white" title="Tài khoản hoạt động">
                            <UserCheck class="h-3.5 w-3.5" />
                        </span>
                    </div>

                    <!-- User Information -->
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-lg font-bold text-slate-900 sm:text-xl">
                            {{ user.name || 'Nhân viên' }}
                        </h2>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 sm:text-sm">
                            <p>Tên đăng nhập: <span class="font-semibold text-slate-700">{{ user.username || '—' }}</span></p>
                            <span class="hidden sm:inline text-slate-300">•</span>
                            <p>Email: <span class="font-medium text-slate-700">{{ user.email || '—' }}</span></p>
                        </div>
                    </div>
                </div>

                <!-- Roles Badges -->
                <div v-if="roles.length" class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 sm:border-0 sm:pt-0">
                    <span 
                        v-for="role in roles" 
                        :key="role" 
                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-100 bg-indigo-50/80 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100/80"
                    >
                        <UserRound class="h-3.5 w-3.5 text-indigo-500" />
                        {{ role }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Main Form Content -->
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <DataPanel class="rounded-2xl border border-slate-200/80 shadow-sm">
                <UpdateProfileInformationForm :must-verify-email="props.mustVerifyEmail" :status="props.status" />
            </DataPanel>
            
            <DataPanel class="rounded-2xl border border-slate-200/80 shadow-sm">
                <UpdatePasswordForm />
            </DataPanel>
        </div>

        <!-- Security Note -->
        <div class="flex items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 p-4 text-xs leading-relaxed text-amber-900 sm:text-sm">
            <KeyRound class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
            <div>
                <span class="font-semibold text-amber-950">Lưu ý về tên đăng nhập:</span> Tên đăng nhập được dùng cố định để đăng nhập vào hệ thống. Trong mọi trường hợp, tài khoản Super Admin duy nhất luôn giữ nguyên tên đăng nhập mặc định là <strong>admin</strong>.
            </div>
        </div>
    </main>
</template>