<script setup>

import { onBeforeUnmount, onMounted, ref } from 'vue'
import FloatingInput
from '@/Components/UI/FloatingInput.vue'

const props = defineProps({

    item: Object,

    searchGiftProducts: Function,

    selectGiftProduct: Function,
})

const searchContainer = ref(null)
const showResults = ref(false)

const hideResultsOnOutsideClick = event => {
    if (!searchContainer.value?.contains(event.target)) {
        showResults.value = false
    }
}

const selectGift = product => {
    showResults.value = false
    props.selectGiftProduct(props.item, product)
}

onMounted(() => document.addEventListener('click', hideResultsOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('click', hideResultsOnOutsideClick))

</script>

<template>

    <div ref="searchContainer" class="relative min-w-0 flex-[2]">

        <FloatingInput
            v-model="item.gift_keyword"
            @input="
                showResults = true;
                searchGiftProducts(
                    item
                )
            "
            @focus="showResults = true"
            label="Tìm quà"
        />

        <div
            v-if="
                showResults &&
                item.gift_results &&
                item.gift_results.length
            "
            class="absolute left-0 top-full z-[60] mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl shadow-slate-900/10 ring-1 ring-black/5"
        >

            <button
                v-for="product in item.gift_results"
                :key="product.id"
                type="button"
                @click="selectGift(product)"
                class="block w-full border-b border-slate-100 px-3 py-2 text-left transition last:border-b-0 hover:bg-blue-50"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <span class="truncate text-xs font-semibold text-slate-800">{{ product.name }}</span>
                    <span v-if="product.product_type === 'imei' || product.manage_stock_by_serial" class="shrink-0 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-700">IMEI</span>
                </div>
                <div v-if="product.sku" class="mt-0.5 truncate text-[10px] font-medium text-slate-500">{{ product.sku }}</div>
            </button>
        </div>
    </div>

</template>
