<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import SupplierFormFields from '@/Pages/Suppliers/Partials/SupplierFormFields.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import api from '@/Services/api'

const props = defineProps({
    supplier: Object,
    name: String,
    title: String,
    apiMode: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    name: props.supplier?.name || props.name || '',
    phone: props.supplier?.phone || '',
    email: props.supplier?.email || '',
    address: props.supplier?.address || ''
})

const submitViaApi = async () => {
    form.processing = true
    form.clearErrors()
    try {
        const { data } = await api.post('/suppliers', form.data())
        emit('updated', data.data)
        emit('close')
    } catch (error) {
        if (error.response?.status === 422) {
            form.errors = Object.fromEntries(
                Object.entries(error.response.data.errors || {}).map(([field, messages]) => [field, messages[0]])
            )
        } else {
            form.errors.name = error.response?.data?.message || 'Không thể lưu nhà cung cấp.'
        }
    } finally {
        form.processing = false
    }
}

const submit = () => {
    if (props.apiMode && !props.supplier?.id) {
        submitViaApi()
        return
    }

    const options = {
        preserveScroll: true,
        onSuccess: (page) => {
            emit('updated', page.props.flash?.newSupplier ?? null)
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
