<script setup>
import { ref, computed } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import axios from 'axios'
import { ArrowDownToLine, Check, CheckCheck, Download, FileDown, FileSpreadsheet, Folder, Image, Lightbulb, Sheet, SkipForward, SquareMousePointer, TriangleAlert, Undo2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import JSZip from 'jszip'



const file = ref(null)
const images = ref([])
const previewData = ref([])
const step = ref('upload') // upload | preview
const checking = ref(false) 
const exporting = ref(false)
const isSkipping = ref(false)
const progress = ref(0)
let interval = null

const duplicateModal = ref(false)
const duplicateData = ref(null)

const report = ref(null)
const showReport = ref(false)


const importSkip = async () => {

    isSkipping.value = true

    const form = new FormData()
    form.append('file', file.value)

    images.value.forEach(img => {
    form.append('images[]', img.file)
})
    try {
        const res = await axios.post(route('products.import'), form)

        report.value = res.data
        showReport.value = true
        // reset
        isSkipping.value = false
        duplicateModal.value = false

        // ✅ THÊM ĐOẠN NÀY
        if (res.data.success) {
            toast.success(`Import ${res.data.count} sản phẩm thành công`)
        }

        if (res.data.error_count > 0) {
            toast.warning(`Có ${res.data.error_count} sản phẩm lỗi`)
        }

    } catch (e) {
        toast.error('Import thất bại')
    }
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
        toast.error('❌ Chỉ được chọn file Excel!')
        e.target.value = ''
        return
    }

    file.value = f
    
    // reset input để lần sau chọn lại vẫn trigger
    e.target.value = null
}

const handleImages = async (e) => {
    const files = Array.from(e.target.files)
    if (!files.length) return

    // 1. Nếu là 1 file ZIP đơn lẻ
    if (files.length === 1 && files[0].name.toLowerCase().endsWith('.zip')) {
        try {
            const zip = await JSZip.loadAsync(files[0])
            const extracted = []

            for (const fileName in zip.files) {
                const zipEntry = zip.files[fileName]
                // Bỏ qua folder và file ẩn, chỉ lấy file ảnh
                if (!zipEntry.dir && fileName.match(/\.(jpg|jpeg|png|webp)$/i)) {
                    const blob = await zipEntry.async('blob')
                    const imgFile = new File([blob], fileName.split('/').pop(), {
                        type: blob.type || 'image/jpeg'
                    })

                    extracted.push({
                        file: imgFile,
                        preview: URL.createObjectURL(blob)
                    })
                }
            }

            images.value = [...images.value, ...extracted]
        } catch (err) {
            toast.error('❌ File ZIP không hợp lệ hoặc bị lỗi!')
        }
        e.target.value = '' // Reset input
        return
    }

    // 2. Nếu là chọn File lẻ hoặc chọn Thư mục (Folder)
    const imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif']
    
    const validImages = files
        .filter(f => {
            const ext = f.name.split('.').pop().toLowerCase()
            return f.type.startsWith('image/') || imageExtensions.includes(ext)
        })
        .map(f => ({
            file: f,
            preview: URL.createObjectURL(f)
        }))

    // Nối thêm vào danh sách ảnh hiện có (tránh đè mất ảnh cũ)
    images.value = [...images.value, ...validImages]

    // Reset input để có thể chọn lại cùng file/folder lần sau
    e.target.value = ''
}

const normalize = (str) => {
    return str
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '') // 🔥 giống backend
}

const findImage = (name) => {
    if (!name) return null

    const normalize = (str) =>
        str.toLowerCase().replace(/[^a-z0-9]/g, '')

    const target = normalize(name)

    const img = images.value.find(i =>
        normalize(i.file.name) === target
    )

    return img ? img.preview : null
}
/* ================= PREVIEW ================= */
const previewFile = async () => {
    if (!file.value) return toast.error('Bạn phải chọn file tước')

    const form = new FormData()
    form.append('file', file.value)

    // thêm ảnh
    images.value.forEach(img => {
        form.append('images[]', img.file)
    })

    checking.value = true   // bật loading

    try {
        const res = await axios.post('/products/validate', form)

        previewData.value = res.data.valid
        step.value = 'preview'

        // lấy dòng lỗi từ preview (chuẩn nhất)
        const errorRows = previewData.value.filter(i => i.is_error)

        // set data dùng chung
        duplicateData.value = {
            error_count: errorRows.length,
            duplicate: errorRows.length, // để template dùng
            valid: previewData.value.length - errorRows.length
        }

      

    } catch (e) {
        toast.error('File lỗi')
    }

    checking.value = false  // loading
}


const hasError = computed(() => {
    return previewData.value.some(i => i.is_error)
})


const previewContainer = ref(null)

// Xử lý chuyển hướng lăn chuột dọc thành cuộn ngang
const handleWheel = (e) => {
    if (!previewContainer.value) return

    e.preventDefault()
    previewContainer.value.scrollLeft += e.deltaY * 0.7
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

const clearImages = () => {
    images.value.forEach(img => {
        URL.revokeObjectURL(img.preview)
    })
    images.value = []
}

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
                        toast.success('Xuất file thành công!')
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
        <div class="p-2">
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
                     <div class="col-span-5 bg-green-50 rounded-xl px-2 py-2.5 text-sm">
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
                        <div class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-purple-400 rounded-xl p-3 bg-white hover:bg-purple-50 transition">
                            <div class="mb-1"><SquareMousePointer/></div>

                            <div class="font-medium text-center">
                                Kéo & thả ảnh / Zip / Folder vào đây
                            </div>

                            <div class="flex gap-2">
                                <button 
                                    type="button"
                                    @click="$refs.imageFileInput.click()" 
                                    class="flex items-center justify-center gap-1 p-1 text-xs bg-purple-100 text-purple-700 hover:bg-purple-200 font-medium rounded-lg transition"
                                >
                                    <Image /> Chọn File / Zip
                                </button>

                                <button 
                                    type="button"
                                    @click="$refs.imageFolderInput.click()" 
                                    class="flex items-center justify-center gap-1 p-1 text-xs bg-purple-100 text-purple-700 hover:bg-purple-200 font-medium rounded-lg transition"
                                >
                                    <Folder /> Chọn Thư mục
                                </button>
                            </div>

                            <div class="text-[11px] text-gray-400 mt-1">
                                Hỗ trợ jpg, png, webp,... zip hoặc Folder ảnh
                            </div>

                            <input
                                ref="imageFileInput"
                                type="file"
                                multiple
                                accept="image/*,.zip"
                                class="hidden"
                                @change="handleImages"
                            />

                            <input
                                ref="imageFolderInput"
                                type="file"
                                multiple
                                webkitdirectory
                                directory
                                class="hidden"
                                @change="handleImages"
                            />
                        </div>

                        <div v-if="images.length" class="mt-2 w-full p-1 space-y-2">
                            <div 
                                ref="previewContainer"
                                @wheel.prevent="handleWheel"
                                class="w-full max-w-full overflow-x-auto overflow-y-hidden"
                            >
                                <div class="flex gap-2 w-max py-1 px-1">

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
                            </div>

                            <div class="text-xs text-gray-500 flex justify-between items-center pt-1">
                                <span>Đã chọn <strong class="text-purple-700">{{ images.length }}</strong> ảnh ({{ totalSize }} MB)</span>

                                <button
                                    @click="clearImages"
                                    class="text-red-500 hover:text-red-700 hover:underline font-medium"
                                >
                                    Xóa tất cả
                                </button>
                            </div>

                        </div>

                    </div>

                    <div class="col-span-5 bg-purple-50 rounded-xl px-2 py-2.5 text-sm">
                        <div class="flex items-center font-semibold text-purple-700 mb-2"><Lightbulb class="w-4 h-4 mr-2" />Lưu ý</div>
                        <ul class="list-disc ml-4 text-gray-600 space-y-1 text-xs">
                            <li>Tên ảnh trùng cột "ảnh"</li>
                            <li>Không dấu</li>
                            <li>Có thể chọn folder hoặc file zip</li>
                        </ul>
                    </div>

                </div>

                <!-- ACTION -->
                <div class="flex justify-between items-center border-t pt-3">

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
                            :disabled="checking"
                            class="flex items-center justify-center gap-2 px-4 py-2 border rounded-lg bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-60 disabled:cursor-not-allowed"
                        >

                            <!-- Spinner -->
                            <svg
                                v-if="checking"
                                class="w-4 h-4 animate-spin"
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
                            <span class="flex gap-1">
                                <template v-if="checking">
                                    Đang kiểm tra...
                                </template>
                                <template v-else>
                                    <Check /> Kiểm tra file
                                </template>
                            </span>

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
                            :key="isSkipping"
                            :disabled="isSkipping"
                            class="flex items-center gap-2 px-3 py-2 bg-blue-500 text-white rounded disabled:opacity-60"
                        >
                            <!-- Spinner -->
                            <svg
                                v-if="isSkipping"
                                class="w-4 h-4 animate-spin"
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
                            <template v-if="isSkipping">
                                Đang import...
                            </template>
                            <template v-else>
                                <SkipForward />
                                Bỏ qua {{ previewData.filter(i => i.is_error).length }} sản phẩm lỗi
                            </template>
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