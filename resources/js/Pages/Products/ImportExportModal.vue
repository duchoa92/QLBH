<script setup>
import { ref } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    endpoint: String
})

const file = ref(null)
const errors = ref([])

/* ================= IMPORT ================= */
const importFile = () => {
    if (!file.value) return

    const formData = new FormData()
    formData.append('file', file.value)

    router.post(`/${props.endpoint}/import`, formData, {
        forceFormData: true,
        onSuccess: () => {
            closeModal()
        },
        onError: (err) => {
            errors.value = err.errors || ['File lỗi']
        }
    })
}

/* ================= EXPORT ================= */
const exportFile = () => {
    window.open(`/${props.endpoint}/export`, '_blank')
}

/* ================= TEMPLATE ================= */
const downloadTemplate = () => {
    window.open(`/${props.endpoint}/template`, '_blank')
}

/* ================= VALIDATE ================= */
const validateFile = async () => {
    if (!file.value) return

    const formData = new FormData()
    formData.append('file', file.value)

    const res = await fetch(`/${props.endpoint}/validate`, {
        method: 'POST',
        body: formData
    })

    const data = await res.json()
    errors.value = data.errors || []
}
</script>

<template>
<BaseModal title="Import / Export dữ liệu" @close="closeModal()">

    <div class="space-y-4">

        <!-- FILE -->
        <input type="file" @change="e => file = e.target.files[0]" />

        <!-- ACTION -->
        <div class="flex gap-2 flex-wrap">

            <button @click="importFile"
                class="px-3 py-2 bg-green-600 text-white rounded">
                Nhập file
            </button>

            <button @click="exportFile"
                class="px-3 py-2 bg-blue-600 text-white rounded">
                Xuất file
            </button>

            <button @click="downloadTemplate"
                class="px-3 py-2 bg-gray-600 text-white rounded">
                File mẫu
            </button>

            <button @click="validateFile"
                class="px-3 py-2 bg-yellow-500 text-white rounded">
                Kiểm tra file
            </button>

        </div>

        <!-- ERROR -->
        <div v-if="errors.length" class="bg-red-50 border p-2 rounded text-sm text-red-600">
            <div v-for="(e, i) in errors" :key="i">
                {{ e }}
            </div>
        </div>

    </div>

</BaseModal>
</template>