<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
    products: Object
})

/*
|------------------------------------------------------------------
| SELECT
|------------------------------------------------------------------
*/
const selectedIds = ref([])

const allSelected = computed(() => {
    return props.products.data.length > 0 &&
        props.products.data.every(p => selectedIds.value.includes(p.id))
})

const toggleAll = (e) => {
    if (e.target.checked) {
        selectedIds.value = props.products.data.map(p => p.id)
    } else {
        selectedIds.value = []
    }
}

const toggleOne = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(i => i !== id)
    } else {
        selectedIds.value.push(id)
    }
}

/*
|------------------------------------------------------------------
| ACTIONS
|------------------------------------------------------------------
*/

// restore 1
const restore = (id) => {
    router.post(route('products.restore', id))
}

// force delete 1
const forceDelete = (id) => {
    if (!confirm('Xóa vĩnh viễn?')) return
    router.delete(route('products.forceDelete', id))
}

// bulk restore
const bulkRestore = () => {
    if (!selectedIds.value.length) return

    router.post(route('products.bulkRestore'), {
        ids: selectedIds.value
    }, {
        onSuccess: () => selectedIds.value = []
    })
}

// bulk force delete
const bulkForceDelete = () => {
    if (!selectedIds.value.length) return

    if (!confirm('Xóa vĩnh viễn các sản phẩm đã chọn?')) return

    router.post(route('products.bulkForceDelete'), {
        ids: selectedIds.value
    }, {
        onSuccess: () => selectedIds.value = []
    })
}

</script>

<template>
<div class="space-y-4">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Thùng rác</h1>
            <p class="text-sm text-gray-500">Sản phẩm đã xóa</p>
        </div>

        <Link :href="route('products.index')" class="btn-green">
            ← Quay lại
        </Link>
    </div>

    <!-- BULK -->
    <div v-if="selectedIds.length" class="bg-blue-50 p-3 flex justify-between items-center">
        <div>Đã chọn {{ selectedIds.length }} sản phẩm</div>

        <div class="flex gap-2">
            <button @click="bulkRestore" class="btn-green">
                Khôi phục
            </button>

            <button @click="bulkForceDelete" class="btn-red">
                Xóa vĩnh viễn
            </button>
        </div>
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded shadow overflow-hidden">
        <table class="w-full text-sm">

            <thead class="bg-gray-100">
            <tr>
                <th class="p-2">
                    <input type="checkbox"
                        :checked="allSelected"
                        @change="toggleAll">
                </th>
                <th class="p-2">ID</th>
                <th class="p-2">Tên</th>
                <th class="p-2">Danh mục</th>
                <th class="p-2">Thương hiệu</th>
                <th></th>
            </tr>
            </thead>

            <tbody>
            <tr v-for="p in products.data"
                :key="p.id"
                class="border-t hover:bg-gray-50">

                <td class="p-2">
                    <input type="checkbox"
                        :checked="selectedIds.includes(p.id)"
                        @change="toggleOne(p.id)">
                </td>

                <td>{{ p.id }}</td>
                <td>{{ p.name }}</td>
                <td>{{ p.category?.name }}</td>
                <td>{{ p.brand?.name }}</td>

                <td>
                    <div class="flex gap-2">
                        <button @click="restore(p.id)" class="btn-green">
                            Restore
                        </button>

                        <button @click="forceDelete(p.id)" class="btn-red">
                            Xóa hẳn
                        </button>
                    </div>
                </td>
            </tr>

            <tr v-if="!products.data.length">
                <td colspan="6" class="text-center p-4 text-gray-500">
                    Thùng rác trống
                </td>
            </tr>
            </tbody>

        </table>
    </div>

</div>
</template>

<style scoped>
.btn-green {
    @apply px-3 py-1 bg-green-600 text-white rounded;
}

.btn-red {
    @apply px-3 py-1 bg-red-600 text-white rounded;
}
</style>