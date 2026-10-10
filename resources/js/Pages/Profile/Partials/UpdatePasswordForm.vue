<script setup>
import { useForm } from '@inertiajs/vue3'
import { KeyRound, ShieldCheck, Check } from 'lucide-vue-next'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.current_password) form.reset('current_password')
            if (form.errors.password) form.reset('password', 'password_confirmation')
        },
    })
}
</script>

<template>
    <section>
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <ShieldCheck class="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Bảo mật tài khoản</h2>
                <p class="text-xs text-slate-500">Đổi mật khẩu định kỳ để bảo vệ thông tin cá nhân và dữ liệu hệ thống.</p>
            </div>
        </header>

        <form class="space-y-4 p-5" @submit.prevent="updatePassword">
            <FloatingInput 
                v-model="form.current_password" 
                id="profile-current-password" 
                name="current_password" 
                type="password" 
                label="Mật khẩu hiện tại" 
                autocomplete="current-password" 
                required 
                :error="form.errors.current_password" 
            />

            <FloatingInput 
                v-model="form.password" 
                id="profile-new-password" 
                name="password" 
                type="password" 
                label="Mật khẩu mới" 
                autocomplete="new-password" 
                required 
                :error="form.errors.password" 
            />

            <FloatingInput 
                v-model="form.password_confirmation" 
                id="profile-password-confirmation" 
                name="password_confirmation" 
                type="password" 
                label="Xác nhận mật khẩu mới" 
                autocomplete="new-password" 
                required 
                :error="form.errors.password_confirmation" 
            />

            <!-- Action Footer -->
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <span v-if="form.recentlySuccessful" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                        <Check class="h-4 w-4" /> Đã đổi mật khẩu
                    </span>
                </Transition>

                <ActionButton type="submit" :disabled="form.processing">
                    <KeyRound class="h-4 w-4" /> {{ form.processing ? 'Đang cập nhật...' : 'Đổi mật khẩu' }}
                </ActionButton>
            </div>
        </form>
    </section>
</template>