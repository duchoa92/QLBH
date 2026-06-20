<script setup>

import { useForm } from '@inertiajs/vue3'

const form = useForm({

    name: '',

    phone: '',

    email: '',

    address: '',

})

const submit = () => {

    form.post(
        route('suppliers.store')
    )
}

</script>

<template>

    <div class="p-6">

        <h1
            class="
                text-2xl
                font-bold
                mb-6
            "
        >
            Thêm nhà cung cấp
        </h1>

        <form
            @submit.prevent="submit"
            class="space-y-4"
        >

            <input
                v-model="form.name"
                placeholder="Tên NCC"
                class="
                    border
                    p-2
                    w-full
                "
            >

            <input
                v-model="form.phone"
                placeholder="Điện thoại"
                class="
                    border
                    p-2
                    w-full
                "
            >

            <input
                v-model="form.email"
                placeholder="Email"
                class="
                    border
                    p-2
                    w-full
                "
            >

            <input
                v-model="form.address"
                placeholder="Địa chỉ"
                class="
                    border
                    p-2
                    w-full
                "
            >

            <button
                type="submit"
                class="
                    bg-blue-600
                    text-white
                    px-4
                    py-2
                    rounded
                "
            >
                Lưu
            </button>

        </form>

    </div>

</template>