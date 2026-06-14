<script setup>
import { ref, watch, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { debounce } from 'lodash'

const props = defineProps({
    products: Object,
    filters: Object
})

const keyword = ref(props.filters?.search || '')

const serverSearching = ref(false)
const loading = ref(false)
const searchServer = debounce((value) => {
    serverSearching.value = true
    
    router.get(route('products.index'), {
        search: value
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        onFinish: () => {
            serverSearching.value = false
        }
    })
}, 400)

watch(keyword, (value) => {
    searchServer(value)
})


const destroy = (id) => {

    if (!confirm('Xóa sản phẩm này?')) {
        return
    }

    router.delete(
        route('products.destroy', id)
    )
}

const money = (value) => {

    return Number(value || 0).toLocaleString('vi-VN')
}


const isSearching = computed(() => {
    return keyword.value.trim().length > 0
})


const showImeis = computed(() => {
    return serverSearching.value || keyword.value.trim().length > 0
})


const getImeis = (product) => {
    return product.imeis ?? []
}

const highlight = (text) => {
    if (!text) return ''

    const kw = keyword.value.trim()
    if (!kw) return text

    const regex = new RegExp(`(${kw})`, 'gi')
    return text.replace(regex, '<span class="bg-yellow-200">$1</span>')
}

</script>

<template>
    <div class="space-y-4">

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h1 class="text-2xl font-black text-slate-950">
                    Sản Phẩm
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Quản lý kho hàng, tồn kho và IMEI.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">

                <Link
                    :href="route('products.trash')"
                    class="rounded-md border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-100"
                >
                    Thùng rác
                </Link>

                <Link
                    :href="route('products.create')"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
                >
                    Thêm sản phẩm
                </Link>

            </div>

        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">

            <div class="overflow-x-auto">

                <div class="p-4 border-b">
                    <input
                        v-model="keyword"
                        placeholder="Tìm sản phẩm..."
                        class="w-full rounded-md border px-3 py-2 text-sm focus:ring focus:ring-blue-200"
                    />
                </div>

                <div v-if="loading" class="p-2 text-sm text-blue-500">
                    🔍 Đang tìm...
                </div>
                <div v-if="keyword" class="text-sm text-gray-500">
                    kết quả tìm với: <b>{{ keyword }}</b>
                    <button 
                        v-if="keyword"
                        @click="keyword = ''"
                        class="ml-2 text-sm text-red-500 hover:text-black"
                    >xóa</button>
                </div>

                <table class="w-full min-w-[920px] text-sm">

                    <thead class="bg-slate-50 sticky top-0 z-10 text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Sản phẩm</th>
                            <th class="px-4 py-3 text-left">Danh mục</th>
                            <th class="px-4 py-3 text-left">Thương hiệu</th>
                            <th class="px-4 py-3 text-right">Giá bán</th>
                            <th class="px-4 py-3 text-center">Tông kho</th>
                            <th class="px-4 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="hover:bg-blue-50 transition"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="product.image_url"
                                        :src="product.image_url"
                                        class="h-12 w-12 rounded-md border border-slate-200 object-cover"
                                    >

                                    <div
                                        v-else
                                        class="flex h-12 w-12 items-center justify-center rounded-md bg-slate-100 text-xs font-bold text-slate-400"
                                    >
                                        Ảnh sản phẩm
                                    </div>

                                    <div>
                                        <div class="font-bold text-slate-950">
                                            <div
                                                class="font-bold text-slate-950"
                                                v-html="highlight(product.name)"
                                            ></div>
                                        </div>

                                        <div class="text-xs text-slate-500">
                                            <span>SKU: </span>
                                            <span v-html="highlight(product.sku)"></span>
                                        </div>
                                        <!-- CHỈ HIỆN IMEI KHI SEARCH -->
                                        <div v-if="showImeis && getImeis(product).length">
                                            <div
                                                v-for="imei in getImeis(product).slice(0,3)"
                                                :key="imei.id"
                                                class="text-xs text-gray-500"
                                            >
                                                Imei: <span v-html="highlight(imei.imei)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-slate-700">
                                <span v-html="highlight(product.category?.name)"></span>
                            </td>

                            <td class="px-4 py-3 text-slate-700">
                                <span v-html="highlight(product.brand?.name)"></span>
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-rose-600">
                                {{ money(product.sell_price) }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span
                                    class="rounded px-2 py-1 text-xs font-bold"
                                    :class="
                                        product.stock == 0
                                            ? 'bg-rose-100 text-rose-700'
                                            : product.stock < 5
                                            ? 'bg-yellow-100 text-yellow-700'
                                            : 'bg-emerald-50 text-emerald-700'
                                    "
                                >
                                    {{ product.stock }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2 text-sm">

                                    <Link
                                        :href="route('products.show', product.id)"
                                        class="text-gray-500 hover:text-black"
                                        title="Xem"
                                    >
                                        👁
                                    </Link>

                                    <Link
                                        :href="route('products.edit', product.id)"
                                        class="text-blue-500 hover:text-blue-700"
                                        title="Sửa"
                                    >
                                        ✏️
                                    </Link>

                                    <button
                                        @click="destroy(product.id)"
                                        class="text-red-500 hover:text-red-700"
                                        title="Xóa"
                                    >
                                        🗑
                                    </button>

                                </div>
                            </td>
                        </tr>

                        <tr v-if="!products.data.length && !loading">
                            <td
                                colspan="6"
                                class="px-4 py-10 text-center text-sm font-semibold text-slate-500"
                            >
                                Chưa có sản phẩm nào!.
                            </td>
                        </tr>
                    </tbody>

                    
                </table>

                <!--Phân trang-->
                    <div class="flex justify-center mt-4 gap-1">

                        <Link
                            v-for="link in products.links"
                            :key="link.label"
                            :href="link.url || ''"
                            v-html="link.label"
                            class="px-3 py-1 text-sm border rounded transition"
                            :class="{
                                'bg-blue-600 text-white': link.active,
                                'text-gray-400 pointer-events-none': !link.url
                            }"
                        />

                    </div>
            </div>

        </div>

    </div>
</template>
