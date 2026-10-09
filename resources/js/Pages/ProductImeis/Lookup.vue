<script setup>
import { formatCurrency, formatDateTime } from '@/utils/format'
import { computed, ref } from 'vue'
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    History,
    Package,
    Search,
    ShieldCheck,
    ShieldX,
    Wrench,
} from 'lucide-vue-next'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import api from '@/Services/api'

defineOptions({
    layout: AdminLayout,
})

const keyword = ref('')
const loading = ref(false)
const result = ref(null)
const errorMessage = ref('')

const money = formatCurrency

const formatDate = (value) => {
    if (!value) return '-'

    return formatDateTime(value)
}

const variantText = computed(() => {
    const attributes = result.value?.imei?.variant?.attributes

    if (!attributes || typeof attributes !== 'object') {
        return result.value?.imei?.variant?.sku || '-'
    }

    return Object.values(attributes)
        .map(value => {
            if (!value || typeof value !== 'object') return value

            return value.value || value.name || value.label || ''
        })
        .filter(Boolean)
        .join(' / ') || result.value?.imei?.variant?.sku || '-'
})

const warrantyMeta = computed(() => {
    const status = result.value?.warranty?.status

    if (status === 'active') {
        return {
            icon: ShieldCheck,
            class: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        }
    }

    if (status === 'expired') {
        return {
            icon: ShieldX,
            class: 'border-rose-200 bg-rose-50 text-rose-700',
        }
    }

    return {
        icon: Clock,
        class: 'border-slate-200 bg-slate-50 text-slate-600',
    }
})

const hasExtraInfo = computed(() => {
    const extraInfo = result.value?.imei?.extra_info || {}

    return Boolean(
        extraInfo.note ||
        extraInfo.image_path
    )
})

const extraInfoImageUrl = computed(() => {
    const imagePath = result.value?.imei?.extra_info?.image_path

    return imagePath ? `/storage/${imagePath}` : null
})

const lookup = async () => {
    const imei = keyword.value.trim()

    if (!imei) return

    loading.value = true
    errorMessage.value = ''
    result.value = null

    try {
        const response = await api.get('/api/imeis/lookup', {
            params: {
                imei,
            },
        })

        result.value = response.data
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ||
            'Không tra cứu được IMEI. Vui lòng thử lại.'
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="space-y-5 p-6">
        <PageHeader
            title="Tra cứu IMEI"
            description="Kiểm tra thông tin máy, lịch sử nhập bán, sửa chữa và bảo hành"
        />

        <DataPanel>
            <div class="border-b border-slate-200 p-4">
                <div class="flex flex-col gap-3 md:flex-row">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="keyword"
                            type="text"
                            placeholder="Nhập hoặc quét IMEI / Serial..."
                            class="w-full rounded-xl border border-slate-200 py-3 pl-10 pr-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            @keyup.enter="lookup"
                        >
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="loading || !keyword.trim()"
                        @click="lookup"
                    >
                        <Search class="h-4 w-4" />
                        {{ loading ? 'Đang tra cứu...' : 'Tra cứu' }}
                    </button>
                </div>

                <p
                    v-if="errorMessage"
                    class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
                >
                    {{ errorMessage }}
                </p>
            </div>

            <div
                v-if="!result && !loading"
                class="flex flex-col items-center justify-center px-6 py-16 text-center"
            >
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <Search class="h-7 w-7" />
                </div>
                <div class="mt-4 text-base font-bold text-slate-900">
                    Nhập IMEI để bắt đầu tra cứu
                </div>
                <div class="mt-1 max-w-lg text-sm text-slate-500">
                    Trang này dùng để xem nhanh lịch sử mua bán, sửa chữa, thông tin thiết bị và tình trạng bảo hành.
                </div>
            </div>

            <div
                v-if="result"
                class="space-y-5 p-4"
            >
                <div
                    class="rounded-2xl border p-4"
                    :class="result.exists ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'"
                >
                    <div class="flex items-start gap-3">
                        <CheckCircle2
                            v-if="result.exists"
                            class="mt-0.5 h-5 w-5 shrink-0"
                        />
                        <AlertTriangle
                            v-else
                            class="mt-0.5 h-5 w-5 shrink-0"
                        />
                        <div>
                            <div class="font-bold">
                                {{ result.message }}
                            </div>
                            <div class="mt-1 text-sm">
                                {{ result.exists ? 'Đã tìm thấy dữ liệu IMEI trong hệ thống.' : 'Chưa có dữ liệu IMEI trong hệ thống.' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-400">
                            <Package class="h-4 w-4" />
                            Sản phẩm
                        </div>
                        <div class="mt-2 font-bold text-slate-900">
                            {{ result.imei?.product?.name || '-' }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            SKU: {{ result.imei?.product?.sku || '-' }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-bold uppercase text-slate-400">
                            IMEI / Serial
                        </div>
                        <div class="mt-2 font-mono text-sm font-bold text-slate-900">
                            {{ result.imei?.imei || keyword }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ result.imei?.serial || '-' }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-bold uppercase text-slate-400">
                            Biến thể
                        </div>
                        <div class="mt-2 font-semibold text-slate-900">
                            {{ variantText }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            Trạng thái: {{ result.imei?.status || '-' }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border p-4"
                        :class="warrantyMeta.class"
                    >
                        <div class="flex items-center gap-2 text-xs font-bold uppercase">
                            <component :is="warrantyMeta.icon" class="h-4 w-4" />
                            BH NCC
                        </div>
                        <div class="mt-2 font-black">
                            {{ result.warranty?.label || '-' }}
                        </div>
                        <div
                            v-if="result.warranty?.remaining_days !== null"
                            class="mt-1 text-xs font-semibold"
                        >
                            {{ result.warranty.remaining_days >= 0 ? `Còn ${result.warranty.remaining_days} ngày` : `Quá hạn ${Math.abs(result.warranty.remaining_days)} ngày` }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex items-center gap-2 font-bold text-slate-900">
                            <ShieldCheck class="h-5 w-5 text-indigo-600" />
                            Thông tin BH nhà cung cấp
                        </div>
                        <div class="space-y-2 text-sm text-slate-600">
                            <div class="flex justify-between gap-4">
                                <span>Ngày bắt đầu</span>
                                <span class="font-semibold text-slate-900">{{ formatDate(result.warranty?.start_at) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>Hạn BH NCC</span>
                                <span class="font-semibold text-slate-900">{{ formatDate(result.warranty?.expired_at) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>Giá nhập hiện tại</span>
                                <span class="font-semibold text-slate-900">{{ money(result.imei?.cost_price) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>Giá bán hiện tại</span>
                                <span class="font-semibold text-slate-900">{{ money(result.imei?.sell_price) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 lg:col-span-2">
                        <div class="mb-3 flex items-center gap-2 font-bold text-slate-900">
                            <History class="h-5 w-5 text-indigo-600" />
                            Tổng quan nghiệp vụ
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-xs font-bold uppercase text-slate-400">Số lần nhập</div>
                                <div class="mt-1 text-2xl font-black text-slate-900">{{ result.summary?.import_count || 0 }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-xs font-bold uppercase text-slate-400">Số lần bán</div>
                                <div class="mt-1 text-2xl font-black text-slate-900">{{ result.summary?.sale_count || 0 }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <div class="text-xs font-bold uppercase text-slate-400">Số lần sửa</div>
                                <div class="mt-1 text-2xl font-black text-slate-900">{{ result.summary?.repair_count || 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasExtraInfo"
                    class="rounded-2xl border border-slate-200 bg-white p-4"
                >
                    <div class="mb-3 font-bold text-slate-900">
                        Thông tin thiết bị lưu kèm
                    </div>
                    <div class="grid gap-3 text-sm lg:grid-cols-[1fr_auto]">
                        <div v-if="result.imei.extra_info?.note">
                            <div class="text-xs font-bold uppercase text-slate-400">Thông tin</div>
                            <div class="mt-1 whitespace-pre-line font-semibold text-slate-900">{{ result.imei.extra_info.note }}</div>
                        </div>
                        <a
                            v-if="extraInfoImageUrl"
                            :href="extraInfoImageUrl"
                            target="_blank"
                            class="inline-flex items-center justify-center rounded-xl border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100"
                        >
                            Xem ảnh thiết bị
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 font-bold text-slate-900">Lịch sử nhập</div>
                        <div
                            v-if="result.imports?.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="item in result.imports"
                                :key="item.id"
                                class="rounded-xl bg-slate-50 p-3 text-sm"
                            >
                                <div class="font-bold text-slate-900">{{ item.code || 'Phiếu nhập' }}</div>
                                <div class="mt-1 text-slate-600">Giá nhập: {{ money(item.cost_price) }}</div>
                                <div class="text-slate-600">Giá bán: {{ money(item.sell_price) }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ formatDate(item.happened_at || item.created_at) }}</div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-400">Chưa có lịch sử nhập chi tiết.</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 font-bold text-slate-900">Lịch sử bán</div>
                        <div
                            v-if="result.sales?.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="sale in result.sales"
                                :key="sale.id"
                                class="rounded-xl bg-slate-50 p-3 text-sm"
                            >
                                <div class="font-bold text-slate-900">{{ sale.code || 'Hóa đơn' }}</div>
                                <div class="mt-1 text-slate-600">Khách: {{ sale.customer_name || '-' }}</div>
                                <div class="text-slate-600">Giá bán: {{ money(sale.unit_price) }}</div>
                                <div class="text-slate-600">Thành tiền: {{ money(sale.subtotal) }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ formatDate(sale.sold_at) }}</div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-400">Chưa có lịch sử bán.</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex items-center gap-2 font-bold text-slate-900">
                            <Wrench class="h-5 w-5 text-indigo-600" />
                            Lịch sử sửa chữa
                        </div>
                        <div
                            v-if="result.repairs?.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="repair in result.repairs"
                                :key="repair.id"
                                class="rounded-xl bg-slate-50 p-3 text-sm"
                            >
                                <div class="font-bold text-slate-900">{{ repair.code || 'Phiếu sửa' }}</div>
                                <div class="mt-1 text-slate-600">{{ repair.repair_request || repair.note || '-' }}</div>
                                <div class="text-slate-600">Chi phí: {{ money(repair.final_cost || repair.estimated_cost) }}</div>
                                <div class="text-slate-600">Trạng thái: {{ repair.status || '-' }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ formatDate(repair.received_at) }}</div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-400">Chưa có lịch sử sửa chữa.</div>
                    </div>
                </div>
            </div>
        </DataPanel>
    </div>
</template>
