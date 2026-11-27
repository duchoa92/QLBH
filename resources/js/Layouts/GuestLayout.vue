<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 px-4 pt-6 sm:justify-center sm:pt-0"
    >
        <div class="w-full text-center sm:max-w-md">
            <p class="text-base font-bold text-slate-800">
                Phát triển bởi Đức Hòa Computer
            </p>
            <p class="mt-0.5 text-sm text-slate-500">Vạn Xuân, Hưng Yên</p>
            <p class="mt-2 text-sm text-slate-600">
                Hỗ trợ:
                ĐT: <a href="tel:0906064789" class="font-semibold text-emerald-700 hover:underline">0906064789</a>
                · Zalo: <a href="https://zalo.me/0906064789" target="_blank" rel="noopener" class="font-semibold text-emerald-700 hover:underline">0906064789</a>
                · FB: <a href="https://fb.com/duchoait" target="_blank" rel="noopener" class="font-semibold text-emerald-700 hover:underline">fb.com/duchoait</a>
            </p>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>