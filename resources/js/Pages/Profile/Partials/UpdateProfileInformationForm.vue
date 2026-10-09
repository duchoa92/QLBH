<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Camera, Check, Save, Trash2, UserRound } from 'lucide-vue-next'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

const props = defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: '' },
})

const page = usePage()
const user = computed(() => page.props.auth?.user || {})
const avatarInput = ref(null)
const avatarPreview = ref(null)
const form = useForm({
    name: user.value.name || '',
    email: user.value.email || '',
    phone: user.value.phone || '',
    avatar: null,
    remove_avatar: false,
})

const avatarUrl = computed(() => {
    if (avatarPreview.value) return avatarPreview.value
    if (form.remove_avatar || !user.value.avatar) return null
    return `/storage/${user.value.avatar}`
})

const clearPreview = () => {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
    avatarPreview.value = null
}

const chooseAvatar = (event) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    clearPreview()
    avatarPreview.value = URL.createObjectURL(file)
    form.avatar = file
    form.remove_avatar = false
}

const removeAvatar = () => {
    clearPreview()
    form.avatar = null
    form.remove_avatar = true
}

const submit = () => {
    form.transform(data => ({ ...data, _method: 'patch' })).post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            clearPreview()
            form.avatar = null
            form.remove_avatar = false
        },
    })
}

onBeforeUnmount(clearPreview)
</script>

<template>
    <section>
        <header class="flex items-start gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><UserRound class="h-5 w-5" /></span>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Thông tin cá nhân</h2>
                <p class="mt-0.5 text-xs text-slate-500">Cập nhật ảnh đại diện, tên hiển thị và thông tin liên hệ.</p>
            </div>
        </header>

        <form class="space-y-4 p-4 sm:p-5" @submit.prevent="submit">
            <div class="flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm">
                    <img v-if="avatarUrl" :src="avatarUrl" alt="Ảnh đại diện" class="h-full w-full object-cover">
                    <span v-else class="text-xl font-bold text-blue-700">{{ (form.name || user.name || 'N').charAt(0).toUpperCase() }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800">Ảnh đại diện</p>
                    <p class="mt-0.5 text-xs text-slate-500">JPG, PNG hoặc WebP; tối đa 5 MB.</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="chooseAvatar">
                        <ActionButton type="button" variant="secondary" class="!px-3 !py-1.5 text-xs" @click="avatarInput?.click()">
                            <Camera class="h-3.5 w-3.5" /> Chọn ảnh
                        </ActionButton>
                        <ActionButton v-if="user.avatar || avatarPreview" type="button" variant="ghost" class="!px-2.5 !py-1.5 text-xs text-red-600" @click="removeAvatar">
                            <Trash2 class="h-3.5 w-3.5" /> Xóa ảnh
                        </ActionButton>
                    </div>
                    <p v-if="form.errors.avatar" class="mt-1 text-xs text-red-600">{{ form.errors.avatar }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <FloatingInput v-model="form.name" id="profile-name" name="name" label="Họ và tên" autocomplete="name" required :error="form.errors.name" />
                <FloatingInput v-model="form.phone" id="profile-phone" name="phone" type="tel" label="Số điện thoại" autocomplete="tel" :error="form.errors.phone" />
                <FloatingInput v-model="form.email" id="profile-email" name="email" type="email" label="Email" autocomplete="email" required :error="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p>Email của bạn chưa được xác minh.</p>
                <Link :href="route('verification.send')" method="post" as="button" class="mt-1 font-semibold underline">Gửi lại email xác minh</Link>
                <p v-if="status === 'verification-link-sent'" class="mt-1 text-emerald-700">Đã gửi liên kết xác minh mới.</p>
            </div>

            <div v-if="page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <span v-if="form.recentlySuccessful" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700"><Check class="h-4 w-4" /> Đã lưu</span>
                <ActionButton type="submit" :disabled="form.processing"><Save class="h-4 w-4" />{{ form.processing ? 'Đang lưu...' : 'Lưu thông tin' }}</ActionButton>
            </div>
        </form>
    </section>
</template>
