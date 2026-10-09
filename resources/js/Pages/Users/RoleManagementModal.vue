<script setup>
import { computed, onMounted, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Plus, Save, ShieldCheck, Trash2, CheckSquare, Square } from 'lucide-vue-next'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { useConfirm } from '@/Composables/useConfirm'

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    initialRoleId: { type: Number, default: null },
    startInCreate: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'updated'])
const confirmBox = useConfirm()
const selectedRoleId = ref(null)
const form = useForm({ name: '', permissions: [] })

const moduleLabels = {
    backups: 'Sao lưu',
    brands: 'Thương hiệu',
    categories: 'Danh mục',
    customer_debts: 'Công nợ khách hàng',
    customers: 'Khách hàng',
    dashboard: 'Trang tổng quan',
    inventory: 'Kho hàng',
    pos: 'Bán hàng',
    payment_accounts: 'Tài khoản thanh toán',
    products: 'Sản phẩm & kho',
    repairs: 'Sửa chữa',
    reports: 'Báo cáo',
    roles: 'Vai trò',
    sales: 'Đơn hàng',
    settings: 'Thiết lập',
    stock_imports: 'Nhập hàng',
    supplier_debts: 'Công nợ nhà cung cấp',
    suppliers: 'Nhà cung cấp',
    units: 'Đơn vị tính',
    users: 'Nhân viên',
}

const actionLabels = {
    access: 'Truy cập',
    cancel: 'Hủy',
    complete: 'Hoàn tất',
    create: 'Thêm mới',
    delete: 'Xóa',
    edit: 'Sửa',
    export: 'Xuất tệp',
    import: 'Nhập tệp',
    manage: 'Quản lý',
    pay: 'Thanh toán/thu',
    return: 'Xác nhận trả máy',
    view: 'Xem',
}

// Phân màu sắc trực quan cho các loại hành động
const actionBadgeClasses = {
    create: 'hover:border-emerald-300 hover:bg-emerald-50 text-emerald-700',
    edit: 'hover:border-amber-300 hover:bg-amber-50 text-amber-700',
    delete: 'hover:border-red-300 hover:bg-red-50 text-red-700',
    view: 'hover:border-blue-300 hover:bg-blue-50 text-blue-700',
    access: 'hover:border-slate-300 hover:bg-slate-100 text-slate-700',
    manage: 'hover:border-indigo-300 hover:bg-indigo-50 text-indigo-700',
}

const protectedRoleNames = ['Super Admin', 'Admin']

const permissionGroups = computed(() => {
    const groups = new Map()
    for (const permission of props.permissions) {
        const [module, action] = permission.split('.', 2)
        if (!groups.has(module)) groups.set(module, [])
        groups.get(module).push({
            name: permission,
            action,
            label: actionLabels[action] || action || permission,
        })
    }
    return [...groups.entries()].map(([key, items]) => ({
        key,
        label: moduleLabels[key] || key,
        permissions: items,
    }))
})

const selectedRole = computed(() => props.roles.find(role => role.id === selectedRoleId.value) || null)
const isReadOnly = computed(() => selectedRole.value && !selectedRole.value.can_edit)
const isNameLocked = computed(() => selectedRole.value && protectedRoleNames.includes(selectedRole.value.name))

const createRole = () => {
    selectedRoleId.value = null
    form.reset()
    form.clearErrors()
    form.permissions = []
}

const editRole = (role) => {
    selectedRoleId.value = role.id
    form.clearErrors()
    form.name = role.name
    form.permissions = [...(role.permissions || [])]
}

onMounted(() => {
    if (props.startInCreate) createRole()
    else if (props.initialRoleId) {
        const role = props.roles.find(item => item.id === props.initialRoleId)
        if (role) editRole(role)
    }
})

// Kiểm tra và Toggle nhóm quyền theo Module
const isGroupFullySelected = (group) => {
    return group.permissions.every(p => form.permissions.includes(p.name))
}

const toggleGroupPermissions = (group) => {
    if (isReadOnly.value) return
    const groupPermNames = group.permissions
        .filter(p => p.name !== 'roles.manage') // Giữ cố định quyền admin
        .map(p => p.name)

    if (isGroupFullySelected(group)) {
        form.permissions = form.permissions.filter(p => !groupPermNames.includes(p))
    } else {
        const newSet = new Set([...form.permissions, ...groupPermNames])
        form.permissions = Array.from(newSet)
    }
}

const saveRole = () => {
    const isNew = !selectedRole.value
    const roleId = selectedRole.value?.id || null
    const roleName = form.name.trim()
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('updated', { id: roleId, name: roleName, created: isNew }),
    }

    if (selectedRole.value) {
        form.put(route('roles.update', selectedRole.value.id), options)
        return
    }

    form.post(route('roles.store'), options)
}

const deleteRole = (role) => {
    confirmBox.show({
        title: 'Xóa vai trò',
        message: `Bạn có chắc muốn xóa vai trò “${role.name}”?`,
        confirmText: 'Xóa vai trò',
        onConfirm: () => router.delete(route('roles.destroy', role.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedRoleId.value === role.id) createRole()
                emit('updated', { id: role.id, name: role.name, deleted: true })
            },
        }),
    })
}
</script>

<template>
    <div class="flex h-[min(calc(100dvh-8rem),700px)] min-h-[220px] flex-col gap-3">
      <div class="grid min-h-0 flex-1 grid-rows-[minmax(110px,0.35fr)_minmax(0,1fr)] gap-3 lg:grid-cols-[260px_minmax(0,1fr)] lg:grid-rows-1">
        <!-- Danh sách vai trò bên trái -->
        <section class="flex min-h-0 flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-2xs">
            <div class="mb-2.5 flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Danh sách vai trò</h3>
                    <p class="text-[11px] text-slate-500">Chọn vai trò để chỉnh sửa</p>
                </div>
                <ActionButton class="shrink-0 !px-2.5 !py-1.5 text-xs" @click="createRole">
                    <Plus class="h-3.5 w-3.5" /> Tạo mới
                </ActionButton>
            </div>

            <div class="min-h-0 flex-1 space-y-1.5 overflow-y-auto pr-0.5">
                <div
                    v-for="role in roles"
                    :key="role.id"
                    class="flex items-center gap-1 rounded-xl border p-2 transition cursor-pointer"
                    :class="selectedRoleId === role.id ? 'border-emerald-500 bg-emerald-50/60 ring-1 ring-emerald-500/30' : 'border-slate-200/80 hover:border-slate-300 hover:bg-slate-50/50'"
                    @click="editRole(role)"
                >
                    <div class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-bold text-slate-800">{{ role.name }}</span>
                        <span class="mt-0.5 block text-[10px] font-medium text-slate-500">
                            {{ role.permissions?.length || 0 }} quyền · {{ role.users_count || 0 }} nhân viên
                            <span v-if="role.system" class="ml-0.5 font-bold text-amber-600">• Bảo vệ</span>
                        </span>
                    </div>
                    <button
                        v-if="!role.system"
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30 transition-colors"
                        :disabled="role.users_count > 0"
                        :title="role.users_count > 0 ? 'Chuyển nhân viên sang vai trò khác trước khi xóa' : 'Xóa vai trò'"
                        @click.stop="deleteRole(role)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
                <p v-if="!roles.length" class="rounded-lg border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                    Chưa có vai trò nào.
                </p>
            </div>
        </section>

        <!-- Form thiết lập quyền hạn bên phải -->
        <section class="flex min-h-0 min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs sm:p-4">
            <!-- Header chi tiết -->
            <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <ShieldCheck class="h-4 w-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">
                            {{ isReadOnly ? `Vai trò chỉ xem: ${selectedRole.name}` : selectedRole ? `Chỉnh sửa vai trò: ${selectedRole.name}` : 'Tạo vai trò mới' }}
                        </h3>
                        <p class="text-[11px] text-slate-500">
                            {{ isReadOnly ? 'Bạn không có quyền chỉnh sửa vai trò này.' : isNameLocked ? 'Tên vai trò giữ cố định; có thể tùy chỉnh danh sách quyền.' : 'Nhập tên vai trò và tick chọn các quyền muốn cấp.' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tên vai trò -->
            <div v-if="!isReadOnly && !isNameLocked" class="mb-3">
                <FloatingInput v-model="form.name" label="Tên vai trò" :error="form.errors.name" />
            </div>
            <div v-else-if="isNameLocked" class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700">
                Tên vai trò: {{ selectedRole.name }}
            </div>

            <!-- Danh sách Nhóm Quyền (Dạng Card Grid - Không bị cuộn ngang) -->
            <div class="min-h-0 flex-1 overflow-y-auto pr-1 space-y-2.5">
                <div v-if="permissionGroups.length" class="grid gap-2.5 sm:grid-cols-2">
                    <div
                        v-for="group in permissionGroups"
                        :key="group.key"
                        class="rounded-xl border border-slate-200/90 bg-white p-2.5 transition-all hover:border-slate-300 hover:shadow-2xs"
                    >
                        <!-- Header nhóm chức năng -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-1.5 mb-2">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                {{ group.label }}
                                <span v-if="group.permissions.some(p => p.name === 'roles.manage')" class="text-[10px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded">
                                    Admin
                                </span>
                            </span>
                            <button
                                v-if="!isReadOnly"
                                type="button"
                                class="text-[10px] font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 bg-slate-50 hover:bg-emerald-50 px-2 py-0.5 rounded-md transition-colors"
                                @click="toggleGroupPermissions(group)"
                            >
                                <component :is="isGroupFullySelected(group) ? CheckSquare : Square" class="h-3 w-3" />
                                {{ isGroupFullySelected(group) ? 'Bỏ chọn' : 'Tất cả' }}
                            </button>
                        </div>

                        <!-- Danh sách checkbox từng quyền trong nhóm -->
                        <div class="flex flex-wrap gap-1.5">
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50/60 px-2 py-1 text-xs font-medium text-slate-700 transition-all hover:bg-white"
                                :class="[
                                    form.permissions.includes(permission.name) ? 'border-emerald-300 bg-emerald-50/80 text-emerald-900 font-semibold' : '',
                                    actionBadgeClasses[permission.action] || ''
                                ]"
                            >
                                <input
                                    v-model="form.permissions"
                                    type="checkbox"
                                    :value="permission.name"
                                    :disabled="isReadOnly || permission.name === 'roles.manage'"
                                    class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 disabled:opacity-50"
                                >
                                <span class="text-[11px]">{{ permission.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <p v-if="!permissionGroups.length" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800 border border-amber-200">
                    Chưa có danh sách quyền. Vui lòng chạy bộ seeder phân quyền trên máy chủ.
                </p>
            </div>

            <p v-if="form.errors.permissions" class="mt-2 text-xs text-red-600 font-medium">{{ form.errors.permissions }}</p>

        </section>
      </div>

      <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 bg-white px-3 py-2.5">
          <ActionButton variant="secondary" class="!px-3 !py-1.5 text-xs" :disabled="form.processing" @click="emit('close')">Đóng</ActionButton>
          <ActionButton v-if="!isReadOnly" class="!px-4 !py-1.5 text-xs font-bold !bg-emerald-600 hover:!bg-emerald-700 !text-white" :disabled="form.processing || !permissions.length" @click="saveRole">
              <Save class="h-3.5 w-3.5" />
              {{ form.processing ? 'Đang lưu...' : selectedRole ? 'Lưu thay đổi' : 'Tạo vai trò' }}
          </ActionButton>
      </div>
    </div>
</template>
