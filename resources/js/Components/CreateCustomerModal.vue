<script setup>
import axios from 'axios'
import { reactive } from 'vue'

const props = defineProps({
    show: Boolean,
    initialName: String
})

const emit = defineEmits(['close', 'created'])

const form = reactive({
    full_name: props.initialName || '',
    phone: ''
})

const loading = ref(false)

const close = () => {
    emit('close')
}

const submit = async () => {
    loading.value = true

    try {
        const res = await axios.post('/api/customers', form)

        emit('created', res.data.data)

        form.full_name = ''
        form.phone = ''

        close()
    } finally {
        loading.value = false
    }
}

</script>


<template>
    <div v-if="show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">

        <div class="bg-white w-[400px] p-4 rounded shadow">

            <h2 class="text-lg font-bold mb-3">
                Tạo khách hàng mới
            </h2>

            <!-- NAME -->
            <input
                v-model="form.full_name"
                type="text"
                class="w-full border p-2 rounded mb-2"
                placeholder="Họ tên *"
            />

            <!-- PHONE -->
            <input
                v-model="form.phone"
                type="text"
                class="w-full border p-2 rounded mb-3"
                placeholder="Số điện thoại"
            />

            <!-- ACTION -->
            <div class="flex justify-end gap-2">

                <button
                    class="px-3 py-1 border rounded"
                    @click="close"
                >
                    Hủy
                </button>

                <button
                    :disabled="loading"
                    class="px-3 py-1 bg-blue-600 text-white rounded disabled:opacity-50"
                    @click="submit"
                >
                    {{ loading ? 'Đang lưu...' : 'Lưu' }}
                </button>

            </div>

        </div>

    </div>
</template>

