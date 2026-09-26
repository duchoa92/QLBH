<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'

const props = defineProps({
    imei: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    sell_price: props.imei.sell_price ?? 0,
})

const submit = () => {
    form.patch(route('product-imeis.update-price', props.imei.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated')
            emit('close')
        },
    })
}
</script>

<template>
    <BaseModal title="Cập nhật giá bán" size="sm" @close="emit('close')">
        <div class="space-y-2">
            <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                IMEI: {{ imei.imei }}
            </div>

            <FloatingInput
                v-model.number="form.sell_price"
                type="number"
                min="0"
                label="Giá bán mới"
                :error="form.errors.sell_price"
            />
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton variant="secondary" @click="emit('close')">Hủy</ActionButton>
                <ActionButton variant="primary" :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Đang lưu...' : 'Lưu giá' }}
                </ActionButton>
            </div>
        </template>
    </BaseModal>
</template>
