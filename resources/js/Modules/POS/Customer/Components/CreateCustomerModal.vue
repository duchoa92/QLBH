<script setup>

import { ref, watch } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'

const props = defineProps({

    show: Boolean,

    keyword: {
        type: String,
        default: '',
    },

    // Khách chưa có dữ liệu được nhận diện bằng camera: hiện ảnh và hỏi đồng ý lưu khuôn mặt.
    faceThumb: {
        type: String,
        default: '',
    },

    askFaceConsent: Boolean,
})

const emit = defineEmits([
    'close',
    'save',
    'full-detail',
])

const fullName = ref('')
const phone = ref('')
const faceConsent = ref(true) // DEMO: mặc định đồng ý, sửa lại thành false khi chạy thật

watch(

    () => props.show,

    (value) => {

        if (!value) {

            return
        }

        fullName.value = props.keyword
        phone.value = ''
        faceConsent.value = true // DEMO: mặc định đồng ý
    }
)

const submit = () => {

    emit(
        'save',
        {
            full_name: fullName.value,
            phone: phone.value,
            ...(props.askFaceConsent ? { face_consent: faceConsent.value } : {}),
        }
    )
}

const openFullDetail = () => {

    emit(
        'full-detail',
        {
            full_name: fullName.value,
            phone: phone.value,
        }
    )
}

</script>

<template>

<BaseModal v-if="show" title="Tạo khách hàng" size="md" @close="emit('close')">
    <div class="mb-4 flex justify-end">
        <button type="button" class="text-sm font-semibold text-blue-600 hover:underline" @click="openFullDetail">
            Tạo chi tiết →
        </button>
    </div>
    <div class="space-y-4">
        <div v-if="faceThumb" class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-2">
            <img :src="faceThumb" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover">
            <p class="text-xs text-amber-800">Khách này chưa có dữ liệu khuôn mặt. Nhập thông tin để lưu khách.</p>
        </div>
        <label class="block text-sm font-semibold text-slate-700">
            Họ tên
            <input v-model="fullName" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
        </label>
        <label class="block text-sm font-semibold text-slate-700">
            Số điện thoại
            <input v-model="phone" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
        </label>
        <label v-if="askFaceConsent" class="flex min-h-11 items-start gap-2 text-sm text-slate-700">
            <input v-model="faceConsent" type="checkbox" class="mt-1 h-4 w-4 shrink-0">
            <span>Khách đồng ý lưu khuôn mặt để nhận ra lần sau</span>
        </label>
    </div>
    <template #footer>
        <div class="flex justify-end gap-2">
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="emit('close')">Hủy</button>
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="submit">Lưu nhanh</button>
        </div>
    </template>
</BaseModal>

</template>
