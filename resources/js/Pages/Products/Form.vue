<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import TagBadge from '@/Components/UI/TagBadge.vue'
import {toast} from 'vue-sonner'
import { X } from 'lucide-vue-next'



const props = defineProps({
    title: String,
    size: {
        type: String,
        default: 'xl',
    },
    product: Object,
    categories: Array,
    brands: Array,
    units: {
        type: Array,
        default: () => [],
    },
})



const showVariants = ref(!!props.product?.variants?.length)

// Đặt autoSku ở đây, TRƯỚC TẤT CẢ watcher có sử dụng nó
const autoSku = ref(props.product?.sku ?? '')

const activeIndex = ref(-1)

const dropdown = ref({
    type: null,     // 'attr' | 'value'
    variantIndex: null,
    attrIndex: null,
    keyword: '',
    ref: null
})

const setDropdownRef = (el, type, vIndex, aIndex = null) => {
    if (
        dropdown.value.type === type &&
        dropdown.value.variantIndex === vIndex &&
        dropdown.value.attrIndex === aIndex
    ) {
        dropdown.value.ref = el
    }
}

const handleClickOutside = (e) => {
    if (!dropdown.value.type) return

    // 🔥 nếu click vào suggestion thì bỏ qua
    if (e.target.closest('.suggestion-item')) return

    if (dropdown.value.ref && !dropdown.value.ref.contains(e.target)) {
        dropdown.value.type = null
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside)
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
    unit_id: props.product?.unit_id ?? null,
    sku: props.product?.sku ?? '',
    cost_price: props.product?.cost_price ?? '',
    sell_price: props.product?.sell_price ?? '',
    image: null,
    variants: props.product?.variants ?? [],
    manage_stock_by_serial: props.product?.manage_stock_by_serial ?? false,
    product_type: props.product?.product_type ?? 'normal',
})


/*
|--------------------------------------------------------------------------
| IMAGE PREVIEW
|--------------------------------------------------------------------------
*/
const preview = ref(null)

const getImageUrl = (image) => {
    if (!image) return null

    // Nếu đã là URL đầy đủ
    if (image.startsWith('http://') || image.startsWith('https://')) {
        return image
    }

    return `/storage/${image}`
}



const handleImage = (e) => {
    const file = e.target.files[0]
    if (!file) return

    form.image = file
    preview.value = URL.createObjectURL(file)
}


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

const unitOptions = computed(() => [
    {
        id: null,
        name: 'Không chọn',
    },
    ...(props.units || []).map(unit => ({
        ...unit,
        name: unit.short_name
            ? `${unit.name} (${unit.short_name})`
            : unit.name,
    })),
])



// reset form khi props.product thay đổi (chọn sửa sản phẩm khác)
watch(() => props.product, (p) => {

    if (!p) {
        form.reset()

        autoSku.value = ''
        showVariants.value = false
        preview.value = null

        return
    }

    form.id = p.id ?? null
    form.name = p.name ?? ''
    form.category_id = p.category_id ?? null
    form.brand_id = p.brand_id ?? null
    form.unit_id = p.unit_id ?? null
    form.sku = p.sku ?? ''
    form.cost_price = p.cost_price ?? ''
    form.sell_price = p.sell_price ?? ''

    // Ảnh cũ chỉ dùng để preview
    // Không đưa tên file cũ vào form.image
    form.image = null
    preview.value = getImageUrl(p.image)

    autoSku.value = p.sku ?? ''

    if (p.variants?.length) {

        const merged = {}

        p.variants.forEach(variant => {
            (variant.attributes || []).forEach(attr => {

                if (!merged[attr.name]) {
                    merged[attr.name] = new Set()
                }

                const value = Array.isArray(attr.value)
                    ? attr.value[0]
                    : attr.value

                if (value) {
                    merged[attr.name].add(value)
                }
            })
        })

        form.variants = [{
            attributes: Object.keys(merged).map(name => ({
                id: Date.now(),
                name,
                value: Array.from(merged[name])
            })),
            sku: '',
            barcode: '',
            cost_price: p.cost_price || 0,
            sell_price: p.sell_price || 0,
            stock: 0,
            imeis: ''
        }]

    } else {
        form.variants = []
    }

    // Có biến thể nếu DB trả về ít nhất 1 variant
    showVariants.value = form.variants.length > 0

    form.manage_stock_by_serial =
        p.manage_stock_by_serial ?? false

    form.product_type =
        p.product_type ?? 'normal'

}, { immediate: true })




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

watch(showVariants, (value) => {

    if (value) {

        // Nếu chưa có variant thì tạo 1 variant rỗng
        if (!form.variants.length) {
            form.variants = [{
                attributes: buildVariantAttributes(),
                sku: '',
                barcode: '',
                cost_price: form.cost_price || 0,
                sell_price: form.sell_price || 0,
                stock: 0,
                imeis: ''
            }]
        }

    } else {

        // Tắt checkbox → xóa biến thể
        form.variants = []
    }
})


const buildVariantAttributes = () => {
    return categoryAttributes.value.map(attr => ({
        id: attr.id,
        name: attr.name,
        value: [],
    }))
}


const addAttrValue = (attr, rawValue = dropdown.value.keyword) => {
    const val = rawValue.trim()
    if (!val) return

    const exists = attr.value.some(v => normalize(v) === normalize(val))
    if (exists) {
        return toast.error('Giá trị đã tồn tại')
    }

    attr.value.push(val)
    dropdown.value.keyword = ''   // reset input
    activeIndex.value = -1        // reset chọn
}





watch(
    () => form.category_id,
    (newCategoryId, oldCategoryId) => {
        // Khi đang sửa và đây là lần load dữ liệu ban đầu
        // thì KHÔNG được xóa brand_id
        if (form.id && oldCategoryId === undefined) {
            return
        }

        // Nếu người dùng thực sự đổi danh mục
        if (
            oldCategoryId !== undefined &&
            Number(newCategoryId) !== Number(oldCategoryId)
        ) {
            form.brand_id = null
        }

        // Cập nhật attributes của variant
        if (!categoryAttributes.value.length) {
            return
        }

        if (form.variants.length) {
            form.variants.forEach(variant => {
                variant.attributes = buildVariantAttributes()
            })
        }
    }
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

watch(
    [() => form.category_id, () => form.brand_id],
    async ([categoryId, brandId]) => {

        // Đang sửa → tuyệt đối không tự đổi SKU
        if (form.id) {
            return
        }

        // Chưa đủ thông tin
        if (!categoryId || !brandId) {
            form.sku = ''
            autoSku.value = ''
            return
        }

        try {
            const response = await fetch(
                route('products.previewSku', {
                    category_id: categoryId,
                    brand_id: brandId
                }),
                {
                    headers: {
                        Accept: 'application/json',
                    }
                }
            )

            if (!response.ok) {
                throw new Error('Không lấy được SKU')
            }

            const result = await response.json()

            form.sku = result.sku || ''
            autoSku.value = result.sku || ''

        } catch (error) {
            console.error('Preview SKU error:', error)
        }
    }
)

const normalize = (text) => {
    return (text || '')
        .toString()
        .toLowerCase() // thêm dòng này (fix hoa/thường)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
}

watch(() => dropdown.value.keyword, () => {
    activeIndex.value = -1
})

// Thêm thuộc tính
const createAttribute = (rawName = dropdown.value.keyword) => {
    const name = rawName.trim()
    if (!name) return

    const v = form.variants[dropdown.value.variantIndex]

    const exists = v.attributes.some(a => normalize(a.name) === normalize(name))
    if (exists) return toast.error('Thuộc tính đã tồn tại')

    v.attributes.push({
        id: Date.now(),
        name,
        value: [],
    })

    dropdown.value.keyword = ''
    activeIndex.value = -1
    dropdown.value.type = null
}


// Dropdown thêm thuộc tính
const openAttrDropdown = async (variantIndex) => {
    dropdown.value.type = 'attr'
    dropdown.value.variantIndex = variantIndex
    dropdown.value.attrIndex = null
    dropdown.value.keyword = ''
    activeIndex.value = -1

    await nextTick()

    // 👇 focus input
    const input = document.querySelector(`#attr-input-${variantIndex}`)
    input?.focus()
}

// Dropdown thêm giá trị
const openValueDropdown = async (variantIndex, attrIndex) => {
    dropdown.value.type = 'value'
    dropdown.value.variantIndex = variantIndex
    dropdown.value.attrIndex = attrIndex
    dropdown.value.keyword = ''
    activeIndex.value = -1

    await nextTick()
}


// Xử lý Keyboard
const syncValueDropdown = (variantIndex, attrIndex, keyword = dropdown.value.keyword) => {
    const shouldResetActive =
        dropdown.value.type !== 'value' ||
        dropdown.value.variantIndex !== variantIndex ||
        dropdown.value.attrIndex !== attrIndex ||
        dropdown.value.keyword !== keyword

    dropdown.value.type = 'value'
    dropdown.value.variantIndex = variantIndex
    dropdown.value.attrIndex = attrIndex
    dropdown.value.keyword = keyword

    if (shouldResetActive) {
        activeIndex.value = -1
    }
}

const handleKeyDown = (e, attr = null, variantIndex = null, attrIndex = null) => {
    if (attr && variantIndex !== null && attrIndex !== null) {
        syncValueDropdown(variantIndex, attrIndex, e.target.value)
    }

    if (!dropdown.value.type) return

    if (e.key === 'ArrowDown') {
        if (!suggestions.value.length) return
        e.preventDefault()
        activeIndex.value = (activeIndex.value + 1) % suggestions.value.length
        return
    }

    if (e.key === 'ArrowUp') {
        if (!suggestions.value.length) return
        e.preventDefault()
        activeIndex.value =
            (activeIndex.value - 1 + suggestions.value.length) % suggestions.value.length
        return
    }

    if (e.key === 'Enter') {
        e.preventDefault()


        dropdown.value.keyword = e.target.value

        // nếu còn suggestion → chọn
        if (
            suggestions.value.length &&
            activeIndex.value >= 0 &&
            suggestions.value[activeIndex.value]
        ) {
            selectSuggestion(suggestions.value[activeIndex.value])
            return
        }

        // hết suggestion → tạo mới
        if (dropdown.value.type === 'value') {
            if (attr) addAttrValue(attr, e.target.value)
        } else if (dropdown.value.type === 'attr') {
            createAttribute(e.target.value)
        }
    }
}

// Gợi ý Thuộc tính và giá trị
const suggestions = computed(() => {
    if (!dropdown.value.type) return []

    const keyword = normalize(dropdown.value.keyword)

    const variant = form.variants[dropdown.value.variantIndex]
    if (!variant) return []

    // GỢI Ý THUỘC TÍNH
    if (dropdown.value.type === 'attr') {
        const selected = variant.attributes.map(a => normalize(a.name))

        const all = props.categories
            ?.flatMap(c => c.attributes || [])
            .map(a => a.name)
            .filter((v, i, arr) => arr.indexOf(v) === i) || []

        return all
            .filter(name => !selected.includes(normalize(name)))
            .filter(name => normalize(name).includes(keyword))
    }

    // GỢI Ý GIÁ TRỊ
    if (dropdown.value.type === 'value') {
        const attr = variant.attributes[dropdown.value.attrIndex]
        if (!attr) return []

        const origin = categoryAttributes.value.find(
            a =>
                Number(a.id) === Number(attr.id) ||
                normalize(a.name) === normalize(attr.name)
        )

        return (origin?.values || [])
            .map(v => v.value)
            .filter(v => !attr.value.includes(v))
            .filter(v => normalize(v).includes(keyword))
    }

    return []
})

watch(suggestions, (list) => {
    if (!list.length) {
        activeIndex.value = -1
    }
})




// Chọn gợi ý
const selectSuggestion = async (item) => {
    const v = form.variants[dropdown.value.variantIndex]

    // 1. GỢI Ý GIÁ TRỊ THUỘC TÍNH
    if (dropdown.value.type === 'value') {
        const attr = v.attributes[dropdown.value.attrIndex]
        if (!attr) return

        const exists = attr.value.some(val => normalize(val) === normalize(item))
        if (exists) {
            return toast.error('Giá trị đã tồn tại')
        }

        attr.value.push(item)
        dropdown.value.keyword = '' // Reset từ khóa
        activeIndex.value = -1
        return
    }

    // 2. GỢI Ý THUỘC TÍNH BIẾN THỂ
    if (dropdown.value.type === 'attr') {
        const exists = v.attributes.some(a => normalize(a.name) === normalize(item))
        if (exists) {
            return toast.error('Thuộc tính đã tồn tại')
        }

        v.attributes.push({
            id: Date.now(),
            name: item,
            value: []
        })

        // GIỮ NGUYÊN dropdown.type = 'attr' ĐỂ KHÔNG ĐÓNG DROPDOWN
        dropdown.value.keyword = '' // Clear từ khóa tìm kiếm để load toàn bộ gợi ý còn lại
        activeIndex.value = -1

        await nextTick()
        // Re-focus lại ô input nhập thuộc tính
        const inputEl = document.querySelector(`#attr-input-${dropdown.value.variantIndex}`)
        if (inputEl) {
            inputEl.focus()
        }
    }
}

watch(
    () => form.variants.map(v => v.attributes.length),
    () => {
        if (dropdown.value.type === 'attr') {
            dropdown.value.keyword = ''
            activeIndex.value = -1
        }
    }
)


// Xóa thuộc tính
const removeAttribute = (variant, attrIndex) => {
    variant.attributes.splice(attrIndex, 1)
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
        form
            .transform(data => {
                const payload = {
                    ...data,
                    _method: 'PUT',
                }

                // Không chọn ảnh mới thì không gửi image
                if (!(data.image instanceof File)) {
                    delete payload.image
                }

                return payload
            })
            .post(route('products.update', form.id), options)
    } else {
        form.post(route('products.store'), options)
    }
}



</script>

<template>
    <BaseModal
        :title="title"
        :size="size"
        @close="closeModal()"
    >
        <div class="grid gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
            <!-- LEFT -->
            <div class="space-y-3">
                <label class="group flex aspect-square w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 transition hover:border-emerald-400 hover:bg-emerald-50/40">

                    <img
                        v-if="preview"
                        :src="preview"
                        class="h-full w-full object-cover"
                    />

                    <span
                        v-else
                        class="px-4 text-center text-sm font-semibold text-slate-400 group-hover:text-emerald-600"
                    >
                        Bấm để thêm ảnh sản phẩm
                    </span>

                    <input
                        type="file"
                        class="hidden"
                        accept="image/*"
                        @change="handleImage"
                    />
                </label>

                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500">
                    Ảnh nên vuông, nền sáng để hiển thị đẹp ở POS và danh sách sản phẩm.
                </div>
            </div>

            <!-- RIGHT -->
            <div class="min-w-0 space-y-5">

                <!-- FORM -->
                <div class="grid gap-4 md:grid-cols-2">

                    <div class="md:col-span-2">
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

                    <FloatingSelect
                        v-model="form.unit_id"
                        :options="unitOptions"
                        option-label="name"
                        option-value="id"
                        label="Đơn vị tính"
                        :error="form.errors.unit_id"
                    />

                    <!-- SKU + IMEI + BIẾN THỂ -->
                    <div class="grid gap-3">

                        <!-- SKU -->
                        <FloatingInput
                            v-model="form.sku"
                            label="SKU"
                            :error="form.errors.sku"
                        />

                        <!-- CÓ IMEI + NÚT BIẾN THỂ -->
                        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <!-- IMEI -->
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                                <input
                                    v-model="form.manage_stock_by_serial"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                Có IMEI
                            </label>

                            <!-- VARIANT -->
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                                <input
                                    v-model="showVariants"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                Có thuộc tính
                            </label>
                        </div>
                    </div>


                    <!-- ========================= -->
                    <!-- DANH SÁCH Thuộc tính-->
                    <!-- ========================= -->
                    <div
                        v-if="form.variants.length"
                        class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4 md:col-span-2"
                    >
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">
                                Thuộc tính biến thể
                            </h3>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">
                                Nhập giá trị rồi nhấn Enter hoặc dấu phẩy để thêm nhanh.
                            </p>
                        </div>

                        <div
                            v-for="(v, i) in form.variants"
                            :key="v.id || i"
                            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                        >

                            <!-- Thuộc tính mới -->
                            <div
                                v-for="(attr, i2) in v.attributes"
                                :key="attr.id || attr.name"
                                class="relative"
                            >
                                <!-- INPUT -->
                                <div
                                    class="relative"
                                    :ref="el => setDropdownRef(el, 'value', i, i2)"
                                >
                                    <!-- INPUT NHẬP GIÁ TRỊ THUỘC TÍNH -->
                                    <FloatingInput
                                        :model-value="
                                            dropdown.type === 'value' &&
                                            dropdown.variantIndex === i &&
                                            dropdown.attrIndex === i2
                                                ? dropdown.keyword
                                                : ''
                                        "
                                        :label="attr.name"
                                        @focus="openValueDropdown(i, i2)"
                                        @update:model-value="val => {
                                            if (dropdown.type === 'value' && dropdown.variantIndex === i && dropdown.attrIndex === i2) {
                                                dropdown.keyword = val
                                            }
                                        }"
                                        @keydown="e => {
                                            if (e.key === ',') {
                                                e.preventDefault()
                                                addAttrValue(attr, e.target.value)
                                                return
                                            }
                                            handleKeyDown(e, attr, i, i2)
                                        }"
                                    />

                                    <div
                                        v-if="
                                            dropdown.type === 'value' &&
                                            dropdown.variantIndex === i &&
                                            dropdown.attrIndex === i2 &&
                                            suggestions.length
                                        "
                                        class="absolute z-20 max-h-40 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                    >
                                        <div
                                            v-for="(item, idx) in suggestions"
                                            :key="item + '-' + idx"
                                            class="suggestion-item cursor-pointer px-3 py-2 text-sm hover:bg-slate-50"
                                            @mousedown.prevent="selectSuggestion(item)"
                                        >
                                            {{ item }}
                                        </div>
                                    </div>
                                </div>

                                <!-- NÚT XÓA NỔI TRÊN INPUT -->
                                <button
                                    type="button"
                                    @click="removeAttribute(v, i2)"
                                    class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow hover:bg-red-600"
                                >
                                    <X :size="13" />
                                </button>

                                <!-- TAG -->
                                <div
                                    v-if="attr.value.length"
                                    class="pt-2 flex flex-wrap gap-2"
                                >
                                    <TagBadge
                                        v-for="(val, idx) in attr.value"
                                        :key="idx"
                                        :label="val"
                                        removable
                                        size="lg"
                                        color="blue"
                                        @remove="attr.value.splice(idx, 1)"
                                    />
                                </div>
                            </div>

                            <!-- NÚT THÊM -->
                            <div>
                                <div class="w-full">

                                   <div
                                        v-if="dropdown.type === 'attr' && dropdown.variantIndex === i"
                                        class="relative"
                                        :ref="el => setDropdownRef(el, 'attr', i)"
                                    >
                                        <input
                                            :id="'attr-input-' + i"
                                            :value="dropdown.type === 'attr' && dropdown.variantIndex === i ? dropdown.keyword : ''"
                                            class="h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                            placeholder="Nhập hoặc chọn thuộc tính..."
                                            @focus="openAttrDropdown(i)"
                                            @input="e => {
                                                dropdown.type = 'attr'
                                                dropdown.variantIndex = i
                                                dropdown.keyword = e.target.value
                                            }"
                                            @keydown="e => handleKeyDown(e, null, i)"
                                        />

                                        <div
                                            v-if="suggestions.length"
                                            class="absolute z-10 max-h-40 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                        >
                                            <div
                                                v-for="(item, idx) in suggestions"
                                                :key="idx"
                                                :class="[
                                                    'px-3 py-2 cursor-pointer text-sm',
                                                    idx === activeIndex ? 'bg-blue-100' : 'hover:bg-gray-100'
                                                ]"
                                                @mousedown.prevent="selectSuggestion(item)"
                                            >
                                                {{ item }}
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        v-else
                                        @click="openAttrDropdown(i)"
                                        type="button"
                                        class="h-[42px] rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700"
                                    >
                                        + Thêm
                                    </button>

                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="grid gap-4 md:col-span-2 md:grid-cols-2">
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
