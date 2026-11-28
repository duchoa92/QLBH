<script setup>
import { computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { Send, Trash2 } from 'lucide-vue-next'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { useConfirm } from '@/Composables/useConfirm'

// Email gửi đi (SMTP) để hệ thống gửi liên kết "Quên mật khẩu". Thông báo thành công/lỗi hiện bằng toast.
const props = defineProps({
    mail: { type: Object, default: () => ({}) },
})

const page = usePage()
const confirmBox = useConfirm()

const form = useForm({
    host: props.mail.host || '',
    port: props.mail.port || '587',
    encryption: props.mail.encryption || 'tls',
    username: props.mail.username || '',
    password: '',
    from_address: props.mail.from_address || '',
    from_name: props.mail.from_name || '',
})

const testForm = useForm({
    to: page.props.auth?.user?.email || props.mail.from_address || '',
})

const save = () => form.post(route('settings.mail.update'), {
    preserveScroll: true,
    onSuccess: () => form.reset('password'),
})

const sendTest = () => testForm.post(route('settings.mail.test'), { preserveScroll: true })

const remove = () => confirmBox.show({
    title: 'Xóa cấu hình email',
    message: 'Sau khi xóa, chức năng quên mật khẩu sẽ không gửi được email.',
    confirmText: 'Xóa',
    onConfirm: () => router.delete(route('settings.mail.destroy'), { preserveScroll: true }),
})

const passwordPlaceholder = computed(() => props.mail.has_password ? 'Để trống để giữ mật khẩu cũ' : '')

const setEncryption = (value) => {
    form.encryption = value
    form.port = value === 'ssl' ? '465' : '587'
}
</script>

<template>
    <div class="space-y-4">
        <form class="space-y-3 rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="save">
            <div class="grid gap-3 sm:grid-cols-[1fr_120px]">
                <FloatingInput id="mail-host" v-model="form.host" label="Máy chủ SMTP" :error="form.errors.host" />
                <FloatingInput id="mail-port" v-model="form.port" label="Cổng" inputmode="numeric" :error="form.errors.port" />
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-500">Mã hóa</span>
                <button
                    v-for="option in [{ value: 'tls', label: 'TLS (587)' }, { value: 'ssl', label: 'SSL (465)' }]"
                    :key="option.value"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-xs font-bold transition"
                    :class="form.encryption === option.value ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                    @click="setEncryption(option.value)"
                >{{ option.label }}</button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <FloatingInput id="mail-username" v-model="form.username" label="Tài khoản" autocomplete="off" :error="form.errors.username" />
                <FloatingInput id="mail-password" v-model="form.password" secret label="Mật khẩu" :placeholder-text="passwordPlaceholder" :error="form.errors.password" />
                <FloatingInput id="mail-from" v-model="form.from_address" type="email" label="Email gửi đi" :error="form.errors.from_address" />
                <FloatingInput id="mail-from-name" v-model="form.from_name" label="Tên người gửi" :error="form.errors.from_name" />
            </div>

            <p class="text-xs text-slate-500">Gmail: smtp.gmail.com · 587 · TLS · dùng “Mật khẩu ứng dụng”.</p>

            <div class="flex items-center justify-between gap-2 pt-1">
                <button v-if="mail.configured" type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50" @click="remove">
                    <Trash2 :size="14" /> Xóa cấu hình
                </button>
                <span v-else />
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
                </button>
            </div>
        </form>

        <form v-if="mail.configured" class="space-y-2 rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="sendTest">
            <h3 class="text-sm font-bold text-slate-900">Gửi thử</h3>
            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <FloatingInput id="mail-test" v-model="testForm.to" type="email" label="Email nhận thư thử" :error="testForm.errors.to" />
                </div>
                <button type="submit" class="inline-flex h-[46px] shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-slate-700 disabled:opacity-60" :disabled="testForm.processing">
                    <Send :size="15" /> {{ testForm.processing ? 'Đang gửi...' : 'Gửi' }}
                </button>
            </div>
        </form>
    </div>
</template>
