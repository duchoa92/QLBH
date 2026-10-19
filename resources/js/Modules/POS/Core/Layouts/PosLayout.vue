<script setup>
import { computed, ref } from 'vue'
import { PackageSearch, ShoppingCart, X, Store } from 'lucide-vue-next'

const emit = defineEmits(['close', 'manage-shift', 'show-shift-history'])
const props = defineProps({
  cartCount: { type: Number, default: 0 },
  grandTotal: { type: Number, default: 0 },
  shift: { type: Object, default: null },
})
const mobilePanel = ref('products')
const formattedTotal = computed(() => `${Number(props.grandTotal || 0).toLocaleString('vi-VN')} ₫`)
</script>

<template>
  <!-- Phủ toàn màn hình, nằm trên menu/sidebar của trang quản trị -->
  <div class="fixed inset-0 z-40 flex flex-col overflow-hidden bg-slate-100">

    <!-- Thanh trên cùng: tiêu đề + nút đóng -->
    <header class="flex h-11 shrink-0 items-center justify-between px-3">
      <div class="flex items-center gap-2 text-sm font-bold text-slate-700">
        <Store class="h-4 w-4 text-indigo-600" />
        POS bán hàng
      </div>

      <button type="button" class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100" @click="emit('manage-shift')">
        {{ shift ? `Tiền mặt ${Number(shift.expected_cash).toLocaleString('vi-VN')} ₫ · Nợ khách ${Number(shift.customer_debt_amount).toLocaleString('vi-VN')} ₫` : 'Mở ca làm' }}
      </button>

      <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200" @click="emit('show-shift-history')">Lịch sử kiểm kê</button>

      <button
        type="button"
        title="Đóng POS (dữ liệu đang làm được lưu lại)"
        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-rose-50 hover:text-rose-600 active:scale-90"
        @click="emit('close')"
      >
        <X class="h-5 w-5" />
      </button>
    </header>

    <!-- Nội dung POS -->
    <div class="min-h-0 flex-1 overflow-hidden">
      <div class="flex h-full min-h-0 flex-col lg:flex-row">

        <div :class="mobilePanel === 'products' ? 'flex' : 'hidden'" class="min-h-0 w-full flex-1 flex-col px-1 pb-2 lg:flex lg:flex-none lg:w-[65%]">
          <slot name="main" />
        </div>

        <div :class="mobilePanel === 'cart' ? 'flex' : 'hidden'" class="min-h-0 w-full flex-1 flex-col px-1 pb-2 lg:flex lg:flex-none lg:w-[35%]">
          <slot name="sidebar" />
        </div>

      </div>
    </div>

    <nav class="flex h-16 shrink-0 items-stretch gap-2 border-t border-slate-200 bg-white px-2 py-1.5 shadow-[0_-4px_12px_rgba(15,23,42,0.06)] lg:hidden" aria-label="Chuyển khu vực POS">
      <button type="button" class="flex min-w-0 flex-1 items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold transition" :class="mobilePanel === 'products' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="mobilePanel = 'products'">
        <PackageSearch class="h-5 w-5 shrink-0" />
        <span>Sản phẩm</span>
      </button>
      <button type="button" class="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-xl px-3 text-sm font-semibold transition" :class="mobilePanel === 'cart' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="mobilePanel = 'cart'">
        <span class="flex min-w-0 items-center gap-2">
          <ShoppingCart class="h-5 w-5 shrink-0" />
          <span class="truncate">Giỏ hàng ({{ cartCount }})</span>
        </span>
        <span class="shrink-0 text-xs font-bold">{{ formattedTotal }}</span>
      </button>
    </nav>

  </div>
</template>
