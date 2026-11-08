<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { ref, watch, onMounted, nextTick } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { openModal, registerUrlModal } from '@/Stores/modal'
import CategoryForm from './Form.vue'
import TrashModal from '@/Components/TrashModal.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { SquarePen, Trash2, FilePlus } from 'lucide-vue-next'
import BaseTable from '@/Components/UI/BaseTable.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import { useLiveList } from '@/Composables/useLiveList'
import CatalogTabs from '@/Components/CatalogTabs.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'


defineOptions({ layout: AdminLayout })

const props = defineProps({
    categories: Object,
    filters: Object
})
const page = usePage()

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },

    { key: 'name', label: 'Tên danh mục', sortable: true },

    { 
        key: 'is_active', 
        label: 'Trạng thái', 
        sortable: true,
        width: '120px',
        class: 'text-center'
    }
]



const confirmBox = useConfirm()

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

// Tìm kiếm/lọc/sắp xếp bằng AJAX, không tải lại trang; kết quả được bôi vàng.
const list = useLiveList({
    props,
    dataKey: 'categories',
    params: () => ({ search: search.value.trim(), status: status.value }),
})
const { data: categoriesData, filters: filtersData, loading } = list
watch(search, () => list.schedule())
watch(status, () => list.request())

/* ================= RELOAD ================= */
const loadData = () => {
    router.reload({ only: ['categories'] })
    loadTrashCount()
}

/* ================= MODAL ================= */
const openCreate = () => {
    openModal(CategoryForm, {
        props: {
            title: 'Thêm danh mục',
            size: 'sm'
        },
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(CategoryForm, {
        props: {
            category: item,
            title: 'Sửa danh mục',
            size: 'sm',
        },
        onUpdated: loadData
    })
}

const openEditFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const categoryId = Number(url.searchParams.get('open_category'))
    if (!categoryId) return
    url.searchParams.delete('open_category')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true, onSuccess: () => openModal(RecordDetailModal, { props: { type: 'category', recordId: categoryId }, deepLink: { type: 'category', id: categoryId } }) })
}

registerUrlModal('category', RecordDetailModal, id => ({ type: 'category', recordId: id }))

watch(() => page.url, () => nextTick(openEditFromGlobalSearch), { immediate: true })

/* ================= DELETE ================= */
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển vào thùng rác?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(`/categories/${id}`, {
                preserveScroll: true,
                onSuccess: loadData
            })
        }
    })
}

/* ================= TRASH ================= */
const trashCount = ref(0)

const loadTrashCount = async () => {
    try {
        const res = await fetch('/categories/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        const data = await res.json()
        trashCount.value = data.meta?.total || 0
    } catch {
        trashCount.value = 0
    }
}

const openTrash = () => {
    openModal(TrashModal, {
        props: { endpoint: 'categories' },
        onUpdated: loadData
    })
}

onMounted(loadTrashCount)

/* ================= STATUS ================= */
const loadingStatus = ref(null)

const toggleStatus = (id) => {
    if (loadingStatus.value) return

    loadingStatus.value = id

    // 👉 update UI trước
    const item = categoriesData.value?.data?.find(i => i.id === id)
    if (item) item.is_active = !item.is_active

    router.patch(`/categories/${id}/toggle-status`, {}, {
        preserveScroll: true,
        // hoàn tác nếu server báo lỗi
        onError: () => { if (item) item.is_active = !item.is_active },
        onFinish: () => loadingStatus.value = null
    })
}

const sort = ({ field, order }) => list.request({ sort_by: field, sort_order: order })
</script>

<template>
<div class="space-y-3">
    <PageHeader
        title="Danh mục & Thương hiệu"
    />

    <CatalogTabs>
        <ActionButton @click="openCreate"><FilePlus :size="16" />Thêm</ActionButton>
        <ActionButton variant="danger" @click="openTrash"><Trash2 :size="16" />Thùng rác ({{ trashCount }})</ActionButton>
    </CatalogTabs>

    <DataPanel detached>
        <template #header>
            <ListToolbar v-model="search" :loading="loading" placeholder="Tìm danh mục...">
                <template #filters><ListFilterSelect
                    v-model="status"
                    label="Trạng thái"
                    :show-placeholder="false"
                    :options="[
                        { value: '', label: 'Tất cả' },
                        { value: 1, label: 'Hoạt động' },
                        { value: 0, label: 'Ngừng' }
                    ]"
                /></template>
            </ListToolbar>
        </template>

        <BaseTable
            :data="categoriesData"
            :columns="columns"
            :filters="filtersData"
            row-clickable
            @sort="sort"
            @row-click="openEdit($event)"
        >
            <template #row="{ row }">

                <td class="px-3.5 py-2.5 text-center">{{ row.id }}</td>

                <td class="px-3.5 py-2.5 font-medium">
                    <HighlightText :text="row.name" :keyword="search" />
                </td>

                <td class="px-3.5 py-2.5 text-center">
                    <button
                        @click.stop="toggleStatus(row.id)"
                        :disabled="loadingStatus === row.id"
                        role="switch"
                        :aria-checked="!!row.is_active"
                        aria-label="Bật/tắt trạng thái"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :class="row.is_active ? 'bg-green-500' : 'bg-gray-300'"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                            :class="row.is_active ? 'translate-x-6' : 'translate-x-1'"
                        />
                    </button>
                </td>

                <td class="px-3.5 py-2.5 text-center" @click.stop>
                    <div class="app-row-actions">
                        <Tooltip text="Sửa">
                            <button type="button" @click="openEdit(row)" class="app-row-action app-row-action--edit">
                                <SquarePen class="h-4 w-4" />
                            </button>
                        </Tooltip>

                        <Tooltip text="Chuyển vào thùng rác" position="top">
                            <button type="button" @click="destroy(row.id)" class="app-row-action app-row-action--danger">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </Tooltip>

                    </div>
                </td>

            </template>

            <!-- Thẻ trên điện thoại: có công tắc trạng thái và nút xóa -->
            <template #mobile-actions="{ row }">
                <div class="flex items-center justify-between gap-3 pl-6" @click.stop>
                    <button
                        type="button"
                        @click="toggleStatus(row.id)"
                        :disabled="loadingStatus === row.id"
                        role="switch"
                        :aria-checked="!!row.is_active"
                        aria-label="Bật/tắt trạng thái"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :class="row.is_active ? 'bg-green-500' : 'bg-gray-300'"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                            :class="row.is_active ? 'translate-x-6' : 'translate-x-1'"
                        />
                    </button>
                    <button type="button" @click="destroy(row.id)" class="app-row-action app-row-action--danger" aria-label="Chuyển vào thùng rác">
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </BaseTable>
    </DataPanel>

</div>
</template>
