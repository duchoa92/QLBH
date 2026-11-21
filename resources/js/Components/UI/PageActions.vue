<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { floatingActionsCount, floatingActionsTarget } from '@/Stores/floatingActions'

// Đưa các nút chức năng đặt tự do trong trang vào nút nổi của layout (xem FloatingActions.vue).
onMounted(() => { floatingActionsCount.value += 1 })
onBeforeUnmount(() => { floatingActionsCount.value = Math.max(0, floatingActionsCount.value - 1) })
</script>

<template>
    <Teleport v-if="floatingActionsTarget" :to="floatingActionsTarget">
        <slot />
    </Teleport>
</template>
