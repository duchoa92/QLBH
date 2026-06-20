
<script setup>
import axios from 'axios'
import { ref } from 'vue'

const keyword = ref('')
const results = ref([])
const loading = ref(false)

const emit = defineEmits(['selected', 'create', 'walkin'])

let timeout = null

// tìm khách hàng theo từ khóa
const searchCustomer = () => {
    clearTimeout(timeout)

    timeout = setTimeout(async () => {
        loading.value = true

        try {
            const res = await axios.get('/api/customers/search', {
                params: { search: keyword.value }
            })

            results.value = res.data

            // AUTO ERP LOGIC
            if (keyword.value.length >= 9) {
                if (res.data.length === 1) {
                    select(res.data[0])
                }

                if (res.data.length === 0) {
                    emit('create', keyword.value)
                }
            }

        } finally {
            loading.value = false
        }

    }, 300)
}


// check nhanh xem có khách nào khớp không, nếu có thì auto chọn luôn, không có thì emit tạo mới
const quickCheckCustomer = () => {
    // Nếu không có kết quả nào → tạo mới
    if (results.value.length === 0) {
        emit('create', keyword.value)
        return
    }
    // Nếu chỉ có 1 kết quả → auto chọn
    if (results.value.length === 1) {
        select(results.value[0])
    }
}

// chọn khách hàng từ kết quả tìm kiếm
const select = (customer) => {
    emit('selected', customer)
    results.value = []
    keyword.value = customer.full_name
}


 // Tìm nhanh khi nhập đủ 9 số (điện thoại)
const handleInput = () => {
    searchCustomer()

    // Nếu là số điện thoại đủ dài → auto check nhanh
    if (/^\d{9,}$/.test(keyword.value)) {
        quickCheckCustomer()
    }
}


</script>

<template>
    <div class="border p-2 rounded">

        <!-- tìm kiếm khách hàng -->
        <input
            v-model="keyword"
            @input="handleInput"
            placeholder="Tìm SĐT / Tên khách"
            class="w-full border p-2 rounded"
        />

        <div v-if="results.length" class="border mt-2 rounded bg-white">
            <div
                v-for="c in results"
                :key="c.id"
                @click="select(c)"
                class="p-2 hover:bg-gray-100 cursor-pointer"
            >
                {{ c.full_name }} - {{ c.phone }}
            </div>
        </div>

        <div class="mt-2 flex justify-between">
            <button @click="$emit('create', keyword)" class="text-blue-600">
                + Tạo khách mới
            </button>

            <button @click="$emit('walkin')" class="text-gray-500">
                Khách lẻ
            </button>
        </div>

        <div v-if="!loading && keyword.length >= 3 && results.length === 0"
            class="p-2 text-gray-500 text-sm">
            Không tìm thấy khách hàng
            <button
                class="text-blue-600 ml-2"
                @click="$emit('create', keyword)"
            >
                + Tạo mới
            </button>
        </div>

    </div>
</template>
