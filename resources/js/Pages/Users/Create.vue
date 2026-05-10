<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    roles: Array,
});

const form = useForm({
    name: '',
    username: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
});

const submit = () => {
    form.post(route('users.store'));
};
</script>

<template>
    <Head title="Create User" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Create User
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <form @submit.prevent="submit">

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

                            <!-- Name -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Name
                                </label>

                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded border-gray-300"
                                >

                                <div
                                    v-if="form.errors.name"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Username -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Username
                                </label>

                                <input
                                    v-model="form.username"
                                    type="text"
                                    class="w-full rounded border-gray-300"
                                >

                                <div
                                    v-if="form.errors.username"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.username }}
                                </div>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Phone
                                </label>

                                <input
                                    v-model="form.phone"
                                    type="text"
                                    class="w-full rounded border-gray-300"
                                >

                                <div
                                    v-if="form.errors.phone"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.phone }}
                                </div>
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Email
                                </label>

                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded border-gray-300"
                                >

                                <div
                                    v-if="form.errors.email"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <!-- Password -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Password
                                </label>

                                <input
                                    v-model="form.password"
                                    type="password"
                                    class="w-full rounded border-gray-300"
                                >

                                <div
                                    v-if="form.errors.password"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.password }}
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Confirm Password
                                </label>

                                <input
                                    v-model="form.password_confirmation"
                                    type="password"
                                    class="w-full rounded border-gray-300"
                                >
                            </div>

                            <!-- Role -->
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">
                                    Role
                                </label>

                                <select
                                    v-model="form.role"
                                    class="w-full rounded border-gray-300"
                                >
                                    <option value="">
                                        -- Select Role --
                                    </option>

                                    <option
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="role.name"
                                    >
                                        {{ role.name }}
                                    </option>
                                </select>

                                <div
                                    v-if="form.errors.role"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ form.errors.role }}
                                </div>
                            </div>

                        </div>

                        <div class="mt-6">
                            <button
                                type="submit"
                                class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                            >
                                Create User
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>