<script setup>

import { ref, watch } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'

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
    'full-detail',
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
        <label class="block text-sm font-semibold text-slate-700">
            Họ tên
            <input v-model="fullName" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
        </label>
        <label class="block text-sm font-semibold text-slate-700">
            Số điện thoại
            <input v-model="phone" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
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
