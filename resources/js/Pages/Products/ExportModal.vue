<script setup>
import { ref, onBeforeUnmount } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import axios from 'axios'
import { Image, Sheet } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const exporting = ref(false)
const progress = ref(0)
const withImages = ref(false) // kèm ảnh: tải về file .zip (Excel + thư mục images)
let interval = null

onBeforeUnmount(() => {
    if (interval) clearInterval(interval)
})

// Lấy thông báo lỗi từ máy chủ (422 có message hoặc errors), nếu không có thì dùng câu mặc định
const apiError = (e, fallback) => {
    const data = e?.response?.data
    if (data?.message && !data?.errors) return data.message
    if (data?.error) return data.error
    const first = data?.errors ? Object.values(data.errors)[0] : null
    return (Array.isArray(first) ? first[0] : first) || data?.message || fallback
}

const stop = () => {
    if (interval) clearInterval(interval)
    interval = null
    exporting.value = false
}

const exportFile = async () => {
    if (exporting.value) return

    try {
        exporting.value = true
        progress.value = 0

        const res = await axios.post(route('products.export.start'), { with_images: withImages.value })
        const id = res.data.id
        const startedAt = Date.now()

        interval = setInterval(async () => {
            // Quá 15 phút mà chưa xong (thường do chưa chạy queue worker) thì thôi chờ
            if (Date.now() - startedAt > 15 * 60 * 1000) {
                stop()
                toast.error('Xuất file quá lâu. Vui lòng kiểm tra tiến trình xử lý nền (queue) rồi thử lại.')
                return
            }

            try {
                const check = await axios.get(route('products.export.check', { id }))

                progress.value = check.data.progress || 0

                if (check.data.status === 'failed') {
                    stop()
                    toast.error('Xuất file thất bại, vui lòng thử lại.')
                    return
                }

                if (check.data.status === 'done') {
                    clearInterval(interval)
                    interval = null
                    progress.value = 100

                    setTimeout(() => {
                        window.location.href = route('products.export.download', id)
                        toast.success('Xuất file thành công!')
                        exporting.value = false
                    }, 500)
                }
            } catch (e) {
                stop()
                toast.error(apiError(e, 'Không kiểm tra được tiến trình xuất file'))
            }
        }, 800)
    } catch (e) {
        stop()
        toast.error(apiError(e, 'Không thể bắt đầu xuất file'))
    }
}
</script>

<template>
    <BaseModal title="Xuất danh sách sản phẩm" size="md" @close="closeModal()">
        <div class="space-y-4 p-2">
            <p class="text-sm text-slate-600">
                Xuất toàn bộ sản phẩm (không gồm thùng rác) ra file Excel với đúng các cột của file mẫu nhập,
                nên có thể sửa rồi nhập lại.
            </p>

            <label class="flex cursor-pointer items-start gap-2 rounded-lg border bg-slate-50 p-3 text-sm">
                <input
                    v-model="withImages"
                    type="checkbox"
                    :disabled="exporting"
                    class="mt-0.5 rounded border-slate-300 text-green-600 focus:ring-green-500"
                >
                <span>
                    <span class="flex items-center gap-1 font-semibold text-slate-700"><Image :size="16" /> Kèm hình ảnh sản phẩm</span>
                    <span class="mt-0.5 block text-xs text-slate-500">
                        Tải về một file .zip gồm file Excel và thư mục <code>images</code>. Khi nhập lại, chọn file Excel
                        ở ô nhập file và chọn file .zip này ở ô chọn ảnh. Xuất kèm ảnh sẽ lâu hơn khi có nhiều ảnh.
                    </span>
                </span>
            </label>

            <div class="flex justify-end gap-2 border-t pt-3">
                <button @click="closeModal()" class="rounded-lg border px-4 py-2 text-slate-600 hover:bg-slate-50">Đóng</button>

                <button
                    @click="exportFile"
                    :disabled="exporting"
                    class="relative flex items-center justify-center gap-2 overflow-hidden rounded-lg border bg-green-600 px-4 py-2 text-white hover:bg-green-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <div
                        v-if="exporting"
                        class="absolute left-0 top-0 h-full bg-blue-100 transition-all duration-300"
                        :style="{ width: progress + '%' }"
                    ></div>

                    <svg v-if="exporting" class="z-10 h-4 w-4 animate-spin" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z" />
                    </svg>

                    <span class="z-10 flex gap-1">
                        <template v-if="exporting">Đang xuất {{ progress }}%</template>
                        <template v-else><Sheet /> {{ withImages ? 'Xuất Excel + ảnh (.zip)' : 'Xuất Excel' }}</template>
                    </span>
                </button>
            </div>
        </div>
    </BaseModal>
</template>
