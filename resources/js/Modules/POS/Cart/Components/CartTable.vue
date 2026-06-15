<script setup>
import {
    PenSquare,
    Trash2,
} from 'lucide-vue-next'

defineProps({

    selectedIndex: {
        type: Number,
        default: -1,
    },

    items: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'remove',
])

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

const toggleNote = (item) => {

    item.showNote =
        !item.showNote

}

</script>

<template>
  <div
    v-for="item in items"
    :key="item.imei_id ? 'imei-' + item.imei_id : 'product-' + item.id"
    class="bg-white border border-gray-200 rounded-xl p-3 mb-2 shadow-sm"
  >
    <div class="flex gap-3">
      <div class="w-14 h-14 rounded-lg border flex items-center justify-center shrink-0 bg-gray-50 overflow-hidden">
        <img v-if="item.image" :src="item.image" class="w-full h-full object-cover" />
        <span v-else class="text-gray-400">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2h12"/></svg>
        </span>
      </div>

      <div class="flex-1 min-w-0 flex flex-col justify-center">
        <div class="flex justify-between items-center">
          <div class="font-semibold text-sm truncate">{{ item.name }}</div>
          <div class="flex gap-2 shrink-0">
            <button @click="toggleNote(item)" class="text-gray-400 hover:text-blue-600"><PenSquare class="w-4 h-4" /></button>
            <button @click="emit('remove', item)" class="text-red-400 hover:text-red-600"><Trash2 class="w-4 h-4" /></button>
          </div>
        </div>
        <div class="text-xs text-gray-500">{{ item.unit_name ?? 'Cái' }}</div>
      </div>
    </div>

    <div class="flex justify-between items-center mt-3 pt-3 border-t border-dashed border-gray-100">
      <div class="flex items-center border rounded-lg overflow-hidden h-7">
        <button class="w-8 bg-gray-50 hover:bg-gray-100 border-r" @click="item.quantity > 1 ? item.quantity-- : emit('remove', item)">-</button>
        <input 
          v-model="item.quantity" 
          type="number" 
          class="w-12 text-center outline-none bg-transparent border-none focus:ring-0 text-sm no-spinner" 
        />
        <button class="w-8 bg-gray-50 hover:bg-gray-100 border-l" @click="item.quantity++">+</button>
      </div>

      <div class="text-green-600 font-bold text-sm">
        {{ format(item.price * item.quantity) }}
      </div>
    </div>

    <textarea
      v-if="item.showNote"
      v-model="item.note"
      rows="1"
      placeholder="Ghi chú..."
      class="w-full mt-3 p-2 border rounded-lg text-sm focus:border-blue-500 outline-none"
    />
  </div>
</template>

<style scoped>
/* Ẩn mũi tên input */
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.no-spinner {
  -moz-appearance: textfield;
}
</style>
