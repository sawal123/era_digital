<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Data Master Pembayaran', href: '/payment-methods' },
        ],
    },
});

const props = defineProps({
    paymentMethods: Array,
});

const searchQuery = ref('');
const formOpen = ref(false);
const isEditing = ref(false);
const selectedId = ref(null);
const deleteConfirmOpen = ref(false);
const deleteTarget = ref(null);
const deleteProcessing = ref(false);
const deleteError = ref('');

const form = useForm({
    name: '',
    code: '',
    is_cash: false,
    is_active: true,
    sort_order: 0,
});

const filteredMethods = computed(() => {
    const query = searchQuery.value.toLowerCase();

    if (!query) {
        return props.paymentMethods;
    }

    return props.paymentMethods.filter(
        (method) =>
            method.name.toLowerCase().includes(query) ||
            method.code.toLowerCase().includes(query),
    );
});

const openCreateModal = () => {
    isEditing.value = false;
    selectedId.value = null;
    form.reset();
    form.is_cash = false;
    form.is_active = true;
    form.sort_order = props.paymentMethods.length + 1;
    form.clearErrors();
    formOpen.value = true;
};

const openEditModal = (method) => {
    isEditing.value = true;
    selectedId.value = method.id;
    form.name = method.name;
    form.code = method.code;
    form.is_cash = method.is_cash;
    form.is_active = method.is_active;
    form.sort_order = method.sort_order;
    form.clearErrors();
    formOpen.value = true;
};

const handleSubmit = () => {
    const options = {
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    };

    if (isEditing.value) {
        form.put(`/payment-methods/${selectedId.value}`, options);
    } else {
        form.post('/payment-methods', options);
    }
};

const openDeleteConfirm = (method) => {
    deleteTarget.value = method;
    deleteError.value = '';
    deleteConfirmOpen.value = true;
};

const closeDeleteConfirm = () => {
    if (deleteProcessing.value) {
        return;
    }

    deleteConfirmOpen.value = false;
    deleteTarget.value = null;
    deleteError.value = '';
};

const confirmDeleteMethod = () => {
    if (!deleteTarget.value) {
        return;
    }

    deleteProcessing.value = true;
    router.delete(`/payment-methods/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
            deleteError.value = '';
        },
        onError: (errors) => {
            deleteError.value =
                errors.error || 'Gagal menghapus metode pembayaran.';
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};
</script>

<template>
    <Head title="Data Master Pembayaran" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Data Master Pembayaran
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola metode pembayaran yang tersedia pada POS kasir.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700"
            >
                <i class="fas fa-plus text-xs"></i>
                Tambah Metode
            </Button>
        </div>

        <div class="flex w-full items-center gap-3 sm:w-80">
            <div class="relative w-full">
                <i
                    class="fas fa-search absolute top-3 left-3.5 text-sm text-muted-foreground"
                ></i>
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari metode pembayaran..."
                    class="rounded-xl border-border bg-card pl-10 text-foreground"
                />
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="p-4 pl-6">Nama Metode</th>
                            <th class="p-4">Kode</th>
                            <th class="p-4">Jenis</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-center">Urutan</th>
                            <th class="p-4 text-center">Transaksi</th>
                            <th class="p-4 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredMethods.length === 0">
                            <td
                                colspan="7"
                                class="p-8 text-center text-muted-foreground"
                            >
                                <i
                                    class="fas fa-credit-card mb-2 text-3xl opacity-30"
                                ></i>
                                <p>Tidak ada metode pembayaran ditemukan.</p>
                            </td>
                        </tr>
                        <tr
                            v-for="method in filteredMethods"
                            :key="method.id"
                            class="transition hover:bg-muted/30"
                        >
                            <td class="p-4 pl-6 font-semibold text-foreground">
                                {{ method.name }}
                            </td>
                            <td
                                class="p-4 font-mono text-xs text-muted-foreground"
                            >
                                {{ method.code }}
                            </td>
                            <td class="p-4">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        method.is_cash
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400'
                                            : 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-400'
                                    "
                                >
                                    {{ method.is_cash ? 'Tunai' : 'Non-Tunai' }}
                                </span>
                            </td>
                            <td class="p-4">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        method.is_active
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400'
                                            : 'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400'
                                    "
                                >
                                    {{
                                        method.is_active ? 'Aktif' : 'Nonaktif'
                                    }}
                                </span>
                            </td>
                            <td class="p-4 text-center font-mono">
                                {{ method.sort_order }}
                            </td>
                            <td class="p-4 text-center text-muted-foreground">
                                {{ method.transactions_count }}
                            </td>
                            <td class="space-x-2 p-4 pr-6 text-right">
                                <Button
                                    @click="openEditModal(method)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Edit metode pembayaran"
                                    aria-label="Edit metode pembayaran"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400"
                                >
                                    <i class="fas fa-edit"></i>
                                </Button>
                                <Button
                                    @click="openDeleteConfirm(method)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Hapus metode pembayaran"
                                    aria-label="Hapus metode pembayaran"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400"
                                >
                                    <i class="fas fa-trash-alt"></i>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[460px]"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        isEditing
                            ? 'Edit Metode Pembayaran'
                            : 'Tambah Metode Pembayaran'
                    }}</DialogTitle>
                    <DialogDescription
                        >Metode aktif akan tampil sebagai pilihan kartu pada POS
                        kasir.</DialogDescription
                    >
                </DialogHeader>

                <form @submit.prevent="handleSubmit" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="payment-name">Nama Metode</Label>
                        <Input
                            id="payment-name"
                            v-model="form.name"
                            placeholder="Contoh: QRIS BCA"
                            class="rounded-xl"
                            required
                        />
                        <p v-if="form.errors.name" class="text-xs text-red-500">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="payment-code">Kode</Label>
                        <Input
                            id="payment-code"
                            v-model="form.code"
                            placeholder="Contoh: qris_bca"
                            class="rounded-xl font-mono"
                            required
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Gunakan kode singkat tanpa spasi.
                        </p>
                        <p v-if="form.errors.code" class="text-xs text-red-500">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="payment-order">Urutan Tampil</Label>
                            <Input
                                id="payment-order"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="rounded-xl"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="payment-status">Status</Label>
                            <select
                                id="payment-status"
                                v-model="form.is_active"
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground"
                            >
                                <option :value="true">Aktif</option>
                                <option :value="false">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-muted/30 p-3"
                    >
                        <input
                            v-model="form.is_cash"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-border text-indigo-600"
                        />
                        <span>
                            <span class="block text-sm font-semibold"
                                >Metode Tunai</span
                            >
                            <span class="block text-xs text-muted-foreground"
                                >Jika dipilih, POS akan meminta uang diterima
                                dan menghitung kembalian. Hanya satu metode
                                dapat menjadi tunai.</span
                            >
                        </span>
                    </label>

                    <DialogFooter class="gap-2 pt-4">
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="secondary"
                                class="rounded-xl"
                                >Batal</Button
                            >
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="deleteConfirmOpen"
            @update:open="
                (open) =>
                    open ? (deleteConfirmOpen = true) : closeDeleteConfirm()
            "
        >
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[420px]"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-triangle-exclamation text-red-500"></i>
                        Hapus Metode Pembayaran?
                    </DialogTitle>
                    <DialogDescription>
                        Metode pembayaran {{ deleteTarget?.name }} akan dihapus
                        dari pilihan POS kasir.
                    </DialogDescription>
                </DialogHeader>
                <p
                    v-if="deleteError"
                    class="rounded-xl border border-red-500/20 bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400"
                >
                    {{ deleteError }}
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        class="rounded-xl"
                        @click="closeDeleteConfirm"
                        >Batal</Button
                    >
                    <Button
                        type="button"
                        :disabled="deleteProcessing"
                        class="rounded-xl bg-red-600 text-white hover:bg-red-700"
                        @click="confirmDeleteMethod"
                    >
                        {{ deleteProcessing ? 'Menghapus...' : 'Ya, Hapus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
