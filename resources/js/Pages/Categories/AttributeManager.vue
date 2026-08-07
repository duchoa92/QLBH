<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    category: Object
})

const newAttr = ref('')
const newValue = ref({})

const addAttribute = () => {
    router.post(route('attributes.store'), {
        category_id: props.category.id,
        name: newAttr.value
    }, {
        onSuccess: () => newAttr.value = ''
    })
}

const deleteAttr = (id) => {
    router.delete(route('attributes.destroy', id))
}

const addValue = (attr) => {
    router.post(route('attribute-values.store'), {
        attribute_id: attr.id,
        value: newValue.value[attr.id]
    }, {
        onSuccess: () => newValue.value[attr.id] = ''
    })
}
</script>

<template>
<div class="space-y-4">

    <h2 class="font-bold text-lg">Thuộc tính danh mục</h2>

    <!-- ADD ATTRIBUTE -->
    <div class="flex gap-2">
        <input v-model="newAttr" placeholder="Tên thuộc tính"
               class="border px-3 py-2 rounded w-full"/>
        <button @click="addAttribute" class="bg-green-600 text-white px-3 rounded">
            Thêm
        </button>
    </div>

    <!-- LIST -->
    <div v-for="attr in category.attributes" :key="attr.id" class="border p-3 rounded">

        <div class="flex justify-between">
            <strong>{{ attr.name }}</strong>

            <button @click="deleteAttr(attr.id)" class="text-red-500">
                Xóa
            </button>
        </div>

        <!-- VALUES -->
        <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="v in attr.values" :key="v.id"
                  class="px-2 py-1 bg-gray-200 rounded">
                {{ v.value }}
            </span>
        </div>

        <!-- ADD VALUE -->
        <div class="flex gap-2 mt-2">
            <input v-model="newValue[attr.id]"
                   placeholder="Giá trị"
                   class="border px-2 py-1 rounded w-full"/>
            <button @click="addValue(attr)" class="bg-blue-500 text-white px-2 rounded">
                +
            </button>
        </div>

    </div>

</div>
</template>