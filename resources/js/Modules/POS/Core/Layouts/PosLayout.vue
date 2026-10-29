<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ClipboardList, PackageSearch, ShoppingCart, X, Store } from 'lucide-vue-next'

const emit = defineEmits(['close', 'manage-shift', 'show-shift-history'])
const props = defineProps({
  cartCount: { type: Number, default: 0 },
  grandTotal: { type: Number, default: 0 },
  shift: { type: Object, default: null },
  openingCountEnabled: { type: Boolean, default: true },
})
const mobilePanel = ref('products')
const cartButton = ref(null)
const cartPulse = ref(false)
const cartNeedsAttention = ref(props.cartCount > 0)
let cartPulseTimer = null
const formattedTotal = computed(() => `${Number(props.grandTotal || 0).toLocaleString('vi-VN')} ₫`)
watch(() => props.cartCount, (count, previousCount) => {
  if (count > previousCount && mobilePanel.value !== 'cart') cartNeedsAttention.value = true
})
watch(mobilePanel, panel => {
  if (panel === 'cart') cartNeedsAttention.value = false
})

const showCart = () => {
  mobilePanel.value = 'cart'
  cartNeedsAttention.value = false
}

const pulseCartButton = () => {
  cartPulse.value = true
  window.clearTimeout(cartPulseTimer)
  cartPulseTimer = window.setTimeout(() => {
    cartPulse.value = false
  }, 1500)
}
const compactMoney = amount => {
  const value = Number(amount || 0)
  if (value >= 1_000_000) return `${Number((value / 1_000_000).toFixed(1))}tr`
  if (value >= 1_000) return `${Math.round(value / 1_000)}k`
  return value.toLocaleString('vi-VN')
}

const animateProductToCart = (originRect, productName = 'Sản phẩm') => {
  if (!originRect || window.matchMedia('(min-width: 1024px)').matches) return

  const targetRect = cartButton.value?.getBoundingClientRect()
  if (!targetRect) return

  const width = Math.min(220, Math.max(132, originRect.width * 0.8))
  const height = 36
  const startX = originRect.left + (originRect.width - width) / 2
  const startY = originRect.top + (originRect.height - height) / 2
  const endX = targetRect.left + targetRect.width / 2 - width / 2
  const endY = targetRect.top + targetRect.height / 2 - height / 2
  const flyer = document.createElement('div')

  flyer.className = 'pointer-events-none fixed z-[10060] flex items-center rounded-full border border-blue-200 bg-white px-3 text-xs font-bold text-blue-700 shadow-xl'
  flyer.style.left = `${startX}px`
  flyer.style.top = `${startY}px`
  flyer.style.width = `${width}px`
  flyer.style.height = `${height}px`
  flyer.style.willChange = 'transform, opacity'
  flyer.textContent = `＋ ${productName}`
  flyer.title = productName
  document.body.appendChild(flyer)

  if (typeof flyer.animate === 'function') {
    const animation = flyer.animate([
      { transform: 'translate(0, 0) scale(1)', opacity: 1 },
      { transform: `translate(${endX - startX}px, ${endY - startY}px) scale(0.28)`, opacity: 0.25 },
    ], {
      duration: 560,
      easing: 'cubic-bezier(0.22, 0.8, 0.3, 1)',
      fill: 'forwards',
    })
    animation.onfinish = () => {
      flyer.remove()
      pulseCartButton()
    }
    window.setTimeout(() => flyer.remove(), 800)
  } else {
    flyer.remove()
    pulseCartButton()
  }
}

defineExpose({ animateProductToCart })
onBeforeUnmount(() => window.clearTimeout(cartPulseTimer))
</script>

<template>
  <!-- Phủ toàn màn hình, nằm trên menu/sidebar của trang quản trị -->
  <div class="fixed inset-0 z-40 flex flex-col overflow-hidden bg-slate-100">

    <!-- Thanh trên cùng: tiêu đề + nút đóng -->
    <header class="flex h-12 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-2 shadow-sm sm:justify-between sm:px-3">
      <div class="flex shrink-0 items-center gap-2 text-sm font-extrabold text-slate-800">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm">
          <Store class="h-4 w-4" />
        </span>
        <span class="hidden sm:inline">POS bán hàng</span>
      </div>

      <button v-if="openingCountEnabled || shift" type="button" class="min-w-0 flex-1 truncate rounded-lg bg-blue-600 px-2 py-1.5 text-[11px] font-bold text-white shadow-sm transition hover:bg-blue-700 active:scale-[0.98] sm:flex-none sm:px-3 sm:text-xs" @click="emit('manage-shift')">
        <span class="sm:hidden">{{ shift ? `Tiền ${compactMoney(shift.expected_cash)} · Nợ ${compactMoney(shift.customer_debt_amount)}` : 'Mở ca làm' }}</span>
        <span class="hidden sm:inline">{{ shift ? `Tiền mặt ${Number(shift.expected_cash).toLocaleString('vi-VN')} ₫ · Nợ khách ${Number(shift.customer_debt_amount).toLocaleString('vi-VN')} ₫` : 'Mở ca làm' }}</span>
      </button>

      <button v-if="openingCountEnabled" type="button" aria-label="Lịch sử kiểm kê" title="Lịch sử kiểm kê" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 sm:h-auto sm:w-auto sm:gap-1.5 sm:px-2.5 sm:py-1.5 sm:text-xs sm:font-bold" @click="emit('show-shift-history')">
        <ClipboardList class="h-4 w-4 sm:h-3.5 sm:w-3.5" />
        <span class="hidden sm:inline">Lịch sử kiểm kê</span>
      </button>

      <button
        type="button"
        title="Đóng POS (dữ liệu đang làm được lưu lại)"
        class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-transparent text-slate-500 transition hover:border-rose-100 hover:bg-rose-50 hover:text-rose-600 active:scale-90"
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
      <button type="button" class="flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition" :class="mobilePanel === 'products' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'" @click="mobilePanel = 'products'">
        <PackageSearch class="h-4 w-4 shrink-0" />
        <span>Sản phẩm</span>
      </button>
      <button ref="cartButton" type="button" class="flex min-w-0 flex-1 items-center justify-between gap-1.5 rounded-lg px-3 text-xs font-semibold transition" :class="[mobilePanel === 'cart' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100', cartPulse ? 'scale-[1.04] ring-2 ring-blue-300' : '']" @click="showCart">
        <span class="flex min-w-0 items-center gap-1.5">
          <ShoppingCart class="h-4 w-4 shrink-0" />
          <span class="truncate">Giỏ hàng</span>
          <span
            v-if="cartCount > 0"
            class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-extrabold leading-none text-white shadow-sm ring-2 ring-rose-100"
            :class="cartNeedsAttention ? 'pos-cart-badge-pulse' : ''"
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

<style scoped>
@keyframes pos-cart-badge-attention {
  0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 1px 2px rgb(15 23 42 / 0.12), 0 0 0 2px rgb(255 228 230); }
  50% { opacity: 0.78; transform: scale(1.1); box-shadow: 0 0 0 3px rgb(251 113 133 / 0.18); }
}

.pos-cart-badge-pulse {
  animation: pos-cart-badge-attention 1.25s ease-in-out infinite;
}
</style>
