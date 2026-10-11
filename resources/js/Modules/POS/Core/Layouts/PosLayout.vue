<script setup>
import { X, Store } from 'lucide-vue-next'

const emit = defineEmits(['close'])
</script>

<template>
  <!-- Phủ toàn màn hình, nằm trên menu/sidebar của trang quản trị -->
  <div class="fixed inset-0 z-40 flex flex-col bg-slate-100">

    <!-- Thanh trên cùng: tiêu đề + nút đóng -->
    <header class="flex h-11 shrink-0 items-center justify-between px-3">
      <div class="flex items-center gap-2 text-sm font-bold text-slate-700">
        <Store class="h-4 w-4 text-indigo-600" />
        POS bán hàng
      </div>

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
    <div class="min-h-0 flex-1 overflow-y-auto lg:overflow-hidden">
      <div class="flex h-full flex-col lg:flex-row">

        <div class="flex min-h-0 w-full flex-col px-1 pb-2 lg:w-[65%]">
          <slot name="main" />
        </div>

        <div class="flex min-h-0 w-full flex-col px-1 pb-2 lg:w-[35%]">
          <slot name="sidebar" />
        </div>

      </div>
    </div>

  </div>
</template>
