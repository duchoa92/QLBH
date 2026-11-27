<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, ArrowLeft, CheckCircle2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    status: { type: String, default: null },
    sentTo: { type: String, default: null },
});

const form = useForm({ login: '' });
const sent = ref(Boolean(props.status));
const target = ref(props.sentTo);

const submit = () => form.post(route('password.email'), {
    preserveScroll: true,
    onSuccess: (page) => {
        sent.value = true;
        target.value = page.props.sentTo ?? null;
    },
});

const retry = () => {
    sent.value = false;
    form.clearErrors();
};
</script>

<template>
    <GuestLayout>
        <Head title="Quên mật khẩu" />

        <section class="space-y-6">
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <Mail class="h-7 w-7" />
                </div>
                <h1 class="text-xl font-semibold text-gray-900">Quên mật khẩu</h1>
            </div>

            <div v-if="sent" class="space-y-4 text-center">
                <div role="status" class="flex items-center justify-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-800">
                    <CheckCircle2 class="h-5 w-5 shrink-0" />
                    <span>Đã gửi liên kết{{ target ? ` tới ${target}` : '' }}.</span>
                </div>
                <p class="text-sm text-gray-600">Kiểm tra hộp thư đến và thư rác.</p>
                <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="retry">Gửi lại</button>
            </div>

            <form v-else class="space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="login" value="Tên đăng nhập, email hoặc số điện thoại" />
                    <TextInput
                        id="login"
                        v-model="form.login"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.login" />
                </div>

                <PrimaryButton class="w-full justify-center" :disabled="form.processing">
                    {{ form.processing ? 'Đang gửi...' : 'Gửi liên kết' }}
                </PrimaryButton>
            </form>

            <div class="border-t border-gray-100 pt-4 text-center">
                <Link :href="route('login')" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                    <ArrowLeft class="h-4 w-4" /> Quay lại đăng nhập
                </Link>
            </div>
        </section>
    </GuestLayout>
</template>
