<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';



const { props } = usePage();
const status = props.status;

const form = useForm({
  username: 'admin',
  name: 'Admin',
  password: '',
  password_confirmation: ''
})

const submit = () => {
  form.post('/setup-admin')
}
</script>

<template>
    <GuestLayout>
        <Head title="Khởi tạo tài khoản quản trị" />

            <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <InputLabel value="Tên đăng nhập (mặc định)" />
                    <TextInput
                        v-model="form.username"
                        readonly
                        class="mt-1 block w-full bg-gray-100 cursor-not-allowed"
                    />

                    <InputLabel class="mt-2" for="name" value="Tên hiển thị" />
                    <TextInput
                    v-model="form.name"
                    type="text"
                    placeholder="Tên hiển thị"
                    class="mt-1 block w-full border rounded px-3 py-2"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />

                    <InputLabel class="mt-2" for="password" value="Mật khẩu" />
                    <TextInput
                    v-model="form.password"
                    type="password"
                    placeholder="Mật khẩu"
                    class="w-full border rounded px-3 py-2"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />

                    <InputLabel class="mt-2" for="password_confirmation" value="Nhập lại mật khẩu" />
                    <TextInput
                    v-model="form.password_confirmation"
                    type="password"
                    placeholder="Nhập lại mật khẩu"
                    class="w-full border rounded px-3 py-2"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />

                <div class="mt-4 flex items-center justify-end">
                    <PrimaryButton
                        class="ms-4"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Khởi tạo
                    </PrimaryButton>
                </div>
                </div>

            </form>
    </GuestLayout>
</template>
