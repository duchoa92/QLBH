<script setup>
import { computed, ref } from 'vue'
import { formatMoney } from '@/utils/format'

/*
|--------------------------------------------------------------------------
| Biểu đồ cột SVG đơn giản, không phụ thuộc thư viện ngoài.
|--------------------------------------------------------------------------
|
| props.series: [{ label: '01/09', value: 120000 }, ...]
| props.color: màu cột (mặc định xanh dương)
|
*/

const props = defineProps({
    series: {
        type: Array,
        default: () => [],
    },
    color: {
        type: String,
        default: '#2563eb',
    },
    height: {
        type: Number,
        default: 220,
    },
    valueSuffix: {
        type: String,
        default: 'đ',
    },
})

const hovered = ref(null)

const maxValue = computed(() => {
    const max = Math.max(
        ...props.series.map(item => Number(item.value) || 0),
        0
    )

    return max === 0 ? 1 : max
})

const width = 720
const padding = { top: 16, right: 12, bottom: 28, left: 12 }

const barsCount = computed(() => props.series.length || 1)

const barWidth = computed(() => {
    const usable = width - padding.left - padding.right
    const gap = 6
    return Math.max(
        (usable - gap * (barsCount.value - 1)) / barsCount.value,
        4
    )
})

const bars = computed(() => {
    const usableHeight = props.height - padding.top - padding.bottom

    return props.series.map((item, index) => {
        const value = Number(item.value) || 0
        const barHeight = (value / maxValue.value) * usableHeight
        const x = padding.left + index * (barWidth.value + 6)
        const y = padding.top + (usableHeight - barHeight)

        return {
            ...item,
            x,
            y,
            width: barWidth.value,
            height: Math.max(barHeight, value > 0 ? 2 : 0),
        }
    })
})
</script>

<template>
    <div class="w-full">

        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="w-full"
            :style="{ height: height + 'px' }"
            preserveAspectRatio="none"
        >
            <!-- baseline -->
            <line
                :x1="padding.left"
                :x2="width - padding.right"
                :y1="height - padding.bottom"
                :y2="height - padding.bottom"
                stroke="#e2e8f0"
                stroke-width="1"
            />

            <g
                v-for="(bar, index) in bars"
                :key="index"
                @mouseenter="hovered = index"
                @mouseleave="hovered = null"
            >
                <rect
                    :x="bar.x"
                    :y="bar.y"
                    :width="bar.width"
                    :height="bar.height"
                    :fill="hovered === index ? color : color"
                    :fill-opacity="hovered === index ? 1 : 0.75"
                    rx="3"
                />

                <text
                    v-if="series.length <= 16"
                    :x="bar.x + bar.width / 2"
                    :y="height - padding.bottom + 14"
                    text-anchor="middle"
                    font-size="9"
                    fill="#64748b"
                >
                    {{ bar.label }}
                </text>
            </g>
        </svg>

        <div
            v-if="hovered !== null && bars[hovered]"
            class="mt-1 text-center text-xs font-semibold text-slate-700"
        >
            {{ bars[hovered].label }} —
            {{ formatMoney(bars[hovered].value) }} {{ valueSuffix }}
        </div>

        <div
            v-if="!series.length"
            class="flex h-full items-center justify-center text-sm text-slate-400"
        >
            Không có dữ liệu trong khoảng thời gian này
        </div>

    </div>
</template>
