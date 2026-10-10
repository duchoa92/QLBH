<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Camera, Check, Save, Trash2, UserRound, MailWarning } from 'lucide-vue-next'
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
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <UserRound class="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Thông tin cá nhân</h2>
                <p class="text-xs text-slate-500">Cập nhật ảnh đại diện, tên hiển thị và thông tin liên hệ của bạn.</p>
            </div>
        </header>

        <form class="space-y-5 p-5" @submit.prevent="submit">
            <!-- Avatar Picker Section -->
            <div class="flex flex-col gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-4 sm:flex-row sm:items-center">
                <div class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                    <img v-if="avatarUrl" :src="avatarUrl" alt="Ảnh đại diện" class="h-full w-full object-cover">
                    <span v-else class="text-2xl font-bold text-indigo-600">{{ (form.name || user.name || 'N').charAt(0).toUpperCase() }}</span>
                </div>

                <div class="min-w-0 flex-1 space-y-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-800">Ảnh đại diện tài khoản</p>
                        <p class="text-[11px] text-slate-500">Chấp nhận định dạng JPG, PNG hoặc WebP. Dung lượng tối đa 5 MB.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="chooseAvatar">
                        <ActionButton type="button" variant="secondary" class="!px-3 !py-1.5 text-xs font-medium" @click="avatarInput?.click()">
                            <Camera class="h-3.5 w-3.5" /> Chọn ảnh
                        </ActionButton>
                        <ActionButton v-if="user.avatar || avatarPreview" type="button" variant="ghost" class="!px-2.5 !py-1.5 text-xs text-red-600 hover:bg-red-50" @click="removeAvatar">
                            <Trash2 class="h-3.5 w-3.5" /> Xóa ảnh
                        </ActionButton>
                    </div>

                    <p v-if="form.errors.avatar" class="text-xs font-medium text-red-600">{{ form.errors.avatar }}</p>
                </div>
            </div>

            <!-- Form Inputs -->
            <div class="space-y-4">
                <FloatingInput v-model="form.name" id="profile-name" name="name" label="Họ và tên" autocomplete="name" required :error="form.errors.name" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <FloatingInput v-model="form.phone" id="profile-phone" name="phone" type="tel" label="Số điện thoại" autocomplete="tel" :error="form.errors.phone" />
                    <FloatingInput v-model="form.email" id="profile-email" name="email" type="email" label="Địa chỉ Email" autocomplete="email" required :error="form.errors.email" />
                </div>
            </div>

            <!-- Verification Notice -->
            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs text-amber-900">
                <MailWarning class="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
                <div>
                    <p>Địa chỉ email của bạn hiện chưa được xác minh.</p>
                    <Link :href="route('verification.send')" method="post" as="button" class="mt-1 font-semibold text-amber-950 underline hover:text-amber-800">
                        Bấm vào đây để gửi lại email xác minh
                    </Link>
                    <p v-if="status === 'verification-link-sent'" class="mt-1 font-medium text-emerald-700">
                        ✓ Một liên kết xác minh mới đã được gửi tới email của bạn.
                    </p>
                </div>
            </div>

            <!-- Flash Success Message -->
            <div v-if="page.props.flash?.success" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-medium text-emerald-800">
                <Check class="h-4 w-4 text-emerald-600" />
                {{ page.props.flash.success }}
            </div>

            <!-- Submit Button Footer -->
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <span v-if="form.recentlySuccessful" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                        <Check class="h-4 w-4" /> Đã cập nhật thành công
                    </span>
                </Transition>

                <ActionButton type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" /> {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                </ActionButton>
            </div>
        </form>
    </section>
</template>