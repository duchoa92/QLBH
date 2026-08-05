<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'


const props = defineProps({
    title: String,
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
    sku: null,
    cost_price: props.product?.cost_price ?? '',
    sell_price: props.product?.sell_price ?? '',
    image: null,
    variants: props.product?.variants ?? [],

    manage_stock_by_serial: props.product?.manage_stock_by_serial ?? false,
    product_type: props.product?.product_type ?? 'normal',

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
    form.sku = p.sku ?? null
    form.cost_price = p.cost_price ?? ''
    form.sell_price = p.sell_price ?? ''
    form.variants = p.variants ?? []

    form.manage_stock_by_serial = p.manage_stock_by_serial ?? false
    form.product_type = p.product_type ?? 'normal'

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


const showVariants = ref(false)
// Thêm xóa biến thể
const toggleVariants = () => {

    if (showVariants.value) {
        // xóa hết
        form.variants = []
    } else {
        // thêm 1 cái mặc định
        form.variants = [{
            color: '',
            storage: '',
            version: '',
            sku: '',
            cost_price: 0,
            sell_price: 0,
            stock: 0,
        }]
    }

    showVariants.value = !showVariants.value
}


// Tạo SKU tự động

const makeCode = (text) => {
    if (!text) return ''

    const words = text.toLowerCase().split(' ')

    if (words.length === 1) {
        return words[0].slice(0, 3).toUpperCase()
    }

    if (words.length === 2) {
        return (words[0][0] + words[1].slice(0, 2)).toUpperCase()
    }

    return (words[0][0] + words[1][0] + words[2][0]).toUpperCase()
}

watch(
    () => [form.category_id, form.brand_id, form.variants],
    () => {
        const cat = props.categories.find(c => c.id == form.category_id)
        const brand = props.brands.find(b => b.id == form.brand_id)

        if (!cat || !brand) return

        const base = makeCode(cat.name) + makeCode(brand.name)

        form.sku = base

        form.variants.forEach(v => {
            let suffix = []

            if (v.storage) suffix.push(v.storage)
            if (v.color) suffix.push(v.color.slice(0, 3).toUpperCase())

            v.sku = base + (suffix.length ? '-' + suffix.join('-') : '')
        })
    },
    { deep: true }
)

watch(
    () => form.variants,
    () => {
        const cat = props.categories.find(c => c.id == form.category_id)
        const brand = props.brands.find(b => b.id == form.brand_id)

        const catCode = makeCode(cat?.name)
        const brandCode = makeCode(brand?.name)

        form.variants.forEach(v => {
            let parts = []

            if (v.storage) parts.push(v.storage)
            if (v.color) parts.push(v.color.slice(0,3).toUpperCase())

            v.sku = `${catCode}${brandCode}-${parts.join('-')}`
        })
    },
    { deep: true }
)

/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/
const emit = defineEmits(['close', 'updated'])

const submit = () => {

    const options = {
        onSuccess: () => {
            form.reset()
            form.clearErrors()
            emit('updated')
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
    <BaseModal :title="title" @close="closeModal()">
        <div class="flex overflow-hidden">
            <!-- LEFT -->
            <div class="w-1/3 border-r p-4 flex flex-col items-center justify-center">
                <label class="w-full h-[300px] border-2 border-dashed rounded-lg flex items-center justify-center cursor-pointer overflow-hidden">

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
            <div class="flex-1 p-4 space-y-4 overflow-y-auto max-h-[80vh]">

                <!-- FORM -->
                <div class="grid grid-cols-2 gap-4">

                    <div class="col-span-2">
                        <FloatingInput
                            v-model="form.name"
                            label="Tên hàng hóa"
                            :error="form.errors.name"
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

                        :error="form.errors.category_id"
                    />

                    <FloatingSelect
                        v-model="form.brand_id"
                        :options="brands"
                        option-label="name"
                        option-value="id"
                        label="Thương hiệu"
                        :error="form.errors.brand_id"
                    />

                    <div class="col-span-2 grid grid-cols-2 gap-4">
                        <FloatingInput
                            v-model="form.sku"
                            label="SKU"
                            :error="form.errors.sku"
                        />
                        <!-- CHECKBOX IMEI -->
                        <div class="flex items-center gap-2 mt-2">
                            <input
                                id="imei"
                                type="checkbox"
                                v-model="form.manage_stock_by_serial"
                                class="w-5 h-5 accent-green-600 cursor-pointer"
                            />

                            <label for="imei" class="text-sm cursor-pointer select-none">
                                Có IMEI
                            </label>
                        </div>
                    </div>

                    <div class="col-span-2 grid grid-cols-3 gap-4">
                        <FloatingInput
                            v-model="form.cost_price"
                            label="Giá vốn"
                            type="number"
                            :error="form.errors.cost_price"
                        />

                        <FloatingInput
                            v-model="form.sell_price"
                            label="Giá bán"
                            type="number"
                            :error="form.errors.sell_price"

                        />

                    </div>

                    <div>
                        <button
                            type="button"
                            class="px-3 py-2 bg-gray-200 rounded text-sm"
                            @click="toggleVariants"
                        >
                            {{ showVariants ? 'Xóa biến thể' : 'Thêm biến thể' }}
                        </button>

                        <div v-if="showVariants">
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

                            </div>
                        </div>
                        
                    </div>

                </div>
            </div>

        </div>

        <template #footer>
        <div class="flex justify-end gap-2">
            <button @click="closeModal()" class="px-4 py-2 bg-gray-200 rounded">
                Hủy
            </button>

            <button 
                @click="submit"
                class="px-4 py-2 bg-green-600 text-white rounded"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
            </button>
        </div>
    </template>

    </BaseModal>

</template>


<style scoped>
.btn-green {
    @apply px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700;
}
.btn-gray {
    @apply px-4 py-2 bg-gray-200 rounded;
}
</style>