<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import axios from 'axios'
import { ArrowDownToLine, Check, CircleFadingArrowUp, Download, Folder, Image, Lightbulb, Sheet, SkipForward, SquareMousePointer, TriangleAlert, Undo2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import JSZip from 'jszip'

const emit = defineEmits(['close', 'updated'])

const MAX_IMAGE_MB = 5
const MAX_EXCEL_MB = 10
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif']
const EXCEL_EXTENSIONS = ['xlsx', 'xls', 'csv']

const file = ref(null)
const images = ref([])
const previewData = ref([])
const step = ref('upload') // upload | preview
const checking = ref(false)
const exporting = ref(false)
const isSkipping = ref(false)
const importing = ref(false)
const progress = ref(0)
let interval = null

const uploadingImages = ref(false)
const uploadProgress = ref(0)
const imageToken = ref(null)
let uploadedSignature = ''

const duplicateModal = ref(false)
const duplicateData = ref(null)

const report = ref(null)
const showReport = ref(false)

onBeforeUnmount(() => {
    if (interval) clearInterval(interval)
})

/* ================= TIỆN ÍCH ================= */
const extensionOf = (name = '') => name.split('.').pop().toLowerCase()

// Lấy thông báo lỗi từ máy chủ (422 có message hoặc errors), nếu không có thì dùng câu mặc định
const apiError = (e, fallback) => {
    const data = e?.response?.data
    if (data?.message && !data?.errors) return data.message
    const first = data?.errors ? Object.values(data.errors)[0] : null
    return (Array.isArray(first) ? first[0] : first) || data?.message || fallback
}

/* ================= ẢNH: TẢI THEO ĐỢT ================= */
const imageSignature = () => images.value.map(i => `${i.file.name}:${i.file.size}`).join('|')

// Chia ảnh thành các đợt ≤ 10 file và ≤ ~6MB để không vượt giới hạn upload của PHP
const makeBatches = (list) => {
    const batches = []
    let current = []
    let bytes = 0

    for (const img of list) {
        if (current.length && (current.length >= 10 || bytes + img.file.size > 6 * 1024 * 1024)) {
            batches.push(current)
            current = []
            bytes = 0
        }
        current.push(img)
        bytes += img.file.size
    }

    if (current.length) batches.push(current)
    return batches
}

// Tải ảnh lên máy chủ (một lần cho cả bước kiểm tra và bước nhập), trả về token
const ensureImagesUploaded = async () => {
    if (!images.value.length) {
        imageToken.value = null
        uploadedSignature = ''
        return null
    }

    const signature = imageSignature()
    if (imageToken.value && signature === uploadedSignature) return imageToken.value

    uploadingImages.value = true
    uploadProgress.value = 0

    try {
        let token = null
        const batches = makeBatches(images.value)

        for (let i = 0; i < batches.length; i++) {
            const form = new FormData()
            if (token) form.append('token', token)
            batches[i].forEach(img => form.append('images[]', img.file))

            const res = await axios.post(route('products.import.images'), form)
            token = res.data.token
            uploadProgress.value = Math.round(((i + 1) / batches.length) * 100)
        }

        imageToken.value = token
        uploadedSignature = signature
        return token
    } finally {
        uploadingImages.value = false
    }
}

/* ================= IMPORT ================= */
const importSkip = async () => {

    isSkipping.value = true

    try {
        const form = new FormData()
        form.append('file', file.value)

        const token = await ensureImagesUploaded()
        if (token) form.append('image_token', token)

        const res = await axios.post(route('products.import'), form)

        report.value = res.data
        showReport.value = true
        duplicateModal.value = false

        if (res.data.success) {
            // Máy chủ đã xóa ảnh tạm sau khi nhập xong
            imageToken.value = null
            uploadedSignature = ''

            if (res.data.count > 0) {
                toast.success(`Import ${res.data.count} sản phẩm thành công`)
                emit('updated')
            }
        } else {
            toast.error(res.data.errors?.[0]?.error || 'Import thất bại, chưa có sản phẩm nào được thêm')
        }

        if (res.data.error_count > 0 && res.data.success) {
            toast.warning(`Có ${res.data.error_count} sản phẩm lỗi`)
        }

    } catch (e) {
        toast.error(apiError(e, 'Import thất bại'))
    } finally {
        isSkipping.value = false
    }
}

/* ================= FILE ================= */
const acceptExcelFile = (f) => {
    if (!f) return false

    if (!EXCEL_EXTENSIONS.includes(extensionOf(f.name))) {
        toast.error('Chỉ được chọn file Excel (.xlsx, .xls, .csv)')
        return false
    }

    if (f.size > MAX_EXCEL_MB * 1024 * 1024) {
        toast.error(`File quá lớn (tối đa ${MAX_EXCEL_MB}MB)`)
        return false
    }

    file.value = f
    return true
}

const handleFile = (e) => {
    acceptExcelFile(e.target.files[0])

    // reset input để lần sau chọn lại vẫn trigger
    e.target.value = null
}

// Thêm ảnh vào danh sách: bỏ ảnh sai định dạng / quá lớn
const addImageFiles = (files) => {
    let tooBig = 0
    let invalid = 0
    const accepted = []

    for (const f of files) {
        const ext = extensionOf(f.name)

        if (!IMAGE_EXTENSIONS.includes(ext)) {
            invalid++
            continue
        }

        if (f.size > MAX_IMAGE_MB * 1024 * 1024) {
            tooBig++
            continue
        }

        accepted.push({ file: f, preview: URL.createObjectURL(f) })
    }

    images.value = [...images.value, ...accepted]

    if (tooBig) toast.warning(`${tooBig} ảnh lớn hơn ${MAX_IMAGE_MB}MB đã bị bỏ qua`)
    if (invalid && accepted.length === 0) toast.error('Chỉ nhận ảnh jpg, png, webp, gif hoặc file zip chứa ảnh')
}

const extractZip = async (zipFile) => {
    const zip = await JSZip.loadAsync(zipFile)
    const extracted = []

    for (const entryName in zip.files) {
        const entry = zip.files[entryName]
        const baseName = entryName.split('/').pop()

        // Bỏ thư mục, file ẩn và rác của macOS
        if (entry.dir || entryName.includes('__MACOSX') || baseName.startsWith('.')) continue
        if (!IMAGE_EXTENSIONS.includes(extensionOf(baseName))) continue

        const blob = await entry.async('blob')
        extracted.push(new File([blob], baseName, { type: blob.type || 'image/jpeg' }))
    }

    return extracted
}

const handleImages = async (e) => {
    const files = Array.from(e.target.files)
    if (!files.length) return

    // 1. Nếu là 1 file ZIP đơn lẻ
    if (files.length === 1 && files[0].name.toLowerCase().endsWith('.zip')) {
        try {
            addImageFiles(await extractZip(files[0]))
        } catch (err) {
            toast.error('File ZIP không hợp lệ hoặc bị lỗi!')
        }
        e.target.value = ''
        return
    }

    // 2. Chọn file lẻ hoặc cả thư mục: nối thêm vào danh sách hiện có
    addImageFiles(files)
    e.target.value = ''
}

const findImage = (name) => {
    if (!name) return null

    const normalize = (str) => {
        const base = str.replace(/\\/g, '/').split('/').pop().replace(/\.[^.]*$/, '')
        return base.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase().replace(/[^a-z0-9]/g, '')
    }

    const target = normalize(name)
    if (!target) return null

    const img = images.value.find(i => normalize(i.file.name) === target)

    return img ? img.preview : null
}

/* ================= PREVIEW ================= */
const previewFile = async () => {
    if (!file.value) return toast.error('Bạn phải chọn file trước')

    checking.value = true   // bật loading

    try {
        const form = new FormData()
        form.append('file', file.value)

        const token = await ensureImagesUploaded()
        if (token) form.append('image_token', token)

        const res = await axios.post(route('products.validate'), form)

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
        toast.error(apiError(e, 'Không đọc được file, vui lòng kiểm tra lại'))
    } finally {
        checking.value = false  // loading
    }
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

const importFile = async () => {

    importing.value = true

    try {
        await importSkip()
    } finally {
        importing.value = false
    }
}

// kéo thả file Excel
const handleDropExcel = (e) => {
    e.preventDefault()
    acceptExcelFile(e.dataTransfer.files[0])
}

// kéo thả ảnh (nối thêm, hỗ trợ cả zip)
const handleDropImages = async (e) => {
    const files = Array.from(e.dataTransfer.files)

    if (files.length === 1 && files[0].name.toLowerCase().endsWith('.zip')) {
        try {
            addImageFiles(await extractZip(files[0]))
        } catch (err) {
            toast.error('File ZIP không hợp lệ hoặc bị lỗi!')
        }
        return
    }

    addImageFiles(files)
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
    imageToken.value = null
    uploadedSignature = ''
}

/* ================= EXPORT ================= */

const exportFile = async () => {
    if (exporting.value) return

    try {
        exporting.value = true
        progress.value = 0

        const res = await axios.post(route('products.export.start'))
        const id = res.data.id
        const startedAt = Date.now()

        const stop = () => {
            clearInterval(interval)
            interval = null
            exporting.value = false
        }

        interval = setInterval(async () => {
            // Quá 15 phút mà chưa xong (thường do chưa chạy queue worker) thì thôi chờ
            if (Date.now() - startedAt > 15 * 60 * 1000) {
                stop()
                toast.error('Xuất file quá lâu. Vui lòng kiểm tra tiến trình xử lý nền (queue) rồi thử lại.')
                return
            }

            try {
                const check = await axios.get(route('products.export.check', { id }))

                progress.value = check.data.progress || 0

                if (check.data.status === 'failed') {
                    stop()
                    toast.error('Xuất file thất bại, vui lòng thử lại.')
                    return
                }

                if (check.data.status === 'done') {
                    clearInterval(interval)
                    interval = null

                    progress.value = 100

                    setTimeout(() => {
                        window.location.href = route('products.export.download', id)
                        toast.success('Xuất file thành công!')
                        exporting.value = false

                    }, 500)
                }

            } catch (e) {
                stop()
                toast.error(apiError(e, 'Không kiểm tra được tiến trình xuất file'))
            }
        }, 800)

    } catch (e) {
        exporting.value = false
        toast.error(apiError(e, 'Không thể bắt đầu xuất file'))
    }
}

// Tải file lỗi
const downloadErrorFile = async () => {

    if (!report.value?.errors?.length) return

    try {
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
        link.remove()
        window.URL.revokeObjectURL(url)
    } catch (e) {
        toast.error('Không xuất được file lỗi')
    }
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
                                    {{ uploadingImages ? `Đang tải ảnh... ${uploadProgress}%` : 'Đang kiểm tra...' }}
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
                    <table class="w-full text-sm text-center items-center">
                        <tr>
                            <th class="border p-2">#</th>
                            <th class="border p-2">Tên</th>
                            <th class="border p-2">SKU</th>
                            <th class="border p-2">Danh mục</th>
                            <th class="border p-2">Thương hiệu</th>
                            <th class="border p-2">Ảnh</th>
                            <th class="border p-2">Giá bán</th>
                            <th class="border p-2">Giá nhập</th>
                            <th class="border p-2">Tồn kho</th>
                            <th class="border p-2">Đơn vị</th>
                            <th class="border p-2">Loại</th>
                            <th class="border p-2">IMEI</th>
                            <th class="border p-2">Trạng thái</th>
                        </tr>

                        <tr
                            v-for="(item, i) in previewData"
                            :key="i"
                            :class="item.is_error ? 'bg-red-50' : 'bg-green-50'"
                        >
                            <td class="border">{{ i + 1 }}</td>

                            <td class="border">{{ item.name }}</td>
                            <td class="border">{{ item.sku }}</td>
                            <td class="border">{{ item.category || '-' }}</td>
                            <td class="border">{{ item.brand || '-' }}</td>
                            <td class="border">
                                <img 
                                    v-if="findImage(item.image_name)"
                                    :src="findImage(item.image_name)"
                                    class="w-12 h-12 object-cover rounded"
                                />

                                <span v-else-if="item.image_name" class="text-red-500">Thiếu ảnh</span>
                                <span v-else class="text-gray-400 italic">-</span>
                            </td>

                            <td class="border">
                                <span v-if="item.sell_price !== ''">
                                    {{$money(item.sell_price)  }}
                                </span>
                                <span v-else class="text-gray-400 italic">
                                    -
                                </span>
                            </td>

                            <td class="border">
                                <span v-if="item.cost_price !== ''">
                                    {{$money(item.cost_price)  }}
                                </span>
                                <span v-else class="text-gray-400 italic">
                                    -
                                </span>
                            </td>

                            <td class="border">
                                <span v-if="item.stock !== ''">
                                    {{ item.stock }}
                                </span>
                                <span v-else class="text-gray-400 italic">
                                    -
                                </span>
                            </td>

                            <td class="border">
                                {{ item.unit || '-' }}
                            </td>

                            <td class="border">
                                {{ item.type || 'normal' }}
                            </td>

                            <td class="border">
                                {{ item.manage_stock_by_serial || '-' }}
                            </td>

                            <td
                                :class="item.is_error ? 'text-red-600' : 'text-green-600'"
                                class="font-semibold border"
                            >
                                {{ item.status }}
                                <div v-if="item.warning" class="text-xs font-normal text-amber-600">{{ item.warning }}</div>
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
                                Nhập và bỏ qua {{ previewData.filter(i => i.is_error).length }} sản phẩm lỗi
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
                        :disabled="importing"
                        class="flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded disabled:opacity-60"
                    >
                        <!-- Spinner -->
                        <svg
                            v-if="importing"
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
                        <template v-if="importing">
                            Đang import...
                        </template>
                        <template v-else>
                            <CircleFadingArrowUp /> Nhập
                        </template>
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
