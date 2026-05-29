<script setup>

defineProps({

    show: {
        type: Boolean,
        default: false,
    },

    holdName: {
        type: String,
        default: '',
    },
})

defineEmits([
    'close',
    'save',
    'update:holdName',
])

</script>

<template>

    <div
        v-if="show"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >

        <div class="bg-white w-[400px] rounded-xl p-5 shadow-xl">

            <div class="text-lg font-bold mb-4">
                Lưu tạm hóa đơn
            </div>

            <div>

                <label class="block mb-2 text-sm font-medium">
                    Tên hóa đơn
                </label>

                <input
                    :value="holdName"
                    @input="$emit(
                        'update:holdName',
                        $event.target.value
                    )"
                    type="text"
                    class="w-full border rounded-lg px-3 py-2"
                    placeholder="Nhập tên hóa đơn"
                />

            </div>

            <div class="flex justify-end gap-2 mt-5">

                <button
                    @click="$emit('close')"
                    class="px-4 py-2 border rounded-lg"
                >
                    Hủy
                </button>

                <button
                    @click="$emit('save')"
                    class="px-4 py-2 bg-yellow-500 text-white rounded-lg"
                >
                    Lưu tạm
                </button>

            </div>

        </div>

    </div>

</template>