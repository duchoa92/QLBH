<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Check, Mail, UserRound } from 'lucide-vue-next'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: '' },
})

const user = usePage().props.auth.user
const form = useForm({ name: user.name || '', email: user.email || '' })
</script>

<template>
    <section>
        <header class="flex items-start gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><UserRound class="h-5 w-5" /></span>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Thông tin cá nhân</h2>
                <p class="mt-0.5 text-xs text-slate-500">Cập nhật tên hiển thị và email liên hệ.</p>
            </div>
        </header>

        <form class="space-y-4 p-4 sm:p-5" @submit.prevent="form.patch(route('profile.update'), { preserveScroll: true })">
            <div class="grid gap-4 sm:grid-cols-2">
                <FloatingInput v-model="form.name" id="profile-name" name="name" label="Họ và tên" autocomplete="name" required :error="form.errors.name" />
                <FloatingInput v-model="form.email" id="profile-email" name="email" type="email" label="Email" autocomplete="email" required :error="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p>Email của bạn chưa được xác minh.</p>
                <Link :href="route('verification.send')" method="post" as="button" class="mt-1 font-semibold underline">Gửi lại email xác minh</Link>
                <p v-if="status === 'verification-link-sent'" class="mt-1 text-emerald-700">Đã gửi liên kết xác minh mới.</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <span v-if="form.recentlySuccessful" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700"><Check class="h-4 w-4" /> Đã lưu</span>
                <ActionButton type="submit" :disabled="form.processing"><Mail class="h-4 w-4" />{{ form.processing ? 'Đang lưu...' : 'Lưu thông tin' }}</ActionButton>
            </div>
        </form>
    </section>
</template>
