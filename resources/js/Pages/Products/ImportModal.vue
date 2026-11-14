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
const ingesting = ref(false)
const dragging = ref(false)
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
// Hướng xử lý lỗi: null = chưa chọn (còn lỗi thì chưa cho nhập); skip = TH1 bỏ qua dòng lỗi; custom = TH2 chọn cách xử lý từng loại lỗi
const errorMode = ref(null) // null = chưa chọn: còn lỗi thì chưa cho nhập
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

// Thêm ảnh vào danh sách: bỏ ảnh sai định dạng / quá lớn. Trả về số ảnh đã thêm.
const addImageFiles = (files, quiet = false) => {
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
    if (!quiet && invalid && accepted.length === 0) toast.error('Chỉ nhận ảnh jpg, png, webp, gif')

    return accepted.length
}

/* ================= MỘT VÙNG CHỌN: file Excel + ảnh (file lẻ, thư mục, zip) ================= */
const sheetPath = ref('') // đường dẫn của file Excel đang dùng (để chọn ảnh gần nó nhất khi trùng tên)

const dirSegments = (path = '') => path.replace(/\\/g, '/').split('/').slice(0, -1).filter(Boolean)
const baseNameOf = (path = '') => path.replace(/\\/g, '/').split('/').pop()
const isJunkPath = (path) => {
    const parts = path.replace(/\\/g, '/').split('/')
    return path.includes('__MACOSX') || parts.some(part => part.startsWith('.') || part.startsWith('~$'))
}

// Ảnh nằm càng gần file Excel (cùng thư mục, thư mục con, thư mục cha...) điểm càng cao
const proximity = (imagePath, excelPath) => {
    const a = dirSegments(imagePath)
    const b = dirSegments(excelPath)
    let shared = 0
    while (shared < a.length && shared < b.length && a[shared].toLowerCase() === b[shared].toLowerCase()) shared++
    return shared * 10 - (a.length - shared) - (b.length - shared)
}

// Giải nén zip: lấy cả file Excel và ảnh bên trong, giữ đường dẫn để biết ảnh nằm ở đâu so với file Excel
const expandZip = async (zipFile) => {
    const zip = await JSZip.loadAsync(zipFile)
    const root = zipFile.name.replace(/\.zip$/i, '')
    const items = []

    for (const entryName in zip.files) {
        const entry = zip.files[entryName]
        if (entry.dir || isJunkPath(entryName)) continue

        const ext = extensionOf(entryName)
        if (!IMAGE_EXTENSIONS.includes(ext) && !EXCEL_EXTENSIONS.includes(ext)) continue

        const blob = await entry.async('blob')
        items.push({
            file: new File([blob], baseNameOf(entryName), { type: blob.type || '', lastModified: entry.date ? entry.date.getTime() : Date.now() }),
            path: `${root}/${entryName}`,
        })
    }

    return items
}

// Đọc đệ quy thư mục kéo-thả (FileSystemEntry)
const readDirectory = (directory) => new Promise((resolve, reject) => {
    const reader = directory.createReader()
    const all = []
    const readMore = () => reader.readEntries((batch) => {
        if (!batch.length) return resolve(all)
        all.push(...batch)
        readMore()
    }, reject)
    readMore()
})

const walkEntry = async (entry) => {
    if (entry.isFile) {
        const file = await new Promise((resolve, reject) => entry.file(resolve, reject))
        return [{ file, path: (entry.fullPath || file.name).replace(/^\//, '') }]
    }

    if (!entry.isDirectory) return []

    const children = await readDirectory(entry)
    return (await Promise.all(children.map(walkEntry))).flat()
}

// Nhận một danh sách phẳng [{ file, path }] từ mọi nguồn: chọn file, chọn thư mục, kéo-thả file/thư mục/zip
const ingest = async (rawItems) => {
    if (ingesting.value) return
    ingesting.value = true

    try {
        const items = []
        let zipErrors = 0

        for (const item of rawItems) {
            if (isJunkPath(item.path)) continue

            if (item.file.name.toLowerCase().endsWith('.zip')) {
                try {
                    items.push(...await expandZip(item.file))
                } catch {
                    zipErrors++
                }
                continue
            }

            items.push(item)
        }

        const sheets = items.filter(item => EXCEL_EXTENSIONS.includes(extensionOf(item.file.name)))
        const pictures = items.filter(item => IMAGE_EXTENSIONS.includes(extensionOf(item.file.name)))

        if (zipErrors) toast.error(zipErrors === 1 ? 'File ZIP không hợp lệ hoặc bị lỗi' : `${zipErrors} file ZIP không hợp lệ hoặc bị lỗi`)

        // File Excel: nhiều file thì lấy file ở thư mục nông nhất, rồi file mới nhất
        let usedSheet = null
        if (sheets.length) {
            const best = [...sheets].sort((x, y) => dirSegments(x.path).length - dirSegments(y.path).length || (y.file.lastModified || 0) - (x.file.lastModified || 0))[0]
            if (acceptExcelFile(best.file)) {
                usedSheet = best
                sheetPath.value = best.path
                if (sheets.length > 1) toast.info(`Có ${sheets.length} file Excel, đang dùng "${best.file.name}"`)
            }
        }

        // Ảnh: bỏ trùng tên (giữ ảnh gần file Excel nhất), nối thêm vào ảnh đã chọn
        const known = new Set(images.value.map(img => img.file.name.toLowerCase()))
        const byName = new Map()
        for (const item of pictures) {
            const key = item.file.name.toLowerCase()
            if (known.has(key)) continue
            const current = byName.get(key)
            if (!current || proximity(item.path, sheetPath.value) > proximity(current.path, sheetPath.value)) byName.set(key, item)
        }

        const added = addImageFiles([...byName.values()].map(item => item.file), true)

        if (!usedSheet && !pictures.length && !zipErrors) {
            toast.error('Không thấy file Excel (.xlsx, .xls, .csv) hoặc ảnh (jpg, png, webp, gif) trong lựa chọn')
        } else if (usedSheet || added) {
            const parts = []
            if (usedSheet) parts.push('1 file Excel')
            if (added) parts.push(`${added} ảnh`)
            toast.success(`Đã nhận ${parts.join(' và ')}`)
        }
    } finally {
        ingesting.value = false
    }
}

const fromFileList = (list) => Array.from(list || []).map(file => ({ file, path: file.webkitRelativePath || file.name }))

const handlePick = async (e) => {
    const items = fromFileList(e.target.files)
    e.target.value = ''
    if (items.length) await ingest(items)
}

const handleDropAll = async (e) => {
    dragging.value = false

    // Phải lấy entry ngay trong sự kiện drop (sau await danh sách này không còn dùng được)
    const entries = Array.from(e.dataTransfer?.items || [])
        .map(item => (typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null))
        .filter(Boolean)
    const fallbackFiles = fromFileList(e.dataTransfer?.files)

    if (entries.length) {
        try {
            const items = (await Promise.all(entries.map(walkEntry))).flat()
            if (items.length) return await ingest(items)
        } catch {
            // rơi xuống cách đọc file thường bên dưới
        }
    }

    if (fallbackFiles.length) await ingest(fallbackFiles)
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

const setErrorMode = (mode) => {
    errorMode.value = mode
    if (mode === 'skip') options.value = { ...DEFAULT_OPTIONS, on_error: 'skip_row' }
    else options.value = { ...options.value, on_error: 'skip_row' }
}

const ERROR_MODES = [
    { value: 'skip', title: 'Bỏ qua dòng lỗi' },
    { value: 'custom', title: 'Xử lý từng loại lỗi' },
]

// Các loại lỗi chọn riêng ở TH2 (lỗi bắt buộc như thiếu tên/danh mục/giá bán chỉ có thể bỏ qua dòng hoặc dừng)
const CUSTOM_GROUPS = OPTION_GROUPS.filter(g => g.key !== 'on_error')
const RESIDUAL_GROUP = OPTION_GROUPS.find(g => g.key === 'on_error')

const backToUpload = () => {
    step.value = 'upload'
    skipRows.value = []
    errorMode.value = null
    options.value = { ...DEFAULT_OPTIONS }
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

                <!-- ===== MỘT VÙNG CHỌN: file Excel + ảnh (file lẻ, thư mục hoặc zip) ===== -->
                <div class="grid grid-cols-12 items-start gap-4">

                    <div class="col-span-12 min-w-0 md:col-span-7"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDropAll"
                    >
                        <div
                            class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed p-4 transition"
                            :class="dragging ? 'border-blue-500 bg-blue-50' : 'border-blue-300 bg-white hover:bg-blue-50'"
                            @click="$refs.pickInput.click()"
                        >
                            <SquareMousePointer />

                            <div class="text-center font-medium">
                                Kéo & thả vào đây hoặc <span class="font-semibold text-blue-600">chọn file</span>
                            </div>

                            <div class="text-center text-xs text-gray-500">
                                File Excel + ảnh sản phẩm — file lẻ, cả thư mục hoặc file zip đều được
                            </div>

                            <div class="mt-1 flex gap-2" @click.stop>
                                <button type="button" class="flex items-center gap-1 rounded-lg bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-200" @click="$refs.pickInput.click()">
                                    <Image class="h-4 w-4" /> Chọn file / zip
                                </button>
                                <button type="button" class="flex items-center gap-1 rounded-lg bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-200" @click="$refs.folderInput.click()">
                                    <Folder class="h-4 w-4" /> Chọn thư mục
                                </button>
                            </div>

                            <div v-if="ingesting" class="mt-1 flex items-center gap-2 text-xs font-medium text-blue-700">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                                Đang đọc lựa chọn...
                            </div>

                            <input ref="pickInput" type="file" multiple accept=".xlsx,.xls,.csv,.zip,image/*" class="hidden" @click.stop @change="handlePick" />
                            <input ref="folderInput" type="file" multiple webkitdirectory directory class="hidden" @click.stop @change="handlePick" />
                        </div>

                        <!-- File Excel đã nhận -->
                        <div v-if="file" class="mt-2 flex items-center justify-between rounded-lg border bg-gray-50 p-1.5">
                            <div class="flex min-w-0 items-center text-sm">
                                <Sheet class="mr-1 h-5 w-5 shrink-0 text-green-600" /> <span class="truncate">{{ file.name }}</span>
                            </div>
                            <button type="button" class="px-1 text-gray-400 hover:text-red-500" title="Bỏ file Excel" @click="file = null; sheetPath = ''">✕</button>
                        </div>

                        <!-- Ảnh đã nhận -->
                        <div v-if="images.length" class="mt-2 w-full space-y-2 p-1">
                            <div
                                ref="previewContainer"
                                @wheel.prevent="handleWheel"
                                class="w-full max-w-full overflow-x-auto overflow-y-hidden"
                            >
                                <div class="flex w-max gap-2 px-1 py-1">
                                    <div
                                        v-for="(img, i) in images"
                                        :key="img.preview"
                                        class="group relative h-14 w-14 flex-shrink-0 overflow-hidden rounded-lg border bg-white shadow-sm"
                                    >
                                        <img :src="img.preview" :alt="img.file.name" :title="img.file.name" class="h-full w-full object-cover transition group-hover:scale-110" />

                                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100">
                                            <button
                                                type="button"
                                                @click.stop="removeImage(i)"
                                                class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white shadow hover:bg-red-600"
                                                title="Xóa ảnh này"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1 text-xs text-gray-500">
                                <span>Đã nhận <strong class="text-purple-700">{{ images.length }}</strong> ảnh ({{ totalSize }} MB)</span>
                                <button type="button" @click="clearImages" class="font-medium text-red-500 hover:text-red-700 hover:underline">Xóa tất cả ảnh</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 rounded-xl bg-blue-50 px-3 py-2.5 text-sm md:col-span-5">
                        <div class="mb-2 flex items-center font-semibold text-blue-700"><Lightbulb class="mr-2 h-4 w-4" /> Cách chọn</div>
                        <ul class="ml-4 list-disc space-y-1 text-xs text-gray-600">
                            <li>Chọn <strong>thư mục</strong>, file <strong>zip</strong> hoặc nhiều file lẻ (Excel + ảnh)</li>
                            <li>Ảnh ở thư mục cha hay con đều nhận; tên ảnh khớp cột "ảnh"</li>
                            <li>Tối đa 10MB, 5.000 sản phẩm/lần; SKU trống thì tự tạo</li>
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

                <!-- Hướng xử lý lỗi: một khối duy nhất -->
                <div v-if="summary.error > 0 || errorMode" class="rounded-lg border-2 bg-amber-50 p-3" :class="blockedByAbort ? 'border-amber-400' : 'border-amber-200'">
                    <div class="mb-2 flex items-center gap-1.5 text-sm font-bold text-amber-800">
                        <TriangleAlert :size="18" />
                        <span v-if="summary.error > 0">Có {{ summary.error }} dòng lỗi<span v-if="blockedByAbort"> - chưa thể nhập, chọn cách xử lý:</span><span v-else> - cách xử lý:</span></span>
                        <span v-else>Cách xử lý lỗi</span>
                    </div>

                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <label
                            v-for="mode in ERROR_MODES"
                            :key="mode.value"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm transition"
                            :class="errorMode === mode.value ? 'border-blue-500 ring-1 ring-blue-500' : 'border-slate-200 hover:border-slate-300'"
                        >
                            <input type="radio" :checked="errorMode === mode.value" @change="setErrorMode(mode.value)" />
                            <span class="font-semibold text-slate-800">{{ mode.title }}</span>
                        </label>
                    </div>

                    <div v-if="errorMode === 'custom'" class="mt-3 space-y-3 rounded-lg bg-white p-3">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div v-for="group in CUSTOM_GROUPS" :key="group.key" :title="group.hint">
                                <FloatingSelect
                                    v-model="options[group.key]"
                                    :label="group.label"
                                    :options="group.choices"
                                    :show-placeholder="false"
                                />
                            </div>
                            <div :title="RESIDUAL_GROUP.hint">
                                <FloatingSelect
                                    v-model="options.on_error"
                                    label="Dòng còn lỗi sau khi xử lý (thiếu tên, danh mục, giá bán...)"
                                    :options="RESIDUAL_GROUP.choices"
                                    :show-placeholder="false"
                                />
                            </div>
                        </div>
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

            </div>
        </div>
        <template v-if="step === 'preview'" #footer>
            <div class="flex justify-end gap-2">
                <button @click="backToUpload" class="flex items-center gap-1 px-3 py-2 bg-gray-400 text-white rounded">
                    <Undo2 /> Quay lại
                </button>
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
                        Nhập sản phẩm
                    </template>
                </button>

            </div>
        </template>
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
