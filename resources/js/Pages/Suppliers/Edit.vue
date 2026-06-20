<script setup>

import { useForm } from '@inertiajs/vue3'

const props = defineProps({

    supplier: Object,

})

const form = useForm({

    name:
        props.supplier.name,

    phone:
        props.supplier.phone,

    email:
        props.supplier.email,

    address:
        props.supplier.address,

})

const submit = () => {

    form.put(

        route(
            'suppliers.update',
            props.supplier.id
        )

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
            Sửa nhà cung cấp
        </h1>

        <form
            @submit.prevent="submit"
            class="space-y-4"
        >

            <input
                v-model="form.name"
                class="
                    border
                    p-2
                    w-full
                "
            >

            <input
                v-model="form.phone"
                class="
                    border
                    p-2
                    w-full
                "
            >

            <input
                v-model="form.email"
                class="
                    border
                    p-2
                    w-full
                "
            >

            <input
                v-model="form.address"
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
                Cập nhật
            </button>

        </form>

    </div>

</template>