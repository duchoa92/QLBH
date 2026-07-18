<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { ScanSearch } from 'lucide-vue-next'
import { BrowserMultiFormatReader } from '@zxing/browser'

const props = defineProps({
    product: Object,
    categories: Array,
    brands: Array
})

/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/
const form = useForm({
    id: props.product?.id ?? null,
    name: props.product?.name ?? '',
    category_id: props.product?.category_id ?? null,
    brand_id: props.product?.brand_id ?? null,
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    cost_price: props.product?.cost_price ?? '',
    sell_price: props.product?.sell_price ?? '',
    stock: props.product?.stock ?? 0,
    imeis: '',
    image: null,
    variants: props.product?.variants ?? []

})

// reset form khi props.product thay đổi (chọn sửa sản phẩm khác)
watch(() => props.product, (p) => {

    if (!p) {
        form.reset()
        return
    }

    form.name = p.name ?? ''
    form.category_id = p.category_id ?? null
    form.brand_id = p.brand_id ?? null
    form.sku = p.sku ?? ''
    form.barcode = p.barcode ?? ''
    form.cost_price = p.cost_price ?? ''
    form.sell_price = p.sell_price ?? ''
    form.stock = p.stock ?? 0
    form.variants = p.variants ?? []

})

/*
|--------------------------------------------------------------------------
| IMAGE PREVIEW
|--------------------------------------------------------------------------
*/
const preview = ref(null)

// Hiên thị ảnh khi props.product thay đổi
watch(() => props.product, (p) => {
    preview.value = p?.image_url || null
})

const handleImage = (e) => {
    const file = e.target.files[0]
    if (!file) return

    form.image = file
    preview.value = URL.createObjectURL(file)
}

// Thêm xóa biến thể
const addVariant = () => {
    form.variants.push({
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
    form.variants.splice(index, 1)
}

// Tạo barcode
const generateBarcode = () => {
    // dạng 13 số (EAN-like)
    const code = Date.now().toString().slice(-10) +
        Math.floor(Math.random() * 1000).toString().padStart(3, '0')

    form.barcode = code
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


let handler = (e) => {
    if (e.key === 'Enter') {
        if (buffer.length > 5) {
            form.imeis += (form.imeis ? '\n' : '') + buffer
        }
        buffer = ''
    } else {
        buffer += e.key
    }
}

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handler)
    stopCamera() // 🔥 quan trọng
})



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
const emit = defineEmits(['close', 'updated'])

const submit = () => {

    const options = {
        onSuccess: () => {
            emit('updated')
            form.imeis = ''
            closeModal()
        }
    }

    if (form.id) {
        form.put(route('products.update', form.id), options)
    } else {
        form.post(route('products.store'), options)
    }
}


</script>

<template>
<div class="flex bg-white">

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
                    @update:modelValue="v => {
                        form.category_id = v
                        form.brand_id = null
                    }"
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
                    v-for="(v, i) in form.variants"
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


            

            <!-- ACTION -->
            <div class="flex justify-end gap-2 pt-4">
                <button
                    type="button"
                    @click="closeModal()"
                    class="btn-gray"
                >
                    Hủy
                </button>
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="btn-green"
                >
                    {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
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