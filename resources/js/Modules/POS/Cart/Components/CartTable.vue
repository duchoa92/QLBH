<script setup>
import { ref, onMounted, onBeforeUnmount  } from 'vue'
import {
    PenSquare,
    Trash2,
    Gift,
} from 'lucide-vue-next'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { productService } from '@/Modules/POS/Product/Services/productService'
import TagBadge from '@/Components/UI/TagBadge.vue'
import CartItem from './CartItem.vue'

const props = defineProps({

    selectedIndex: {
        type: Number,
        default: -1,
    },

    items: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'remove',
])

const giftProducts = ref([])

const searchingGift = ref(false)
const promotionRef = ref(null)
const promotionPanel = ref(null)

onMounted(() => {

    document.addEventListener(
        'mousedown',
        handleClickOutside
    )
})

onBeforeUnmount(() => {

    document.removeEventListener(
        'mousedown',
        handleClickOutside
    )
})

const handleClickOutside = (event) => {

    const insidePromotion =

        event.target.closest(
            '.promotion-panel'
        )

    const clickButton =

        event.target.closest(
            '.promotion-button'
        )

    if (
        insidePromotion ||
        clickButton
    ) {

        return
    }

    props.items.forEach(item => {

        item.showPromotion = false

    })
}


const closeGiftResults = (
    item
) => {

    item.gift_results = []
}


</script>

<template>
    <CartItem
        v-for="item in items"
        :key="
            item.imei_id
                ? 'imei-' + item.imei_id
                : 'product-' + item.id
        "
        :item="item"
        @remove="
            emit(
                'remove',
                $event
            )
        "
    />  
</template>

<style scoped>
/* Ẩn mũi tên input */
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.no-spinner {
  -moz-appearance: textfield;
}
</style>
