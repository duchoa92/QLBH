<script setup>

defineProps({

    show: {
        type: Boolean,
        default: false,
    },
})

defineEmits([
    'toggle',
])

</script>

<template>

    <div>

        <button
            type="button"
            @click="$emit('toggle')"
            class="text-sm font-semibold text-blue-600 hover:text-blue-700"
        >

            {{
                show
                    ? 'An phim tat'
                    : 'Xem phim tat'
            }}

        </button>

        <div
            v-if="show"
            class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-3"
        >

            <div class="mb-2 text-sm font-bold text-slate-900">
                Phim tat POS
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs text-slate-600">

                <div class="flex items-center gap-2">
                    <kbd class="rounded border bg-white px-2 py-1 font-bold text-slate-700 shadow-sm">
                        F2
                    </kbd>
                    Thanh toan
                </div>

                <div class="flex items-center gap-2">
                    <kbd class="rounded border bg-white px-2 py-1 font-bold text-slate-700 shadow-sm">
                        ESC
                    </kbd>
                    Dong popup
                </div>

                <div class="flex items-center gap-2">
                    <kbd class="rounded border bg-white px-2 py-1 font-bold text-slate-700 shadow-sm">
                        DEL
                    </kbd>
                    Xoa hang
                </div>

                <div class="flex items-center gap-2">
                    <kbd class="rounded border bg-white px-2 py-1 font-bold text-slate-700 shadow-sm">
                        + / -
                    </kbd>
                    Tang giam SL
                </div>

            </div>

        </div>

    </div>

</template>
