<script setup>
import { ref, computed } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import axios from 'axios'
import { ArrowDownToLine, Check, CheckCheck, Download, FileDown, FileSpreadsheet, Image, Lightbulb, Sheet, SkipForward, SquareMousePointer, TriangleAlert, Undo2, X } from 'lucide-vue-next'
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
    form.append('images[]', img.file)
})

    const res = await axios.post(route('products.import'), form)

    report.value = res.data
    showReport.value = true

    duplicateModal.value = false

    loading.value = false
}


/* ================= FILE ================= */
const handleFile = (e) => {
    const f = e.target.files[0]

    if (!f) return

    const allowed = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel', // xls
        'text/csv'
    ]

    if (!allowed.includes(f.type)) {
        alert('❌ Chỉ được chọn file Excel!')
        e.target.value = ''
        return
    }

    file.value = f
}

const handleImages = (e) => {

    const files = Array.from(e.target.files)

    const validImages = files
        .filter(f => f.type.startsWith('image/'))
        .map(f => ({
            file: f,
            preview: URL.createObjectURL(f)
        }))

    images.value = validImages
}

const findImage = (name) => {
    if (!name) return null

    name = name.toLowerCase().replaceAll(' ', '')

    const img = images.value.find(i =>
        i.file.name.toLowerCase().replaceAll(' ', '') === name
    )

    return img ? img.preview : null
}

/* ================= PREVIEW ================= */
const previewFile = async () => {
    if (!file.value) return alert('Chọn file')

    const form = new FormData()
    form.append('file', file.value)

    // thêm ảnh
    images.value.forEach(img => {
        form.append('images[]', img.file)
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


const previewContainer = ref(null)

// Xử lý chuyển hướng lăn chuột dọc thành cuộn ngang
const handleWheel = (e) => {
  if (previewContainer.value) {
    // e.deltaY là độ lăn chuột dọc, gán vào scrollLeft để cuộn ngang
    previewContainer.value.scrollLeft += e.deltaY
  }
}

/* ================= IMPORT ================= */
const importFile = () => {

    importSkip()
}

// kéo thể file
const handleDropExcel = (e) => {
    e.preventDefault()
    file.value = e.dataTransfer.files[0]
}

// kéo thả ảnh
const handleDropImages = (e) => {
    const files = Array.from(e.dataTransfer.files)

    images.value = files
        .filter(f => f.type.startsWith('image/'))
        .map(f => ({
            file: f,
            preview: URL.createObjectURL(f)
        }))
}

const removeImage = (index) => {
    URL.revokeObjectURL(images.value[index].preview)
    images.value.splice(index, 1)
}

const totalSize = computed(() => {
    const total = images.value.reduce((sum, img) => sum + img.file.size, 0)
    return (total / 1024 / 1024).toFixed(2)
})
/* ================= EXPORT ================= */

const exportFile = async () => {
    try {
        exporting.value = true
        progress.value = 0

        const res = await axios.post(route('products.export.start'))
        const id = res.data.id

        interval = setInterval(async () => {
            try {
                const check = await axios.get(route('products.export.check', { id }))

                progress.value = check.data.progress || 0

                if (check.data.status === 'done') {
                    clearInterval(interval)

                    progress.value = 100

                    setTimeout(() => {
                        window.location.href = route('products.export.download', id)
                        exporting.value = false
                    }, 500)
                }

            } catch (e) {
                clearInterval(interval)
                exporting.value = false
            }
        }, 800)

    } catch (e) {
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
        <div class="p-3">
            <!-- STEP 1 -->
            <div v-if="step === 'upload'" class="space-y-4">

                <!-- ===== EXCEL ===== -->
                <div class="grid grid-cols-12 gap-4 items-start">

                   

                    <!-- DROP ZONE -->
                    <div class="col-span-7"
                        @dragover.prevent
                        @drop.prevent="handleDropExcel"
                    >

                        <div
                            class="flex flex-col items-center justify-center gap-1 border-2 border-dashed border-green-400 rounded-xl p-3 bg-white hover:bg-green-50 transition cursor-pointer"
                            @click="$refs.fileInput.click()"
                        >
                            <span><SquareMousePointer /></span>

                            <div class="font-medium">
                                Kéo & thả hoặc 
                                <span class="text-green-600 font-semibold">Chọn file</span>
                            </div>

                            <div class="text-xs text-gray-500">
                                Hỗ trợ định dạng: .xlsx, .xls, .csv
                            </div>

                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="hidden"
                                @change="handleFile"
                            />
                        </div>

                        <!-- FILE PREVIEW -->
                        <div v-if="file" class="mt-2 flex items-center justify-between border rounded-lg p-1 bg-gray-50">
                            <div class="flex text-center text-sm">
                                <Sheet class="w-5 h-5 mr-1"/> {{ file.name }}
                            </div>

                            <button @click="file=null" class="text-gray-400 hover:text-red-500">✕</button>
                        </div>

                    </div>

                    <!-- RIGHT NOTE -->
                     <div class="col-span-5 bg-green-50 rounded-xl p-3 text-sm">
                        <div class="flex items-center font-semibold text-green-700 mb-2"><Check class="flex mr-2" /> Yêu cầu file Excel</div>
                        <ul class="list-disc ml-4 text-gray-600 space-y-1 text-xs">
                            <li>Đúng định dạng file mẫu</li>
                            <li>Dòng đầu là tiêu đề</li>
                            <li>Tối đa 20MB</li>
                        </ul>
                    </div>

                </div>

                <!-- ===== IMAGE ===== -->
                <div class="grid grid-cols-12 gap-4 items-start">

                    <div 
                        class="col-span-7 min-w-0 w-full"
                        @dragover.prevent
                        @drop.prevent="handleDropImages"
                    >
                        <div
                            class="flex flex-col items-center justify-center gap-1 border-2 border-dashed border-purple-400 rounded-xl p-3 bg-white hover:bg-purple-50 transition cursor-pointer"
                            @click="$refs.imageInput.click()"
                        >
                            <div class="mb-1"><SquareMousePointer/></div>

                            <div class="font-medium text-center">
                                Kéo & thả hoặc 
                                <span class="text-purple-600 font-semibold">Chọn ảnh</span>
                            </div>

                            <div class="text-xs text-gray-500">
                                jpg, png, webp... (nhiều file)
                            </div>

                            <input
                                ref="imageInput"
                                type="file"
                                multiple
                                accept="image/*"
                                class="hidden"
                                @change="handleImages"
                            />
                        </div>

                        <div v-if="images.length" class="mt-2 w-full p-1 space-y-2">
                            <div 
                                ref="previewContainer"
                                @wheel.prevent="handleWheel"
                                class="flex gap-2 overflow-x-auto custom-scrollbar py-1 px-1"
                            >
                                <div
                                    v-for="(img, i) in images"
                                    :key="i"
                                    class="relative group w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden border bg-white shadow-sm"
                                >
                                    <img
                                        :src="img.preview"
                                        class="w-full h-full object-cover transition group-hover:scale-110"
                                    />

                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition">
                                        <button
                                            @click.stop="removeImage(i)"
                                            class="bg-red-500 hover:bg-red-600 text-white w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shadow"
                                            title="Xóa ảnh này"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 flex justify-between items-center pt-1">
                                <span>Đã chọn <strong class="text-purple-700">{{ images.length }}</strong> ảnh ({{ totalSize }} MB)</span>

                                <button 
                                    @click="images = []"
                                    class="text-red-500 hover:text-red-700 hover:underline font-medium"
                                >
                                    Xóa tất cả
                                </button>
                            </div>

                        </div>

                    </div>

                    <div class="col-span-5 bg-purple-50 rounded-xl p-3 text-sm">
                        <div class="flex items-center font-semibold text-purple-700 mb-2"><Lightbulb class="w-4 h-4 mr-2" />Lưu ý</div>
                        <ul class="list-disc ml-4 text-gray-600 space-y-1 text-xs">
                            <li>Tên ảnh trùng cột "ảnh"</li>
                            <li>Không dấu</li>
                            <li>Tối đa 10MB/ảnh</li>
                        </ul>
                    </div>

                </div>

                <!-- ACTION -->
                <div class="flex justify-between items-center border-t pt-4">

                    <button
                        @click="downloadTemplate"
                        class="flex text-center gap-1 p-2 border rounded-lg text-blue-600 hover:bg-blue-50"
                    >
                        <ArrowDownToLine /> Tải file mẫu
                    </button>

                    <div class="flex gap-2">

                        <button
                            @click="exportFile"
                            :disabled="exporting"
                            class="relative flex items-center justify-center gap-2 px-4 py-2 border rounded-lg bg-green-600 text-white hover:bg-green-500 disabled:opacity-60 disabled:cursor-not-allowed overflow-hidden"
                        >

                            <!-- Background progress bar -->
                            <div
                                v-if="exporting"
                                class="absolute left-0 top-0 h-full bg-blue-100 transition-all duration-300"
                                :style="{ width: progress + '%' }"
                            ></div>

                            <!-- Spinner -->
                            <svg
                                v-if="exporting"
                                class="w-4 h-4 animate-spin z-10"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                    fill="none"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z"
                                />
                            </svg>

                            <!-- Text -->
                            <span class="flex gap-1 z-10">
                                <template v-if="exporting">
                                    Đang xuất {{ progress }}%
                                </template>
                                <template v-else>
                                    <Sheet /> Xuất Excel
                                </template>
                            </span>

                        </button>

                        <button
                            @click="previewFile"
                            class="flex text-center gap-1 p-2 border rounded-lg bg-blue-600 hover:bg-blue-700 text-white"
                        >
                            <Check /> Kiểm tra file
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
                                    v-if="findImage(item.image_name)"
                                    :src="findImage(item.image_name)"
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
                    <div class="gap-1 flex" >
                        <TriangleAlert  /> Có <span class="text-red-600 font-weight-bold">{{ previewData.filter(i => i.is_error).length }}</span> sản phẩm lỗi trong tổng <span class="font-semibold">{{ previewData.length }}</span> sản phẩm.
                    </div>
                    
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

            <div class="flex text-green-600">
                <Check /> Đã thêm: {{ report?.count || 0 }}
            </div>

            <div class="flex text-red-600">
                <X  /> Lỗi: {{ report?.error_count || 0 }}
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