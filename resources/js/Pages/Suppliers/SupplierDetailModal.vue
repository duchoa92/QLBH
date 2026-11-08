<script setup>
import { ref, onMounted } from 'vue'
import { X, SquarePen, Phone, Mail, MapPin, Building, DollarSign, CreditCard, ExternalLink } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'
import axios from 'axios'

const props = defineProps({
    recordId: {
        type: [Number, String],
        required: true
    },
    supplier: Object, 
})

const emit = defineEmits(['close', 'edit'])

const loading = ref(false)
const supplierData = ref(props.supplier || null)

const fetchSupplierDetail = async () => {
    if (supplierData.value && (supplierData.value.bank_accounts || supplierData.value.address)) return
    loading.value = true
    try {
        const response = await axios.get(route('suppliers.show', props.recordId))
        supplierData.value = response.data.supplier || response.data
    } catch (error) {
        console.error('Không thể tải thông tin nhà cung cấp', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchSupplierDetail()
})

// Hàm mở mã QR thanh toán VietQR tự động điền thông tin
const openVietQrModal = (acc) => {
    const amount = supplierData.value.debt_balance > 0 ? supplierData.value.debt_balance : ''
    const addInfo = encodeURIComponent(`Thanh toan NCC ${supplierData.value.name}`)
    const url = `https://img.vietqr.io/image/${acc.bank_bin}-${acc.bank_account}-compact2.jpg?amount=${amount}&addInfo=${addInfo}&accountName=${encodeURIComponent(acc.bank_account_name || '')}`
    window.open(url, '_blank')
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="relative w-full max-w-3xl rounded-xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">
                        {{ supplierData?.name || 'Đang tải...' }}
                    </h3>
                    
                    <p class="text-base text-slate-500">{{ supplierData.code || '—' }}</p>
                
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        v-if="supplierData"
                        @click="$emit('edit', supplierData); $emit('close')" 
                        class="flex items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-100 transition"
                    >
                        <SquarePen class="h-4 w-4" /> Sửa
                    </button>
                    <button 
                        @click="$emit('close')" 
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto space-y-6 flex-1">
                <!-- Loading State -->
                <div v-if="loading" class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>

                <template v-else-if="supplierData">
                    <!-- Thẻ tổng quan (Mã & Công nợ) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                     

                        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50/50 flex items-center gap-4">
                            <div class="rounded-lg p-3" :class="supplierData.debt_balance > 0 ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'">
                                <DollarSign class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 font-medium">Tổng công nợ hiện tại</p>
                                <p class="text-base font-bold" :class="supplierData.debt_balance > 0 ? 'text-rose-600' : 'text-slate-900'">
                                    {{ formatMoney(supplierData.debt_balance) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Thông tin liên hệ -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Thông tin liên hệ</h4>
                        <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 text-sm">
                            <div class="flex items-center px-4 py-3 gap-3">
                                <Phone class="h-4 w-4 text-slate-400 shrink-0" />
                                <span class="text-slate-500 w-32 shrink-0">Số điện thoại:</span>
                                <span class="font-medium text-slate-900">{{ supplierData.phone || '—' }}</span>
                            </div>
                            <div class="flex items-center px-4 py-3 gap-3">
                                <Mail class="h-4 w-4 text-slate-400 shrink-0" />
                                <span class="text-slate-500 w-32 shrink-0">Email:</span>
                                <span class="font-medium text-slate-900">{{ supplierData.email || '—' }}</span>
                            </div>
                            <div class="flex items-center px-4 py-3 gap-3">
                                <MapPin class="h-4 w-4 text-slate-400 shrink-0" />
                                <span class="text-slate-500 w-32 shrink-0">Địa chỉ:</span>
                                <span class="font-medium text-slate-900">{{ supplierData.address || '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Thông tin tài khoản ngân hàng & Thanh toán nhanh -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tài khoản ngân hàng & Thanh toán</h4>
                        
                        <div v-if="(supplierData.bank_accounts && supplierData.bank_accounts.length) || supplierData.bank_account">
                            <div class="grid grid-cols-1 gap-3">
                                <div 
                                    v-for="(acc, index) in (supplierData.bank_accounts?.length ? supplierData.bank_accounts : [{ bank_name: supplierData.bank_name, bank_bin: supplierData.bank_bin, bank_account: supplierData.bank_account, bank_account_name: supplierData.bank_account_name, is_default: true }])" 
                                    :key="index"
                                    class="rounded-xl border border-slate-200 p-4 bg-slate-50/40 text-sm space-y-3 relative"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2 font-semibold text-slate-900">
                                            <CreditCard class="h-4 w-4 text-blue-600" />
                                            {{ acc.bank_name || 'Ngân hàng' }} 
                                            <span v-if="acc.is_default" class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Mặc định</span>
                                        </div>

                                        <!-- Nút bấm tạo VietQR thanh toán nhanh -->
                                        <button 
                                            v-if="acc.bank_bin && acc.bank_account"
                                            @click="openVietQrModal(acc)"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition shadow-sm"
                                        >
                                            <ExternalLink class="h-3.5 w-3.5" /> Tạo QR Chuyển khoản
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-600 pt-1 border-t border-slate-100">
                                        <div>Số tài khoản: <b class="text-slate-900 font-mono">{{ acc.bank_account }}</b></div>
                                        <div>Chủ tài khoản: <b class="text-slate-900 uppercase">{{ acc.bank_account_name || '—' }}</b></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-xl border border-slate-200 p-4 text-center text-sm text-slate-400 bg-slate-50">
                            Chưa có thông tin tài khoản ngân hàng
                        </div>
                    </div>

                    <!-- Ghi chú thêm nếu có -->
                    <div v-if="supplierData.notes" class="space-y-2">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ghi chú</h4>
                        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50 text-sm text-slate-700 whitespace-pre-line">
                            {{ supplierData.notes }}
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="border-t border-slate-100 px-6 py-3 bg-slate-50 flex justify-end">
                <button 
                    @click="$emit('close')" 
                    class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300 transition"
                >
                    Đóng
                </button>
            </div>

        </div>
    </div>
</template>