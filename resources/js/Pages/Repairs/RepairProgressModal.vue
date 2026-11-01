<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import RepairCompletionModal from './RepairCompletionModal.vue'
import RepairPartsPicker from './RepairPartsPicker.vue'
import PatternLock from '@/Components/PatternLock.vue'
import { imageUrl } from '@/utils/imageUrl'
import { toast } from 'vue-sonner'
import { Zap, PackageSearch, PauseCircle, Camera, ImagePlus, Trash2 } from 'lucide-vue-next'

const props = defineProps({ repair: { type: Object, required: true }, embedded: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'updated', 'completed', 'pay', 'stage-change'])

// Quy trình hiện tại và lịch sử sửa chữa.
const activeTab = ref('log')
const historyModalOpen = ref(['returned', 'cancelled'].includes(props.repair.status))
const completionStep = ref(false)
const hasRepairProgress = ref(['repairing', 'done'].includes(props.repair.status))
// Phiếu đang chờ khi mở lại sẽ mặc định ở chế độ tiếp tục sửa.
// Mở lại phiếu đang chờ thì mặc định vào luồng tiếp tục sửa; thanh trạng thái
// phía trên vẫn phản ánh waiting_mode đã lưu từ props.repair.
const waitingMode = ref('repair_now')
const isWaiting = computed(() => waitingMode.value !== 'repair_now')
const completionModalRef = ref(null)
const selectedParts = ref([])
const payImmediately = ref(true)
const progressImagePreviews = ref([])
const progressImageInput = ref(null)
const progressCameraFallbackInput = ref(null)
const progressPhotoVideo = ref(null)
const progressCameraOpen = ref(false)
const progressCameraLoading = ref(false)
const progressCameraError = ref('')
const imageViewer = ref(null)
let progressCameraStream = null
let progressCameraRequest = 0
const declineWarranty = ref(false)
const declineReason = ref('')
const continueAsService = ref(false)
const decliningWarranty = ref(false)
const credentialsModalOpen = ref(false)
const credentialsLoading = ref(false)
const credentialsLoaded = ref(false)
const credentialsError = ref('')
const credentials = ref(null)
const hasWarrantyCandidate = computed(() => {
    if (!props.repair.warranty_source_type || !props.repair.warranty_source_id || !props.repair.warranty_expires_at || props.repair.warranty_status === 'declined') return false
    const expiresAt = new Date(props.repair.warranty_expires_at)
    expiresAt.setHours(23, 59, 59, 999)
    return expiresAt.getTime() >= Date.now()
})
const canDeclineWarranty = computed(() => hasWarrantyCandidate.value)
const toggleDeclineWarranty = () => {
    if (!canDeclineWarranty.value) return
    declineWarranty.value = !declineWarranty.value
    if (declineWarranty.value) continueAsService.value = false
}
const savedProgressImages = computed(() => [
    ...(props.repair.images || []).map((image) => ({ ...image, url: imageUrl(image.image_path || image.url), group: 'Ảnh lúc tiếp nhận' })),
    ...(props.repair.timelines || []).flatMap((timeline) => (timeline.images || []).map((image) => ({
        ...image,
        url: imageUrl(image.image_path || image.url),
        group: timeline.title || 'Ảnh tiến trình',
    }))),
])

const form = useForm({
    description: '',
    issue: [],
    parts_needed: props.repair.parts_needed || '',
    expected_days: props.repair.expected_days ?? '',
    waiting_for_parts: isWaiting.value,
    images: [],
})

const issueText = ref('')
const openCredentials = async () => {
    credentialsModalOpen.value = true
    if (credentialsLoaded.value || credentialsLoading.value) return
    credentialsLoading.value = true
    credentialsError.value = ''
    try {
        const { data } = await axios.get(route('repairs.credentials', props.repair.id), { headers: { 'Cache-Control': 'no-cache' } })
        credentials.value = data
        credentialsLoaded.value = true
    } catch {
        credentialsError.value = 'Không tải được mật khẩu và tài khoản máy.'
    } finally {
        credentialsLoading.value = false
    }
}
const addProgressImageFiles = (files) => {
    if (!files.length) return
    const validFiles = files.filter((file) => file.size <= 5 * 1024 * 1024)
    if (validFiles.length !== files.length) toast.error('Ảnh vượt quá giới hạn 5 MB/ảnh đã được bỏ qua.')
    if (!validFiles.length) return
    form.images = [...form.images, ...validFiles]
    progressImagePreviews.value = [
        ...progressImagePreviews.value,
        ...validFiles.map((file) => ({ file, url: URL.createObjectURL(file) })),
    ]
}
const updateProgressImages = (event) => {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    addProgressImageFiles(files)
}
const removeProgressImage = (index) => {
    const [preview] = progressImagePreviews.value.splice(index, 1)
    if (preview) URL.revokeObjectURL(preview.url)
    if (imageViewer.value?.previewIndex === index) imageViewer.value = null
    form.images = progressImagePreviews.value.map((item) => item.file)
}
const stopProgressCamera = () => {
    progressCameraRequest += 1
    progressCameraStream?.getTracks().forEach((track) => track.stop())
    progressCameraStream = null
    if (progressPhotoVideo.value) progressPhotoVideo.value.srcObject = null
    progressCameraOpen.value = false
    progressCameraLoading.value = false
}
const usePhoneCamera = () => {
    stopProgressCamera()
    progressCameraFallbackInput.value?.click()
}
const startProgressCamera = async () => {
    if (!globalThis.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        toast.info('Không mở được camera trực tiếp. Hãy chụp bằng điện thoại hoặc chọn ảnh.')
        usePhoneCamera()
        return
    }

    const requestId = ++progressCameraRequest
    progressCameraOpen.value = true
    progressCameraLoading.value = true
    progressCameraError.value = ''
    await nextTick()
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
        if (requestId !== progressCameraRequest) {
            stream.getTracks().forEach((track) => track.stop())
            return
        }
        progressCameraStream = stream
        await nextTick()
        if (progressPhotoVideo.value) {
            progressPhotoVideo.value.srcObject = stream
            await progressPhotoVideo.value.play()
        }
    } catch {
        if (requestId === progressCameraRequest) progressCameraError.value = 'Không mở được camera. Hãy cấp quyền camera hoặc chụp bằng camera của điện thoại.'
    } finally {
        if (requestId === progressCameraRequest) progressCameraLoading.value = false
    }
}
const captureProgressPhoto = () => {
    const video = progressPhotoVideo.value
    if (!video?.videoWidth || !video?.videoHeight) return
    const canvas = document.createElement('canvas')
    const scale = Math.min(1, 1920 / video.videoWidth)
    canvas.width = Math.round(video.videoWidth * scale)
    canvas.height = Math.round(video.videoHeight * scale)
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
    canvas.toBlob((blob) => {
        if (!blob) {
            toast.error('Không tạo được ảnh chụp. Vui lòng thử lại.')
            return
        }
        addProgressImageFiles([new File([blob], `repair-progress-${Date.now()}.jpg`, { type: 'image/jpeg' })])
        toast.success('Đã thêm ảnh chụp vào tiến trình.')
    }, 'image/jpeg', 0.84)
}
const deleteSavedProgressImage = async (image) => {
    if (!image?.id || !window.confirm('Bạn có chắc muốn xóa ảnh này khỏi phiếu sửa chữa?')) return
    try {
        await axios.delete(route('repairs.images.destroy', { repair: props.repair.id, image: image.id }))
        const updatedRepair = {
            ...props.repair,
            images: (props.repair.images || []).filter((item) => Number(item.id) !== Number(image.id)),
            timelines: (props.repair.timelines || []).map((timeline) => ({
                ...timeline,
                images: (timeline.images || []).filter((item) => Number(item.id) !== Number(image.id)),
            })),
        }
        imageViewer.value = null
        emit('updated', updatedRepair)
        toast.success('Đã xóa ảnh khỏi phiếu.')
    } catch (error) {
        toast.error(Object.values(error.response?.data?.errors || {}).flat()[0] || 'Không thể xóa ảnh khỏi phiếu.')
    }
}
const clearProgressImagePreviews = () => {
    progressImagePreviews.value.forEach((preview) => URL.revokeObjectURL(preview.url))
    progressImagePreviews.value = []
}
onUnmounted(() => {
    clearProgressImagePreviews()
    stopProgressCamera()
})
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
            emit('updated', refreshedRepair || {
                ...props.repair,
                status: targetStatus,
                waiting_for_parts: targetStatus !== 'cancelled' && isWaiting.value,
                waiting_mode: targetStatus === 'cancelled' ? 'repair_now' : waitingMode.value,
                parts_needed: waitingMode.value === 'parts' ? form.parts_needed : null,
                expected_days: waitingMode.value === 'parts' ? form.expected_days : null,
            })
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
const openHistory = () => { historyModalOpen.value = true }
const closeProgress = () => emit('close')

defineExpose({ footerActionLabel, footerBusy, footerShowCancel, footerShowSave, footerShowBack: computed(() => completionStep.value), credentialsModalOpen: computed(() => credentialsModalOpen.value), historyModalOpen: computed(() => historyModalOpen.value), imageViewerOpen: computed(() => Boolean(imageViewer.value)), cameraOpen: computed(() => progressCameraOpen.value), canDeclineWarranty, declineWarranty: computed(() => declineWarranty.value), toggleDeclineWarranty, hasUnsavedChanges: computed(() => form.isDirty || Boolean(issueText.value.trim()) || Boolean(declineReason.value.trim()) || declineWarranty.value || continueAsService.value || form.images.length > 0), cancelRepair, saveProgress, returnToProgress, openCompletionStep, openProgressStep, openHistory, openCredentials, closeProgress })
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

            <section v-if="declineWarranty" class="space-y-3 rounded-xl border border-rose-200 bg-rose-50/60 p-3">
                <FloatingInput v-model="declineReason" label="Lý do từ chối bảo hành *" id="repair_warranty_decline_reason" :error="form.errors.decline_reason" required />
                <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-800">
                    <input v-model="continueAsService" type="checkbox" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500" />
                    <span>Tiếp tục sửa dịch vụ</span>
                </label>
                <p class="text-[11px] text-rose-700">Nếu không chọn sửa dịch vụ, lưu sẽ kết thúc phiếu và vô hiệu hóa căn cứ bảo hành.</p>
            </section>

            <template v-if="!declineWarranty || continueAsService">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button v-for="option in [{ value: 'repair_now', label: 'Sửa ngay', hint: 'Tiếp tục xử lý', icon: Zap, tone: 'amber' }, { value: 'parts', label: 'Chờ linh kiện', hint: 'Đặt linh kiện', icon: PackageSearch, tone: 'blue' }, { value: 'wait_repair', label: 'Tạm chờ sửa', hint: 'Chưa thể xử lý', icon: PauseCircle, tone: 'slate' }]" :key="option.value" type="button" class="group flex min-h-[58px] items-center gap-2.5 rounded-xl border px-3 py-2 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400" :class="waitingMode === option.value ? (option.tone === 'amber' ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-200 shadow-sm' : option.tone === 'blue' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200 shadow-sm' : 'border-slate-500 bg-slate-100 ring-2 ring-slate-200 shadow-sm') : 'border-slate-300 bg-white hover:border-slate-400 hover:bg-slate-50'" @click="selectProgressMode(option.value)"><component :is="option.icon" class="h-5 w-5 shrink-0" :class="waitingMode === option.value ? (option.tone === 'amber' ? 'text-amber-700' : option.tone === 'blue' ? 'text-blue-700' : 'text-slate-700') : 'text-slate-500 group-hover:text-slate-700'"/><span class="min-w-0"><span class="block text-xs font-bold text-slate-900">{{ option.label }}</span><span class="mt-0.5 block text-[10px] text-slate-600">{{ option.hint }}</span></span><span v-if="waitingMode === option.value" class="ml-auto shrink-0 rounded-full bg-white/80 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-700">Đang chọn</span></button>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                <div class="space-y-3 md:col-span-7">
                    <section v-if="waitingMode === 'repair_now'" class="space-y-3 rounded-xl border border-slate-200 bg-white p-3">
                        <RepairPartsPicker v-model="selectedParts" />
                    </section>

                    <div v-if="waitingMode === 'parts'" class="grid grid-cols-1 gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-3 sm:grid-cols-2">
                        <FloatingInput v-model="form.parts_needed" label="Tên linh kiện cần chờ *" id="repair_progress_parts" :error="form.errors.parts_needed" required />
                        <FloatingInput v-model="form.expected_days" type="number" min="0" max="365" step="1" label="Dự kiến chờ (ngày) *" id="repair_progress_days" />
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <FloatingInput v-model="issueText" label="Lỗi phát sinh" id="repair_progress_issue" :error="form.errors.issue" />
                        <FloatingInput v-model="form.description" :label="waitingMode === 'wait_repair' ? 'Ghi chú' : 'Ghi chú công việc đã làm...'" id="repair_progress_note" :error="form.errors.description" />
                    </div>
                </div>

                <aside class="space-y-2 rounded-xl border border-slate-200 bg-white p-3 md:col-span-5">
                    <div class="flex items-center justify-between gap-2">
                        <label class="block text-xs font-semibold text-slate-700">Ảnh chụp tiến trình</label>
                        <span v-if="progressImagePreviews.length" class="text-[10px] text-slate-500">{{ progressImagePreviews.length }} ảnh mới</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-dashed border-blue-300 bg-blue-50/50 p-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-700 shadow-sm transition hover:border-blue-400 hover:bg-blue-50" @click="startProgressCamera">
                            <Camera class="h-4 w-4" /> Chụp ảnh
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700" @click="progressImageInput?.click()">
                            <ImagePlus class="h-4 w-4" /> Chọn ảnh
                        </button>
                        <input ref="progressImageInput" class="hidden" type="file" accept="image/*" multiple @change="updateProgressImages" />
                        <input ref="progressCameraFallbackInput" class="hidden" type="file" accept="image/*" capture="environment" @change="updateProgressImages" />
                    </div>
                    <p class="text-[10px] text-slate-500">Ảnh đã lưu vẫn được giữ; có thể bổ sung ảnh tiến trình · tối đa 5 MB/ảnh.</p>
                    <div v-if="savedProgressImages.length || progressImagePreviews.length" class="flex flex-wrap gap-2">
                        <button v-for="(image, index) in savedProgressImages" :key="`saved-${image.id || image.url}-${index}`" type="button" class="group relative h-20 w-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-50" :title="`Xem ${image.group}`" @click="imageViewer = { ...image, saved: true }">
                            <img :src="image.url" :alt="image.group" class="h-full w-full object-cover" />
                            <span class="absolute inset-x-0 bottom-0 truncate bg-slate-900/70 px-1 py-0.5 text-center text-[8px] text-white">{{ image.group }}</span>
                        </button>
                        <div v-for="(preview, index) in progressImagePreviews" :key="preview.url" class="group relative h-20 w-20 overflow-hidden rounded-lg border border-blue-200 bg-slate-50">
                            <button type="button" class="h-full w-full" :aria-label="`Xem ảnh tiến trình ${index + 1}`" @click="imageViewer = { url: preview.url, group: `Ảnh tiến trình ${index + 1}`, previewIndex: index }"><img :src="preview.url" :alt="`Ảnh tiến trình ${index + 1}`" class="h-full w-full object-cover" /></button>
                            <button type="button" class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-slate-900/70 text-xs text-white opacity-0 transition group-hover:opacity-100" :aria-label="`Bỏ ảnh ${index + 1}`" @click="removeProgressImage(index)">×</button>
                        </div>
                    </div>
                    <span v-if="form.errors.images" class="block text-[11px] text-rose-600">{{ form.errors.images }}</span>
                </aside>
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

        <BaseModal v-if="historyModalOpen" :title="`Lịch sử sửa · ${repair.code}`" size="lg" @close="historyModalOpen = false">
        <div class="space-y-3">
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
                        <button v-for="image in timeline.images" :key="image.id" type="button" class="h-16 w-16 overflow-hidden rounded-lg border border-slate-200" aria-label="Xem ảnh tiến trình sửa chữa" @click="imageViewer = { ...image, url: imageUrl(image.image_path || image.url), group: timeline.title || 'Ảnh tiến trình', saved: true }">
                            <img :src="imageUrl(image.image_path || image.url)" alt="Ảnh tiến trình sửa chữa" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </article>
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Chưa có lịch sử cập nhật.</div>
        </div>
        </BaseModal>

        <BaseModal v-if="imageViewer" :title="imageViewer.group || 'Xem ảnh sửa chữa'" size="xl" @close="imageViewer = null">
            <div class="flex flex-col items-center gap-3">
                <img :src="imageViewer.url" :alt="imageViewer.group || 'Ảnh sửa chữa'" class="max-h-[68dvh] max-w-full rounded-lg object-contain" />
                <button v-if="imageViewer.saved" type="button" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100" @click="deleteSavedProgressImage(imageViewer)"><Trash2 class="h-4 w-4" /> Xóa ảnh</button>
                <button v-else-if="Number.isInteger(imageViewer.previewIndex)" type="button" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100" @click="removeProgressImage(imageViewer.previewIndex)"><Trash2 class="h-4 w-4" /> Bỏ ảnh</button>
            </div>
        </BaseModal>

        <BaseModal v-if="progressCameraOpen" title="Chụp ảnh tiến trình" size="lg" @close="stopProgressCamera">
            <div class="space-y-3">
                <p class="text-xs text-slate-500">Ảnh chụp sẽ được thêm vào tiến trình sửa chữa.</p>
                <div class="relative aspect-video overflow-hidden rounded-xl bg-slate-950">
                    <video ref="progressPhotoVideo" autoplay playsinline class="h-full w-full object-contain" />
                    <div v-if="progressCameraLoading" class="absolute inset-0 flex items-center justify-center bg-slate-950/60 text-sm font-semibold text-white">Đang mở camera…</div>
                    <div v-if="progressCameraError" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950/85 p-5 text-center text-sm text-white">
                        <p>{{ progressCameraError }}</p>
                        <button type="button" class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-800 hover:bg-slate-100" @click="usePhoneCamera">Chụp bằng điện thoại</button>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="stopProgressCamera">Đóng</button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="progressCameraLoading || Boolean(progressCameraError) || !progressPhotoVideo?.videoWidth" @click="captureProgressPhoto"><Camera class="h-4 w-4" /> Chụp ảnh</button>
                </div>
            </div>
        </BaseModal>

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

    <BaseModal v-if="credentialsModalOpen" :title="`Mật khẩu máy & tài khoản · ${repair.code}`" size="md" @close="credentialsModalOpen = false">
        <div v-if="credentialsLoading" class="py-8 text-center text-sm text-slate-500">Đang tải thông tin bảo mật...</div>
        <div v-else-if="credentialsError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ credentialsError }}</div>
        <div v-else-if="credentials" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <section class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">
                <h3 class="font-bold text-slate-800">Khóa màn hình <span class="font-normal text-slate-500">· {{ credentials.screen_lock_type || (credentials.screen_pattern ? 'Mẫu hình' : credentials.screen_password ? 'Mật khẩu / PIN' : 'Chưa lưu') }}</span></h3>
                <p v-if="credentials.screen_password" class="break-all"><span class="text-slate-500">Mật khẩu/PIN:</span> <strong class="select-all text-slate-900">{{ credentials.screen_password }}</strong></p>
                <div v-if="credentials.screen_pattern" class="space-y-1"><span class="text-slate-500">Mẫu hình khóa:</span><div class="w-fit rounded-lg border border-slate-200 bg-white p-1"><PatternLock :model-value="credentials.screen_pattern" readonly /></div></div>
                <p v-if="!credentials.screen_password && !credentials.screen_pattern" class="text-slate-500">Không có mật khẩu khóa màn hình.</p>
            </section>
            <section class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">
                <h3 class="font-bold text-slate-800">Tài khoản máy <span class="font-normal text-slate-500">· {{ credentials.account_type || 'Chưa lưu' }}</span></h3>
                <p><span class="text-slate-500">Email/SĐT:</span> <strong class="break-all text-slate-900">{{ credentials.account_email || '—' }}</strong></p>
                <p><span class="text-slate-500">Mật khẩu:</span> <strong class="select-all break-all text-slate-900">{{ credentials.account_password || '—' }}</strong></p>
            </section>
        </div>
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
