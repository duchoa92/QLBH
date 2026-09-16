<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
    category: Object
})

const newAttr = ref('')
const newValue = ref({})

const addAttribute = () => {
    const name = newAttr.value?.trim()
    if (!name) return

    router.post(route('attributes.store'), {
        category_id: props.category.id,
        name
    }, {
        onSuccess: () => newAttr.value = ''
    })
}

const deleteAttr = (id) => {
    router.delete(route('attributes.destroy', id))
}

const addValue = (attr) => {
    const value = newValue.value[attr.id]?.trim()
    if (!value) return

    router.post(route('attribute-values.store'), {
        attribute_id: attr.id,
        value
    }, {
        onSuccess: () => newValue.value[attr.id] = ''
    })
}
</script>

<template>
<div class="space-y-4">
    <div>
        <h2 class="text-sm font-bold text-slate-900">
            Thuộc tính danh mục
        </h2>
        <p class="mt-0.5 text-xs font-medium text-slate-500">
            Dùng để tạo biến thể sản phẩm như màu sắc, dung lượng hoặc kích thước.
        </p>
    </div>

    <div class="flex gap-2">
        <input
            v-model="newAttr"
            placeholder="Tên thuộc tính"
            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
            @keyup.enter="addAttribute"
        >
        <button
            type="button"
            class="inline-flex h-10 items-center gap-1 rounded-lg bg-emerald-600 px-3 text-sm font-bold text-white transition hover:bg-emerald-700"
            @click="addAttribute"
        >
            <Plus :size="16" />
            Thêm
        </button>
    </div>

    <div
        v-for="attr in category.attributes"
        :key="attr.id"
        class="rounded-lg border border-slate-200 bg-slate-50 p-3"
    >
        <div class="flex items-center justify-between gap-3">
            <strong class="text-sm text-slate-900">{{ attr.name }}</strong>

            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                title="Xóa thuộc tính"
                @click="deleteAttr(attr.id)"
            >
                <Trash2 :size="16" />
            </button>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <span
                v-for="v in attr.values"
                :key="v.id"
                class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700"
            >
                {{ v.value }}
            </span>
            <span
                v-if="!attr.values?.length"
                class="text-xs font-medium text-slate-400"
            >
                Chưa có giá trị
            </span>
        </div>

        <div class="mt-3 flex gap-2">
            <input
                v-model="newValue[attr.id]"
                placeholder="Thêm giá trị"
                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                @keyup.enter="addValue(attr)"
            >
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700"
                title="Thêm giá trị"
                @click="addValue(attr)"
            >
                <Plus :size="16" />
            </button>
        </div>
    </div>
</div>
</template>
