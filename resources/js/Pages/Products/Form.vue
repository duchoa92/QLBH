<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch, watchEffect } from 'vue'
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



const showVariants = ref(false)

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

/*
|--------------------------------------------------------------------------
| LỌC THƯƠNG HIỆU THEO DANH MỤC
|--------------------------------------------------------------------------
*/
const filteredBrands = computed(() => {
    if (!form.category_id) {
        return []
    }

    return props.brands.filter(
        brand => Number(brand.category_id) === Number(form.category_id)
    )
})

/*
|--------------------------------------------------------------------------
| ĐỔI DANH MỤC → XÓA THƯƠNG HIỆU ĐÃ CHỌN
|--------------------------------------------------------------------------
*/
watch(
    () => form.category_id,
    () => {
        form.brand_id = null
    }
)

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
    autoSku.value = p.sku ?? ''
    form.cost_price = p.cost_price ?? ''
    form.sell_price = p.sell_price ?? ''
    form.variants = p.variants ?? []

    showVariants.value = form.variants.length > 0

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
    if (!p) {
        form.reset()
        showVariants.value = false // 🔥 thêm dòng này
        return
    }

    form.variants = p.variants ?? []

    showVariants.value = form.variants.length > 0 // 🔥 chuẩn
})

const handleImage = (e) => {
    const file = e.target.files[0]
    if (!file) return

    form.image = file
    preview.value = URL.createObjectURL(file)
}



const categoryAttributes = computed(() => {
    const cat = props.categories.find(
        c => Number(c.id) === Number(form.category_id)
    )

    if (!cat?.attributes) {
        return []
    }

    return cat.attributes.map(attr => ({
        id: attr.id,
        name: attr.name,
        values: (
            attr.values ??
            attr.options ??
            []
        ).map(value => {
            if (typeof value === 'string') {
                return {
                    value
                }
            }

            return value
        })
    }))
})

const buildVariantAttributes = () => {
    return categoryAttributes.value.map(attr => ({
        id: attr.id,
        name: attr.name,
        value: ''
    }))
}
// Thêm xóa biến thể
const toggleVariants = () => {
    if (form.variants.length) {
        form.variants = []
        return
    }

    form.variants = [{
        attributes: buildVariantAttributes(),
        sku: '',
        cost_price: 0,
        sell_price: 0,
        stock: 0,
    }]
}

watch(
    () => form.category_id,
    () => {
        // Đổi danh mục thì thương hiệu phải reset
        form.brand_id = null

        // Chưa có thuộc tính thì không tạo biến thể
        if (!categoryAttributes.value.length) {
            return
        }

        // Nếu đang bật biến thể thì cập nhật ngay
        if (form.variants.length) {
            form.variants.forEach(variant => {
                variant.attributes = buildVariantAttributes()
            })
        }
    },
    { flush: 'sync' }
)


// Tạo SKU tự động

const makeCode = (text) => {
    if (!text) return ''

    // 🔥 bỏ dấu tiếng Việt
    const noAccent = text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')

    const words = noAccent.trim().split(/\s+/)

    if (words.length === 1) {
        return words[0].slice(0, 3).toUpperCase()
    }

    if (words.length === 2) {
        return (words[0][0] + words[1].slice(0, 2)).toUpperCase()
    }

    return words.slice(0, 3).map(w => w[0]).join('').toUpperCase()
}

const autoSku = ref('')
watch(
    [() => form.category_id, () => form.brand_id],
    ([categoryId, brandId], [oldCategoryId, oldBrandId]) => {

        const cat = props.categories.find(
            c => Number(c.id) === Number(categoryId)
        )

        const brand = props.brands.find(
            b => Number(b.id) === Number(brandId)
        )

        if (!cat || !brand) return

        const base =
            makeCode(cat.name) +
            makeCode(brand.name)

        /*
        |--------------------------------------------------------------------------
        | SKU sản phẩm
        |--------------------------------------------------------------------------
        */

        // Khi thêm mới hoặc khi SKU đang là SKU tự động cũ
        if (
            !form.id ||
            !form.sku ||
            form.sku === autoSku.value
        ) {
            form.sku = base
            autoSku.value = base
        }

        /*
        |--------------------------------------------------------------------------
        | SKU biến thể
        |--------------------------------------------------------------------------
        */

        form.variants.forEach(v => {

            const parts = []

            v.attributes?.forEach(attr => {

                if (attr.value) {

                    parts.push(
                        attr.value
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .replace(/đ/g, 'd')
                            .replace(/Đ/g, 'D')
                            .slice(0, 3)
                            .toUpperCase()
                    )

                }

            })

            v.sku =
                base +
                (parts.length
                    ? '-' + parts.join('-')
                    : ''
                )
        })
    }
)

const normalize = (text) => {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
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
                        :options="categories"
                        option-label="name"
                        option-value="id"
                        label="Danh mục"

                        :error="form.errors.category_id"
                    />

                    <FloatingSelect
                        v-model="form.brand_id"
                        :options="filteredBrands"
                        option-label="name"
                        option-value="id"
                        label="Thương hiệu"
                        :error="form.errors.brand_id"
                    />

                    <!-- SKU + IMEI + BIẾN THỂ -->
                    <div class="col-span-2 grid grid-cols-2 gap-4">

                        <!-- SKU -->
                        <FloatingInput
                            v-model="form.sku"
                            label="SKU"
                            :error="form.errors.sku"
                        />

                        <!-- CÓ IMEI + NÚT BIẾN THỂ -->
                        <div class="flex items-center gap-3">

                            <!-- CHECKBOX IMEI -->
                            <div class="flex items-center gap-2">
                                <input
                                    id="imei"
                                    type="checkbox"
                                    v-model="form.manage_stock_by_serial"
                                    class="w-5 h-5 accent-green-600 cursor-pointer"
                                />

                                <label
                                    for="imei"
                                    class="text-sm cursor-pointer select-none"
                                >
                                    Có IMEI
                                </label>
                            </div>

                            <!-- THÊM / XÓA BIẾN THỂ -->
                            <button
                                type="button"
                                class="px-3 py-2 bg-gray-200 rounded text-sm whitespace-nowrap"
                                @click="toggleVariants"
                            >
                                {{ form.variants.length ? 'Xóa biến thể' : 'Thêm biến thể' }}
                            </button>

                        </div>
                    </div>


                    <!-- ========================= -->
                    <!-- DANH SÁCH BIẾN THỂ -->
                    <!-- ========================= -->
                    <div
                        v-if="form.variants.length"
                        class="col-span-2 border rounded-lg p-3"
                    >

                        <div
                            v-for="(v, i) in form.variants"
                            :key="i"
                            class="space-y-3"
                        >

                            <!-- THUỘC TÍNH -->
                            <div class="grid grid-cols-3 gap-3">

                                <div
                                    v-for="(attr, i2) in v.attributes"
                                    :key="i2"
                                >

                                    <FloatingSelect
                                        v-model="attr.value"
                                        :key="`${form.category_id}-${attr.id}-${i2}`"
                                        :options="
                                            categoryAttributes.find(a => a.id === attr.id)?.values ?? []
                                        "
                                        option-label="value"
                                        option-value="value"
                                        :label="attr.name"
                                    />

                                </div>

                            </div>

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