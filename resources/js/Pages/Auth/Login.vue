<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FloatingInput from '@/Components/UI/FloatingInput.vue';
import { ArrowRight, ShieldCheck } from 'lucide-vue-next';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Đăng nhập" />

        <section class="space-y-6">
            <header class="text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 shadow-sm ring-1 ring-emerald-100">
                    <ShieldCheck class="h-6 w-6" />
                </span>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">Chào mừng bạn trở lại</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Đăng nhập để tiếp tục quản lý cửa hàng của bạn.</p>
            </header>

            <div v-if="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm font-medium text-emerald-800">
                {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <FloatingInput
                        id="login"
                        v-model="form.login"
                        class="block w-full"
                        required
                        autofocus
                        autocomplete="username"
                        label="Tên đăng nhập, email hoặc số điện thoại"
                        :error="form.errors.login"
                    />
                </div>

                <div>
                    <FloatingInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="block w-full"
                        required
                        autocomplete="current-password"
                        label="Mật khẩu"
                        :error="form.errors.password"
                    />
                </div>

                <div class="flex items-center justify-between gap-3">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <Checkbox name="remember" v-model:checked="form.remember" />
                        Ghi nhớ đăng nhập
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm font-semibold text-emerald-700 transition hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                    >Quên mật khẩu?</Link>
                </div>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Đang đăng nhập...' : 'Đăng nhập' }}
                    <ArrowRight v-if="!form.processing" class="h-4 w-4" />
                </button>
            </form>

            <p class="flex items-center justify-center gap-1.5 text-center text-xs text-slate-400"><ShieldCheck class="h-3.5 w-3.5" /> Thông tin đăng nhập được bảo vệ an toàn.</p>
        </section>
    </GuestLayout>
</template>
