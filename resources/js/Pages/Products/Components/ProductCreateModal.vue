<script setup>
import { ref, watch  } from 'vue'
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
    version: '',

    variants: [],
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


// Biến thể
const variants = ref([
    {
        color: '',
        storage: '',
        version: '',
        sku: '',
        barcode: '',
        cost_price: 0,
        sell_price: 0,
        stock: 0,
        imeis: ''
    }
])

// Thêm xóa biến thể
const addVariant = () => {
    variants.value.push({
        color: '',
        storage: '',
        version: '',
        sku: '',
        barcode: '',
        cost_price: 0,
        sell_price: 0,
        stock: 0,
        imeis: ''
    })
}

const removeVariant = (index) => {
    variants.value.splice(index, 1)
}




// Tạo barcode
const generateBarcode = () => {
    // dạng 13 số (EAN-like)
    const code = Date.now().toString().slice(-10) +
        Math.floor(Math.random() * 1000).toString().padStart(3, '0')

    form.barcode = code
}

// Tạo biến thể
const generateVariants = () => {

    const colors = form.color ? [form.color] : ['']
    const storages = form.storage ? [form.storage] : ['']
    const versions = form.version ? [form.version] : ['']

    const result = []

    colors.forEach(c => {
        storages.forEach(s => {
            versions.forEach(v => {
                result.push({
                    color: c,
                    storage: s,
                    version: v,

                    sku: '',
                    barcode: '',
                    cost_price: form.cost_price || 0,
                    sell_price: form.sell_price || 0,
                    stock: form.stock || 0
                })
            })
        })
    })

    form.variants = result

    console.log('VARIANTS', form.variants)
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

watch(
    () => [form.color, form.storage, form.version, form.has_variant],
    () => {
        if (form.has_variant) {
            generateVariants()
        }
    }
)

// Hàm scan
const handleScan = async (code) => {

    if (!code) return

    try {
        const res = await axios.post('/scan', { code })

        const data = res.data

        if (data.type === 'imei') {
            console.log('IMEI → đúng máy', data.variant)
        }

        if (data.type === 'variant') {
            console.log('Barcode → đúng biến thể', data.variant)
        }

        if (data.type === 'product') {
            console.log('Product thường', data.product)
        }

    } catch (e) {
        console.log('Không tìm thấy')
    }
}


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/
const submit = () => {
    form.transform(data => ({
        ...data,
        variants: variants.value
    })).post(route('products.store'))
}


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
                        @keyup.enter="() => {
                            handleScan(form.imeis)
                            form.imeis = ''
                        }"
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

                <div class="flex justify-between items-center">
                    <h3 class="font-semibold">Biến thể</h3>

                    <button
                        type="button"
                        class="px-3 py-2 bg-gray-200 rounded text-sm"
                        @click="addVariant"
                    >
                        + Thêm biến thể
                    </button>
                </div>

                <div
                    v-for="(v, i) in variants"
                    :key="i"
                    class="border rounded p-3 mt-3 space-y-3"
                >

                    <!-- THUỘC TÍNH -->
                    <div class="grid grid-cols-3 gap-2">
                        <FloatingInput v-model="v.color" label="Màu" />
                        <FloatingInput v-model="v.storage" label="Bộ nhớ" />
                        <FloatingInput v-model="v.version" label="Phiên bản" />
                    </div>

                    <!-- SKU + BARCODE -->
                    <div class="grid grid-cols-2 gap-2">
                        <FloatingInput v-model="v.sku" label="SKU" />
                        <FloatingInput v-model="v.barcode" label="Barcode" />
                    </div>

                    <!-- GIÁ + TỒN -->
                    <div class="grid grid-cols-3 gap-2">
                        <FloatingInput v-model="v.cost_price" label="Giá nhập" type="number"/>
                        <FloatingInput v-model="v.sell_price" label="Giá bán" type="number"/>
                        <FloatingInput v-model="v.stock" label="Tồn kho" type="number"/>
                    </div>

                    <!-- IMEI -->
                    <textarea
                        v-model="v.imeis"
                        rows="3"
                        class="w-full border rounded p-2"
                        placeholder="IMEI riêng cho biến thể"
                    />

                    <button
                        type="button"
                        class="text-red-500 text-sm"
                        @click="removeVariant(i)"
                    >
                        Xóa biến thể
                    </button>

                </div>

            </div>


            <!--Biến Thể-->
            <div v-if="form.has_variant && form.variants.length" class="mt-4">

                <h3 class="font-semibold mb-2">Danh sách biến thể</h3>

                <div class="space-y-2">

                    <div
                        v-for="(v, i) in form.variants"
                        :key="i"
                        class="grid grid-cols-6 gap-2 items-center"
                    >

                        <div>{{ v.color }} - {{ v.storage }} - {{ v.version }}</div>

                        <input v-model="v.sku" placeholder="SKU" class="border p-1 rounded" />

                        <input v-model="v.barcode" placeholder="Barcode" class="border p-1 rounded" />

                        <input v-model="v.cost_price" type="number" class="border p-1 rounded" />

                        <input v-model="v.sell_price" type="number" class="border p-1 rounded" />

                        <input v-model="v.stock" type="number" class="border p-1 rounded" />

                    </div>

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