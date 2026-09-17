<script setup>
import { ref } from 'vue'
import { Trash2, Gift, Percent, Tag, MessageSquare } from 'lucide-vue-next'

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['remove', 'update-quantity', 'update-discount'])

// Toggle hiển thị ô nhập ghi chú/giảm giá cho từng dòng
const activeDiscountItem = ref(null)

const formatMoney = (val) => Number(val || 0).toLocaleString('vi-VN')

// Tính thành tiền từng dòng
const calculateLineTotal = (item) => {
    const price = Number(item.price || item.sell_price || 0)
    const qty = Number(item.quantity || 1)
    let discount = 0

    if (item.discount_type === 'percent') {
        discount = (price * qty * Number(item.discount_value || 0)) / 100
    } else if (item.discount_type === 'amount') {
        discount = Number(item.discount_value || 0)
    }

    return Math.max(0, price * qty - discount)
}

const toggleDiscountInput = (itemId) => {
    activeDiscountItem.value = activeDiscountItem.value === itemId ? null : itemId
}
</script>

<template>
    <div class="divide-y divide-slate-100">
        <div
            v-for="(item, index) in items"
            :key="item.imei_id ? 'imei-' + item.imei_id : (item.cart_item_id || item.id || index)"
            class="group bg-white p-2.5 transition-colors hover:bg-slate-50/80"
        >
            <!-- HÀNG CHÍNH: ẢNH - THÔNG TIN - GIÁ -->
            <div class="flex items-start gap-2.5">
                
                <!-- 1. HÌNH ẢNH SẢN PHẨM -->
                <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-slate-200/80 bg-slate-50">
                    <img
                        v-if="item.image || item.image_url"
                        :src="item.image || item.image_url"
                        :alt="item.name"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                        <Tag class="h-5 w-5 stroke-1" />
                    </div>

                    <!-- BADGE QUÀ TẶNG (NẾU GIÁ = 0 HOẶC IS_GIFT) -->
                    <div
                        v-if="item.is_gift || Number(item.price || item.sell_price) === 0"
                        class="absolute inset-0 bg-indigo-900/40 backdrop-blur-[1px] flex items-center justify-center"
                        title="Hàng quà tặng"
                    >
                        <Gift class="h-5 w-5 text-amber-300 animate-pulse" />
                    </div>
                </div>

                <!-- 2. THÔNG TIN SẢN PHẨM & IMEI -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-1">
                        <h4 class="text-xs font-bold leading-snug text-slate-900 line-clamp-2">
                            {{ item.name }}
                        </h4>

                        <!-- NÚT XÓA -->
                        <button
                            type="button"
                            class="text-slate-300 hover:text-rose-600 transition-colors p-0.5"
                            title="Xóa khỏi giỏ"
                            @click="emit('remove', item)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- HIỂN THỊ PHIÊN BẢN / IMEI / SERIAL -->
                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span v-if="item.variant_name || item.variant?.name" class="font-semibold text-indigo-600">
                            {{ item.variant_name || item.variant?.name }}
                        </span>

                        <span
                            v-if="item.imei || item.serial"
                            class="rounded bg-slate-100 px-1 py-0.2 font-mono font-bold text-slate-600 border border-slate-200"
                        >
                            S/N: {{ item.imei || item.serial }}
                        </span>

                        <!-- BADGE QUÀ TẶNG NHÃN CHỮ -->
                        <span
                            v-if="item.is_gift || Number(item.price || item.sell_price) === 0"
                            class="inline-flex items-center gap-0.5 rounded bg-amber-100 px-1.5 py-0.2 font-bold text-amber-800 text-[10px]"
                        >
                            <Gift class="h-3 w-3" /> Quà tặng
                        </span>
                    </div>

                    <!-- HÀNG THAO TÁC: SỐ LƯỢNG (BÊN TRÁI) - GIÁ & GIẢM GIÁ (BÊN PHẢI) -->
                    <div class="mt-2 flex items-center justify-between gap-2">
                        
                        <!-- CỤM TĂNG GIẢM SỐ LƯỢNG (SÁT TRÁI) -->
                        <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50 overflow-hidden">
                            <button
                                type="button"
                                :disabled="item.imei || item.serial"
                                class="flex h-6 w-6 items-center justify-center font-bold text-slate-600 hover:bg-slate-200 disabled:opacity-30"
                                @click="item.quantity > 1 ? item.quantity-- : emit('remove', item)"
                            >
                                -
                            </button>
                            <span class="w-7 text-center font-bold text-xs text-slate-800">
                                {{ item.quantity }}
                            </span>
                            <button
                                type="button"
                                :disabled="item.imei || item.serial"
                                class="flex h-6 w-6 items-center justify-center font-bold text-slate-600 hover:bg-slate-200 disabled:opacity-30"
                                @click="item.quantity++"
                            >
                                +
                            </button>
                        </div>

                        <!-- CỤM GIÁ & TÍNH TOÁN -->
                        <div class="text-right">
                            <!-- Hiển thị giá gốc gạch ngang nếu có chiết khấu -->
                            <div
                                v-if="item.discount_value > 0"
                                class="text-[10px] text-slate-400 line-through"
                            >
                                {{ formatMoney((item.price || item.sell_price) * item.quantity) }}đ
                            </div>

                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Nút bật ô chỉnh giảm giá dòng -->
                                <button
                                    v-if="!item.is_gift && Number(item.price || item.sell_price) > 0"
                                    type="button"
                                    class="text-[10px] font-bold p-0.5 rounded"
                                    :class="item.discount_value > 0 ? 'text-amber-600 bg-amber-50' : 'text-slate-400 hover:text-slate-600'"
                                    @click="toggleDiscountInput(item.cart_item_id || item.id)"
                                    title="Thêm chiết khấu món"
                                >
                                    <Percent class="h-3 w-3" />
                                </button>

                                <!-- Nút bật ghi chú món -->
                                <button
                                    type="button"
                                    class="text-[10px] p-0.5 rounded"
                                    :class="item.note ? 'text-indigo-600 bg-indigo-50 font-bold' : 'text-slate-400 hover:text-slate-600'"
                                    @click="item.showNote = !item.showNote"
                                    title="Thêm ghi chú"
                                >
                                    <MessageSquare class="h-3 w-3" />
                                </button>

                                <!-- Thành tiền -->
                                <span class="text-xs font-black text-emerald-600">
                                    {{ formatMoney(calculateLineTotal(item)) }}đ
                                </span>
                            </div>
                        </div>

                    </div>

                    <!-- KHỐI MỞ RỘNG: Ô NHẬP CHIẾT KHẤU DÒNG -->
                    <div
                        v-if="activeDiscountItem === (item.cart_item_id || item.id)"
                        class="mt-2 rounded-lg border border-amber-200 bg-amber-50/50 p-2 text-xs"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-amber-800 text-[11px]">Giảm món:</span>
                            <div class="flex flex-1 items-center gap-1">
                                <input
                                    v-model.number="item.discount_value"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    class="w-full rounded border border-amber-300 bg-white px-2 py-1 text-xs font-bold outline-none focus:ring-1 focus:ring-amber-500"
                                />
                                <select
                                    v-model="item.discount_type"
                                    class="rounded border border-amber-300 bg-white px-1 py-1 text-xs font-bold outline-none"
                                >
                                    <option value="amount">VNĐ</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- KHỐI MỞ RỘNG: GHI CHÚ MÓN -->
                    <div v-if="item.showNote" class="mt-2">
                        <input
                            v-model="item.note"
                            type="text"
                            placeholder="Ghi chú cho món này (VD: Trầy xước nhẹ, tặng kèm bao da...)"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:bg-white"
                        />
                    </div>

                </div>

            </div>
        </div>
    </div>
</template>