<script setup>
import { computed } from 'vue';
import { PackageCheck } from 'lucide-vue-next';

// Danh sách phụ kiện nhận kèm khi tiếp nhận máy, hiển thị lúc trả máy để đối chiếu xem máy còn thiếu gì.
const props = defineProps({ accessories: { type: [Array, String], default: () => [] } });

const items = computed(() => {
    const raw = Array.isArray(props.accessories) ? props.accessories : String(props.accessories ?? '').split(',');
    return raw.map((item) => String(item ?? '').trim()).filter(Boolean);
});
</script>

<template>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
        <div class="flex items-center gap-2 text-xs font-bold text-amber-900">
            <PackageCheck class="h-4 w-4 shrink-0" />
            <span>Phụ kiện nhận kèm khi tiếp nhận<template v-if="items.length"> ({{ items.length }})</template></span>
        </div>
        <ul v-if="items.length" class="mt-2 flex flex-wrap gap-1.5">
            <li v-for="item in items" :key="item" class="rounded-full border border-amber-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-900">{{ item }}</li>
        </ul>
        <p v-else class="mt-1.5 text-[11px] font-medium text-amber-800">Phiếu không ghi nhận phụ kiện kèm theo.</p>
        <p v-if="items.length" class="mt-2 text-[11px] font-medium text-amber-800">Đối chiếu từng mục với máy trước khi giao để biết máy còn thiếu gì.</p>
    </div>
</template>
