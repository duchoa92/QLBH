<script setup>

import { onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    title: String,
    size: {
        type: String,
        default: 'lg' // sm | md | lg | xl
    },
    bodyClass: {
        type: String,
        default: 'p-4',
    },
    headerClass: {
        type: String,
        default: '',
    },
    modalClass: {
        type: String,
        default: '',
    },
    layerClass: {
        type: String,
        default: 'z-[1000]',
    },
    footerClass: {
        type: String,
        default: 'py-3',
    },
    embedded: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'updated'])

const handleEsc = (e) => {
    if (e.key === 'Escape') {
        emit('close')
    }
}

onMounted(() => {
    if (!props.embedded) window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    if (!props.embedded) window.removeEventListener('keydown', handleEsc)
})

</script>

<template>
    <template v-if="embedded">
        <div :class="bodyClass"><slot /></div>
    </template>
    <Teleport v-else to="body">
    <div class="base-modal-layer fixed inset-0 flex items-center justify-center overflow-y-auto p-0 sm:p-5 modal-scrollbar" :class="layerClass">
        <div
            class="absolute inset-0 bg-slate-950/50 backdrop-blur-[2px]"
            @click="$emit('close')"
        ></div>

        <section
            role="dialog"
            aria-modal="true"
            class="base-modal-sheet relative flex min-h-0 max-h-[calc(100dvh-0.75rem)] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 sm:max-h-[calc(100dvh-2.5rem)] sm:rounded-xl"
            :class="[modalClass, {
                'max-w-md': size === 'sm',
                'max-w-xl': size === 'md',
                'max-w-3xl': size === 'lg',
                'max-w-4xl': size === 'xl',
                'max-w-6xl': size === '2xl'
            }]"
        >
            <!-- HEADER -->
            <div class="base-modal-header flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3" :class="headerClass">
                <slot name="header">
                    <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-red-500 active:bg-white sm:h-8 sm:w-8"
                        aria-label="Đóng"
                    >
                        ✕
                    </button>
                </slot>
            </div>

            <!-- Có chân (nút thao tác): thân + chân cùng nằm trong một vùng cuộn, chân trượt theo nội dung
                 và chỉ hiện ở cuối, không cố định đè lên nội dung nên không bị nhầm với các nút xử lý bên trên. -->
            <div v-if="$slots.footer" class="base-modal-scroll flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain bg-white modal-scrollbar">
                <div class="base-modal-body shrink-0 bg-white" :class="bodyClass">
                    <slot />
                </div>

                <div class="base-modal-footer mt-auto border-t border-slate-200 bg-slate-50 px-4" :class="footerClass">
                    <slot name="footer" />
                </div>
            </div>

            <!-- Không có chân: thân tự cuộn như trước -->
            <div v-else class="base-modal-body min-h-0 flex-1 overflow-y-auto overscroll-contain bg-white modal-scrollbar" :class="bodyClass">
                <slot />
            </div>

        </section>
    </div>
    </Teleport>
</template>

<style scoped>
/* Mobile: bình thường căn giữa; khi bàn phím mở, quy tắc kb-open bên dưới sẽ bám lên đầu. */
@media (max-width: 639px) {
    .base-modal-sheet {
        animation: base-modal-sheet-in 180ms ease-out;
    }

    .base-modal-footer {
        padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
    }
}

/* Khi bàn phím chiếm nửa màn hình: modal dùng toàn bộ phần còn nhìn thấy, chỉ phần thân
   cuộn; tiêu đề và nút lưu/đóng không bị ép lẫn vào nội dung. */
:global(html.kb-open) .base-modal-sheet {
    /* Ghi đè cả modal có height riêng (chẳng hạn h-[min(88dvh,820px)]).
       Không chừa khoảng đầu/cuối: khi bàn phím mở cần dùng hết vùng nhìn thấy. */
    height: var(--vv-h, 100dvh) !important;
    max-height: var(--vv-h, 100dvh) !important;
    border-radius: 0 !important;
}

:global(html.kb-open) .base-modal-layer {
    align-items: flex-start !important;
    overflow: hidden !important;
    padding: 0 !important;
}

:global(html.kb-open) .base-modal-body {
    overscroll-behavior: contain;
}

@media (max-width: 639px) {
    :global(html.kb-open) .base-modal-header {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    :global(html.kb-open) .base-modal-footer {
        padding-top: 0.5rem;
        padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
    }
}

@keyframes base-modal-sheet-in {
    from { transform: translateY(24px); opacity: 0.6; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.modal-scrollbar::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}

.modal-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.modal-scrollbar::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: #cbd5e1;
}
</style>
