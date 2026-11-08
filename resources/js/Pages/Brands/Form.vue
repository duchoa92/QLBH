<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { useReferenceData } from '@/Stores/referenceData'

const props = defineProps({
    brand: Object,
    categories: Array,
    title: String,
    modalId: Number
})

const { categories } = useReferenceData({
    categories: props.categories,
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    id: props.brand?.id || null,
    name: props.brand?.name || '',
    // Dòng trong danh sách có `categories: [{ id, name }]`; nơi khác có thể chỉ truyền `category_ids`.
    category_ids: props.brand?.categories
        ? props.brand.categories.map(c => c.id)
        : [...(props.brand?.category_ids ?? [])],
})

// Gồm cả danh mục đang gắn với thương hiệu nhưng không còn nằm trong danh sách ô chọn (ví dụ danh mục đã ngừng).
const options = computed(() => {
    const list = [...(categories.value || [])]

    ;(props.brand?.categories ?? []).forEach(c => {
        if (!list.some(item => Number(item.id) === Number(c.id))) {
            list.push({ id: c.id, name: c.name })
        }
    })

    return list
})

const isSelected = (id) => form.category_ids.some(x => Number(x) === Number(id))

const toggle = (id) => {
    form.category_ids = isSelected(id)
        ? form.category_ids.filter(x => Number(x) !== Number(id))
        : [...form.category_ids, id]
}

const allSelected = computed(() =>
    options.value.length > 0 && options.value.every(c => isSelected(c.id))
)

const toggleAll = () => {
    form.category_ids = allSelected.value ? [] : options.value.map(c => c.id)
}

const categoryError = computed(() =>
    form.errors.category_ids
    || Object.entries(form.errors).find(([key]) => key.startsWith('category_ids.'))?.[1]
)

const submit = () => {
    if (form.id) {
        form.put(`/brands/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated')
                emit('close')
            }
        })
    } else {
        form.post('/brands', {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated')
                emit('close')
            }
        })
    }
}
</script>

<template>
<BaseModal
    :title= "props.title"
    size="md"
    @close="emit('close')"
>
    <!-- content -->
    <div class="space-y-4">
        <FloatingInput
            v-model="form.name"
            label="Tên thương hiệu"
            :error="form.errors.name"
        />

        <!-- Danh mục áp dụng (chọn nhiều) -->
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">
                    Danh mục áp dụng
                    <span class="font-normal text-slate-400">({{ form.category_ids.length }} đã chọn)</span>
                </span>
                <button
                    v-if="options.length"
                    type="button"
                    class="text-xs font-medium text-blue-600 hover:underline"
                    @click="toggleAll"
                >
                    {{ allSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
                </button>
            </div>

            <div
                class="flex max-h-52 flex-wrap gap-2 overflow-y-auto rounded-lg border p-2"
                :class="categoryError ? 'border-red-400' : 'border-gray-300'"
            >
                <button
                    v-for="c in options"
                    :key="c.id"
                    type="button"
                    role="checkbox"
                    :aria-checked="isSelected(c.id)"
                    class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition"
                    :class="isSelected(c.id)
                        ? 'border-emerald-600 bg-emerald-50 text-emerald-700'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                    @click="toggle(c.id)"
                >
                    <Check v-if="isSelected(c.id)" class="h-3.5 w-3.5" />
                    {{ c.name }}
                </button>

                <p v-if="!options.length" class="px-1 py-1 text-xs text-slate-400">Chưa có danh mục nào.</p>
            </div>

            <p v-if="categoryError" class="mt-1 text-sm text-red-500">{{ categoryError }}</p>
        </div>
    </div>

    <!-- footer -->
    <template #footer>
        <div class="flex justify-end gap-2">
            <button @click="emit('close')" class="px-4 py-2 bg-gray-200 rounded">
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
