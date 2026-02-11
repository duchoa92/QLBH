<script setup>
import { useForm, Link } from '@inertiajs/vue3'

defineProps({
    roles: Array
})

const form = useForm({
    name: '',
    username: '',
    password: '',
    password_confirmation: '',
    role_id: ''
})

const submit = () => {
    form.post(route('users.store'))
}
</script>

<template>
    <div class="p-6 max-w-xl">
        <h1 class="text-xl font-bold mb-6">Tạo người dùng</h1>

        <form @submit.prevent="submit" class="space-y-4">

            <div>
                <label class="block mb-1">Tên</label>
                <input v-model="form.name" class="input" />
                <div class="text-red-500 text-sm" v-if="form.errors.name">
                    {{ form.errors.name }}
                </div>
            </div>

            <div>
                <label class="block mb-1">Username</label>
                <input v-model="form.username" class="input" />
                <div class="text-red-500 text-sm" v-if="form.errors.username">
                    {{ form.errors.username }}
                </div>
            </div>

            <div>
                <label class="block mb-1">Mật khẩu</label>
                <input type="password" v-model="form.password" class="input" />
            </div>

            <div>
                <label class="block mb-1">Nhập lại mật khẩu</label>
                <input type="password" v-model="form.password_confirmation" class="input" />
            </div>

            <div>
                <label class="block mb-1">Vai trò</label>
                <select v-model="form.role_id" class="input">
                    <option v-for="r in roles" :key="r.id" :value="r.id">
                        {{ r.name }}
                    </option>
                </select>
            </div>

            <div class="flex gap-2">
                <button class="btn btn-primary" :disabled="form.processing">
                    Lưu
                </button>

                <Link :href="route('users.index')" class="btn">
                    Quay lại
                </Link>
            </div>
        </form>
    </div>
</template>
