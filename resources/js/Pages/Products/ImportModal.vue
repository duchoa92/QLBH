<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { closeModal } from '@/Stores/modal'
import axios from 'axios'
import { ArrowDownToLine, Check, CircleFadingArrowUp, Download, Folder, Image, Lightbulb, Sheet, SquareMousePointer, TriangleAlert, Undo2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import JSZip from 'jszip'

const emit = defineEmits(['close', 'updated'])

const MAX_IMAGE_MB = 5
const MAX_EXCEL_MB = 10
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif']
const EXCEL_EXTENSIONS = ['xlsx', 'xls', 'csv']
const MAX_PREVIEW_ROWS = 1000

const file = ref(null)
const images = ref([])
const previewData = ref([])
const summary = ref({ total: 0, create: 0, update: 0, skip: 0, error: 0 })
const step = ref('upload') // upload | preview
const checking = ref(false)
const importing = ref(false)

const uploadingImages = ref(false)
const uploadProgress = ref(0)
const imageToken = ref(null)
let uploadedSignature = ''

const report = ref(null)
const showReport = ref(false)

/* ================= LỰA CHỌN XỬ LÝ ================= */
// Các lựa chọn gửi lên máy chủ ở bước kiểm tra và bước nhập (máy chủ đối chiếu với danh sách cho phép).
const DEFAULT_OPTIONS = {
    on_error: 'abort', // an toàn: có lỗi thì không cho nhập, người dùng phải chọn cách xử lý
    optional_field: 'error',
    duplicate_sku: 'error',
    duplicate_in_file: 'error',
    missing_reference: 'create',
}

// short: chữ ngắn dùng trong dòng tóm tắt; hint: giải thích dưới ô chọn
const OPTION_GROUPS = [
    {
        key: 'on_error',
        label: 'Dòng bị lỗi',
        hint: 'Thiếu tên, danh mục hoặc giá bán...',
        choices: [
            { value: 'abort', label: 'Không cho nhập khi còn lỗi', short: 'không cho nhập khi còn lỗi' },
            { value: 'skip_row', label: 'Bỏ qua dòng lỗi, nhập phần còn lại', short: 'bỏ qua dòng lỗi' },
        ],
    },
    {
        key: 'optional_field',
        label: 'Cột không bắt buộc bị sai',
        hint: 'Giá nhập, tồn kho, loại, IMEI, trạng thái, ảnh...',
        choices: [
            { value: 'error', label: 'Tính là lỗi dòng', short: 'tính là lỗi' },
            { value: 'ignore', label: 'Bỏ qua giá trị sai, vẫn nhập dòng', short: 'bỏ qua giá trị sai' },
        ],
    },
    {
        key: 'duplicate_sku',
        label: 'SKU đã có trong hệ thống',
        hint: 'Kể cả sản phẩm trong thùng rác.',
        choices: [
            { value: 'error', label: 'Tính là lỗi dòng', short: 'tính là lỗi' },
            { value: 'skip', label: 'Bỏ qua dòng đó', short: 'bỏ qua' },
            { value: 'update', label: 'Cập nhật sản phẩm cũ (không đổi tồn kho)', short: 'cập nhật sản phẩm cũ' },
            { value: 'new_sku', label: 'Thêm mới với SKU tự tạo', short: 'thêm mới SKU tự tạo' },
        ],
    },
    {
        key: 'duplicate_in_file',
        label: 'SKU lặp trong file',
        hint: 'Dòng đầu tiên luôn được giữ.',
        choices: [
            { value: 'error', label: 'Tính là lỗi các dòng lặp', short: 'tính là lỗi' },
            { value: 'skip', label: 'Bỏ qua các dòng lặp', short: 'bỏ qua' },
            { value: 'new_sku', label: 'Thêm mới với SKU tự tạo', short: 'SKU tự tạo' },
        ],
    },
    {
        key: 'missing_reference',
        label: 'Danh mục / thương hiệu / đơn vị chưa có',
        hint: 'Danh mục bắt buộc; thương hiệu, đơn vị thì không.',
        choices: [
            { value: 'create', label: 'Tự tạo mới', short: 'tự tạo mới' },
            { value: 'reject', label: 'Không chấp nhận', short: 'không chấp nhận' },
        ],
    },
]

// Mỗi lần nhập đều bắt đầu từ cách xử lý an toàn (không lưu lựa chọn cũ), tránh vô tình ghi đè/bỏ qua dữ liệu.
const options = ref({ ...DEFAULT_OPTIONS })
const skipRows = ref([]) // số dòng (theo file Excel) người dùng chọn bỏ qua
const viewMode = ref('issues') // all | issues
const showOptions = ref(false) // phần chọn cách xử lý lỗi/trùng chỉ hiện khi bấm
const notice = ref('')
let recheckTimer = null

onBeforeUnmount(() => {
    if (recheckTimer) clearTimeout(recheckTimer)
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


/* ================= GỬI FILE ================= */
const buildForm = async () => {
    const form = new FormData()
    form.append('file', file.value)

    const token = await ensureImagesUploaded()
    if (token) form.append('image_token', token)

    form.append('options', JSON.stringify(options.value))
    form.append('skip_rows', JSON.stringify(skipRows.value))

    return form
}

const doImport = async () => {
    if (importing.value || !canImport.value) return

    importing.value = true

    try {
        const res = await axios.post(route('products.import'), await buildForm())

        report.value = res.data
        showReport.value = true

        if (res.data.success) {
            // Máy chủ đã xóa ảnh tạm sau khi nhập xong
            imageToken.value = null
            uploadedSignature = ''

            if (res.data.count > 0) {
                const parts = []
                if (res.data.created) parts.push(`thêm ${res.data.created}`)
                if (res.data.updated) parts.push(`cập nhật ${res.data.updated}`)
                toast.success(`Đã ${parts.join(', ')} sản phẩm`)
                emit('updated')
            }

            if (res.data.error_count > 0) toast.warning(`Có ${res.data.error_count} dòng lỗi chưa nhập`)
        } else {
            toast.error(res.data.message || res.data.errors?.[0]?.error || 'Import thất bại, chưa có sản phẩm nào được thêm')
        }
    } catch (e) {
        toast.error(apiError(e, 'Import thất bại'))
    } finally {
        importing.value = false
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


/* ================= KIỂM TRA FILE ================= */
const previewFile = async ({ silent = false } = {}) => {
    if (!file.value) return toast.error('Bạn phải chọn file trước')

    checking.value = true

    try {
        const res = await axios.post(route('products.validate'), await buildForm())

        previewData.value = res.data.valid
        summary.value = res.data.summary || summary.value
        notice.value = res.data.notice || ''
        step.value = 'preview'
    } catch (e) {
        toast.error(apiError(e, 'Không đọc được file, vui lòng kiểm tra lại'))
    } finally {
        checking.value = false
    }
}

// Đổi lựa chọn hoặc bỏ qua dòng: kiểm tra lại sau một nhịp ngắn để bảng luôn khớp với những gì sẽ nhập.
const scheduleRecheck = () => {
    if (step.value !== 'preview') return
    if (recheckTimer) clearTimeout(recheckTimer)
    recheckTimer = setTimeout(() => previewFile({ silent: true }), 500)
}

watch(options, scheduleRecheck, { deep: true })

const toggleSkip = (row) => {
    skipRows.value = skipRows.value.includes(row)
        ? skipRows.value.filter(r => r !== row)
        : [...skipRows.value, row]
    scheduleRecheck()
}

const resetOptions = () => { options.value = { ...DEFAULT_OPTIONS } }

// Dòng tóm tắt lựa chọn hiện tại (hiện khi phần chi tiết đang ẩn)
const optionsSummary = computed(() => {
    const changed = OPTION_GROUPS
        .filter(group => options.value[group.key] !== DEFAULT_OPTIONS[group.key])
        .map(group => `${group.label}: ${group.choices.find(c => c.value === options.value[group.key])?.short ?? ''}`)
    return changed.length ? changed.join(' · ') : 'Mặc định: có lỗi thì không cho nhập'
})

const backToUpload = () => {
    step.value = 'upload'
    skipRows.value = []
}

const importableCount = computed(() => Number(summary.value.create || 0) + Number(summary.value.update || 0))

const blockedByAbort = computed(() => options.value.on_error === 'abort' && Number(summary.value.error || 0) > 0)

const canImport = computed(() => importableCount.value > 0 && !blockedByAbort.value && !checking.value)

const needsAttention = (item) => item.action !== 'create' || Boolean(item.warning)

const visibleRows = computed(() => {
    const rows = viewMode.value === 'issues' ? previewData.value.filter(needsAttention) : previewData.value
    return rows.slice(0, MAX_PREVIEW_ROWS)
})

const hiddenRowCount = computed(() => {
    const total = viewMode.value === 'issues' ? previewData.value.filter(needsAttention).length : previewData.value.length
    return Math.max(0, total - MAX_PREVIEW_ROWS)
})

const ACTION_LABELS = {
    create: ['Thêm mới', 'bg-emerald-100 text-emerald-700'],
    update: ['Cập nhật', 'bg-sky-100 text-sky-700'],
    skip: ['Bỏ qua', 'bg-slate-200 text-slate-600'],
    error: ['Lỗi', 'bg-red-100 text-red-700'],
}

const rowClass = (item) => ({
    create: 'bg-white',
    update: 'bg-sky-50',
    skip: 'bg-slate-100 text-slate-500',
    error: 'bg-red-50',
}[item.action] || 'bg-white')

const previewContainer = ref(null)

// Xử lý chuyển hướng lăn chuột dọc thành cuộn ngang
const handleWheel = (e) => {
    if (!previewContainer.value) return

    e.preventDefault()
    previewContainer.value.scrollLeft += e.deltaY * 0.7
}

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
    <BaseModal title="Nhập sản phẩm từ file" size="xl" @close="closeModal()">
        <div class="p-2">
            <!-- BƯỚC 1: CHỌN FILE -->
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
                            <li>SKU để trống thì hệ thống tự tạo</li>
                            <li>Dòng đầu là tiêu đề</li>
                            <li>Tối đa 10MB, 5.000 sản phẩm mỗi lần</li>
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

                    <button
                        @click="previewFile()"
                        :disabled="checking || !file"
                        class="flex items-center justify-center gap-2 px-4 py-2 border rounded-lg bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <svg v-if="checking" class="w-4 h-4 animate-spin" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                        </svg>
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

            <!-- BƯỚC 2: KIỂM TRA + LỰA CHỌN -->
            <div v-if="step === 'preview'" class="space-y-3">

                <!-- File không có dòng tiêu đề -->
                <div v-if="notice" class="flex items-start gap-2 rounded bg-yellow-100 p-3 text-sm">
                    <TriangleAlert class="shrink-0" :size="18" />
                    <span>{{ notice }}</span>
                </div>

                <!-- Cách xử lý lỗi / trùng: ẩn, bấm mới hiện -->
                <div class="rounded-lg border-2 bg-amber-50" :class="blockedByAbort ? 'border-amber-400' : 'border-amber-200'">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left"
                        @click="showOptions = !showOptions"
                    >
                        <span class="flex shrink-0 items-center gap-1.5 text-base font-bold text-amber-800"><TriangleAlert :size="18" /> Xử lý lỗi</span>
                        <span class="truncate text-xs text-slate-600">{{ optionsSummary }}</span>
                        <span class="shrink-0 rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white">{{ showOptions ? 'Ẩn' : 'Chọn cách xử lý' }}</span>
                    </button>

                    <div v-if="showOptions" class="space-y-3 border-t border-amber-200 bg-white px-3 py-3">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div v-for="group in OPTION_GROUPS" :key="group.key" :title="group.hint">
                                <FloatingSelect
                                    v-model="options[group.key]"
                                    :label="group.label"
                                    :options="group.choices"
                                    :show-placeholder="false"
                                />
                            </div>
                        </div>
                        <button type="button" class="text-xs text-blue-600 hover:underline" @click="resetOptions">Đặt lại mặc định</button>
                    </div>
                </div>

                <!-- Tóm tắt -->
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-700">Thêm mới: {{ summary.create }}</span>
                    <span class="rounded-full bg-sky-100 px-3 py-1 font-semibold text-sky-700">Cập nhật: {{ summary.update }}</span>
                    <span class="rounded-full bg-slate-200 px-3 py-1 font-semibold text-slate-600">Bỏ qua: {{ summary.skip }}</span>
                    <span class="rounded-full bg-red-100 px-3 py-1 font-semibold text-red-700">Lỗi: {{ summary.error }}</span>
                    <span v-if="checking" class="text-xs text-slate-500">Đang kiểm tra lại...</span>

                    <div class="ml-auto flex items-center gap-1 text-xs">
                        <button type="button" class="rounded-lg border px-2 py-1" :class="viewMode === 'issues' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600'" @click="viewMode = 'issues'">Dòng cần chú ý</button>
                        <button type="button" class="rounded-lg border px-2 py-1" :class="viewMode === 'all' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600'" @click="viewMode = 'all'">Tất cả ({{ summary.total }})</button>
                    </div>
                </div>

                <!-- Bảng -->
                <div class="max-h-[340px] overflow-auto border" :class="checking ? 'opacity-60' : ''">
                    <table class="w-full text-sm text-center items-center">
                        <thead class="sticky top-0 bg-white">
                            <tr>
                                <th class="border p-2" title="Tích để bỏ qua dòng này">Bỏ qua</th>
                                <th class="border p-2" title="Số dòng trong file Excel">Dòng</th>
                                <th class="border p-2 text-red-600" title="Bắt buộc">Tên *</th>
                                <th class="border p-2">SKU</th>
                                <th class="border p-2 text-red-600" title="Bắt buộc">Danh mục *</th>
                                <th class="border p-2">Thương hiệu</th>
                                <th class="border p-2">Ảnh</th>
                                <th class="border p-2 text-red-600" title="Bắt buộc">Giá bán *</th>
                                <th class="border p-2">Giá nhập</th>
                                <th class="border p-2">Tồn kho</th>
                                <th class="border p-2">Đơn vị</th>
                                <th class="border p-2">Loại</th>
                                <th class="border p-2">Kết quả</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!visibleRows.length">
                                <td colspan="13" class="p-4 text-sm text-slate-500">Không có dòng nào cần chú ý.</td>
                            </tr>

                            <tr v-for="item in visibleRows" :key="item.row" :class="rowClass(item)">
                                <td class="border">
                                    <input type="checkbox" :checked="skipRows.includes(item.row)" @change="toggleSkip(item.row)" class="rounded border-slate-300" :aria-label="`Bỏ qua dòng ${item.row}`">
                                </td>
                                <td class="border">{{ item.row }}</td>
                                <td class="border text-left">{{ item.name }}</td>
                                <td class="border">
                                    <template v-if="item.sku">{{ item.sku }}</template>
                                    <span v-else class="italic text-slate-400">(tự tạo)</span>
                                    <span v-if="item.sku_auto && item.sku" class="ml-1 rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-700">Tự tạo</span>
                                </td>
                                <td class="border">{{ item.category || '-' }}</td>
                                <td class="border">{{ item.brand || '-' }}</td>
                                <td class="border">
                                    <img v-if="findImage(item.image_name)" :src="findImage(item.image_name)" class="w-12 h-12 object-cover rounded mx-auto" />
                                    <span v-else-if="item.image_name" class="text-red-500">Thiếu ảnh</span>
                                    <span v-else class="text-gray-400 italic">-</span>
                                </td>
                                <td class="border"><span v-if="item.sell_price !== ''">{{ $money(item.sell_price) }}</span><span v-else class="text-gray-400 italic">-</span></td>
                                <td class="border"><span v-if="item.cost_price !== ''">{{ $money(item.cost_price) }}</span><span v-else class="text-gray-400 italic">-</span></td>
                                <td class="border"><span v-if="item.stock !== ''">{{ item.stock }}</span><span v-else class="text-gray-400 italic">-</span></td>
                                <td class="border">{{ item.unit || '-' }}</td>
                                <td class="border">{{ item.type || 'normal' }}</td>
                                <td class="border text-left">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="ACTION_LABELS[item.action]?.[1]">{{ ACTION_LABELS[item.action]?.[0] }}</span>
                                    <div v-if="item.action !== 'create' && item.action !== 'update'" class="mt-0.5 text-xs" :class="item.action === 'error' ? 'text-red-600' : 'text-slate-500'">{{ item.status }}</div>
                                    <div v-if="item.warning" class="mt-0.5 text-xs text-amber-600">{{ item.warning }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="hiddenRowCount" class="text-xs text-slate-500">Đang hiển thị {{ MAX_PREVIEW_ROWS }} dòng đầu, còn {{ hiddenRowCount }} dòng nữa (vẫn được xử lý khi nhập).</p>

                <div v-if="blockedByAbort" class="space-y-2 rounded bg-yellow-100 p-3 text-sm">
                    <div class="flex items-start gap-2">
                        <TriangleAlert class="shrink-0" :size="18" />
                        <span>Có <strong>{{ summary.error }}</strong> dòng lỗi nên <strong>chưa cho nhập</strong>. Hãy sửa file, hoặc chọn cách xử lý:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded border border-amber-400 bg-white px-3 py-1 font-semibold text-amber-800 hover:bg-amber-50" @click="options.on_error = 'skip_row'">Bỏ qua các dòng lỗi, nhập phần còn lại</button>
                        <button v-if="options.optional_field === 'error'" type="button" class="rounded border border-amber-400 bg-white px-3 py-1 font-semibold text-amber-800 hover:bg-amber-50" @click="options.optional_field = 'ignore'">Bỏ qua giá trị sai ở cột không bắt buộc</button>
                        <button type="button" class="rounded border bg-white px-3 py-1 text-slate-700 hover:bg-slate-50" @click="showOptions = true">Chọn cách xử lý khác</button>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button
                        @click="doImport"
                        :disabled="importing || !canImport"
                        class="flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <svg v-if="importing" class="w-4 h-4 animate-spin" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                        </svg>
                        <template v-if="importing">Đang nhập...</template>
                        <template v-else>
                            <CircleFadingArrowUp />
                            Nhập {{ importableCount }} sản phẩm<template v-if="summary.error + summary.skip"> (bỏ qua {{ summary.error + summary.skip }})</template>
                        </template>
                    </button>

                    <button @click="backToUpload" class="flex items-center gap-1 px-3 py-2 bg-gray-400 text-white rounded">
                        <Undo2 /> Quay lại
                    </button>
                </div>
            </div>
        </div>
    </BaseModal>

    <BaseModal v-if="showReport" title="Kết quả nhập sản phẩm" @close="showReport = false">
        <div class="p-4 space-y-3">
            <div v-if="report?.message" class="rounded bg-yellow-100 p-3 text-sm">{{ report.message }}</div>

            <div class="flex text-emerald-600"><Check /> Đã thêm mới: {{ report?.created || 0 }}</div>
            <div class="flex text-sky-600"><Check /> Đã cập nhật: {{ report?.updated || 0 }}</div>
            <div class="flex text-slate-500"><X /> Bỏ qua: {{ report?.skipped || 0 }}</div>
            <div class="flex text-red-600"><X /> Lỗi: {{ report?.error_count || 0 }}</div>

            <div v-if="report?.errors?.length" class="max-h-48 overflow-auto rounded border text-xs">
                <div v-for="(e, i) in report.errors.slice(0, 50)" :key="i" class="border-b px-2 py-1 last:border-b-0">
                    <strong>Dòng {{ e.row }}</strong> {{ e.name }}: <span class="text-red-600">{{ e.error }}</span>
                </div>
                <div v-if="report.errors.length > 50" class="px-2 py-1 text-slate-500">... và {{ report.errors.length - 50 }} dòng lỗi khác (xem trong file lỗi).</div>
            </div>

            <div class="flex">
                <button v-if="report?.errors?.length" @click="downloadErrorFile" class="flex m-1 px-3 py-1 bg-red-500 text-white rounded">
                    <Download /> Xuất file lỗi
                </button>
                <button @click="showReport = false; if (report?.success) closeModal()" class="m-1 px-3 py-1 bg-gray-500 text-white rounded">Đóng</button>
            </div>
        </div>
    </BaseModal>
</template>
