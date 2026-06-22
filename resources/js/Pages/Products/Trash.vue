<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { Trash2, RotateCcw } from 'lucide-vue-next'

const props = defineProps({
    products: Object
})

const selected = ref([])

// chọn tất cả
const toggleAll = (e) => {
    if (e.target.checked) {
        selected.value = props.products.data.map(p => p.id)
    } else {
        selected.value = []
    }
}

// chọn từng dòng
const toggleOne = (id) => {
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter(i => i !== id)
    } else {
        selected.value.push(id)
    }
}

// khôi phục 1
const restore = (id) => {
    router.post(route('products.restore', id))
}

// xóa vĩnh viễn 1
const forceDelete = (id) => {
    if (!confirm('Xóa vĩnh viễn? Không thể khôi phục!')) return

    router.delete(route('products.forceDelete', id))
}

// bulk restore
const bulkRestore = () => {
    router.post(route('products.bulkRestore'), {
        ids: selected.value
    }, {
        onSuccess: () => selected.value = []
    })
}

// bulk delete
const bulkDelete = () => {
    if (!confirm('Xóa vĩnh viễn tất cả?')) return

    router.post(route('products.bulkForceDelete'), {
        ids: selected.value
    }, {
        onSuccess: () => selected.value = []
    })
}
</script>

<template>
<div class="space-y-4">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Thùng rác</h1>

        <Link :href="route('products.index')" class="btn">
            ← Quay lại
        </Link>
    </div>

    <!-- BULK -->
    <div v-if="selected.length" class="bg-yellow-50 p-2 flex justify-between">
        <div>Đã chọn {{ selected.length }}</div>

        <div class="flex gap-2">
            <button @click="bulkRestore" class="btn-green">
                Khôi phục
            </button>

            <button @click="bulkDelete" class="btn-red">
                Xóa vĩnh viễn
            </button>
        </div>
    </div>

    <!-- TABLE -->
    <table class="w-full border text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="p-2">
                    <input type="checkbox" @change="toggleAll">
                </th>
                <th>ID</th>
                <th>Tên</th>
                <th>Giá</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <tr v-for="p in products.data" :key="p.id" class="border-t">
                <td class="p-2">
                    <input
                        type="checkbox"
                        :checked="selected.includes(p.id)"
                        @change="toggleOne(p.id)"
                    >
                </td>

                <td>{{ p.id }}</td>
                <td>{{ p.name }}</td>
                <td>{{ p.sell_price }}</td>

                <td>
                    <div class="flex gap-2">
                        <button @click="restore(p.id)">
                            <RotateCcw size="16"/>
                        </button>

                        <button @click="forceDelete(p.id)">
                            <Trash2 size="16"/>
                        </button>
                    </div>
                </td>
            </tr>

            <tr v-if="!products.data.length">
                <td colspan="5" class="text-center p-4">
                    Thùng rác trống
                </td>
            </tr>
        </tbody>
    </table>

</div>
</template>

<style scoped>
.btn {
    @apply px-3 py-2 bg-gray-200 rounded;
}
.btn-green {
    @apply px-3 py-2 bg-green-600 text-white rounded;
}
.btn-red {
    @apply px-3 py-2 bg-red-600 text-white rounded;
}
</style>