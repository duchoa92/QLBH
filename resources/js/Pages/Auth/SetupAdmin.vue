<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

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
                <div class="mt-4">
                    <InputLabel for="username" value="Tên đăng nhập (mặc định)" />
                    <TextInput
                    v-model="form.username"
                    type="text"
                    disabled
                    placeholder="Tên đăng nhập (username)"
                    class="w-full border rounded px-3 py-2"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />

                    <InputLabel for="name" value="Tên hiển thị" />
                    <TextInput
                    v-model="form.name"
                    type="text"
                    placeholder="Tên hiển thị"
                    class="w-full border rounded px-3 py-2"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />

                    <InputLabel for="password" value="Mật khẩu" />
                    <TextInput
                    v-model="form.password"
                    type="password"
                    placeholder="Mật khẩu"
                    class="w-full border rounded px-3 py-2"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />

                    <InputLabel for="password_confirmation" value="Nhập lại mật khẩu" />
                    <TextInput
                    v-model="form.password_confirmation"
                    type="password"
                    placeholder="Nhập lại mật khẩu"
                    class="w-full border rounded px-3 py-2"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />

<!--                     <button
                    class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"
                    >
                    Tạo Admin
                    </button> -->
                    <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Khởi tạo
                </PrimaryButton>

                    <div v-if="form.errors" class="text-red-600 text-sm">
                        <div v-for="(error, key) in form.errors" :key="key">
                            {{ error }}
                        </div>
                    </div>
                </div>

            </form>
    </GuestLayout>
</template>
