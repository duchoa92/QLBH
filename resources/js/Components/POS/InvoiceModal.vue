<script setup>
const props = defineProps({
    show: Boolean,
    data: Object
})

const emit = defineEmits(['close'])

const printInvoice = () => {
    window.print()
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">

        <div class="bg-white w-[380px] rounded-lg shadow-lg p-4 print:w-full print:shadow-none">

            <!-- HEADER -->
            <div class="text-center border-b pb-2 mb-3">
                <h2 class="font-bold text-lg">HÓA ĐƠN</h2>
                <p class="text-xs text-gray-500">Cửa hàng của bạn</p>
            </div>

            <!-- INFO -->
            <div class="text-sm space-y-1 mb-3">
                <div>Mã: {{ data?.id }}</div>
                <div>Ngày: {{ new Date().toLocaleString() }}</div>
            </div>

            <!-- ITEMS -->
            <div class="text-sm border-t pt-2 space-y-1">
                <div
                    v-for="item in data?.items || []"
                    :key="item.id"
                    class="flex justify-between"
                >
                    <span>{{ item.name }} x{{ item.quantity }}</span>
                    <span>{{ Number(item.price).toLocaleString() }}</span>
                </div>
            </div>

            <!-- TOTAL -->
            <div class="border-t mt-3 pt-2 font-bold flex justify-between">
                <span>Tổng</span>
                <span>{{ Number(data?.total || 0).toLocaleString() }} đ</span>
            </div>

            <!-- ACTION -->
            <div class="flex gap-2 mt-4 print:hidden">
                <button
                    @click="emit('close')"
                    class="flex-1 border rounded py-2"
                >
                    Đóng
                </button>

                <button
                    @click="printInvoice"
                    class="flex-1 bg-blue-600 text-white rounded py-2"
                >
                    🖨 In
                </button>
            </div>

        </div>

    </div>
</template>