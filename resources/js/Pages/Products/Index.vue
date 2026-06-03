<script setup>
import { Link, router } from '@inertiajs/vue3'

defineProps({
    products: Object,
})

const destroy = (id) => {

    if (!confirm('Xoa san pham nay?')) {
        return
    }

    router.delete(
        route('products.destroy', id)
    )
}

const money = (value) => {

    return Number(value || 0).toLocaleString('vi-VN')
}
</script>

<template>
    <div class="space-y-4">

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h1 class="text-2xl font-black text-slate-950">
                    San pham
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Quan ly danh sach hang hoa, ton kho va IMEI.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">

                <Link
                    :href="route('products.trash')"
                    class="rounded-md border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-100"
                >
                    Thung rac
                </Link>

                <Link
                    :href="route('products.create')"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
                >
                    Them san pham
                </Link>

            </div>

        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">

            <div class="overflow-x-auto">

                <table class="w-full min-w-[920px] text-sm">

                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">San pham</th>
                            <th class="px-4 py-3 text-left">Danh muc</th>
                            <th class="px-4 py-3 text-left">Thuong hieu</th>
                            <th class="px-4 py-3 text-right">Gia ban</th>
                            <th class="px-4 py-3 text-center">Ton kho</th>
                            <th class="px-4 py-3 text-right">Thao tac</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="hover:bg-slate-50"
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
                                        IMG
                                    </div>

                                    <div>
                                        <div class="font-bold text-slate-950">
                                            {{ product.name }}
                                        </div>

                                        <div class="text-xs text-slate-500">
                                            ID: {{ product.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-slate-700">
                                {{ product.category?.name || '-' }}
                            </td>

                            <td class="px-4 py-3 text-slate-700">
                                {{ product.brand?.name || '-' }}
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-rose-600">
                                {{ money(product.sell_price) }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span
                                    class="rounded px-2 py-1 text-xs font-bold"
                                    :class="Number(product.stock || 0) > 0
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-rose-50 text-rose-700'"
                                >
                                    {{ product.stock }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link
                                        :href="route('product-imeis.index', product.id)"
                                        class="font-bold text-emerald-700 hover:text-emerald-800"
                                    >
                                        IMEI
                                    </Link>

                                    <Link
                                        :href="route('products.show', product.id)"
                                        class="font-bold text-slate-600 hover:text-slate-950"
                                    >
                                        Xem
                                    </Link>

                                    <Link
                                        :href="route('products.edit', product.id)"
                                        class="font-bold text-blue-600 hover:text-blue-700"
                                    >
                                        Sua
                                    </Link>

                                    <button
                                        type="button"
                                        @click="destroy(product.id)"
                                        class="font-bold text-rose-600 hover:text-rose-700"
                                    >
                                        Xoa
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!products.data.length">
                            <td
                                colspan="6"
                                class="px-4 py-10 text-center text-sm font-semibold text-slate-500"
                            >
                                Chua co san pham.
                            </td>
                        </tr>
                    </tbody>

                </table>

            </div>

        </div>

    </div>
</template>
