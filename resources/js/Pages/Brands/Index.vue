<script setup>
import { router, useForm, usePage   } from '@inertiajs/vue3'
import { ref, watch, nextTick  } from 'vue'
import Swal from 'sweetalert2'
import { toast } from 'vue-sonner'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Modal from '@/Components/Modal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'


defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    brands: Object,
    filters: Object,
    categories: Array
})

const page = usePage()

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            toast.success(msg)
        }
    }
)

/* ================= FILTER ================= */
const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id ?? null)

let timeout = null

watch([search, category_id], ([s, c]) => {
    clearTimeout(timeout)

    timeout = setTimeout(() => {
        router.get('/brands', {
            search: s,
            category_id: c
        }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})

/* ================= DELETE ================= */
const destroy = (id) => {
    Swal.fire({
        title: 'Xác nhận xóa?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/brands/${id}`)
        }
    })
}

/* ================= MODAL ================= */
const showModal = ref(false)
const isEdit = ref(false)

const form = useForm({
    id: null,
    name: '',
    category_id: null
})

/* 👉 MỞ CREATE */
const openCreate = async () => {
    isEdit.value = false
    form.reset()
    form.clearErrors()
    form.id = null

    showModal.value = true

    await nextTick()
    document.querySelector('[name="name"]')?.focus()
}

/* 👉 MỞ EDIT */
const openEdit = (brand) => {
    isEdit.value = true

    form.id = brand.id
    form.name = brand.name
    form.category_id = brand.category_id

    form.clearErrors()
    showModal.value = true
}

const handleError = async (errors) => {

    const messages = Object.values(errors)

    // 👉 hiện lần lượt
    for (let i = 0; i < messages.length; i++) {
        toast.error(messages[i])

        // delay 1 chút cho dễ nhìn
        await new Promise(r => setTimeout(r, 500))
    }

    // 👉 focus lỗi đầu tiên
    await nextTick()

    const first = Object.keys(errors)[0]
    const el = document.querySelector(`[name="${first}"]`)

    if (el) {
        el.focus()
        el.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        })
    }
}


const showTrash = ref(false)
const trashData = ref([])
const loadingTrash = ref(false)

// Mở thùng rác
const openTrash = async () => {
    showTrash.value = true
    loadingTrash.value = true

    try {
        const res = await fetch('/brands/trash', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        trashData.value = await res.json()
    } catch (e) {
        toast.error('Không tải được thùng rác')
    } finally {
        loadingTrash.value = false
    }
}

// Khôi phục thương hiệu
const restore = (id) => {
    router.post(`/brands/${id}/restore`, {}, {
        onSuccess: () => {
            trashData.value = trashData.value.filter(i => i.id !== id)
        }
    })
}

// Xóa vĩnh viễn thương hiệu
const forceDelete = (id) => {
    
    Swal.fire({
        title: 'Xóa vĩnh viễn?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa'
    }).then((r) => {
        if (r.isConfirmed) {
            router.delete(`/brands/${id}/force`, {
                preserveScroll: true,
                onSuccess: () => {
                    openTrash() // reload lại danh sách thùng rác
                },
                onError: (errors) => {
                     Object.values(errors).forEach(msg => {
                        if (msg) toast.error(msg)
                    })
                }
            })
        }
    })
}

/* 👉 SUBMIT */
const submit = () => {
    if (isEdit.value) {
        form.put(`/brands/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
            }
        })
    } else {
        form.post('/brands', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
            }
        })
    }
}


</script>

<template>
<div>

    <!-- HEADER -->
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Thương hiệu</h1>
            <p class="text-gray-500">Quản lý thương hiệu</p>
        </div>

        <div class="flex gap-2">
            <button
                @click="openTrash"
                class="px-4 py-2 bg-gray-600 text-white rounded"
            >
                Thùng rác
            </button>

            <button
                @click="openCreate"
                class="px-4 py-2 bg-black text-white rounded"
            >
                Thêm mới
            </button>
        </div>
    </div>

    <!-- FILTER -->
    <div class="flex gap-3 mb-5">

        <FloatingInput
            name="search"
            v-model="search"
            label="Tìm kiếm..."
            class="w-64"
        />

        <FloatingSelect
            name="category_id"
            v-model="category_id"
            label="Danh mục"
            :options="categories.map(c => ({ value: c.id, label: c.name }))"
            class="w-64"
        />

        

    </div>

    <!-- TABLE -->
    <table class="w-full bg-white border">

        <thead>
            <tr class="bg-gray-100">
                <th class="border p-2">ID</th>
                <th class="border p-2">Tên</th>
                <th class="border p-2">Danh mục</th>
                <th class="border p-2">Trạng thái</th>
                <th class="border p-2">Hành động</th>
            </tr>
        </thead>

        <tbody>

            <tr v-for="brand in brands.data" :key="brand.id">

                <td class="border p-2">{{ brand.id }}</td>

                <td class="border p-2">{{ brand.name }}</td>

                <td class="border p-2">
                    {{ brand.category?.name || '---' }}
                </td>

                <td class="border p-2">
                    <span v-if="brand.is_active" class="text-green-600">
                        Hoạt động
                    </span>
                    <span v-else class="text-yellow-600">
                        Tạm khóa
                    </span>
                </td>

                <td class="border p-2">
                    <div class="flex gap-2">

                        <button
                            @click="openEdit(brand)"
                            class="px-3 py-1 bg-blue-500 text-white rounded"
                        >
                            Sửa
                        </button>
                        <button
                            @click="destroy(brand.id)"
                            class="px-3 py-1 bg-red-500 text-white rounded">
                            Xóa
                        </button>

                    </div>
                </td>

            </tr>

            <tr v-if="brands.data.length === 0">
                <td colspan="5" class="text-center p-5 text-gray-500">
                    Không có dữ liệu
                </td>
            </tr>

        </tbody>

    </table>

    <!-- Thêm mới -->
   <Modal
        :show="showModal"
        :title="isEdit ? 'Cập nhật thương hiệu' : 'Thêm thương hiệu'"
        @close="showModal = false"
    >

        <!-- BODY -->
        <template #default>

            <FloatingInput
                name="name"
                v-model="form.name"
                label="Tên thương hiệu"
                class="mb-3"
                :disabled="form.processing"
            />
        

            <FloatingSelect
                name="category_id"
                v-model="form.category_id"
                label="Danh mục"
                :options="categories.map(c => ({ value: c.id, label: c.name }))"
                :disabled="form.processing"
                class="mb-3"
            />
            
        </template>

        <!-- FOOTER -->
        <template #footer>

            <button
                @click="showModal = false"
                class="px-3 py-1 bg-gray-400 text-white rounded"
            >
                Hủy
            </button>

            <button
                @click="submit"
                :disabled="form.processing"
                class="btn-blue"
            >
                <span v-if="form.processing">Đang xử lý...</span>
                <span v-else>{{ isEdit ? 'Cập nhật' : 'Lưu' }}</span>
            </button>

        </template>

    </Modal>

    <!-- THÙNG RÁC -->
    <Modal
        :show="showTrash"
        title="Thùng rác"
        maxWidth="2xl"
        @close="showTrash = false"
    >

        <template #default>

            <div v-if="loadingTrash" class="text-center p-5">
                Đang tải...
            </div>

            <table v-else class="w-full border">

                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-2">ID</th>
                        <th class="p-2">Tên</th>
                        <th class="p-2">Danh mục</th>
                        <th class="p-2">Hành động</th>
                    </tr>
                </thead>

                <tbody>

                    <tr v-for="b in trashData" :key="b.id">
                        <td class="p-2">{{ b.id }}</td>
                        <td class="p-2">{{ b.name }}</td>
                        <td class="p-2">
                            {{ b.category?.name || '---' }}
                        </td>

                        <td class="p-2 flex gap-2">

                            <button
                                @click="restore(b.id)"
                                class="px-2 py-1 bg-green-600 text-white rounded"
                            >
                                Khôi phục
                            </button>

                            <button
                                @click="forceDelete(b.id)"
                                class="px-2 py-1 bg-red-600 text-white rounded"
                            >
                                Xóa
                            </button>

                        </td>
                    </tr>

                    <tr v-if="trashData.length === 0">
                        <td colspan="4" class="text-center p-4 text-gray-500">
                            Thùng rác trống
                        </td>
                    </tr>

                </tbody>

            </table>

        </template>

    </Modal>

</div>



</template>