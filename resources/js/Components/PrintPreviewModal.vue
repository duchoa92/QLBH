<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Printer, X } from 'lucide-vue-next'

const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'Xem trước bản in' },
    src: { type: String, default: '' },
    html: { type: String, default: '' },
})
const emit = defineEmits(['close'])
const page = usePage()
const source = computed(() => props.html ? `data:text/html;charset=utf-8,${encodeURIComponent(props.html)}` : props.src)
const paper = computed(() => page.props.settings?.print_paper_size || 'a4')
const modalWidth = computed(() => ({ '58mm': '460px', '80mm': '560px', a5: '760px', a4: '960px' }[paper.value] || '960px'))
const applyPrintSettings = (event) => {
    const frameDocument = event.target?.contentDocument
    if (!frameDocument) return
    let style = frameDocument.getElementById('modal-print-settings')
    if (!style) {
        style = frameDocument.createElement('style')
        style.id = 'modal-print-settings'
        frameDocument.head.appendChild(style)
    }
    // Chrome ignores a thermal width paired with `auto`; provide a valid custom page size.
    const sizes = { a4: 'A4', a5: 'A5', '80mm': '80mm 200mm', '58mm': '58mm 150mm' }
    const thermal = paper.value === '80mm' || paper.value === '58mm'
    const orientation = thermal ? '' : ` ${page.props.settings?.print_orientation === 'landscape' ? 'landscape' : 'portrait'}`
    const margin = thermal ? '0' : '10mm'
    const width = paper.value === '58mm' ? '54mm' : paper.value === '80mm' ? '76mm' : '100%'
    style.textContent = `:root{--configured-receipt-width:${width}} @page{size:${sizes[paper.value] || 'A4'}${orientation};margin:${margin}} .receipt-container{width:var(--configured-receipt-width)!important;margin-left:auto!important;margin-right:auto!important}`
}
const print = () => document.querySelector('#print-preview-frame')?.contentWindow?.print()
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[1300] flex items-center justify-center bg-slate-950/60 p-3 sm:p-6" role="dialog" aria-modal="true" :aria-label="title">
            <section class="flex h-[min(90dvh,900px)] w-full flex-col overflow-hidden rounded-xl bg-white shadow-2xl" :style="{ maxWidth: modalWidth }">
                <header class="flex shrink-0 items-center justify-between border-b px-4 py-3">
                    <h2 class="font-semibold text-slate-800">{{ title }}</h2>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="print" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"><Printer class="h-4 w-4" /> In</button>
                        <button type="button" @click="emit('close')" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Đóng"><X class="h-5 w-5" /></button>
                    </div>
                </header>
                <iframe id="print-preview-frame" :key="source" :src="source" :srcdoc="html || undefined" class="min-h-0 w-full flex-1 bg-slate-100" :title="title" @load="applyPrintSettings" />
            </section>
        </div>
    </Teleport>
</template>
