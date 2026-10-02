<script setup>
import axios from 'axios'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useDebounceSearch } from '@/Composables/useDebounceSearch'
import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import CreateCustomerModal from '@/Modules/POS/Customer/Components/CreateCustomerModal.vue'
import { customerService } from '@/Modules/POS/Customer/Services/customerService'
import { toast } from 'vue-sonner'
import { UserRoundPlus, X } from 'lucide-vue-next'

const emit = defineEmits(['selected', 'next', 'update:modelValue'])
const props = defineProps({
    modelValue: { type: String, default: '' },
    initialCustomer: { type: Object, default: null },
})

const selectedCustomer = ref(null)
const showCreateModal = ref(false)
const rootRef = ref(null)
const suggestionsOpen = ref(false)

const { keyword, results: customers, search } = useDebounceSearch(async (searchText) => {
    const response = await axios.get('/api/customers/search', { params: { search: searchText } })
    return response.data?.data ?? response.data ?? []
}, 250)

const selectCustomer = (customer) => {
    selectedCustomer.value = customer
    suggestionsOpen.value = false
    keyword.value = customer.full_name
    customers.value = []
    emit('update:modelValue', customer.full_name)
    emit('selected', customer)
}

const clearCustomer = () => {
    selectedCustomer.value = null
    suggestionsOpen.value = false
    keyword.value = ''
    customers.value = []
    emit('update:modelValue', '')
    emit('selected', null)
}

const handleInput = () => {
    selectedCustomer.value = null
    suggestionsOpen.value = true
    emit('selected', null)
    emit('update:modelValue', keyword.value)
    search()
}

const saveNewCustomer = async (data) => {
    try {
        const customer = await customerService.create(data)
        selectCustomer({ ...customer, debt_balance: 0 })
        showCreateModal.value = false
        toast.success('Đã thêm khách hàng mới')
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể tạo khách hàng')
    }
}

const { activeIndex, itemRefs, setItemRef, onKeyDown, setActive } = useAutocompleteKeyboard(customers, (customer) => selectCustomer(customer))

const hideSuggestionsOnOutsideClick = (event) => {
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        suggestionsOpen.value = false
        customers.value = []
    }
}

const handleFocus = () => {
    if (keyword.value.trim()) {
        suggestionsOpen.value = true
        search()
    }
}

onMounted(() => {
    document.addEventListener('click', hideSuggestionsOnOutsideClick)
    if (props.initialCustomer) {
        selectedCustomer.value = {
            ...props.initialCustomer,
            full_name: props.initialCustomer.full_name || props.initialCustomer.name || '',
            cccd: props.initialCustomer.cccd || props.initialCustomer.identity_card || '',
        }
        keyword.value = selectedCustomer.value.full_name
    }
})
onBeforeUnmount(() => document.removeEventListener('click', hideSuggestionsOnOutsideClick))

</script>

<template>
    <div ref="rootRef" class="relative">
        <div v-if="selectedCustomer" class="flex min-h-10 items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
            <div class="min-w-0 text-sm">
                <div class="truncate font-semibold text-blue-950">{{ selectedCustomer.full_name }}</div>
                <div class="truncate text-xs text-blue-700">
                    {{ selectedCustomer.phone || 'Chưa có số điện thoại' }}
                    <span v-if="Number(selectedCustomer.debt_balance || 0) > 0" class="ml-2 font-semibold text-rose-600">
                        Nợ: {{ Number(selectedCustomer.debt_balance).toLocaleString('vi-VN') }} đ
                    </span>
                </div>
            </div>
            <button type="button" class="shrink-0 rounded-full p-1 text-blue-500 hover:bg-rose-100 hover:text-rose-600" title="Bỏ chọn khách hàng" @click="clearCustomer">
                <X :size="16" />
            </button>
        </div>

        <div v-else class="relative">
            <FloatingInput
                v-model="keyword"
                label="Tìm khách hàng theo tên, SĐT, mã hoặc CCCD"
                id="repair_customer_search"
                autocomplete="off"
                @input="handleInput"
                @focus="handleFocus"
                @keydown="onKeyDown"
            />
            <button v-if="keyword.trim()" type="button" title="Thêm khách hàng mới" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1.5 text-blue-600 hover:bg-blue-50" @click="showCreateModal = true">
                <UserRoundPlus :size="18" />
            </button>
            <div v-if="suggestionsOpen && customers.length" class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg">
                <button
                    v-for="(customer, index) in customers"
                    :key="customer.id"
                    :ref="(el) => { setItemRef(el, index) }"
                    type="button"
                    :class="['block w-full border-b border-slate-100 p-2.5 text-left last:border-0', index === activeIndex ? 'bg-blue-50' : 'hover:bg-slate-50']"
                    @mouseenter="setActive(index)"
                    @mousedown.prevent="selectCustomer(customer)"
                >
                    <div class="text-sm font-semibold text-slate-900">{{ customer.full_name }}</div>
                    <div class="mt-0.5 flex justify-between gap-3 text-xs text-slate-500">
                        <span>{{ customer.phone || 'Chưa có SĐT' }}</span>
                        <span v-if="Number(customer.debt_balance || 0) > 0" class="font-semibold text-rose-600">Nợ {{ Number(customer.debt_balance).toLocaleString('vi-VN') }} đ</span>
                    </div>
                </button>
            </div>
        </div>

        <CreateCustomerModal :show="showCreateModal" :keyword="keyword" @close="showCreateModal = false" @save="saveNewCustomer" />
    </div>
</template>
