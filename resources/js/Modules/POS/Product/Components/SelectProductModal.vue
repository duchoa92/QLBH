<script setup>
import { ref } from 'vue'

const props = defineProps({
    show: Boolean,
    product: Object
})

const emit = defineEmits(['close', 'confirm'])

const selectedVariant = ref(null)
const selectedImei = ref(null)

const confirm = () => {
    emit('confirm', {
        variant: selectedVariant.value,
        imei: selectedImei.value
    })
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white w-[400px] p-4 rounded">
            <h2 class="font-bold mb-3">Chọn sản phẩm</h2>

            <!-- Variant -->
            <div v-if="product?.variants?.length">
                <p>Chọn thuộc tính:</p>
                <div class="flex gap-2 flex-wrap mt-2">
                    <button
                        v-for="v in product.variants"
                        :key="v.id"
                        @click="selectedVariant = v"
                        class="border px-2 py-1 rounded"
                    >
                        {{ v.name }}
                    </button>
                </div>
            </div>

            <!-- IMEI -->
            <div v-if="product?.imeis?.length" class="mt-3">
                <p>Chọn IMEI:</p>
                <select v-model="selectedImei" class="w-full border p-2 mt-1">
                    <option v-for="i in product.imeis" :key="i.id" :value="i">
                        {{ i.code }}
                    </option>
                </select>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button @click="emit('close')">Huỷ</button>
                <button class="bg-blue-500 text-white px-3 py-1" @click="confirm">
                    OK
                </button>
            </div>
        </div>
    </div>
</template>