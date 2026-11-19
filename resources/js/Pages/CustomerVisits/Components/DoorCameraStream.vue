<script setup>
import { computed } from 'vue'
import { CameraOff, Video } from 'lucide-vue-next'
import { useCameraIntelligenceWebrtc } from '@/Modules/POS/Customer/Composables/useCameraIntelligenceWebrtc'

const props = defineProps({
    camera: { type: Object, required: true },
    enabled: { type: Boolean, default: true },
    sourceHash: { type: String, default: '' },
    webrtcUrl: { type: String, default: '' },
})

const player = useCameraIntelligenceWebrtc(
    computed(() => props.enabled),
    { camera: () => props.camera.id, sourceKey: computed(() => `${props.sourceHash}:${props.camera.id}`), webrtcUrl: () => props.webrtcUrl },
)
const faces = computed(() => player.ok.value ? player.faces.value : [])
const tone = { known: 'border-emerald-400', unknown: 'border-amber-400', scanning: 'border-cyan-300' }
const labelTone = { known: 'bg-emerald-500 text-white', unknown: 'bg-amber-500 text-white', scanning: 'bg-black/70 text-cyan-100' }
const boxStyle = face => ({ left: `${face.x * 100}%`, top: `${face.y * 100}%`, width: `${face.w * 100}%`, height: `${face.h * 100}%` })
const labelPos = face => face.y < 0.08 ? 'top-0.5 left-0.5' : '-top-6 left-0'
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-slate-700 bg-black">
        <header class="flex items-center justify-between gap-2 bg-slate-900 px-2.5 py-1.5 text-xs">
            <span class="flex min-w-0 items-center gap-1.5 font-semibold text-slate-100"><Video class="h-3.5 w-3.5 shrink-0 text-cyan-300" /><span class="truncate">{{ camera.name }}</span></span>
            <span class="shrink-0" :class="player.ok.value ? 'text-emerald-300' : 'text-slate-400'">{{ player.ok.value ? `${faces.length} mặt` : 'Đang kết nối' }}</span>
        </header>
        <div class="relative aspect-video min-h-40 bg-black">
            <video :ref="player.setVideo" autoplay muted playsinline class="absolute inset-0 h-full w-full object-contain" :aria-label="`Video ${camera.name}`" />
            <div v-if="player.ok.value" class="pointer-events-none absolute inset-0 overflow-hidden">
                <div v-for="face in faces" :key="face.id" class="absolute rounded-md border-2 shadow-[0_0_0_1px_rgba(15,23,42,0.65)] transition-all duration-150" :class="tone[face.kind]" :style="boxStyle(face)">
                    <span class="absolute max-w-[12rem] truncate rounded px-1.5 py-0.5 text-[11px] font-semibold leading-none" :class="[labelTone[face.kind], labelPos(face)]">{{ face.label }}</span>
                </div>
            </div>
            <p v-if="!player.ok.value && player.loading.value" class="absolute inset-0 flex items-center justify-center p-4 text-center text-xs text-slate-300">Đang kết nối WebRTC…</p>
            <p v-else-if="!player.ok.value" class="absolute inset-0 flex items-center justify-center gap-1.5 p-4 text-center text-xs text-rose-300"><CameraOff class="h-4 w-4 shrink-0" />{{ player.error.value || 'Chưa có luồng camera.' }}</p>
        </div>
    </section>
</template>
