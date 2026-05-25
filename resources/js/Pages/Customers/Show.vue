<script setup>
import { ref } from 'vue'

import CustomerTabs from './Partials/CustomerTabs.vue'

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

const activeTab = ref('Tổng quan')
</script>

<template>

    <div class="p-4">

        <!-- HEADER -->
        <div class="bg-white rounded shadow p-4 mb-4">

            <div class="flex justify-between items-start">

                <div>

                    <h1 class="text-2xl font-bold">
                        {{ customer.full_name }}
                    </h1>

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
                        Công nợ: {{ customer.debt_balance }}
                    </div>

                </div>

            </div>

        </div>

        <!-- TABS -->
        <CustomerTabs
            :tabs="tabs"
            :active-tab="activeTab"
            @change="activeTab = $event"
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

            <table class="w-full">

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

            <table class="w-full">

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
                            {{ item.amount }}
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
                        :src="`/storage/${image.path}`"
                        class="rounded border"
                    />

                </div>

            </div>

        </div>

    </div>

</template>