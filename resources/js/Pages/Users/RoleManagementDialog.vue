<script setup>
import BaseModal from '@/Components/UI/BaseModal.vue'
import RoleManagementModal from './RoleManagementModal.vue'

defineProps({
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    initialRoleId: { type: Number, default: null },
})

const emit = defineEmits(['close', 'updated'])
</script>

<template>
    <BaseModal
        title="Quản lý vai trò và quyền"
        size="xl"
        body-class="!overflow-hidden bg-slate-50 p-3 sm:p-4"
        @close="emit('close')"
    >
        <RoleManagementModal
            :roles="roles"
            :permissions="permissions"
            :initial-role-id="initialRoleId"
            @close="emit('close')"
            @updated="emit('updated', $event)"
        />
    </BaseModal>
</template>
