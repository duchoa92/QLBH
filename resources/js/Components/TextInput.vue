<script setup>
import { computed, onMounted, ref, useAttrs } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);
const attrs = useAttrs();
const passwordVisible = ref(false);
const isPassword = computed(() => attrs.type === 'password');
const inputType = computed(() => isPassword.value && passwordVisible.value ? 'text' : (attrs.type || 'text'));

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div class="relative">
        <input
            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            :class="isPassword ? 'pr-10' : ''"
            :type="inputType"
            v-bind="$attrs"
            v-model="model"
            ref="input"
        />
        <button
            v-if="isPassword"
            type="button"
            class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :title="passwordVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            :aria-label="passwordVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            @click="passwordVisible = !passwordVisible"
        ><EyeOff v-if="passwordVisible" class="h-4 w-4" /><Eye v-else class="h-4 w-4" /></button>
    </div>
</template>
