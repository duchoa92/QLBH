<script setup>
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import SettingSwitch from './SettingSwitch.vue'

// Tinh chỉnh nhận diện: các số này worker camera cửa tự đọc về và áp dụng sau vài giây, không phải sửa file .env.
defineProps({
    form: { type: Object, required: true },
})

const detectFields = [
    { key: 'face_min_face_px', label: 'Mặt nhỏ nhất (px)', hint: 'Mặt rộng dưới số điểm ảnh này bị bỏ qua. Hạ xuống (ví dụ 50) nếu hay sót khách ở xa; quá thấp thì nhận diện kém chính xác.' },
    { key: 'face_detect_fps', label: 'Số hình lấy mỗi giây', hint: 'Camera RTSP: tăng để bắt khách nhanh hơn, tốn CPU hơn. Gợi ý 3–6.' },
    { key: 'face_detect_interval_ms', label: 'Nghỉ giữa 2 lần dò (ms)', hint: 'Càng nhỏ càng nhạy nhưng càng tốn máy. Gợi ý 200–500.' },
]
const roiFields = [
    { key: 'face_roi_left', label: 'Cách lề trái (%)' },
    { key: 'face_roi_top', label: 'Cách lề trên (%)' },
    { key: 'face_roi_width', label: 'Rộng (%)' },
    { key: 'face_roi_height', label: 'Cao (%)' },
]
const learnFields = [
    { key: 'face_auto_learn_min_percent', label: 'Độ giống tối thiểu (%)', hint: 'Chỉ tự thêm mẫu khi độ giống với khách cao hơn mức này. Càng cao càng an toàn.' },
    { key: 'face_auto_learn_margin_percent', label: 'Hơn khách giống thứ nhì (%)', hint: 'Phải hơn người giống thứ hai ít nhất bấy nhiêu, tránh học nhầm khi 2 khách giống nhau.' },
    { key: 'face_auto_learn_cooldown_minutes', label: 'Mỗi khách học lại sau (phút)', hint: 'Mỗi khách chỉ được thêm 1 mẫu tự học trong khoảng này (tối đa 5 mẫu, mẫu cũ nhất bị thay).' },
]
</script>

<template>
    <div class="space-y-4 rounded-lg bg-slate-50 p-3">
        <div>
            <h3 class="text-xs font-bold text-slate-800">Tinh chỉnh nhận diện camera cửa</h3>
            <p class="mt-0.5 text-xs text-slate-500">Không bắt buộc. Lưu xong, worker tự áp dụng sau vài giây, không cần khởi động lại.</p>
        </div>

        <div class="space-y-2">
            <h4 class="text-xs font-semibold text-slate-700">Dò khuôn mặt</h4>
            <div class="grid gap-3 sm:grid-cols-3">
                <div v-for="field in detectFields" :key="field.key">
                    <FloatingInput v-model="form[field.key]" :name="field.key" type="number" :label="field.label" />
                    <p class="mt-1 text-[11px] leading-4 text-slate-500">{{ field.hint }}</p>
                    <p v-if="form.errors[field.key]" class="text-xs text-rose-600">{{ form.errors[field.key] }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <h4 class="text-xs font-semibold text-slate-700">Vùng dò mặt (chỉ camera RTSP)</h4>
            <p class="text-xs text-slate-500">Chỉ dò trong vùng cửa ra vào. Vùng càng nhỏ thì mặt càng to trong mô hình, dò được xa hơn và nhanh hơn. Để 0 / 0 / 100 / 100 là dò cả khung hình.</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="field in roiFields" :key="field.key">
                    <FloatingInput v-model="form[field.key]" :name="field.key" type="number" :label="field.label" />
                    <p v-if="form.errors[field.key]" class="text-xs text-rose-600">{{ form.errors[field.key] }}</p>
                </div>
            </div>
        </div>


        <div class="space-y-3">
            <h4 class="text-xs font-semibold text-slate-700">Tự học khuôn mặt</h4>
            <SettingSwitch
                v-model="form.face_auto_learn_enabled"
                title="Tự bổ sung mẫu khuôn mặt khi nhận ra khách"
                description="Khi worker nhận ra khách quen với độ chắc chắn rất cao, hệ thống lưu thêm mẫu mới để lần sau nhận đúng hơn ở nhiều góc và ánh sáng. Mẫu đăng ký gốc không bao giờ bị xóa."
            />
            <div v-if="form.face_auto_learn_enabled" class="grid gap-3 sm:grid-cols-3">
                <div v-for="field in learnFields" :key="field.key">
                    <FloatingInput v-model="form[field.key]" :name="field.key" type="number" :label="field.label" />
                    <p class="mt-1 text-[11px] leading-4 text-slate-500">{{ field.hint }}</p>
                    <p v-if="form.errors[field.key]" class="text-xs text-rose-600">{{ form.errors[field.key] }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
