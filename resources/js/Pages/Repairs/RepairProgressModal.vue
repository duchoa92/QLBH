<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import RepairCompletionModal from './RepairCompletionModal.vue'
import RepairPartsPicker from './RepairPartsPicker.vue'
import { toast } from 'vue-sonner'

const props = defineProps({ repair: { type: Object, required: true }, embedded: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'updated', 'completed', 'pay', 'stage-change'])

// Quy trình hiện tại và lịch sử sửa chữa.
const activeTab = ref(['returned', 'cancelled'].includes(props.repair.status) ? 'history' : 'log')
const completionStep = ref(false)
const hasRepairProgress = ref(['repairing', 'done'].includes(props.repair.status))
// Phiếu đang chờ khi mở lại sẽ mặc định ở chế độ tiếp tục sửa.
const waitingMode = ref('repair_now')
const isWaiting = computed(() => waitingMode.value !== 'repair_now')
const completionModalRef = ref(null)
const selectedParts = ref([])
const payImmediately = ref(true)
const progressImagePreviews = ref([])
const declineWarranty = ref(false)
const declineReason = ref('')
const continueAsService = ref(false)
const decliningWarranty = ref(false)
const hasWarrantyCandidate = computed(() => {
    if (!props.repair.warranty_source_type || !props.repair.warranty_source_id || !props.repair.warranty_expires_at || props.repair.warranty_status === 'declined') return false
    const expiresAt = new Date(props.repair.warranty_expires_at)
    expiresAt.setHours(23, 59, 59, 999)
    return expiresAt.getTime() >= Date.now()
})

const form = useForm({
    description: '',
    issue: [],
    parts_needed: props.repair.parts_needed || '',
    expected_days: props.repair.expected_days ?? '',
    waiting_for_parts: isWaiting.value,
    images: [],
})

const issueText = ref('')
const updateProgressImages = (event) => {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    if (!files.length) return
    form.images = [...form.images, ...files]
    progressImagePreviews.value = [
        ...progressImagePreviews.value,
        ...files.map((file) => ({ file, url: URL.createObjectURL(file) })),
    ]
}
const removeProgressImage = (index) => {
    const [preview] = progressImagePreviews.value.splice(index, 1)
    if (preview) URL.revokeObjectURL(preview.url)
    form.images = progressImagePreviews.value.map((item) => item.file)
}
const clearProgressImagePreviews = () => {
    progressImagePreviews.value.forEach((preview) => URL.revokeObjectURL(preview.url))
    progressImagePreviews.value = []
}
onUnmounted(clearProgressImagePreviews)
watch(waitingMode, (value) => {
    form.waiting_for_parts = value !== 'repair_now'
})

const submit = (targetStatus = 'repairing', { advanceToCompletion = false } = {}) => new Promise((resolve) => {
    form.issue = issueText.value.split(',').map((item) => item.trim()).filter(Boolean)
    // Keep multipart uploads compatible with PHP request parsing.
    form.transform((data) => ({ ...data, waiting_mode: targetStatus === 'cancelled' ? 'repair_now' : waitingMode.value, waiting_for_parts: targetStatus !== 'cancelled' && isWaiting.value, status: targetStatus, _method: 'patch' }))
    form.post(route('repairs.update-status', props.repair.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            toast.success(targetStatus === 'cancelled' ? 'Đã hủy phiếu sửa chữa' : 'Đã cập nhật tiến trình sửa chữa')
            const refreshedRepair = page.props.repairs?.data?.find((item) => Number(item.id) === Number(props.repair.id))
            emit('updated', refreshedRepair || null)
            if (advanceToCompletion) {
                hasRepairProgress.value = true
                completionStep.value = true
                activeTab.value = 'log'
                emit('stage-change', 'complete')
            }
            if (!embedded || targetStatus === 'cancelled') emit('close')
            resolve(true)
        },
        onError: (errors) => {
            toast.error(Object.values(errors || {}).flat()[0] || 'Không thể cập nhật tiến trình sửa chữa')
            resolve(false)
        },
    })
})

const cancelRepair = () => {
    if (confirm('Bạn có chắc chắn muốn hủy phiếu sửa chữa này không?')) {
        form.waiting_for_parts = false
        submit('cancelled')
    }
}

const onCompleted = (repair) => {
    if (payImmediately.value) emit('completed', repair)
    else emit('updated', repair)
    emit('close')
}

const startPayment = () => {
    emit('pay', props.repair)
    emit('close')
}

const footerBusy = computed(() => form.processing || decliningWarranty.value || Boolean(completionModalRef.value?.saving))
const footerActionLabel = computed(() => footerBusy.value
    ? 'Đang xử lý...'
    : (completionStep.value
        ? (payImmediately.value ? 'Hoàn tất & thanh toán' : 'Hoàn tất sửa chữa')
        : (declineWarranty.value && !continueAsService.value
            ? 'Từ chối BH & kết thúc'
            : (isWaiting.value
            ? (waitingMode.value === 'parts' ? 'Lưu chờ linh kiện' : 'Lưu tạm chờ sửa')
            : 'Lưu tiến trình & tiếp tục'))))
const footerShowCancel = computed(() => !completionStep.value && ['pending', 'repairing'].includes(props.repair.status))
const footerShowSave = computed(() => activeTab.value !== 'history' && ['pending', 'repairing'].includes(props.repair.status))
const saveProgress = async () => {
    if (completionStep.value) {
        await completionModalRef.value?.submit()
        return
    }

    if (declineWarranty.value) {
        if (declineReason.value.trim().length < 5) {
            toast.error('Vui lòng nhập lý do từ chối bảo hành (ít nhất 5 ký tự)')
            return
        }
        if (!continueAsService.value) {
            decliningWarranty.value = true
            try {
                await axios.patch(route('repairs.warranty-decline', props.repair.id), { reason: declineReason.value.trim() })
                const declinedRepair = {
                    ...props.repair,
                    status: 'cancelled',
                    warranty_status: 'declined',
                    warranty_declined_at: new Date().toISOString(),
                    warranty_decline_reason: declineReason.value.trim(),
                }
                toast.success('Đã từ chối bảo hành và kết thúc phiếu')
                emit('updated', declinedRepair)
                emit('close')
            } catch (error) {
                const message = Object.values(error.response?.data?.errors || {}).flat()[0]
                toast.error(message || 'Không thể cập nhật trạng thái từ chối bảo hành')
            } finally {
                decliningWarranty.value = false
            }
            return
        }
    }

    if (isWaiting.value) {
        await submit()
        return
    }

    await submit('repairing', { advanceToCompletion: true })
}
const selectProgressMode = (value) => {
    waitingMode.value = value
    if (value !== 'repair_now') {
        declineWarranty.value = false
        continueAsService.value = false
        declineReason.value = ''
    }
}
const returnToProgress = () => {
    completionStep.value = false
    activeTab.value = 'log'
    emit('stage-change', 'progress')
}
const openCompletionStep = () => {
    if (!hasRepairProgress.value) return
    completionStep.value = true
    activeTab.value = 'log'
    emit('stage-change', 'complete')
}
const openProgressStep = () => {
    completionStep.value = false
    activeTab.value = 'log'
    emit('stage-change', 'progress')
}
const openHistory = () => {
    activeTab.value = 'history'
}
const closeProgress = () => emit('close')

defineExpose({ footerActionLabel, footerBusy, footerShowCancel, footerShowSave, footerShowBack: computed(() => completionStep.value), hasUnsavedChanges: computed(() => form.isDirty || Boolean(issueText.value.trim()) || Boolean(declineReason.value.trim()) || declineWarranty.value || continueAsService.value || form.images.length > 0), cancelRepair, saveProgress, returnToProgress, openCompletionStep, openProgressStep, openHistory, closeProgress })
</script>

<template>
    <BaseModal
        :embedded="embedded"
        :body-class="embedded ? 'p-0' : undefined"
        :title="`Phiếu sửa · ${repair.code}`" 
        size="lg"
        @close="emit('close')"
    >
        <!-- Bước cập nhật tiến trình; bước hoàn tất được chọn từ thanh tiến trình phía trên -->
        <Transition name="repair-step">
        <div v-show="activeTab === 'log' && !completionStep && ['pending', 'repairing'].includes(repair.status)" id="repair-progress-form" class="space-y-3">

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button v-for="option in [{ value: 'repair_now', label: 'Sửa ngay', hint: 'Có thể tiếp tục xử lý' }, { value: 'parts', label: 'Chờ linh kiện', hint: 'Cần đặt linh kiện' }, { value: 'wait_repair', label: 'Tạm chờ sửa', hint: 'Chưa thể làm tiếp' }]" :key="option.value" type="button" class="rounded-xl border p-2.5 text-left transition" :class="waitingMode === option.value ? 'border-amber-400 bg-amber-50 ring-1 ring-amber-200' : 'border-slate-200 hover:bg-slate-50'" @click="selectProgressMode(option.value)"><span class="block text-xs font-bold text-slate-800">{{ option.label }}</span><span class="mt-0.5 block text-[10px] text-slate-500">{{ option.hint }}</span></button>
            </div>

            <section v-if="waitingMode === 'repair_now'" class="space-y-3 rounded-xl border border-slate-200 bg-white p-3">
                <div v-if="declineWarranty" class="space-y-3 rounded-lg border border-rose-200 bg-rose-50/50 p-3">
                    <div class="flex flex-wrap items-center gap-4">
                        <label v-if="hasWarrantyCandidate" class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-rose-800">
                            <input v-model="declineWarranty" type="checkbox" class="rounded border-rose-300 text-rose-600 focus:ring-rose-500" @change="continueAsService = false" />
                            <span>Từ chối bảo hành</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-800">
                            <input v-model="continueAsService" type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500" />
                            <span>Tiếp tục sửa dịch vụ</span>
                        </label>
                    </div>
                    <FloatingInput v-model="declineReason" label="Lý do từ chối bảo hành *" id="repair_warranty_decline_reason" :error="form.errors.decline_reason" required />
                    <p class="text-[11px] text-rose-700">Nếu không tiếp tục sửa dịch vụ, phiếu sẽ kết thúc và căn cứ bảo hành này bị vô hiệu hóa.</p>
                </div>
                <div v-else-if="hasWarrantyCandidate" class="flex flex-wrap items-center gap-4 rounded-lg border border-rose-200 bg-rose-50/70 px-3 py-2.5">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-rose-800">
                        <input v-model="declineWarranty" type="checkbox" class="rounded border-rose-300 text-rose-600 focus:ring-rose-500" @change="continueAsService = false" />
                        <span>Từ chối bảo hành</span>
                    </label>
                </div>
                <RepairPartsPicker v-if="!declineWarranty || continueAsService" v-model="selectedParts" />
            </section>

            <template v-if="!declineWarranty || continueAsService">
            <div v-if="waitingMode === 'parts'" class="grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                <FloatingInput v-model="form.parts_needed" label="Tên linh kiện cần chờ *" id="repair_progress_parts" :error="form.errors.parts_needed" required />
                <FloatingInput v-model="form.expected_days" type="number" min="0" max="365" step="1" label="Dự kiến chờ (ngày) *" id="repair_progress_days" />
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <FloatingInput v-model="issueText" label="Lỗi phát sinh (nếu có, phân cách dấu phẩy)" id="repair_progress_issue" :error="form.errors.issue" />
                <FloatingInput v-model="form.description" :label="waitingMode === 'wait_repair' ? 'Lý do chờ / ghi chú (không bắt buộc)' : 'Ghi chú công việc đã làm... '" id="repair_progress_note" :error="form.errors.description" />
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                    <label class="block text-xs font-semibold text-slate-700">Ảnh chụp tiến trình</label>
                    <span v-if="progressImagePreviews.length" class="text-[10px] text-slate-500">{{ progressImagePreviews.length }} ảnh</span>
                </div>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-blue-300 bg-blue-50/50 px-3 py-2.5 text-xs font-semibold text-blue-800 transition hover:border-blue-500 hover:bg-blue-50">
                    <input class="sr-only" type="file" accept="image/*" multiple @change="updateProgressImages" />
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7a2 2 0 012-2h2l1.2 1.5H18a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V7z"/><circle cx="12" cy="13" r="3" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M18 4v4m-2-2h4"/></svg>
                    <span>{{ progressImagePreviews.length ? 'Thêm ảnh' : 'Chọn ảnh' }}</span>
                    <span class="font-normal text-slate-500">Có thể thêm nhiều ảnh</span>
                </label>
                <div v-if="progressImagePreviews.length" class="mt-2 flex flex-wrap gap-2">
                    <div v-for="(preview, index) in progressImagePreviews" :key="preview.url" class="group relative h-20 w-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                        <img :src="preview.url" :alt="`Ảnh tiến trình ${index + 1}`" class="h-full w-full object-cover" />
                        <button type="button" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-slate-900/70 text-xs text-white opacity-0 transition group-hover:opacity-100" :aria-label="`Bỏ ảnh ${index + 1}`" @click="removeProgressImage(index)">×</button>
                    </div>
                </div>
                <span v-if="form.errors.images" class="mt-1 block text-[11px] text-rose-600">{{ form.errors.images }}</span>
            </div>
            </template>
        </div>
        </Transition>

        <Transition name="repair-step">
        <section v-if="hasRepairProgress" v-show="activeTab === 'log' && completionStep" class="space-y-3">
            <RepairCompletionModal
                ref="completionModalRef"
                :repair="repair"
                v-model="selectedParts"
                :decline-warranty="declineWarranty"
                :decline-reason="declineReason"
                :show-submit="false"
                @updated="onCompleted"
            />
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-900">
                <input v-model="payImmediately" type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500" />
                <span>Thanh toán luôn sau khi hoàn tất</span>
                <span class="ml-auto text-[11px] font-normal text-blue-700">{{ payImmediately ? 'Hoàn tất xong sẽ mở thanh toán' : 'Hoàn tất xong sẽ đóng cửa sổ' }}</span>
            </label>
        </section>
        </Transition>

        <!-- Thanh toán cho phiếu đã hoàn tất -->
        <div v-if="activeTab === 'log' && !completionStep && repair.status === 'done'" class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-center">
            <h3 class="text-base font-bold text-emerald-900">Máy đã hoàn tất sửa chữa!</h3>
            <p class="mt-1 text-xs text-emerald-700">Tổng chi phí: <strong class="text-sm text-emerald-900">{{ $money(repair.final_cost) }}</strong></p>
            <ActionButton class="mt-3 w-full justify-center" @click="startPayment">Thanh toán & Trả máy cho khách</ActionButton>
        </div>

        <!-- Tab lịch sử cập nhật -->
        <div v-if="activeTab === 'history'" class="space-y-3">
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
                    <p v-if="timeline.waiting_for_parts" class="mt-2 rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-800">
                        <strong>{{ timeline.waiting_mode === 'wait_repair' ? 'Tạm chờ sửa' : 'Chờ linh kiện' }}:</strong><template v-if="timeline.parts_needed"> {{ timeline.parts_needed }}</template><span v-if="timeline.expected_days !== null"> · Dự kiến {{ timeline.expected_days }} ngày</span>
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
        <template v-if="!embedded" #footer>
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
                    <ActionButton v-if="completionStep" variant="secondary" @click="returnToProgress">Quay lại tiến trình</ActionButton>
                    
                    <ActionButton 
                        v-if="activeTab !== 'history' && ['pending', 'repairing'].includes(repair.status)"
                        type="button"
                        :disabled="footerBusy"
                        @click="saveProgress"
                    >
                        {{ footerActionLabel }}
                    </ActionButton>
                </div>
            </div>
        </template>
    </BaseModal>
</template>

<style scoped>
.repair-step-enter-active,
.repair-step-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.repair-step-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.repair-step-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}
</style>
