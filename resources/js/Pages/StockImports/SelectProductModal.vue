<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const emit = defineEmits(['close', 'select'])

const products = ref([])
const selected = ref([])
const imeiInput = ref('')

onMounted(async () => {
    const res = await axios.get('/products/list-import')
    products.value = res.data
})

const toggleVariant = (product, variant) => {
    const exist = selected.value.find(i => i.variant_id === variant.id)

    if (exist) {
        selected.value = selected.value.filter(i => i.variant_id !== variant.id)
    } else {
        selected.value.push({
            product_id: product.id,
            variant_id: variant.id,
            name: product.name,
            variant: variant.attributes.map(a => a.value).join(' - '),
            price: variant.sell_price || 0,
            qty: 1,
            manage_imei: product.manage_stock_by_serial,
            imeis: []
        })
    }
}

const confirm = () => {
    emit('select', selected.value)
}
</script>

<template>
<div class="fixed inset-0 bg-black/40 flex items-center justify-center">

    <div class="bg-white w-[700px] p-4 rounded">

        <h3 class="font-bold mb-2">Chọn hàng hóa</h3>

        <div class="max-h-[400px] overflow-auto">

            <div v-for="product in products" :key="product.id" class="border mb-2 p-2">

                <div class="font-bold">{{ product.name }}</div>

                <div v-for="variant in product.variants"
                    :key="variant.id"
                    class="flex justify-between items-center p-2 border-t cursor-pointer hover:bg-gray-100"
                    @click="toggleVariant(product, variant)">

                    <div>
                        {{ variant.attributes.map(a => a.value).join(' - ') }}
                    </div>

                    <input type="checkbox"
                        :checked="selected.some(i => i.variant_id === variant.id)" />
                </div>

            </div>

        </div>

        <div class="flex justify-end gap-2 mt-3">
            <button @click="$emit('close')">Huỷ</button>
            <button @click="confirm"
                class="bg-green-600 text-white px-3 py-1 rounded">
                Thêm
            </button>
        </div>

    </div>
</div>
</template>