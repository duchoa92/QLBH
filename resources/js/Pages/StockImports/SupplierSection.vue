<script setup>

import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
    watch,
} from 'vue'

import {
    Truck,
    X,
    Plus,
    CalendarDays,
} from 'lucide-vue-next'

import FloatingInput from '@/Components/UI/FloatingInput.vue'

import { useSupplierSearch } from './Composables/useSupplierSearch.js'
import { openModal } from '@/Stores/modal'
import SupplierForm from '@/Pages/Suppliers/Form.vue'


/*
|--------------------------------------------------------------------------
| PROPS / EMITS
|--------------------------------------------------------------------------
*/

const emit = defineEmits([
    'selected',
    'update:date',
])

const props = defineProps({

    supplier: {
        type: Object,
        default: null,
    },

    date: {
        type: String,
        default: '',
    },

    error: {
        type: String,
        default: '',
    },

})


/*
|--------------------------------------------------------------------------
| NGÀY NHẬP HÀNG
|--------------------------------------------------------------------------
*/

const importDate = ref(
    props.date ||
    new Date().toISOString().slice(0, 10)
)

watch(
    () => props.date,
    value => {
        if (value) {
            importDate.value = value
        }
    }
)

watch(
    importDate,
    value => {
        emit('update:date', value)
    }
)


/*
|--------------------------------------------------------------------------
| SUPPLIER SEARCH
|--------------------------------------------------------------------------
*/

const {
    keyword,
    suppliers,
    search,
    selectSupplier,
    clearSupplier,
    activeIndex,
    setItemRef,
    onKeyDown,
    setActive,
} = useSupplierSearch(emit)


/*
|--------------------------------------------------------------------------
| ĐỒNG BỘ SUPPLIER TỪ PARENT
|--------------------------------------------------------------------------
*/

watch(
    () => props.supplier,

    supplier => {

        if (supplier) {
            keyword.value = supplier.name
            return
        }

        keyword.value = ''

    },

    {
        immediate: true,
    }
)


/*
|--------------------------------------------------------------------------
| SEARCH REF
|--------------------------------------------------------------------------
*/

const supplierSearchRef = ref(null)


/*
|--------------------------------------------------------------------------
| HIỆN NÚT THÊM NCC
|--------------------------------------------------------------------------
*/

const showCreateButton = computed(() => {
    return (
        keyword.value.trim() &&
        !suppliers.value.length
    )
})


/*
|--------------------------------------------------------------------------
| THÊM NHÀ CUNG CẤP
|--------------------------------------------------------------------------
*/

const createSupplier = () => {

    openModal(SupplierForm, {

        props: {
            name: keyword.value,
            title: 'Thêm nhà cung cấp',
            size: 'sm',
        },

        onUpdated: newSupplier => {
            emit('selected', newSupplier)
        },

    })

}


/*
|--------------------------------------------------------------------------
| CLICK OUTSIDE
|--------------------------------------------------------------------------
*/

const handleClickOutside = event => {

    if (
        supplierSearchRef.value &&
        !supplierSearchRef.value.contains(
            event.target
        )
    ) {
        suppliers.value = []
    }

}


onMounted(() => {

    document.addEventListener(
        'click',
        handleClickOutside
    )

})


onBeforeUnmount(() => {

    document.removeEventListener(
        'click',
        handleClickOutside
    )

})


/*
|--------------------------------------------------------------------------
| FORMAT MONEY
|--------------------------------------------------------------------------
*/

const money = value => {
    return Number(value || 0)
        .toLocaleString('vi-VN')
}

</script>


<template>

    <div
        class="
            bg-white
            transition-colors
        "
        :class="
            error
                ? 'border-red-400 bg-red-50/20'
                : 'border-slate-200'
        "
    >

        <!-- ===================================================== -->
        <!-- NCC + NGÀY NHẬP HÀNG -->
        <!-- ===================================================== -->

        <div class="grid grid-cols-2 gap-3">

            <!-- ================================================= -->
            <!-- NHÀ CUNG CẤP -->
            <!-- ================================================= -->

            <div>

                <!-- ĐÃ CHỌN NCC -->
                <div
                    v-if="props.supplier"
                    class="
                        flex
                        h-10
                        w-full
                        items-center
                        justify-between
                        rounded-md
                        border
                        border-emerald-300
                        bg-emerald-50
                        px-3
                        shadow-sm
                    "
                >

                    <div
                        class="
                            flex
                            min-w-0
                            flex-1
                            items-center
                            gap-2
                        "
                    >

                        <Truck
                            :size="15"
                            class="shrink-0 text-emerald-700"
                        />

                        <div class="min-w-0">

                            <div
                                class="
                                    truncate
                                    text-sm
                                    font-semibold
                                    text-emerald-900
                                "
                            >
                                {{ props.supplier.name }}

                                <span
                                    v-if="
                                        Number(
                                            props.supplier
                                                .debt_balance
                                        ) !== 0
                                    "
                                >
                                    (
                                    nợ:
                                    {{
                                        money(
                                            props.supplier
                                                .debt_balance
                                        )
                                    }}
                                    đ
                                    )
                                </span>

                            </div>

                        </div>

                    </div>


                    <!-- XÓA NCC -->

                    <button
                        type="button"
                        title="Xóa nhà cung cấp"
                        @click="clearSupplier"
                        class="
                            ml-2
                            shrink-0
                            rounded-full
                            p-1
                            text-emerald-400
                            transition-colors
                            hover:bg-red-100
                            hover:text-rose-600
                        "
                    >
                        <X :size="15" />
                    </button>

                </div>


                <!-- CHƯA CHỌN NCC -->

                <div
                    v-else
                    ref="supplierSearchRef"
                    class="relative"
                >

                    <FloatingInput
                        v-model="keyword"
                        @input="search"
                        @keydown="onKeyDown"
                        type="text"
                        label="Nhập tên, SĐT hoặc mã nhà cung cấp"
                    />


                    <!-- NÚT THÊM NCC -->

                    <button
                        v-if="keyword.trim()"
                        type="button"
                        @click="createSupplier"
                        class="
                            absolute
                            right-0
                            top-1/2
                            -translate-y-1/2
                            px-3
                            py-1.5
                            text-emerald-600
                            hover:text-emerald-900
                        "
                    >
                        <Plus :size="20" />
                    </button>


                    <!-- DROPDOWN -->

                    <div
                        v-if="suppliers.length"
                        class="
                            absolute
                            z-50
                            mt-1
                            max-h-64
                            w-full
                            overflow-auto
                            rounded-md
                            border
                            border-slate-200
                            bg-white
                            shadow-lg
                            custom-scrollbar
                        "
                    >

                        <button
                            v-for="(
                                supplier,
                                index
                            ) in suppliers"

                            :key="supplier.id"

                            :ref="
                                el =>
                                    setItemRef(
                                        el,
                                        index
                                    )
                            "

                            type="button"

                            @mouseenter="
                                setActive(index)
                            "

                            @click="
                                selectSupplier(
                                    supplier
                                )
                            "

                            :class="[
                                'block w-full border-b border-slate-100 p-2.5 text-left last:border-b-0 transition-colors',
                                index === activeIndex
                                    ? 'bg-emerald-50'
                                    : 'hover:bg-slate-50'
                            ]"
                        >

                            <!-- TÊN -->

                            <div
                                class="
                                    text-sm
                                    font-semibold
                                    text-slate-900
                                "
                            >
                                {{ supplier.name }}
                            </div>


                            <!-- THÔNG TIN -->

                            <div
                                class="
                                    mt-0.5
                                    flex
                                    gap-2
                                    text-xs
                                    text-slate-500
                                "
                            >

                                <span
                                    v-if="supplier.code"
                                >
                                    {{ supplier.code }}
                                </span>

                                <span
                                    v-if="
                                        supplier.code &&
                                        supplier.phone
                                    "
                                >
                                    •
                                </span>

                                <span
                                    v-if="supplier.phone"
                                >
                                    {{ supplier.phone }}
                                </span>

                            </div>

                        </button>

                    </div>

                </div>

            </div>


            <!-- ================================================= -->
            <!-- NGÀY NHẬP HÀNG -->
            <!-- ================================================= -->

            <div class="relative">

                <FloatingInput
                    v-model="importDate"
                    type="date"
                    label="Ngày nhập hàng"
                />

            </div>

        </div>


        <!-- ===================================================== -->
        <!-- ERROR -->
        <!-- ===================================================== -->

        <p
            v-if="error"
            class="
                mt-1.5
                flex
                items-center
                gap-1
                text-xs
                font-semibold
                text-red-600
            "
        >
            {{ error }}
        </p>

    </div>

</template>


<style scoped>

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

</style>