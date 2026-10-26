<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, ArrowLeft, CheckCircle2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: '' });
const showSuccess = ref(false);
const statusDismissed = ref(false);

const submit = () => form.post(route('password.email'), {
    onSuccess: () => { showSuccess.value = true; },
});
</script>

<template>
    <GuestLayout>
        <Head title="Quên mật khẩu" />

        <section class="space-y-6">
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <Mail class="h-7 w-7" />
                </div>
                <h1 class="text-xl font-semibold text-gray-900">Khôi phục mật khẩu</h1>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Nhập email đã đăng ký. Chúng tôi sẽ gửi liên kết để bạn tạo mật khẩu mới.
                </p>
            </div>

            <div v-if="(status && !statusDismissed) || showSuccess" role="status" class="flex gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                <span>Liên kết đặt lại mật khẩu đã được gửi nếu email này thuộc tài khoản đã đăng ký.</span>
            </div>

            <form v-if="(!status || statusDismissed) && !showSuccess" @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel for="email" value="Địa chỉ email" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="ban@example.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <PrimaryButton class="w-full justify-center" :disabled="form.processing">
                    <span v-if="form.processing">Đang gửi...</span>
                    <span v-else>Gửi liên kết đặt lại mật khẩu</span>
                </PrimaryButton>
            </form>

            <div v-else class="text-center">
                <p class="text-sm text-gray-600">Hãy kiểm tra hộp thư đến và thư mục spam.</p>
                <button type="button" class="mt-4 text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="showSuccess = false; statusDismissed = true; form.clearErrors()">
                    Gửi lại liên kết
                </button>
            </div>

            <div class="border-t border-gray-100 pt-4 text-center">
                <Link :href="route('login')" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                    <ArrowLeft class="h-4 w-4" /> Quay lại đăng nhập
                </Link>
            </div>
        </section>
    </GuestLayout>
</template>
