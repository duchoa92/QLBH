<script setup>
import { ref } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { CheckCheck, FileDown, FileSpreadsheet } from 'lucide-vue-next'
import Tooltip from '@/Components/UI/Tooltip.vue'

const file = ref(null)
const previewData = ref([])
const errors = ref([])
const loading = ref(false)
const step = ref('upload') // upload | preview

/* ================= FILE ================= */
const handleFile = (e) => {
    file.value = e.target.files[0]
}

/* ================= PREVIEW ================= */
const previewFile = async () => {
    if (!file.value) return alert('Chọn file')

    const form = new FormData()
    form.append('file', file.value)

    loading.value = true

    try {
        const res = await axios.post('/products/validate', form)

        previewData.value = res.data.valid
        errors.value = res.data.errors
        step.value = 'preview'

    } catch (e) {
        alert('File lỗi')
    }

    loading.value = false
}

/* ================= IMPORT ================= */
const importFile = () => {
    const form = new FormData()
    form.append('file', file.value)

    router.post(route('products.import'), form, {
        forceFormData: true,
        onSuccess: () => {
            step.value = 'upload'
            file.value = null
        }
    })
}

/* ================= EXPORT ================= */
const exportFile = () => {
    window.open(route('products.exportData'), '_blank')
}

// Tải file mẫu
const downloadTemplate = () => {
    window.open(route('products.template'), '_blank')
}
</script>

<template>
<BaseModal title="Nhập / Xuất sản phẩm" @close="closeModal()">
<div class="p-4">

    <!-- STEP 1 -->
    <div v-if="step === 'upload'" class="space-y-4 ">

        <input type="file" @change="handleFile" />
        <Tooltip text="Kiểm tra file">
            <button
                @click="previewFile"
                class="m-1 p-2 bg-blue-600 text-white rounded"
            >
                <CheckCheck />
            </button>
        </Tooltip>
        
        

    </div>

    <!-- STEP 2 -->
    <div v-if="step === 'preview'" class="space-y-4">

        <!-- ERROR -->
        <div v-if="errors.length" class="bg-red-50 p-3 rounded">
            <b>Lỗi:</b>
            <div v-for="e in errors" :key="e.row">
                Dòng {{ e.row }}: {{ e.error }}
            </div>
        </div>

        <!-- PREVIEW -->
        <div class="max-h-[300px] overflow-auto border">
            <table class="w-full text-sm">
                <tr>
                    <th class="border p-2">Tên</th>
                    <th class="border p-2">Giá</th>
                </tr>

                <tr v-for="(item, i) in previewData" :key="i">
                    <td class="border p-2">{{ item.name }}</td>
                    <td class="border p-2">{{ item.price }}</td>
                </tr>
            </table>
        </div>

        <!-- ACTION -->
        <div class="flex gap-2">
            <button
                @click="step = 'upload'"
                class="px-3 py-2 bg-gray-300 rounded"
            >
                Quay lại
            </button>

            <button
                @click="importFile"
                class="px-3 py-2 bg-green-600 text-white rounded"
            >
                Nhập
            </button>
        </div>

    </div>

    <!-- EXPORT -->
    <div class="flex border-t pt-3 mt-3">
        
        <!-- 👇 THÊM NÚT FILE MẪU -->
            <button
                @click="downloadTemplate"
                class="flex m-1 p-2 bg-gray-600 text-white rounded"
            >
                <FileDown /> File mẫu
            </button>
        
        <button
            @click="exportFile"
            class="flex m-1 p-2 bg-green-600 text-white rounded"
        >
             <FileSpreadsheet /> Xuất Excel
        </button>
    </div>

</div>
    

</BaseModal>
</template>