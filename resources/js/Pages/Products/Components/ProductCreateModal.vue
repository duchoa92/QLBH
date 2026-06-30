<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { ScanSearch } from 'lucide-vue-next'
import { BrowserMultiFormatReader } from '@zxing/browser'

const props = defineProps({
    show: Boolean,
    categories: Array,
    brands: Array
})

const emit = defineEmits(['close'])

/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/
const form = useForm({
    name: '',
    category_id: '',
    brand_id: '',
    sku: '',
    barcode: '',
    cost_price: '',
    sell_price: '',
    stock: 0,
    imeis: '',
    image: null,

    // thuộc tính
    has_variant: false,
    color: '',
    storage: '',
    version: ''
})

/*
|--------------------------------------------------------------------------
| IMAGE PREVIEW
|--------------------------------------------------------------------------
*/
const preview = ref(null)

const handleImage = (e) => {
    const file = e.target.files[0]
    if (!file) return

    form.image = file
    preview.value = URL.createObjectURL(file)
}


const generateBarcode = () => {
    // dạng 13 số (EAN-like)
    const code = Date.now().toString().slice(-10) +
        Math.floor(Math.random() * 1000).toString().padStart(3, '0')

    form.barcode = code
}

/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/
const submit = () => {
    form.post(route('products.store'), {
        onSuccess: () => {
            emit('close')
            form.reset()
            preview.value = null
        }
    })
}

// hiện scan
const showCamera = ref(false)
const videoRef = ref(null)
let codeReader = null
const toggleCamera = () => {
    showCamera.value = !showCamera.value

    if (showCamera.value) {
        startCamera()
    } else {
        stopCamera()
    }
}

const startCamera = async () => {
    codeReader = new BrowserMultiFormatReader()

    await codeReader.decodeFromVideoDevice(null, videoRef.value, (result) => {
        if (!result) return

        const code = result.getText()

        form.imeis += (form.imeis ? '\n' : '') + code
    })
}

const stopCamera = () => {
    if (codeReader) {
        codeReader.reset()
        codeReader = null
    }
}


let buffer = ''

window.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        if (buffer.length > 5) {
            form.imeis += (form.imeis ? '\n' : '') + buffer
        }
        buffer = ''
    } else {
        buffer += e.key
    }
})


</script>

<template>
<div v-if="show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">

    <div class="bg-white w-[1100px] rounded-xl shadow-xl flex overflow-hidden">

        <!-- LEFT: IMAGE -->
        <div class="w-1/3 border-r p-4 flex flex-col items-center justify-center">

            <label class="w-full h-[320px] border-2 border-dashed rounded-lg flex items-center justify-center cursor-pointer overflow-hidden">

                <img
                    v-if="preview"
                    :src="preview"
                    class="w-full h-full object-cover"
                />

                <span v-else class="text-gray-400">Thêm ảnh</span>

                <input
                    type="file"
                    class="hidden"
                    @change="handleImage"
                />
            </label>

        </div>

        <!-- RIGHT -->
        <div class="flex-1 p-6 space-y-4 overflow-y-auto max-h-[90vh]">

            <!-- HEADER -->
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold">Thêm hàng hóa</h2>
                <button @click="emit('close')">✕</button>
            </div>

            <!-- FORM -->
            <div class="grid grid-cols-2 gap-4">

                <div class="col-span-2">
                    <FloatingInput
                        v-model="form.name"
                        label="Tên hàng hóa"
                    />
                </div>

                <FloatingSelect
                    v-model="form.category_id"
                    :options="categories"
                    option-label="name"
                    option-value="id"
                    label="Danh mục"
                />

                <FloatingSelect
                    v-model="form.brand_id"
                    :options="brands"
                    option-label="name"
                    option-value="id"
                    label="Thương hiệu"
                />

                <div class="col-span-2 grid grid-cols-2 gap-4">
                    <FloatingInput
                        v-model="form.sku"
                        label="SKU"
                    />

                    <div class="flex gap-2 items-end">
                        <div class="flex-1">
                            <FloatingInput
                                v-model="form.barcode"
                                label="Barcode"
                            />
                        </div>

                        <button
                            type="button"
                            class="px-3 py-2 bg-gray-200 rounded text-sm hover:bg-gray-300"
                            @click="generateBarcode"
                        >
                            Tạo
                        </button>
                    </div>

                </div>

                <div class="col-span-2 grid grid-cols-3 gap-4">
                    <FloatingInput
                        v-model="form.cost_price"
                        label="Giá vốn"
                        type="number"
                    />

                    <FloatingInput
                        v-model="form.sell_price"
                        label="Giá bán"
                        type="number"
                    />

                    <FloatingInput
                        v-model="form.stock"
                        label="Tồn kho"
                        type="number"
                    />

                </div>

                <div class="col-span-2 space-y-2">
                    <div class="flex justify-between items-center">
                        <label class="text-sm font-medium">IMEI</label>

                        <button
                            title="Quét"
                            type="button"
                            class="text-blue-600 text-sm"
                            @click="toggleCamera"
                        >
                            <ScanSearch />
                        </button>
                    </div>

                    <textarea
                        ref="imeiInput"
                        v-model="form.imeis"
                        rows="5"
                        class="w-full border rounded p-2 focus:ring focus:ring-blue-200"
                        placeholder="Quét hoặc nhập IMEI..."
                    />

                    <div v-if="showCamera" class="border rounded p-2">
                        <video ref="videoRef" class="w-full h-[200px] rounded" />
                    </div>

                </div>

            </div>

            <!-- ===================== -->
            <!-- THUỘC TÍNH -->
            <!-- ===================== -->
            <div class="border-t pt-4">

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.has_variant" />
                    <span class="font-medium">Có thuộc tính (biến thể)</span>
                </label>

                <div
                    v-if="form.has_variant"
                    class="grid grid-cols-3 gap-3 mt-4"
                >

                    <FloatingSelect
                        v-model="form.color"
                        label="Màu sắc"
                        :options="[
                            { id: 'black', name: 'Đen' },
                            { id: 'white', name: 'Trắng' },
                            { id: 'blue', name: 'Xanh' }
                        ]"
                        option-label="name"
                        option-value="id"
                    />

                    <FloatingSelect
                        v-model="form.storage"
                        label="Bộ nhớ"
                        :options="[
                            { id: '64', name: '64GB' },
                            { id: '128', name: '128GB' },
                            { id: '256', name: '256GB' }
                        ]"
                        option-label="name"
                        option-value="id"
                    />

                    <FloatingSelect
                        v-model="form.version"
                        label="Phiên bản"
                        :options="[
                            { id: 'vn', name: 'VN/A' },
                            { id: 'll', name: 'LL/A' },
                            { id: 'jp', name: 'J/A' }
                        ]"
                        option-label="name"
                        option-value="id"
                    />

                </div>

            </div>

            <!-- ACTION -->
            <div class="flex justify-end gap-2 pt-4">
                <button @click="emit('close')" class="btn-gray">Hủy</button>
                <button @click="submit" class="btn-green">
                    Lưu
                </button>
            </div>

        </div>
    </div>
</div>
</template>


<style scoped>
.btn-green {
    @apply px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700;
}
.btn-gray {
    @apply px-4 py-2 bg-gray-200 rounded;
}
</style>