<script setup>

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

const updateQty = (item, event) => {

    let qty = Number(event.target.value)

    if (isNaN(qty) || qty < 1) {

        qty = 1
    }

    item.quantity = qty
}
</script>

<template>

    <div class="h-full">

        <div
            v-if="!items.length"
            class="flex h-full min-h-[220px] items-center justify-center p-6 text-center"
        >

            <div>

                <div class="text-base font-bold text-slate-800">
                    Chua co san pham
                </div>

                <div class="mt-1 text-sm text-slate-500">
                    Chon hang ben trai hoac quet barcode de them vao hoa don.
                </div>

            </div>

        </div>

        <div
            v-else
            class="divide-y divide-slate-100"
        >

            <div
                v-for="(item, index) in items"
                :key="item.id + '-' + (item.imei_id || index)"
                class="p-3 transition"
                :class="{
                    'bg-blue-50':
                        selectedIndex === index,
                }"
            >

                <div class="flex items-start gap-3">

                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-slate-100 text-sm font-bold text-slate-600"
                    >
                        {{ index + 1 }}
                    </div>

                    <div class="min-w-0 flex-1">

                        <div class="font-semibold leading-5 text-slate-900">
                            {{ item.name }}
                        </div>

                        <div class="mt-1 flex flex-wrap gap-1 text-xs text-slate-500">

                            <span v-if="item.serial">
                                Serial: {{ item.serial }}
                            </span>

                            <span v-if="item.color">
                                Mau: {{ item.color }}
                            </span>

                            <span v-if="item.storage">
                                Bo nho: {{ item.storage }}
                            </span>

                        </div>

                        <div class="mt-3 grid grid-cols-[92px_1fr_auto] items-center gap-2">

                            <input
                                :value="item.quantity"

                                :disabled="!!item.imei"

                                @change="
                                    updateQty(
                                        item,
                                        $event
                                    )
                                "

                                type="number"

                                min="1"

                                class="h-10 w-full rounded-md border-slate-300 px-2 text-center text-sm font-bold disabled:bg-slate-100 disabled:text-slate-500"
                            />

                            <div class="text-right">

                                <div class="text-xs text-slate-500">
                                    Don gia
                                </div>

                                <div class="font-semibold text-slate-800">
                                    {{ format(item.price) }}
                                </div>

                            </div>

                            <button
                                type="button"
                                @click="emit('remove', index)"
                                class="h-10 w-10 rounded-md border border-rose-200 bg-rose-50 text-lg font-bold leading-none text-rose-600 transition hover:bg-rose-100"
                                aria-label="Xoa san pham"
                            >
                                x
                            </button>

                        </div>

                        <div class="mt-3 flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">

                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Thanh tien
                            </span>

                            <span class="text-base font-black text-slate-900">
                                {{
                                    format(
                                        item.price *
                                        item.quantity
                                    )
                                }}
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</template>
