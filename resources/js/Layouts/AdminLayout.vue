<script setup>

import Sidebar from '@/Components/Sidebar.vue';
import { Toaster } from 'vue-sonner';
import { watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

// Khởi tạo thông báo toast
const page = usePage()

watch(
    () => page.props.flash.success,

    (message) => {

        if (message) {

            success(message)

        }

    }
)

</script>

<template>

    <div class="min-h-screen bg-gray-100">

        <div class="flex">

            <Sidebar />

            <main class="flex-1 p-6">

                <slot />
                <Toaster richColors />

            </main>

        </div>

    </div>

</template>