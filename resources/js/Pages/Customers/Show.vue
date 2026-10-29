<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

import CustomerTabs from './Partials/CustomerTabs.vue'
import { imageUrl } from '@/utils/imageUrl'

const props = defineProps({
    customer: Object,
})

const tabs = [
    'Tổng quan',
    'Thiết bị',
    'Điểm',
    'Công nợ',
    'Hình ảnh',
]

const tabKeys = {
    overview: 'Tổng quan',
    devices: 'Thiết bị',
    points: 'Điểm',
    debts: 'Công nợ',
    images: 'Hình ảnh',
}
const readActiveTab = () => tabKeys[new URL(window.location.href).searchParams.get('tab')] || 'Tổng quan'
const activeTab = ref(readActiveTab())

const selectTab = (label) => {
    const key = Object.keys(tabKeys).find(candidate => tabKeys[candidate] === label)
    if (!key || activeTab.value === label) return
    activeTab.value = label
    const url = new URL(window.location.href)
    url.searchParams.set('tab', key)
    router.push({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true })
}

const restoreTabFromUrl = () => { activeTab.value = readActiveTab() }
window.addEventListener('popstate', restoreTabFromUrl)
onBeforeUnmount(() => window.removeEventListener('popstate', restoreTabFromUrl))
</script>

<template>

    <div class="p-4">

        <!-- HEADER -->
        <div class="bg-white rounded shadow p-4 mb-4">

            <div class="flex justify-between items-start">

                <div>

                    <div class="text-gray-500 mt-1">
                        {{ customer.phone }}
                    </div>

                </div>

                <div class="text-right">

                    <div class="text-blue-600">
                        Điểm: {{ customer.point_balance }}
                    </div>

                    <div
                        :class="customer.debt_balance > 0
                            ? 'text-red-600'
                            : 'text-green-600'"
                    >
                        Công nợ: {{ $money(customer.debt_balance) }}
                    </div>

                </div>

            </div>

        </div>

        <!-- TABS -->
        <CustomerTabs
            :tabs="tabs"
            :active-tab="activeTab"
            @change="selectTab"
        />

        <!-- TAB CONTENT -->

        <!-- OVERVIEW -->
        <div
            v-if="activeTab === 'Tổng quan'"
            class="bg-white rounded shadow p-4"
        >

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <div class="text-sm text-gray-500">
                        CCCD
                    </div>

                    <div>
                        {{ customer.cccd || '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">
                        Ngày sinh
                    </div>

                    <div>
                        {{ customer.birthday || '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">
                        Địa chỉ
                    </div>

                    <div>
                        {{ customer.address || '-' }}
                    </div>
                </div>

            </div>

        </div>

        <!-- DEVICES -->
        <div
            v-if="activeTab === 'Thiết bị'"
            class="bg-white rounded shadow p-4"
        >

            <div
                v-for="device in customer.devices"
                :key="device.id"
                class="border rounded p-3 mb-2"
            >

                <div class="font-bold">
                    {{ device.brand }} {{ device.model }}
                </div>

                <div class="text-sm text-gray-500">
                    IMEI: {{ device.imei || '-' }}
                </div>

            </div>

        </div>

        <!-- POINTS -->
        <div
            v-if="activeTab === 'Điểm'"
            class="bg-white rounded shadow p-4"
        >

            <table class="app-data-table w-full">

                <thead>

                    <tr class="border-b">

                        <th class="text-left p-2">
                            Loại
                        </th>

                        <th class="text-left p-2">
                            Điểm
                        </th>

                        <th class="text-left p-2">
                            Ghi chú
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="item in customer.points"
                        :key="item.id"
                        class="border-b"
                    >

                        <td class="p-2">
                            {{ item.type }}
                        </td>

                        <td class="p-2">
                            {{ item.points }}
                        </td>

                        <td class="p-2">
                            {{ item.note }}
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <!-- DEBT -->
        <div
            v-if="activeTab === 'Công nợ'"
            class="bg-white rounded shadow p-4"
        >

            <table class="app-data-table w-full">

                <thead>

                    <tr class="border-b">

                        <th class="text-left p-2">
                            Loại
                        </th>

                        <th class="text-left p-2">
                            Số tiền
                        </th>

                        <th class="text-left p-2">
                            Ghi chú
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="item in customer.debts"
                        :key="item.id"
                        class="border-b"
                    >

                        <td class="p-2">
                            {{ item.type }}
                        </td>

                        <td class="p-2">
                            {{ $money(item.amount) }}
                        </td>

                        <td class="p-2">
                            {{ item.note }}
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <!-- IMAGES -->
        <div
            v-if="activeTab === 'Hình ảnh'"
            class="bg-white rounded shadow p-4"
        >

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

                <div
                    v-for="image in customer.images"
                    :key="image.id"
                >

                    <img
                        :src="imageUrl(image.path)"
                        class="rounded border"
                    />

                </div>

            </div>

        </div>

    </div>

</template>
