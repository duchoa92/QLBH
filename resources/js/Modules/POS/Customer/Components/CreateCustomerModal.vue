<script setup>

import { ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({

    show: Boolean,

    keyword: {
        type: String,
        default: '',
    },
})

const emit = defineEmits([
    'close',
    'save',
])

const fullName = ref('')
const phone = ref('')

watch(

    () => props.show,

    (value) => {

        if (!value) {

            return
        }

        fullName.value = props.keyword
        phone.value = ''
    }
)

const submit = () => {

    emit(
        'save',
        {
            full_name: fullName.value,
            phone: phone.value,
        }
    )
}

</script>

<template>

<div
    v-if="show"
    class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center"
>

    <div
        class="bg-white w-[500px] rounded-xl p-5"
    >

        <div
            class="flex justify-between items-center mb-4"
        >

            <h2 class="font-bold text-lg">
                Tạo khách hàng
            </h2>

            <a
                href="/customers/create"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm text-blue-600 hover:underline"
            >
                Tạo chi tiết →
            </a>

            <button
                @click="$emit('close')"
            >
                ✕
            </button>

        </div>

        <div class="space-y-4">

            <div>

                <label>
                    Họ tên
                </label>

                <input
                    v-model="fullName"
                    class="w-full border rounded-lg p-3"
                >

            </div>

            <div>

                <label>
                    Số điện thoại
                </label>

                <input
                    v-model="phone"
                    class="w-full border rounded-lg p-3"
                >

            </div>

            <div
                class="flex justify-end gap-2 mt-5"
            >

                <button
                    @click="emit('close')"
                    class="px-4 py-2 border rounded-lg"
                >
                    Hủy
                </button>

                <button
                    @click="submit"
                    :disabled="loading"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg"
                >
                    Lưu nhanh
                </button>

            </div>


        </div>

    </div>

</div>

</template>