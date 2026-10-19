<script setup>

defineProps({

    gifts: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'remove',
])

</script>

<template>

    <div
        class="mt-2 flex w-full flex-wrap items-center gap-1.5 rounded-lg border border-amber-100 bg-amber-50/60 px-2.5 py-2"
    >

        <span class="mr-1 text-[11px] font-semibold text-amber-900">Quà tặng</span>

        <div
            v-for="gift in gifts"
            :key="gift.key || gift.id"
            class="flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-900"
        >
            <span class="max-w-32 truncate font-semibold">
                {{ gift.name }}
            </span>

            <select
                v-if="gift.requires_imei"
                v-model="gift.imei_id"
                class="h-6 max-w-36 rounded border-amber-300 bg-white px-1 text-[10px] font-semibold text-slate-700"
            >
                <option :value="null">Chọn IMEI</option>
                <option
                    v-for="imei in gift.imei_options || []"
                    :key="imei.id"
                    :value="imei.id"
                >
                    {{ imei.display_code }}
                </option>
            </select>

            <span v-else class="font-bold text-red-700 px-1">
                x {{ gift.quantity }}
            </span>

            <button
                type="button"
                class="font-bold text-amber-700 hover:text-rose-600"
                title="Xóa quà tặng"
                @click="emit('remove', gift.key || gift.id)"
            >
                ×
            </button>
        </div>

    </div>
</template>
