<script setup>
import axios from 'axios'
import { ref, computed } from 'vue'
import { filterByKeywords, highlightText } from '@/utils/searchHelper'

const keyword = ref('')
const results = ref([])
const loading = ref(false)

const emit = defineEmits(['selected', 'create', 'walkin'])

let timeout = null

// Lọc kết quả tìm kiếm theo từ khóa tiếng Việt gần đúng & không dấu
const filteredCustomers = computed(() => {
    return filterByKeywords(results.value, keyword.value, (c) => `${c.full_name ?? c.name ?? ''} ${c.phone ?? ''}`)
})

// Tìm khách hàng theo từ khóa
const searchCustomer = () => {
    clearTimeout(timeout)

    timeout = setTimeout(async () => {
        if (!keyword.value.trim()) {
            results.value = []
            return
        }

        loading.value = true

        try {
            const res = await axios.get('/api/customers/search', {
                params: { search: keyword.value }
            })

            results.value = res.data || []

            // AUTO ERP LOGIC: Khi nhập từ 9 số trở lên
            if (keyword.value.length >= 9) {
                if (results.value.length === 1) {
                    select(results.value[0])
                }

                if (results.value.length === 0) {
                    emit('create', keyword.value)
                }
            }

        } catch (error) {
            console.error('Lỗi tìm kiếm khách hàng:', error)
            results.value = []
        } finally {
            loading.value = false
        }

    }, 300)
}

// Chọn khách hàng từ kết quả tìm kiếm
const select = (customer) => {
    emit('selected', customer)
    results.value = []
    keyword.value = customer.full_name || customer.name || ''
}

const handleInput = () => {
    searchCustomer()
}
</script>

<template>
    <div class="border p-2 rounded">

        <!-- Tìm kiếm khách hàng -->
        <input
            v-model="keyword"
            @input="handleInput"
            placeholder="Tìm SĐT / Tên khách"
            class="w-full border p-2 rounded"
        />

        <!-- Danh sách kết quả -->
        <div v-if="filteredCustomers.length" class="border mt-2 rounded bg-white max-h-60 overflow-y-auto">
            <div
                v-for="c in filteredCustomers"
                :key="c.id"
                @click="select(c)"
                class="p-2 hover:bg-gray-100 cursor-pointer flex justify-between items-center"
            >
                <!-- Highlight tên khách hàng -->
                <span class="font-medium" v-html="highlightText(c.full_name || c.name, keyword)"></span>
                
                <!-- Highlight số điện thoại -->
                <span class="text-sm text-gray-500 ml-2" v-html="highlightText(c.phone, keyword)"></span>
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

        <div v-if="!loading && keyword.length >= 3 && filteredCustomers.length === 0"
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