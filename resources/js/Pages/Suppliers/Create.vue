<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SupplierFormFields from '@/Pages/Suppliers/Partials/SupplierFormFields.vue'

const form = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
})

const submit = () => {
    form.post(route('suppliers.store'))
}
</script>

<template>
    <Head title="Thêm nhà cung cấp" />

    <AuthenticatedLayout>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-bold uppercase tracking-wide text-blue-600">
                        Nhà cung cấp
                    </div>

                    <h2 class="mt-2 text-2xl font-black text-slate-950">
                        Thêm nhà cung cấp
                    </h2>
                </div>

                <Link
                    :href="route('suppliers.index')"
                    class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                >
                    Quay lại
                </Link>
            </div>

            <form
                class="mt-6 space-y-6"
                @submit.prevent="submit"
            >

                <SupplierFormFields :form="form" />

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <Link
                        :href="route('suppliers.index')"
                        class="rounded-md border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50"
                    >
                        Hủy
                    </Link>

                    <button
                        type="submit"
                        class="rounded-md bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
                    </button>
                </div>

            </form>

        </section>

    </AuthenticatedLayout>
</template>
