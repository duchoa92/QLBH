<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { modalState, closeModal } from '@/Stores/modal';


const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
     title: String
});

const emit = defineEmits(['close']);



watch(
    () => props.show,
    (val) => {
        document.body.style.overflow = val ? 'hidden' : ''
    }
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();

        if (props.show) {
            close();
        }
    }
};

onMounted(() => {

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal()
    })

    document.addEventListener('keydown', closeOnEscape)

    if (props.show) {
        setTimeout(() => {
            document.querySelector('input, select, textarea')?.focus()
        }, 100)
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);

    document.body.style.overflow = '';
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <div 
        v-if="modalState.show" 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="closeModal"
    >
        <div class="bg-white w-full max-w-md rounded shadow-lg p-4 relative">

            <!-- ❌ NÚT ĐÓNG -->
            <button 
                @click="closeModal"
                class="absolute top-2 right-2 text-gray-500 hover:text-black text-xl"
            >
                ✕
            </button>

            <!-- TITLE -->
            <h2 class="text-lg font-bold mb-3">
                {{ modalState.title }}
            </h2>

            <!-- CONTENT -->
            <component 
                :is="modalState.component" 
                v-bind="modalState.props"
                @close="closeModal"
                @updated="modalState.onUpdated && modalState.onUpdated()"
            />

        </div>
    </div>
</template>
