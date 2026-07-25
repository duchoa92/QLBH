<script setup>
import { ref, computed } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import axios from 'axios'
import { CheckCheck, Download, FileDown, FileSpreadsheet, SkipForward, TriangleAlert, Undo2 } from 'lucide-vue-next'
import Tooltip from '@/Components/UI/Tooltip.vue'



const file = ref(null)
const images = ref([])
const previewData = ref([])
const loading = ref(false)
const step = ref('upload') // upload | preview

const exporting = ref(false)
const progress = ref(0)
let interval = null

const duplicateModal = ref(false)
const duplicateData = ref(null)

const report = ref(null)
const showReport = ref(false)


const importSkip = async () => {

    loading.value = true

    const form = new FormData()
    form.append('file', file.value)

    images.value.forEach(img => {
        form.append('images[]', img)
    })

    const res = await axios.post(route('products.import'), form)

    report.value = res.data
    showReport.value = true

    duplicateModal.value = false

    loading.value = false
}


/* ================= FILE ================= */
const handleFile = (e) => {
    file.value = e.target.files[0]
}
const handleImages = (e) => {
    images.value = Array.from(e.target.files)
}

/* ================= PREVIEW ================= */
const previewFile = async () => {
    if (!file.value) return alert('Chọn file')

    const form = new FormData()
    form.append('file', file.value)

    // thêm ảnh
    images.value.forEach(img => {
        form.append('images[]', img)
    })

    loading.value = true

    try {
        const res = await axios.post('/products/validate', form)

        previewData.value = res.data.valid
        step.value = 'preview'

        // 👉 lấy dòng lỗi từ preview (chuẩn nhất)
        const errorRows = previewData.value.filter(i => i.is_error)

        // 👉 set data dùng chung
        duplicateData.value = {
            error_count: errorRows.length,
            duplicate: errorRows.length, // để template dùng
            valid: previewData.value.length - errorRows.length
        }

      

    } catch (e) {
        alert('File lỗi')
    }

    loading.value = false
}


const hasError = computed(() => {
    return previewData.value.some(i => i.is_error)
})

/* ================= IMPORT ================= */
const importFile = () => {

    importSkip()
}

/* ================= EXPORT ================= */

const exportFile = async () => {

    try {
        exporting.value = true
        progress.value = 0

        // start job
        const res = await axios.post(route('products.export.start'))
        const id = res.data.id

        interval = setInterval(async () => {

            try {
                const check = await axios.get(route('products.export.check', { id: id }))

                progress.value = check.data.progress

                if (check.data.status === 'done') {

                    clearInterval(interval)

                    window.location.href = route('products.export.download', id)

                    exporting.value = false
                }

            } catch (e) {
                console.error(e)
                clearInterval(interval)
                exporting.value = false
            }

        }, 1000)

    } catch (e) {
        console.error(e)
        exporting.value = false
    }
}

// Tải file lỗi
const downloadErrorFile = async () => {

    if (!report.value?.errors?.length) return

    const res = await axios.post(
        route('products.export.errors'),
        { errors: report.value.errors },
        { responseType: 'blob' }
    )

    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'loi_import.xlsx')
    document.body.appendChild(link)
    link.click()
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
            <div v-if="step === 'upload'">

                <!-- HÀNG INPUT -->
                <div class="space-y-4">

                    <!-- FILE EXCEL -->
                    <div class="flex items-center gap-3">
                        <label class="w-28 font-medium">File Excel</label>

                        <input 
                            type="file" 
                            @change="handleFile"
                            class="border p-2 rounded w-full"
                        />
                    </div>

                    <!-- FILE ẢNH -->
                    <div class="flex items-center gap-3">
                        <label class="w-28 font-medium">Ảnh sản phẩm</label>

                        <input 
                            type="file" 
                            multiple 
                            @change="handleImages"
                            class="border p-2 rounded w-full"
                        />
                    </div>

                    <!-- BUTTON -->
                    <div class="flex justify-end">
                        <Tooltip text="Kiểm tra file">
                            <button
                                @click="previewFile"
                                class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded"
                            >
                                <CheckCheck /> Kiểm tra
                            </button>
                        </Tooltip>
                    </div>

                </div>

                <!-- EXPORT (GIỮ NGUYÊN) -->
                <div class="border-t pt-3 mt-3">

                    <div v-if="exporting" class="mt-3">
                        <div class="bg-gray-200 h-3 rounded">
                            <div
                                class="bg-green-500 h-3 rounded"
                                :style="{ width: progress + '%' }"
                            ></div>
                        </div>

                        <div class="text-sm mt-1">
                            Đang xuất: {{ progress }}%
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            @click="downloadTemplate"
                            class="flex items-center gap-1 m-1 p-2 bg-gray-600 text-white rounded"
                        >
                            <FileDown /> File mẫu
                        </button>

                        <button
                            @click="exportFile"
                            class="flex items-center gap-1 m-1 p-2 bg-green-600 text-white rounded"
                        >
                            <FileSpreadsheet /> Xuất Excel
                        </button>
                    </div>

                </div>

            </div>
            

            <!-- STEP 2 -->
            <div v-if="step === 'preview'" class="space-y-4">


                <!-- PREVIEW -->
                <div class="max-h-[300px] overflow-auto border">
                    <table class="w-full text-sm">
                        <tr>
                            <th class="border p-2">#</th>
                            <th class="border p-2">Tên</th>
                            <th class="border p-2">Ảnh</th>
                            <th class="border p-2">Giá</th>
                            <th class="border p-2">Trạng thái</th>
                        </tr>

                        <tr
                            v-for="(item, i) in previewData"
                            :key="i"
                            :class="item.is_error ? 'bg-red-100' : 'bg-green-50'"
                        >
                            <td>{{ i + 1 }}</td>

                            <td>{{ item.name }}</td>
                            <td>
                                <img 
                                    v-if="item.image_url"
                                    :src="item.image_url"
                                    class="w-12 h-12 object-cover rounded"
                                />

                                <span v-else class="text-red-500">Thiếu ảnh</span>
                            </td>

                            <td>{{ item.sell_price }}</td>

                            <td
                                :class="item.is_error ? 'text-red-600' : 'text-green-600'"
                                class="font-semibold"
                            >
                                {{ item.status }}
                            </td>
                        </tr>
                    </table>
                </div>

                <!--Kết quả-->

                <div v-if="hasError" class="bg-yellow-100 p-3 rounded mt-3">

                    <TriangleAlert /> Có <span class="text-red-600 font-weight-bold">{{ previewData.filter(i => i.is_error).length }}</span> sản phẩm lỗi

                    <div class="flex gap-2 mt-3">
                        <button
                            @click="importSkip"
                            class="flex items-center gap-1 px-3 py-2 bg-blue-500 text-white rounded"
                        >
                            <SkipForward /> Bỏ qua {{ previewData.filter(i => i.is_error).length }} sản phẩm lỗi
                        </button>

                        <button
                            @click="step = 'upload'"
                            class="flex items-center gap-1 px-3 py-2 bg-gray-400 text-white rounded"
                        >
                            <Undo2 /> Quay lại
                        </button>
                    </div>

                </div>

                <div v-else class="flex gap-2 mt-3">

                    <button
                        @click="importFile"
                        class="px-3 py-2 bg-green-600 text-white rounded"
                    >
                        Nhập
                    </button>

                    <button
                        @click="closeModal()"
                        class="px-3 py-2 bg-gray-400 text-white rounded"
                    >
                        Hủy
                    </button>

                </div>

            </div>

        </div>
        
    </BaseModal>

    <BaseModal v-if="showReport" title="Kết quả import" @close="showReport = false">
        <div class="p-4 space-y-3">

            <div class="text-green-600">
                ✔ Đã thêm: {{ report?.count || 0 }}
            </div>

            <div class="text-red-600">
                ❌ Lỗi: {{ report?.error_count || 0 }}
            </div>

            <div class="flex">
                <button
                    v-if="report?.errors?.length"
                    @click="downloadErrorFile"
                    class="flex m-1 px-3 py-1 bg-red-500 text-white rounded"
                >
                    <Download /> Xuất file lỗi
                </button>

            </div>

        </div>
    </BaseModal>

</template>