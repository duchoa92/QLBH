<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import BrandForm from './Form.vue'
import { FilePlus, SquarePen, Trash2 } from 'lucide-vue-next'
import TrashModal from '@/Components/TrashModal.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal, registerUrlModal } from '@/Stores/modal'
import BaseTable from '@/Components/UI/BaseTable.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import { useReferenceData } from '@/Stores/referenceData'
import CatalogTabs from '@/Components/CatalogTabs.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'



defineOptions({ layout: AdminLayout })

const props = defineProps({
    brands: Object,
    filters: Object,
    categories: Array
})
const page = usePage()

const { categories } = useReferenceData({
    categories: props.categories,
})

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },

    { key: 'name', label: 'Tên', sortable: true },

    { 
        key: 'category_name', 
        label: 'Danh mục', 
        sortable: true,
        width: '150px'
    },

    { 
        key: 'is_active', 
        label: 'Trạng thái', 
        sortable: true,
        width: '120px',
        class: 'text-center'
    }
]



const confirmBox = useConfirm()

/* FILTER */
const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id ?? '') // Đồng bộ với value trống '' của option Tất cả

let timeout = null
onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout)
})

// HÀM TRIGGER CHUNG ĐỂ GET DỮ LIỆU
const handleFilter = () => {
    router.get('/brands', {
        search: search.value,
        category_id: category_id.value,
        sort_by: props.filters?.sort_by,
        sort_order: props.filters?.sort_order,
        per_page: props.filters?.per_page
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['brands', 'filters'],
    })
}

// Chỉ debounce với ô tìm kiếm văn bản (Search)
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(handleFilter, 300)
})

// Lập tức trigger filter khi người dùng thay đổi select Category
watch(category_id, handleFilter)

/* RELOAD */
const loadData = () => {
    router.reload({ only: ['brands'] })
    loadTrashCount()
}

/* DELETE */
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển thương hiệu vào thùng rác?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(`/brands/${id}`, {
                preserveScroll: true,
                onSuccess: loadData
            })
        }
    })
}

const openCreate = () => {
    openModal(BrandForm, {
        props: {
            categories: categories.value,
            title: 'Thêm thương hiệu',
            size: 'sm',
        },
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(BrandForm, {
        props: {
            brand: item,
            categories: categories.value,
            title: 'Sửa thương hiệu',
            size: 'sm',
        },
        onUpdated: loadData
    })
}

const openEditFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const brandId = Number(url.searchParams.get('open_brand'))
    if (!brandId) return
    url.searchParams.delete('open_brand')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true, onSuccess: () => openModal(RecordDetailModal, { props: { type: 'brand', recordId: brandId }, deepLink: { type: 'brand', id: brandId } }) })
}

registerUrlModal('brand', RecordDetailModal, id => ({ type: 'brand', recordId: id }))

watch(() => page.url, () => nextTick(openEditFromGlobalSearch), { immediate: true })

/* TRASH COUNT */
const trashCount = ref(0)

const loadTrashCount = async () => {
    try {
        const res = await fetch('/brands/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        const data = await res.json()
        trashCount.value = data.meta?.total || 0
    } catch {
        trashCount.value = 0
    }
}

onMounted(loadTrashCount)

const openTrash = () => {
    openModal(TrashModal, {
        props: { endpoint: 'brands' },
        onUpdated: loadData
    })
}

/* STATUS */
const loadingStatus = ref(null)
const toggleStatus = (id) => {
    if (loadingStatus.value) return
    loadingStatus.value = id

    // update UI trước
    const item = props.brands.data.find(i => i.id === id)
    if (item) item.is_active = !item.is_active

    router.patch(`/brands/${id}/toggle-status`, {}, {
        preserveScroll: true,
        onFinish: () => {
            loadingStatus.value = null
        }
    })
}

// Sort

const sort = ({ field, order }) => {
    router.get('/brands', {
        search: search.value,
        category_id: category_id.value,
        sort_by: field,
        sort_order: order,
        per_page: props.filters?.per_page
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
        ,only: ['brands', 'filters']
    })
}

</script>

<template>
<div class="space-y-3">
    <PageHeader
        title="Danh mục & Thương hiệu"
    />

    <CatalogTabs />

    <DataPanel detached>
        <template #header>
            <ListToolbar v-model="search" placeholder="Tìm thương hiệu...">
                <template #filters><ListFilterSelect
                    v-model="category_id"
                    name="category_id"
                    label="Danh mục"
                    :show-placeholder="false"
                    :options="[
                        { value: '', label: 'Tất cả' },
                        ...categories.map(c => ({ value: c.id, label: c.name }))
                    ]"
                /></template>
                <template #actions><ActionButton @click="openCreate"><FilePlus :size="16" />Thêm</ActionButton><ActionButton variant="danger" @click="openTrash"><Trash2 :size="16" />Thùng rác ({{ trashCount }})</ActionButton></template>
            </ListToolbar>
        </template>

        <BaseTable
            :data="brands"
            :columns="columns"
            :filters="filters"
            row-clickable
            @sort="sort"
            @row-click="openEdit($event)"
        >
            <template #row="{ row }">

                <td class="px-3.5 py-2.5 text-center">{{ row.id }}</td>

                <td class="px-3.5 py-2.5 font-medium">
                    {{ row.name }}
                </td>

                <td class="px-3.5 py-2.5">
                    {{ row.category?.name || '---' }}
                </td>

                <td class="px-3.5 py-2.5 text-center">
                    <button
                        @click.stop="toggleStatus(row.id)"
                        :disabled="loadingStatus === row.id"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :class="row.is_active ? 'bg-green-500 ' : 'bg-gray-300'"
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
                            <button type="button" @click="openEdit(row)" title="Sửa" class="app-row-action app-row-action--edit">
                                <SquarePen class="h-4 w-4" />
                            </button>
                        </Tooltip>

                        <Tooltip text="Chuyển vào thùng rác" position="top">
                            <button type="button" @click="destroy(row.id)"  class="app-row-action app-row-action--danger">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </Tooltip>
                    </div>
                </td>

            </template>
        </BaseTable>
    </DataPanel>
</div>

</template>
