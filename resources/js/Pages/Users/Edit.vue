<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    user: Object,
    roles: Array,
})

const form = useForm({
    name: props.user.name,
    username: props.user.username,
    role_id: props.user.roles[0]?.id ?? null,
})

const submit = () => {
    form.put(route('users.update', props.user.id))
}
</script>

<template>
    <Head title="Sửa người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Sửa người dùng
            </h2>
        </template>

        <div class="py-6 max-w-xl mx-auto">
            <form @submit.prevent="submit" class="space-y-4">

                <div>
                    <label class="block mb-1">Tên</label>
                    <input v-model="form.name" class="w-full border px-3 py-2 rounded" />
                    <div v-if="form.errors.name" class="text-red-500 text-sm">
                        {{ form.errors.name }}
                    </div>
                </div>

                <div>
                    <label class="block mb-1">Username</label>
                    <input v-model="form.username" class="w-full border px-3 py-2 rounded" />
                    <div v-if="form.errors.username" class="text-red-500 text-sm">
                        {{ form.errors.username }}
                    </div>
                </div>

                <div>
                    <label class="block mb-1">Role</label>
                    <select v-model="form.role_id" class="w-full border px-3 py-2 rounded">
                        <option value="">-- Chọn role --</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">
                            {{ role.name }}
                        </option>
                    </select>
                    <div v-if="form.errors.role_id" class="text-red-500 text-sm">
                        {{ form.errors.role_id }}
                    </div>
                </div>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded"
                        :disabled="form.processing"
                    >
                        Lưu
                    </button>

                    <a
                        :href="route('users.index')"
                        class="px-4 py-2 border rounded"
                    >
                        Huỷ
                    </a>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
