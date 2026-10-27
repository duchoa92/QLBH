<script setup>
import { computed, ref } from 'vue'
import { ClipboardList, PackageSearch, ShoppingCart, X, Store } from 'lucide-vue-next'

const emit = defineEmits(['close', 'manage-shift', 'show-shift-history'])
const props = defineProps({
  cartCount: { type: Number, default: 0 },
  grandTotal: { type: Number, default: 0 },
  shift: { type: Object, default: null },
})
const mobilePanel = ref('products')
const formattedTotal = computed(() => `${Number(props.grandTotal || 0).toLocaleString('vi-VN')} ₫`)
const compactMoney = amount => {
  const value = Number(amount || 0)
  if (value >= 1_000_000) return `${Number((value / 1_000_000).toFixed(1))}tr`
  if (value >= 1_000) return `${Math.round(value / 1_000)}k`
  return value.toLocaleString('vi-VN')
}
</script>

<template>
  <!-- Phủ toàn màn hình, nằm trên menu/sidebar của trang quản trị -->
  <div class="fixed inset-0 z-40 flex flex-col overflow-hidden bg-slate-100">

    <!-- Thanh trên cùng: tiêu đề + nút đóng -->
    <header class="flex h-12 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-2 shadow-sm sm:justify-between sm:px-3">
      <div class="flex shrink-0 items-center gap-2 text-sm font-extrabold text-slate-800">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm">
          <Store class="h-4 w-4" />
        </span>
        <span class="hidden sm:inline">POS bán hàng</span>
      </div>

      <button type="button" class="min-w-0 flex-1 truncate rounded-lg bg-indigo-600 px-2 py-1.5 text-[11px] font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-[0.98] sm:flex-none sm:px-3 sm:text-xs" @click="emit('manage-shift')">
        <span class="sm:hidden">{{ shift ? `Tiền ${compactMoney(shift.expected_cash)} · Nợ ${compactMoney(shift.customer_debt_amount)}` : 'Mở ca làm' }}</span>
        <span class="hidden sm:inline">{{ shift ? `Tiền mặt ${Number(shift.expected_cash).toLocaleString('vi-VN')} ₫ · Nợ khách ${Number(shift.customer_debt_amount).toLocaleString('vi-VN')} ₫` : 'Mở ca làm' }}</span>
      </button>

      <button type="button" aria-label="Lịch sử kiểm kê" title="Lịch sử kiểm kê" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 sm:h-auto sm:w-auto sm:gap-1.5 sm:px-2.5 sm:py-1.5 sm:text-xs sm:font-bold" @click="emit('show-shift-history')">
        <ClipboardList class="h-4 w-4 sm:h-3.5 sm:w-3.5" />
        <span class="hidden sm:inline">Lịch sử kiểm kê</span>
      </button>

      <button
        type="button"
        title="Đóng POS (dữ liệu đang làm được lưu lại)"
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-transparent text-slate-500 transition hover:border-rose-100 hover:bg-rose-50 hover:text-rose-600 active:scale-90"
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

    <nav class="flex h-[52px] shrink-0 items-stretch gap-2 border-t border-slate-200 bg-white px-2 py-1 shadow-[0_-4px_12px_rgba(15,23,42,0.06)] lg:hidden" aria-label="Chuyển khu vực POS">
      <button type="button" class="flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition" :class="mobilePanel === 'products' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="mobilePanel = 'products'">
        <PackageSearch class="h-4 w-4 shrink-0" />
        <span>Sản phẩm</span>
      </button>
      <button type="button" class="flex min-w-0 flex-1 items-center justify-between gap-1.5 rounded-lg px-3 text-xs font-semibold transition" :class="mobilePanel === 'cart' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="mobilePanel = 'cart'">
        <span class="flex min-w-0 items-center gap-1.5">
          <ShoppingCart class="h-4 w-4 shrink-0" />
          <span class="truncate">Giỏ hàng</span>
          <span
            v-if="cartCount > 0"
            class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-extrabold leading-none text-white shadow-sm ring-2 ring-rose-100"
            :aria-label="`${cartCount} sản phẩm trong giỏ hàng`"
          >
            {{ cartCount }}
          </span>
        </span>
        <span class="shrink-0 text-[11px] font-extrabold tabular-nums">{{ formattedTotal }}</span>
      </button>
    </nav>

  </div>
</template>
