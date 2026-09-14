<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import SupplierFormFields from '@/Pages/Suppliers/Partials/SupplierFormFields.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'

const props = defineProps({
    supplier: Object,
    name: String,
    title: String
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    name: props.supplier?.name || props.name || '',
    phone: props.supplier?.phone || '',
    email: props.supplier?.email || '',
    address: props.supplier?.address || ''
})

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: (page) => {
            emit('updated', page.props.newSupplier)
            emit('close')
        }
    }

    if (props.supplier?.id) {
        form.put(route('suppliers.update', props.supplier.id), options)
        return
    }

    form.post(route('suppliers.store'), options)
}
</script>

<template>
<BaseModal :title="title" @close="emit('close')">

    <SupplierFormFields :form="form" />

    <template #footer>
        <div class="flex justify-end gap-2">
            <ActionButton
                variant="secondary"
                @click="emit('close')"
            >
                Hủy
            </ActionButton>

            <ActionButton
                variant="primary"
                :disabled="form.processing"
                @click="submit"
            >
                {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
            </ActionButton>
        </div>
    </template>

</BaseModal>
</template>
