<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import RepairStatusProgress from './RepairStatusProgress.vue'
import RepairCompletionModal from './RepairCompletionModal.vue'
import { toast } from 'vue-sonner'

const props = defineProps({ repair: { type: Object, required: true } })
const emit = defineEmits(['close', 'updated', 'completed', 'pay'])

// Quản lý Tab: 'log' (Cập nhật nhật ký) | 'complete' (Hoàn tất & Tính tiền)
const activeTab = ref('log')

const form = useForm({
    description: '',
    issue: [],
    parts_needed: props.repair.parts_needed || '',
    expected_days: props.repair.expected_days ?? '',
    waiting_for_parts: Boolean(props.repair.waiting_for_parts),
    images: [],
})

const issueText = ref('')
const submit = (targetStatus = 'repairing') => {
    form.issue = issueText.value.split(',').map((item) => item.trim()).filter(Boolean)
    // Keep multipart uploads compatible with PHP request parsing.
    form.transform((data) => ({ ...data, status: targetStatus, _method: 'patch' }))
    form.post(route('repairs.update-status', props.repair.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.success(targetStatus === 'cancelled' ? 'Đã hủy phiếu sửa chữa' : 'Đã cập nhật tiến trình sửa chữa')
            emit('updated')
            emit('close')
        },
    })
}

const cancelRepair = () => {
    if (confirm('Bạn có chắc chắn muốn hủy phiếu sửa chữa này không?')) {
        form.waiting_for_parts = false
        submit('cancelled')
    }
}

const onCompleted = (repair) => {
    emit('completed', repair)
    emit('close')
}

const startPayment = () => {
    emit('pay', props.repair)
    emit('close')
}
</script>

<template>
    <BaseModal 
        :title="`Phiếu sửa · ${repair.code}`" 
        :size="activeTab === 'complete' ? 'lg' : 'md'" 
        @close="emit('close')"
    >
        <!-- TIẾN TRÌNH THỜI GIAN (PROGRESS BAR) -->
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50/50 p-3">
            <RepairStatusProgress :status="repair.status" :waiting-for-parts="repair.waiting_for_parts" />
        </div>

        <!-- Các tab nhật ký, hoàn tất và lịch sử -->
        <div v-if="['pending', 'repairing', 'done'].includes(repair.status)" class="mb-4 flex flex-wrap border-b border-slate-200">
            <button 
                type="button"
                @click="activeTab = 'log'"
                class="pb-2 px-4 text-xs font-bold border-b-2 transition"
                :class="activeTab === 'log' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
            >
                {{ repair.status === 'done' ? '💳 Thanh toán / Trả khách' : '📝 Cập nhật tiến trình' }}
            </button>
            <button v-if="['pending', 'repairing'].includes(repair.status)"
                type="button"
                @click="activeTab = 'complete'"
                :disabled="repair.waiting_for_parts"
                :title="repair.waiting_for_parts ? 'Xác nhận đã nhận linh kiện để tiếp tục' : ''"
                class="pb-2 px-4 text-xs font-bold border-b-2 transition"
                :class="[activeTab === 'complete' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-400 hover:text-slate-600', repair.waiting_for_parts ? 'cursor-not-allowed opacity-50' : '']"
            >
                ✅ Hoàn tất sửa & Tính phí
            </button>
            <button type="button" @click="activeTab = 'history'" class="pb-2 px-4 text-xs font-bold border-b-2 transition"
                :class="activeTab === 'history' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-400 hover:text-slate-600'">
                🕘 Lịch sử cập nhật
            </button>
        </div>

        <!-- TAB 1: CẬP NHẬT TIẾN TRÌNH & BÁO LINH KIỆN -->
        <form v-if="activeTab === 'log' && ['pending', 'repairing'].includes(repair.status)" id="repair-progress-form" class="space-y-3" @submit.prevent="submit()">
            <div v-if="repair.status === 'pending'" class="rounded-lg border border-blue-100 bg-blue-50 p-2.5 text-xs text-blue-800">
                💡 Phiếu sẽ tự động chuyển sang <strong>Đang sửa</strong> khi lưu tiến trình.
            </div>
            <div v-if="repair.waiting_for_parts" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-900">
                <span>Phiếu đang chờ <strong>{{ repair.parts_needed || 'linh kiện' }}</strong><span v-if="repair.expected_days !== null"> · dự kiến {{ repair.expected_days }} ngày</span>.</span>
                <button type="button" class="rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 font-semibold text-amber-800 hover:bg-amber-100" @click="form.waiting_for_parts = false">Đã nhận linh kiện</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="flex items-center">
                    <label class="flex w-full items-center gap-2 rounded-xl border border-slate-200 p-2.5 text-xs text-slate-700 hover:bg-slate-50 cursor-pointer">
                        <input v-model="form.waiting_for_parts" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                        <span class="font-semibold text-amber-700">Đang chờ linh kiện</span>
                    </label>
                </div>
            </div>

            <div v-if="form.waiting_for_parts" class="grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                <FloatingInput v-model="form.parts_needed" label="Tên linh kiện cần chờ *" id="repair_progress_parts" :error="form.errors.parts_needed" required />
                <FloatingInput v-model="form.expected_days" type="number" min="0" max="365" step="1" label="Dự kiến chờ (ngày) *" id="repair_progress_days" :error="form.expected_days" required />
            </div>

            <FloatingInput v-model="issueText" label="Lỗi phát sinh (nếu có, phân cách dấu phẩy)" id="repair_progress_issue" :error="form.errors.issue" />
            
            <FloatingInput v-model="form.description" label="Ghi chú công việc đã làm..." id="repair_progress_note" :error="form.errors.description" />

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Ảnh chụp tiến trình</label>
                <input class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200" type="file" accept="image/*" multiple @change="form.images = Array.from($event.target.files || [])" />
                <span v-if="form.images.length" class="mt-1 block text-[11px] text-emerald-600">Đã chọn {{ form.images.length }} ảnh</span>
            </div>
        </form>

        <!-- TAB 2: FORM HOÀN TẤT VÀ TÍNH TIỀN LINH KIỆN -->
        <div v-else-if="activeTab === 'complete' && ['pending', 'repairing'].includes(repair.status)">
            <RepairCompletionModal :repair="repair" @updated="onCompleted" />
        </div>

        <!-- Tab thanh toán cho phiếu đã hoàn tất -->
        <div v-else-if="activeTab === 'log' && repair.status === 'done'" class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-center">
            <h3 class="text-base font-bold text-emerald-900">Máy đã hoàn tất sửa chữa!</h3>
            <p class="mt-1 text-xs text-emerald-700">Tổng chi phí: <strong class="text-sm text-emerald-900">{{ Number(repair.final_cost || 0).toLocaleString('vi-VN') }} đ</strong></p>
            <ActionButton class="mt-3 w-full justify-center" @click="startPayment">Thanh toán & Trả máy cho khách</ActionButton>
        </div>

        <!-- Tab lịch sử cập nhật -->
        <div v-else-if="activeTab === 'history'" class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lịch sử tiến trình sửa chữa</p>
            <div v-if="repair.timelines?.length" class="relative space-y-3 pl-4 before:absolute before:bottom-2 before:left-[5px] before:top-2 before:w-px before:bg-slate-200">
                <article v-for="timeline in repair.timelines" :key="timeline.id" class="relative rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <span class="absolute -left-[15px] top-4 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-500 ring-1 ring-emerald-200" />
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h4 class="text-sm font-bold text-slate-800">{{ timeline.title }}</h4>
                        <span class="text-[11px] text-slate-400">{{ timeline.created_at }} · {{ timeline.user || 'Hệ thống' }}</span>
                    </div>
                    <p v-if="timeline.description" class="mt-1 whitespace-pre-line text-xs text-slate-600">{{ timeline.description }}</p>
                    <p v-if="timeline.issue?.length" class="mt-2 rounded-lg bg-rose-50 px-2.5 py-2 text-xs text-rose-700"><strong>Lỗi phát sinh:</strong> {{ timeline.issue.join(', ') }}</p>
                    <p v-if="timeline.waiting_for_parts && timeline.parts_needed" class="mt-2 rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-800">
                        <strong>Chờ linh kiện:</strong> {{ timeline.parts_needed }}<span v-if="timeline.expected_days !== null"> · Dự kiến {{ timeline.expected_days }} ngày</span>
                    </p>
                    <div v-if="timeline.images?.length" class="mt-2 flex flex-wrap gap-2">
                        <a v-for="image in timeline.images" :key="image.id" :href="image.url" target="_blank" rel="noopener" class="h-16 w-16 overflow-hidden rounded-lg border border-slate-200">
                            <img :src="image.url" alt="Ảnh tiến trình sửa chữa" class="h-full w-full object-cover" />
                        </a>
                    </div>
                </article>
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Chưa có lịch sử cập nhật.</div>
        </div>

        <!-- FOOTER ĐIỀU HƯỚNG -->
        <template #footer>
            <div class="flex items-center justify-between w-full">
                <!-- Nút Hủy phiếu đặt góc trái biệt lập để tránh bấm nhầm -->
                <div>
                    <button 
                        v-if="['pending', 'repairing'].includes(repair.status)" 
                        type="button" 
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition" 
                        :disabled="form.processing" 
                        @click="cancelRepair"
                    >
                        🚨 Hủy phiếu này
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <ActionButton variant="secondary" @click="emit('close')">Đóng</ActionButton>
                    
                    <ActionButton 
                        v-if="activeTab === 'log' && ['pending', 'repairing'].includes(repair.status)" 
                        type="submit" 
                        form="repair-progress-form" 
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Đang lưu...' : (repair.waiting_for_parts && !form.waiting_for_parts ? 'Xác nhận đã có linh kiện' : form.waiting_for_parts ? 'Lưu trạng thái chờ linh kiện' : 'Lưu nhật ký') }}
                    </ActionButton>
                </div>
            </div>
        </template>
    </BaseModal>
</template>
