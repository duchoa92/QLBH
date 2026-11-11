<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import SearchableReferenceSelect from '@/Components/UI/SearchableReferenceSelect.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import TagBadge from '@/Components/UI/TagBadge.vue'
import {toast} from 'vue-sonner'
import { ImagePlus, Plus, Trash2 } from 'lucide-vue-next'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import { useReferenceData } from '@/Stores/referenceData'
import { imageUrl } from '@/utils/imageUrl'
import ReferenceCreateModal from './ReferenceCreateModal.vue'



const props = defineProps({
    title: String,
    size: {
        type: String,
        default: 'xl',
    },
    product: Object,
    initialName: { type: String, default: '' },
    categories: Array,
    brands: Array,
    units: {
        type: Array,
        default: () => [],
    },
})

const { categories, brands, units } = useReferenceData({
    categories: props.categories,
    brands: props.brands,
    units: props.units,
})


const showVariants = ref(!!props.product?.variants?.length)
const priceUnitBasis = ref('base')

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
const pendingValueDrafts = reactive({})
const pendingAttributeDrafts = reactive({})
const addingAttributeFor = reactive({})
const existingWarrantyDays = Number(props.product?.warranty_days ?? 0)
const initialWarrantyUnit = existingWarrantyDays > 0 && existingWarrantyDays % 30 === 0 ? 'month' : 'day'
const warrantyUnitOptions = [
    { value: 'month', label: 'Tháng' },
    { value: 'day', label: 'Ngày' },
]

const variantDraftKey = (variantIndex) => String(form.variants[variantIndex]?.id ?? variantIndex)
const valueDraftKey = (variantIndex, attr) => `${variantDraftKey(variantIndex)}:${attr.id ?? attr.name}`

const closeDropdown = (type, variantIndex, attrIndex = null) => {
    if (
        dropdown.value.type !== type ||
        dropdown.value.variantIndex !== variantIndex ||
        dropdown.value.attrIndex !== attrIndex
    ) return

    dropdown.value.type = null
    dropdown.value.variantIndex = null
    dropdown.value.attrIndex = null
    dropdown.value.keyword = ''
    dropdown.value.ref = null
    activeIndex.value = -1
}

const handleAttributeInputBlur = (variantIndex, event) => {
    closeDropdown('attr', variantIndex)

    const key = variantDraftKey(variantIndex)
    if (!String(event.target.value || '').trim()) {
        delete pendingAttributeDrafts[key]
        addingAttributeFor[key] = false
    }
}

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
        closeDropdown(dropdown.value.type, dropdown.value.variantIndex, dropdown.value.attrIndex)
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
    name: props.product?.name ?? props.initialName ?? '',
    category_id: props.product?.category_id ?? null,
    brand_id: props.product?.brand_id ?? null,
    unit_id: props.product?.unit_id ?? null,
    has_unit_conversion: props.product?.has_unit_conversion ?? false,
    conversion_unit_id: props.product?.conversion_unit_id ?? null,
    conversion_factor: props.product?.conversion_factor ?? 1,
    sku: props.product?.sku ?? '',
    model: props.product?.model ?? '',
    cost_price: props.product?.cost_price ?? '',
    sell_price: props.product?.sell_price ?? '',
    warranty_days: existingWarrantyDays,
    warranty_value: existingWarrantyDays > 0
        ? existingWarrantyDays / (initialWarrantyUnit === 'month' ? 30 : 1)
        : '',
    warranty_unit: initialWarrantyUnit,
    image: null,
    variants: props.product?.variants ?? [],
    manage_stock_by_serial: props.product?.manage_stock_by_serial ?? false,
    product_type: props.product?.product_type ?? 'normal',
})

const changePriceUnitBasis = (basis) => {
    if (priceUnitBasis.value === basis) return
    const factor = Math.max(1, Number(form.conversion_factor || 1))
    const multiplier = basis === 'conversion' ? factor : 1 / factor
    form.cost_price = Number(form.cost_price || 0) * multiplier
    form.sell_price = Number(form.sell_price || 0) * multiplier
    ;(form.variants || []).forEach(variant => {
        variant.cost_price = Number(variant.cost_price || 0) * multiplier
        variant.sell_price = Number(variant.sell_price || 0) * multiplier
    })
    priceUnitBasis.value = basis
}

const clearVariantDrafts = () => {
    ;[pendingValueDrafts, pendingAttributeDrafts, addingAttributeFor].forEach(drafts => {
        Object.keys(drafts).forEach(key => delete drafts[key])
    })
    dropdown.value = { type: null, variantIndex: null, attrIndex: null, keyword: '', ref: null }
    activeIndex.value = -1
}


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


/*
|--------------------------------------------------------------------------
| LỌC THƯƠNG HIỆU THEO DANH MỤC
|--------------------------------------------------------------------------
*/
const filteredBrands = computed(() => {
    if (!form.category_id) {
        return []
    }

    // Thương hiệu dùng chung: hiện mọi thương hiệu có liên kết với danh mục đang chọn.
    return brands.value.filter(
        brand => (brand.category_ids ?? []).some(id => Number(id) === Number(form.category_id))
    )
})

const selectedCategoryName = computed(() =>
    categories.value.find(item => Number(item.id) === Number(form.category_id))?.name || ''
)

const unitOptions = computed(() => units.value.map(unit => ({
        ...unit,
        name: unit.short_name
            ? `${unit.name} (${unit.short_name})`
            : unit.name,
    })))

const conversionUnitOptions = computed(() =>
    units.value
        .filter(unit => Number(unit.id) !== Number(form.unit_id))
        .map(unit => ({
            ...unit,
            name: unit.short_name
                ? `${unit.name} (${unit.short_name})`
                : unit.name,
        }))
)

const referenceModal = ref({ type: null, initialName: '', target: 'primary' })

const openReferenceCreate = (type, initialName = '', target = 'primary') => {
    if (type === 'brand' && !form.category_id) {
        toast.warning('Vui lòng chọn danh mục trước khi thêm thương hiệu.')
        return
    }
    referenceModal.value = { type, initialName, target }
}

const closeReferenceCreate = () => {
    referenceModal.value = { type: null, initialName: '', target: 'primary' }
}

const handleReferenceCreated = (record) => {
    const type = referenceModal.value.type
    if (!record || !type) return

    if (type === 'category') {
        if (!categories.value.some(item => Number(item.id) === Number(record.id))) categories.value.push(record)
        form.category_id = record.id
        form.brand_id = null
    } else if (type === 'brand') {
        if (!brands.value.some(item => Number(item.id) === Number(record.id))) brands.value.push(record)
        // Thương hiệu mới được tạo cho danh mục đang chọn nên giữ nguyên danh mục.
        if (!form.category_id && record.category_ids?.length) form.category_id = record.category_ids[0]
        form.brand_id = record.id
    } else {
        if (!units.value.some(item => Number(item.id) === Number(record.id))) units.value.push(record)
        if (referenceModal.value.target === 'conversion') form.conversion_unit_id = record.id
        else form.unit_id = record.id
    }
}

const selectedUnitName = computed(() => {
    const unit = units.value.find(
        item => Number(item.id) === Number(form.unit_id)
    )

    return unit?.short_name || unit?.name || 'đơn vị bán'
})

const selectedImportUnitName = computed(() => {
    const unit = units.value.find(
        item => Number(item.id) === Number(form.conversion_unit_id)
    )

    return unit?.short_name || unit?.name || 'đơn vị nhập'
})



// reset form khi props.product thay đổi (chọn sửa sản phẩm khác)
watch(() => props.product, (p) => {
    clearVariantDrafts()

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
    form.has_unit_conversion = p.has_unit_conversion ?? false
    form.conversion_unit_id = p.conversion_unit_id ?? null
    form.conversion_factor = p.conversion_factor ?? 1
    form.sku = p.sku ?? ''
    form.model = p.model ?? ''
    form.cost_price = p.cost_price ?? ''
    form.sell_price = p.sell_price ?? ''
    priceUnitBasis.value = 'base'

    // Ảnh cũ chỉ dùng để preview
    // Không đưa tên file cũ vào form.image
    form.image = null
    preview.value = imageUrl(p.image_url ?? p.image)

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
            imeis: '',
            overrides: Object.fromEntries(p.variants.map(variant => [
                variantAttributeKey(variant.attributes || []),
                {
                    sku: variant.sku || '',
                    cost_price: variant.cost_price ?? p.cost_price ?? 0,
                    sell_price: variant.sell_price ?? p.sell_price ?? 0,
                    stock: variant.stock ?? 0,
                },
            ])),
        }]

    } else {
        form.variants = []
    }

    // Có biến thể nếu DB trả về ít nhất 1 variant
    showVariants.value = form.variants.length > 0

    form.manage_stock_by_serial =
        p.manage_stock_by_serial ?? false

    if (form.manage_stock_by_serial) {
        form.has_unit_conversion = false
        form.conversion_unit_id = null
        form.conversion_factor = 1
    }

    form.product_type =
        p.product_type ?? 'normal'

}, { immediate: true })




const categoryAttributes = computed(() => {
    const cat = categories.value.find(
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
                imeis: '',
                overrides: {},
            }]
        }

    } else {

        // Tắt checkbox → xóa biến thể
        form.variants = []
        clearVariantDrafts()
    }
})

watch(() => form.manage_stock_by_serial, (enabled) => {
    if (!enabled) return

    form.has_unit_conversion = false
    form.conversion_unit_id = null
    form.conversion_factor = 1
})

watch(() => form.has_unit_conversion, (enabled) => {
    if (enabled) {
        form.manage_stock_by_serial = false
        return
    }

    form.conversion_unit_id = null
    form.conversion_factor = 1
})

watch(() => form.unit_id, (unitId) => {
    if (Number(unitId) === Number(form.conversion_unit_id)) {
        form.conversion_unit_id = null
    }
})


const buildVariantAttributes = () => {
    return categoryAttributes.value.map(attr => ({
        id: attr.id,
        name: attr.name,
        value: [],
    }))
}

function variantAttributeKey(attributes) {
    return (attributes || [])
        .map((attribute) => `${String(attribute.name || '').trim().toLocaleLowerCase()}=${String(Array.isArray(attribute.value) ? attribute.value[0] : attribute.value || '').trim().toLocaleLowerCase()}`)
        .sort()
        .join('|')
}

const variantPreviewRows = computed(() => {
    const draft = form.variants[0]
    const attributes = draft?.attributes || []
    const completeAttributes = attributes.filter(attribute => attribute.name && attribute.value?.length)

    if (!completeAttributes.length || completeAttributes.length !== attributes.length) return []

    return completeAttributes.reduce((rows, attribute) => rows.flatMap(row =>
        attribute.value.map(value => {
            const values = [...row.attributes, { name: attribute.name, value }]
            return {
                attributes: values,
                key: variantAttributeKey(values),
                label: values.map(item => item.value).join(' - '),
            }
        })
    ), [{ attributes: [] }]).map(row => ({
        ...row,
        label: row.label || row.attributes.map(item => item.value).join(' - '),
    }))
})

const variantOverrideFor = (row) => {
    const draft = form.variants[0]
    if (!draft) return {}
    draft.overrides ||= {}

    if (!draft.overrides[row.key]) {
        draft.overrides[row.key] = {
            sku: '',
            // Giá chung đã điền thì phiên bản mới lấy giá chung làm mặc định, sau đó sửa giá riêng ngay trong bảng
            cost_price: Number(form.cost_price) > 0 ? form.cost_price : (draft.cost_price || 0),
            sell_price: Number(form.sell_price) > 0 ? form.sell_price : (draft.sell_price || 0),
            stock: 0,
        }
    }

    return draft.overrides[row.key]
}


const addAttrValue = (
    attr,
    rawValue = dropdown.value.keyword,
    variantIndex = dropdown.value.variantIndex,
    attrIndex = dropdown.value.attrIndex,
) => {
    const val = rawValue.trim()
    if (!val) return

    const exists = attr.value.some(v => normalize(v) === normalize(val))
    if (exists) {
        return toast.error('Giá trị đã tồn tại')
    }

    attr.value.push(val)
    if (variantIndex !== null && variantIndex !== undefined) {
        delete pendingValueDrafts[valueDraftKey(variantIndex, attr)]
    }
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


let skuPreviewRequestId = 0

watch(
    [() => form.category_id, () => form.brand_id],
    async ([categoryId, brandId]) => {
        // Mỗi lần đổi danh mục/thương hiệu là một yêu cầu mới; phản hồi của yêu cầu cũ đến muộn sẽ bị bỏ qua.
        const requestId = ++skuPreviewRequestId

        // Đang sửa → tuyệt đối không tự đổi SKU
        if (form.id) {
            return
        }

        // SKU do người dùng tự nhập thì giữ nguyên, không ghi đè khi đổi danh mục/thương hiệu
        if (form.sku && form.sku !== autoSku.value) {
            return
        }

        // Chưa chọn gì thì để trống, hệ thống tự cấp SKU khi lưu
        if (!categoryId && !brandId) {
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

            if (requestId !== skuPreviewRequestId) return

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
const createAttribute = (rawName = dropdown.value.keyword, variantIndex = dropdown.value.variantIndex) => {
    const name = rawName.trim()
    if (!name) return

    const v = form.variants[variantIndex]
    if (!v) return

    const exists = v.attributes.some(a => normalize(a.name) === normalize(name))
    if (exists) return toast.error('Thuộc tính đã tồn tại')

    v.attributes.push({
        id: Date.now(),
        name,
        value: [],
    })

    delete pendingAttributeDrafts[variantDraftKey(variantIndex)]
    dropdown.value.keyword = ''
    activeIndex.value = -1
    dropdown.value.type = null
}

// Dropdown thêm thuộc tính
const openAttrDropdown = async (variantIndex) => {
    addingAttributeFor[variantDraftKey(variantIndex)] = true
    dropdown.value.type = 'attr'
    dropdown.value.variantIndex = variantIndex
    dropdown.value.attrIndex = null
    dropdown.value.keyword = pendingAttributeDrafts[variantDraftKey(variantIndex)] || ''
    activeIndex.value = -1

    await nextTick()

    // 👇 focus input
    const input = document.querySelector(`#attr-input-${variantIndex}`)
    input?.focus()
}

// Dropdown thêm giá trị
const openValueDropdown = async (variantIndex, attrIndex) => {
    const attr = form.variants[variantIndex]?.attributes[attrIndex]
    dropdown.value.type = 'value'
    dropdown.value.variantIndex = variantIndex
    dropdown.value.attrIndex = attrIndex
    dropdown.value.keyword = attr ? (pendingValueDrafts[valueDraftKey(variantIndex, attr)] || '') : ''
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
            if (attr) addAttrValue(attr, e.target.value, variantIndex, attrIndex)
        } else if (dropdown.value.type === 'attr') {
            createAttribute(e.target.value, variantIndex)
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

        const all = categories.value
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
        delete pendingValueDrafts[valueDraftKey(dropdown.value.variantIndex, attr)]
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

        delete pendingAttributeDrafts[variantDraftKey(dropdown.value.variantIndex)]
        addingAttributeFor[variantDraftKey(dropdown.value.variantIndex)] = true
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
    const variantIndex = form.variants.indexOf(variant)
    const attr = variant.attributes[attrIndex]
    if (attr && variantIndex !== -1) {
        delete pendingValueDrafts[valueDraftKey(variantIndex, attr)]
    }
    variant.attributes.splice(attrIndex, 1)
}


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/
const emit = defineEmits(['close', 'updated'])

const commitPendingDrafts = () => {
    form.variants.forEach((variant, variantIndex) => {
        variant.attributes.forEach(attr => {
            const key = valueDraftKey(variantIndex, attr)
            const value = String(pendingValueDrafts[key] || '').trim()

            if (value && !attr.value.some(item => normalize(item) === normalize(value))) {
                attr.value.push(value)
            }

            delete pendingValueDrafts[key]
        })

        const nameKey = variantDraftKey(variantIndex)
        const pendingName = String(pendingAttributeDrafts[nameKey] || '').trim()

        if (pendingName && !variant.attributes.some(attr => normalize(attr.name) === normalize(pendingName))) {
            variant.attributes.push({
                id: Date.now() + variantIndex,
                name: pendingName,
                value: [],
            })
        }

        delete pendingAttributeDrafts[nameKey]
    })
}

const submit = () => {
    commitPendingDrafts()

    const normalizePayload = (data) => {
        const payload = { ...data }

        // Giá trong sản phẩm được lưu theo đơn vị cơ sở. Nếu người dùng nhập
        // theo đơn vị quy đổi thì chuẩn hóa trước khi gửi lên máy chủ.
        if (payload.has_unit_conversion && priceUnitBasis.value === 'conversion') {
            const factor = Math.max(1, Number(payload.conversion_factor || 1))
            payload.cost_price = Number(payload.cost_price || 0) / factor
            payload.sell_price = Number(payload.sell_price || 0) / factor
            payload.variants = (payload.variants || []).map(variant => ({
                ...variant,
                cost_price: Number(variant.cost_price || 0) / factor,
                sell_price: Number(variant.sell_price || 0) / factor,
                overrides: Object.fromEntries(Object.entries(variant.overrides || {}).map(([key, override]) => [
                    key,
                    {
                        ...override,
                        cost_price: Number(override.cost_price || 0) / factor,
                        sell_price: Number(override.sell_price || 0) / factor,
                    },
                ])),
            }))
        }

        if (payload.manage_stock_by_serial || !payload.has_unit_conversion) {
            payload.has_unit_conversion = false
            payload.conversion_unit_id = null
            payload.conversion_factor = null
        }

        // SKU khác bản gợi ý tự sinh = người dùng tự nhập; ngược lại server tự cấp số mới khi lưu (tránh trùng).
        payload.sku_manual = Boolean(payload.sku) && payload.sku !== autoSku.value

        const warrantyValue = Math.max(0, Number(payload.warranty_value || 0))
        payload.warranty_days = Math.min(3650, Math.round(
            warrantyValue * (payload.warranty_unit === 'month' ? 30 : 1)
        ))
        delete payload.warranty_value
        delete payload.warranty_unit

        return payload
    }

    const options = {
        onError: () => {
            nextTick(() => {
                document.querySelector('p.text-red-500')?.scrollIntoView({ block: 'center', behavior: 'smooth' })
            })
        },
        onSuccess: (page) => {
            const createdProductId = page?.props?.flash?.createdProductId ?? null
            form.reset()
            form.clearErrors()
            clearVariantDrafts()
            emit('updated', createdProductId)
            closeModal()
        }
    }

    if (form.id) {
        form
            .transform(data => {
                const payload = {
                    ...normalizePayload(data),
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
        form
            .transform(normalizePayload)
            .post(route('products.store'), options)
    }
}



</script>

<template>
    <BaseModal
        :title="title"
        :size="size"
        modal-class="!rounded-3xl !bg-slate-50"
        header-class="!bg-white !px-6 !py-4"
        body-class="!bg-slate-50 p-3 sm:p-4"
        @close="closeModal()"
    >
        <!-- HEADER -->
        <template #header>
            <div class="min-w-0">
                <h2 class="truncate text-xl font-bold tracking-tight text-slate-800">{{ title }}</h2>
            </div>
            <button
                type="button"
                class="ml-3 rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                aria-label="Đóng"
                @click="closeModal()"
            >
                ✕
            </button>
        </template>

        <div class="space-y-3">

            <!-- ========================= -->
            <!-- 1. THÔNG TIN CƠ BẢN -->
            <!-- ========================= -->
            <section class="space-y-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
                    <div class="h-4 w-2 rounded-full bg-blue-600"></div>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-800">1. Thông tin cơ bản</h3>
                </div>

                <!-- ẢNH + TÊN | DANH MỤC | THƯƠNG HIỆU -->
                <div class="flex items-start gap-3 sm:gap-4">
                    <label
                        class="group relative flex h-32 w-32 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 transition hover:border-blue-500"
                    >
                        <img
                            v-if="preview"
                            :src="preview"
                            class="h-full w-full object-cover"
                            @error="preview = null"
                        />

                        <span
                            v-else
                            class="flex flex-col items-center gap-1 px-1 text-center text-[11px] font-semibold text-slate-400 group-hover:text-blue-600"
                        >
                            <ImagePlus :size="22" />
                            Thêm ảnh
                        </span>

                        <span
                            v-if="preview"
                            class="absolute inset-x-0 bottom-0 bg-slate-900/55 py-0.5 text-center text-[10px] font-semibold text-white opacity-0 transition group-hover:opacity-100"
                        >
                            Đổi ảnh
                        </span>

                        <input
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="handleImage"
                        />
                    </label>

                    <div class="min-w-0 flex-1 space-y-3">
                        <FloatingInput
                            v-model="form.name"
                            label="Tên hàng hóa"
                            input-class="!rounded-xl"
                            :error="form.errors.name"
                        />

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <FloatingSelect
                                v-model="form.category_id"
                                :options="categories"
                                label="Danh mục"
                                name="product-category"
                                option-label="name"
                                option-value="id"
                                placeholder-text="Chọn danh mục"
                                create-label="Thêm danh mục"
                                create-in-list
                                input-class="!rounded-xl"
                                :error="form.errors.category_id"
                                @create="openReferenceCreate('category')"
                            />

                            <FloatingSelect
                                v-model="form.brand_id"
                                :options="filteredBrands"
                                label="Thương hiệu"
                                name="product-brand"
                                option-label="name"
                                option-value="id"
                                :placeholder-text="form.category_id ? 'Chọn thương hiệu' : 'Chọn danh mục trước'"
                                create-label="Thêm thương hiệu"
                                create-in-list
                                :disabled="!form.category_id"
                                input-class="!rounded-xl"
                                :error="form.errors.brand_id"
                                @create="openReferenceCreate('brand')"
                            />
                        </div>
                    </div>
                </div>

                <!-- Đơn vị bán, SKU, Mã vạch -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <FloatingSelect
                        v-model="form.unit_id"
                        :options="unitOptions"
                        label="Đơn vị bán và tồn kho"
                        name="product-unit"
                        option-label="name"
                        option-value="id"
                        placeholder-text="Chọn đơn vị"
                        create-label="Thêm đơn vị"
                        create-in-list
                        input-class="!rounded-xl"
                        :error="form.errors.unit_id"
                        @create="openReferenceCreate('unit')"
                    />

                    <FloatingInput
                        v-model="form.sku"
                        label="SKU"
                        input-class="!rounded-xl"
                        :error="form.errors.sku"
                    />

                    <div class="relative">
                        <FloatingInput
                            v-model="form.model"
                            label="Model (nếu có)"
                            input-class="!rounded-xl !pr-12"
                            :error="form.errors.model"
                        />
                        <BarcodeCameraButton class="absolute right-1 top-5 z-10 -translate-y-1/2" @scanned="(code) => { form.model = code }" />
                    </div>
                </div>

                <p class="text-[11px] italic text-slate-400">
                    SKU tự sinh theo danh mục + thương hiệu (vd. DT-SAM-0001). Để nguyên hoặc để trống thì hệ thống tự cấp số kế tiếp khi lưu; bạn cũng có thể tự nhập SKU riêng.
                </p>
            </section>

            <!-- ========================= -->
            <!-- 2. THUỘC TÍNH ĐẶC THÙ & BẢO HÀNH -->
            <!-- ========================= -->
            <section class="space-y-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
                    <div class="h-4 w-2 rounded-full bg-indigo-600"></div>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-800">2. Thuộc tính đặc thù &amp; Bảo hành</h3>
                </div>

                <!-- 2 lựa chọn xếp dọc bên trái | khối Bảo hành bên phải (chật thì khối Bảo hành xuống dưới) -->
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex shrink-0 flex-col gap-3 pt-1">
                        <label :class="['group flex items-center gap-2', form.has_unit_conversion ? 'cursor-not-allowed text-slate-400' : 'cursor-pointer text-slate-700']">
                            <input
                                v-model="form.manage_stock_by_serial"
                                type="checkbox"
                                :disabled="form.has_unit_conversion"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-xs font-semibold">Có Serial / IMEI</span>
                        </label>

                        <label :class="['group flex items-center gap-2', form.manage_stock_by_serial ? 'cursor-not-allowed text-slate-400' : 'cursor-pointer text-slate-700']">
                            <input
                                v-model="form.has_unit_conversion"
                                type="checkbox"
                                :disabled="form.manage_stock_by_serial"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-xs font-semibold">Nhập theo cuộn / thùng</span>
                        </label>
                    </div>

                    <!-- Bảo hành -->
                    <div class="min-w-[17rem] flex-1 space-y-2 rounded-xl border border-slate-200 bg-white p-3">
                        <span class="block text-xs font-bold text-slate-700">Bảo hành cho khách</span>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="grid w-full max-w-[15rem] grid-cols-2 gap-2">
                                <FloatingInput
                                    v-model="form.warranty_value"
                                    type="number"
                                    min="0"
                                    :max="form.warranty_unit === 'month' ? 121 : 3650"
                                    step="1"
                                    label="Thời hạn"
                                    input-class="!h-10 !rounded-xl !py-0 !text-sm"
                                />
                                <FloatingSelect
                                    v-model="form.warranty_unit"
                                    :options="warrantyUnitOptions"
                                    label="Đơn vị"
                                    name="product-warranty-unit"
                                    :show-placeholder="false"
                                    input-class="!h-10 !rounded-xl !py-0 !text-sm"
                                />
                            </div>
                            <span v-if="Number(form.warranty_value || 0) > 0" class="text-xs text-slate-500">
                                Tương đương {{ Math.min(3650, Number(form.warranty_value) * (form.warranty_unit === 'month' ? 30 : 1)) }} ngày
                            </span>
                            <span v-else class="text-xs italic text-slate-400">(Để trống nếu không bảo hành)</span>
                        </div>
                        <span v-if="form.errors.warranty_days" class="block text-[11px] text-red-600">{{ form.errors.warranty_days }}</span>
                    </div>
                </div>

                <!-- Quy đổi đơn vị -->
                <div
                    v-if="form.has_unit_conversion"
                    class="grid items-end gap-3 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-[minmax(180px,1fr)_minmax(110px,0.55fr)_auto]"
                    :class="{ 'opacity-50': form.manage_stock_by_serial }"
                >
                    <FloatingSelect
                        v-model="form.conversion_unit_id"
                        :options="conversionUnitOptions"
                        label="Đơn vị nhập"
                        name="product-conversion-unit"
                        option-label="name"
                        option-value="id"
                        placeholder-text="Chọn đơn vị nhập"
                        create-label="Thêm đơn vị"
                        create-in-list
                        input-class="!rounded-xl"
                        :disabled="form.manage_stock_by_serial"
                        :error="form.errors.conversion_unit_id"
                        @create="openReferenceCreate('unit', '', 'conversion')"
                    />

                    <FloatingInput
                        v-model.number="form.conversion_factor"
                        type="number"
                        min="2"
                        label="Số lượng"
                        input-class="!rounded-xl"
                        :error="form.errors.conversion_factor"
                        :disabled="form.manage_stock_by_serial"
                    />

                    <div class="pb-2 text-sm font-bold text-slate-700">
                        1 {{ selectedImportUnitName }} = {{ form.conversion_factor || 0 }} {{ selectedUnitName }}
                    </div>
                </div>

                <p v-if="form.manage_stock_by_serial" class="rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px] text-amber-700">
                    Lưu ý: Sản phẩm IMEI sẽ yêu cầu quét mã IMEI riêng khi nhập kho và tạo hóa đơn bán lẻ.
                </p>
            </section>

            <!-- ========================= -->
            <!-- 3. GIÁ CHUNG -->
            <!-- ========================= -->
            <section class="space-y-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-2 rounded-full bg-emerald-500"></div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-800">3. Giá chung</h3>
                    </div>
                    <span v-if="form.variants.length" class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                        Phiên bản mới tự lấy giá chung, chỉnh giá riêng ở bảng mục 4 bên dưới
                    </span>
                </div>

                <div v-if="form.has_unit_conversion" class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <span class="font-semibold text-slate-700">Nhập giá theo:</span>
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input :checked="priceUnitBasis === 'base'" type="radio" value="base" name="product-price-unit-basis" class="text-blue-600 focus:ring-blue-500" @change="changePriceUnitBasis('base')" />
                        <span>Đơn vị cơ sở ({{ selectedUnitName }})</span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input :checked="priceUnitBasis === 'conversion'" type="radio" value="conversion" name="product-price-unit-basis" class="text-blue-600 focus:ring-blue-500" @change="changePriceUnitBasis('conversion')" />
                        <span>Đơn vị quy đổi ({{ selectedImportUnitName }})</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <FloatingInput
                        v-model="form.cost_price"
                        :label="`Giá vốn / ${priceUnitBasis === 'conversion' ? selectedImportUnitName : selectedUnitName}`"
                        type="number"
                        format-thousands
                        input-class="!rounded-xl"
                        :error="form.errors.cost_price"
                    />

                    <FloatingInput
                        v-model="form.sell_price"
                        :label="`Giá bán / ${priceUnitBasis === 'conversion' ? selectedImportUnitName : selectedUnitName}`"
                        type="number"
                        format-thousands
                        input-class="!rounded-xl"
                        :error="form.errors.sell_price"
                    />
                </div>
            </section>

            <!-- ========================= -->
            <!-- 4. THUỘC TÍNH & BIẾN THỂ -->
            <!-- ========================= -->
            <section class="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-2 rounded-full bg-purple-600"></div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-800">4. Quản lý thuộc tính &amp; Biến thể</h3>
                    </div>

                    <!-- Toggle (vẫn là checkbox v-model="showVariants") -->
                    <label class="relative flex cursor-pointer items-center gap-3">
                        <span class="text-xs font-semibold text-slate-700">Có thuộc tính (nhiều biến thể)</span>
                        <input v-model="showVariants" type="checkbox" class="peer sr-only" />
                        <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors duration-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform after:duration-200 after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500/40"></span>
                    </label>
                </div>

                <p v-if="!form.variants.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500">
                    Bật công tắc "Có thuộc tính" để cấu hình thuộc tính (màu sắc, dung lượng...) và tạo danh sách biến thể.
                </p>

                <!-- DANH SÁCH THUỘC TÍNH -->
                <div v-if="form.variants.length" class="space-y-5">
                    <div class="space-y-3">
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700" @click="openAttrDropdown(0)"><Plus :size="14" />Thêm nhóm thuộc tính</button>
                        </div>

                        <div v-for="(v, i) in form.variants" :key="v.id || i" class="space-y-3">
                            <div v-for="(attr, i2) in v.attributes" :key="attr.id || attr.name" class="relative space-y-2 rounded-xl border border-slate-200 bg-slate-50/50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-bold text-slate-800">{{ attr.name }}</p>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="Xóa nhóm" aria-label="Xóa nhóm" @click="removeAttribute(v, i2)"><Trash2 :size="16" /></button>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <TagBadge v-for="(val, idx) in attr.value" :key="idx" :label="val" removable size="lg" color="blue" @remove="attr.value.splice(idx, 1)" />
                                    <div class="relative" :ref="el => setDropdownRef(el, 'value', i, i2)">
                                        <input
                                            :value="pendingValueDrafts[valueDraftKey(i, attr)] || ''"
                                            class="h-8 w-36 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            placeholder="Thêm giá trị..."
                                            @focus="openValueDropdown(i, i2)"
                                            @input="e => { pendingValueDrafts[valueDraftKey(i, attr)] = e.target.value; if (dropdown.type === 'value' && dropdown.variantIndex === i && dropdown.attrIndex === i2) dropdown.keyword = e.target.value }"
                                            @keydown="e => { if (e.key === ',') { e.preventDefault(); addAttrValue(attr, e.target.value, i, i2); return }; handleKeyDown(e, attr, i, i2) }"
                                            @blur="closeDropdown('value', i, i2)"
                                        />
                                        <div v-if="dropdown.type === 'value' && dropdown.variantIndex === i && dropdown.attrIndex === i2 && suggestions.length" class="absolute z-20 mt-1 max-h-40 w-56 overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                            <div v-for="(item, idx) in suggestions" :key="item + '-' + idx" class="suggestion-item cursor-pointer px-3 py-2 text-sm hover:bg-slate-50" @mousedown.prevent="selectSuggestion(item)">{{ item }}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-200 text-slate-700 hover:bg-slate-300" aria-label="Thêm giá trị" @click="addAttrValue(attr, pendingValueDrafts[valueDraftKey(i, attr)] || '', i, i2)"><Plus :size="16" /></button>
                                </div>
                            </div>

                            <div v-if="addingAttributeFor[variantDraftKey(i)]" class="relative" :ref="el => setDropdownRef(el, 'attr', i)">
                                <input :id="'attr-input-' + i" :value="pendingAttributeDrafts[variantDraftKey(i)] || ''" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Nhập hoặc chọn nhóm thuộc tính..." @focus="openAttrDropdown(i)" @input="e => { pendingAttributeDrafts[variantDraftKey(i)] = e.target.value; dropdown.type = 'attr'; dropdown.variantIndex = i; dropdown.keyword = e.target.value }" @keydown="e => handleKeyDown(e, null, i)" @blur="e => handleAttributeInputBlur(i, e)" />
                                <div v-if="dropdown.type === 'attr' && dropdown.variantIndex === i && suggestions.length" class="absolute z-10 mt-1 max-h-40 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                    <div v-for="(item, idx) in suggestions" :key="idx" :class="['cursor-pointer px-3 py-2 text-sm', idx === activeIndex ? 'bg-blue-100' : 'hover:bg-slate-50']" @mousedown.prevent="selectSuggestion(item)">{{ item }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MA TRẬN BIẾN THỂ -->
                    <div class="space-y-2 pt-1">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Danh sách phiên bản biến thể</span>
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-700">{{ variantPreviewRows.length }} mẫu</span>
                            </div>
                        </div>

                        <div v-if="variantPreviewRows.length" class="overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                            <div class="max-h-72 overflow-auto">
                                <table class="w-full min-w-[760px] text-left text-xs">
                                    <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 font-bold text-slate-700">
                                        <tr>
                                            <th class="p-3">Biến thể</th>
                                            <th class="p-3">SKU riêng</th>
                                            <th class="w-32 p-3">Giá vốn (đ)</th>
                                            <th class="w-32 p-3">Giá bán (đ)</th>
                                            <th class="w-20 p-3 text-right">Tồn kho</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="row in variantPreviewRows" :key="row.key" class="transition hover:bg-slate-50">
                                            <td class="p-3 font-bold text-slate-800"><span class="mr-2 inline-block h-2 w-2 rounded-full bg-blue-500"></span>{{ row.label }}</td>
                                            <td class="p-3"><input v-model="variantOverrideFor(row).sku" class="h-8 w-full min-w-[10rem] rounded border border-slate-200 bg-slate-50 px-2 font-mono text-xs focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Tự tạo khi lưu" /></td>
                                            <td class="p-3"><FloatingInput v-model.number="variantOverrideFor(row).cost_price" label="Giá vốn" :floating-label="false" format-thousands input-class="!h-8 !rounded !px-2 !py-0 !text-right !text-xs" /></td>
                                            <td class="p-3"><FloatingInput v-model.number="variantOverrideFor(row).sell_price" label="Giá bán" :floating-label="false" format-thousands input-class="!h-8 !rounded !px-2 !py-0 !text-right !text-xs !font-semibold !text-emerald-600" /></td>
                                            <td class="p-3 text-right font-semibold text-slate-600">{{ variantOverrideFor(row).stock || 0 }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p v-else class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-4 text-center text-xs text-slate-500">Thêm ít nhất một giá trị cho mỗi nhóm thuộc tính để tạo phiên bản.</p>
                    </div>
                </div>
            </section>

        </div>

        <!-- FOOTER -->
        <template #footer>
            <div class="-mx-4 -my-3 flex items-center justify-between gap-3 bg-white px-6 py-4 shadow-lg">
                <div class="text-xs text-slate-500">
                    <span v-if="showVariants && variantPreviewRows.length">Tổng số biến thể: <strong class="font-bold text-blue-600">{{ variantPreviewRows.length }}</strong> mẫu</span>
                    
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-5 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none"
                        @click="closeModal()"
                    >
                        Hủy
                    </button>

                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-6 py-2.5 text-xs font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:bg-blue-700 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
                    </button>
                </div>
            </div>
        </template>

    </BaseModal>

    <ReferenceCreateModal
        v-if="referenceModal.type"
        :type="referenceModal.type"
        :initial-name="referenceModal.initialName"
        :category-id="form.category_id"
        :category-name="selectedCategoryName"
        @close="closeReferenceCreate"
        @created="handleReferenceCreated"
    />

</template>


<style scoped>
.btn-green {
    @apply px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700;
}
.btn-gray {
    @apply px-4 py-2 bg-gray-200 rounded;
}

</style>
