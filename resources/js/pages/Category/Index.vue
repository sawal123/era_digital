<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Master Kategori',
                href: '/categories',
            },
        ],
    },
});

const props = defineProps({
    categories: Array,
});

// State Pencarian
const searchQuery = ref('');

// Filter kategori berdasarkan pencarian
const filteredCategories = computed(() => {
    if (!searchQuery.value) {
        return props.categories;
    }

    return props.categories.filter(
        (c) =>
            c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            c.type.toLowerCase().includes(searchQuery.value.toLowerCase()),
    );
});

// Form state
const formOpen = ref(false);
const isEditing = ref(false);
const selectedCategoryId = ref(null);
const deleteConfirmOpen = ref(false);
const deleteTarget = ref(null);
const deleteProcessing = ref(false);
const deleteError = ref('');

const form = useForm({
    name: '',
    type: 'fisik',
});

// Buka modal untuk tambah
const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
};

// Buka modal untuk edit
const openEditModal = (category) => {
    isEditing.value = true;
    selectedCategoryId.value = category.id;
    form.name = category.name;
    form.type = category.type;
    form.clearErrors();
    formOpen.value = true;
};

// Handle submit
const handleSubmit = () => {
    if (isEditing.value) {
        form.put(`/categories/${selectedCategoryId.value}`, {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/categories', {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    }
};

const openDeleteConfirm = (category) => {
    deleteTarget.value = category;
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

// Handle delete
const confirmDeleteCategory = () => {
    if (!deleteTarget.value) {
        return;
    }

    deleteProcessing.value = true;
    router.delete(`/categories/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
            deleteError.value = '';
        },
        onError: (errors) => {
            deleteError.value = errors.error || 'Gagal menghapus kategori.';
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};

const getTypeBadgeClass = (type) => {
    if (type === 'fisik') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900';
    }

    if (type === 'jasa') {
        return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/30 dark:text-orange-400 dark:border-orange-900';
    }

    return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-900';
};

const getTypeLabel = (type) => {
    if (type === 'fisik') {
        return 'Barang Fisik';
    }

    if (type === 'jasa') {
        return 'Jasa Cetak / Jilid';
    }

    return 'Saldo Digital / PPOB';
};
</script>

<template>
    <Head title="Master Kategori" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Header Halaman -->
        <div
            class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Master Kategori
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola kategori bisnis Anda untuk Barang Fisik, Jasa, maupun
                    PPOB.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700"
            >
                <i class="fas fa-plus text-xs"></i>
                Tambah Kategori
            </Button>
        </div>

        <!-- Filter & Search -->
        <div class="flex w-full items-center gap-3 sm:w-80">
            <div class="relative w-full">
                <i
                    class="fas fa-search absolute top-3 left-3.5 text-sm text-muted-foreground"
                ></i>
                <Input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Cari kategori..."
                    class="rounded-xl border-border bg-card pl-10 text-foreground"
                />
            </div>
        </div>

        <!-- Tampilan Tabel Kategori -->
        <div
            class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="p-4 pl-6">Nama Kategori</th>
                            <th class="p-4">Tipe Kategori</th>
                            <th class="p-4">Jumlah Produk</th>
                            <th class="p-4 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredCategories.length === 0">
                            <td
                                colspan="4"
                                class="p-8 text-center text-muted-foreground"
                            >
                                <i
                                    class="fas fa-folder-open mb-2 text-3xl opacity-30"
                                ></i>
                                <p>Tidak ada data kategori ditemukan.</p>
                            </td>
                        </tr>
                        <tr
                            v-for="category in filteredCategories"
                            :key="category.id"
                            class="transition hover:bg-muted/30"
                        >
                            <td class="p-4 pl-6 font-semibold text-foreground">
                                {{ category.name }}
                            </td>
                            <td class="p-4">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="getTypeBadgeClass(category.type)"
                                >
                                    {{ getTypeLabel(category.type) }}
                                </span>
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ category.products_count || 0 }} Produk
                            </td>
                            <td class="space-x-2 p-4 pr-6 text-right">
                                <Button
                                    @click="openEditModal(category)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Edit kategori"
                                    aria-label="Edit kategori"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    <i class="fas fa-edit"></i>
                                </Button>
                                <Button
                                    @click="openDeleteConfirm(category)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Hapus kategori"
                                    aria-label="Hapus kategori"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <i class="fas fa-trash-alt"></i>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DIALOG FORM (Tambah & Edit Kategori) -->
        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[425px]"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        isEditing ? 'Edit Kategori' : 'Tambah Kategori Baru'
                    }}</DialogTitle>
                    <DialogDescription>
                        Lengkapi informasi kategori di bawah ini. Pastikan tipe
                        kategori disesuaikan dengan jenis bisnis Anda.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSubmit" class="space-y-4 py-4">
                    <!-- Nama Kategori -->
                    <div class="space-y-2">
                        <Label for="name" class="text-foreground"
                            >Nama Kategori</Label
                        >
                        <Input
                            id="name"
                            type="text"
                            v-model="form.name"
                            placeholder="Contoh: ATK, Cetak Spanduk, Token Listrik"
                            class="rounded-xl border-border bg-background text-foreground"
                            required
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-xs font-medium text-red-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Tipe Kategori -->
                    <div class="space-y-2">
                        <Label for="type" class="text-foreground"
                            >Tipe Kategori (Penting)</Label
                        >
                        <select
                            id="type"
                            v-model="form.type"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            required
                        >
                            <option value="fisik">
                                Barang Fisik (ATK, Kertas, dll)
                            </option>
                            <option value="jasa">
                                Jasa / Pre-Order (Cetak Spanduk, Jilid, dll)
                            </option>
                            <option value="ppob">
                                Pembayaran Digital / PPOB (Pulsa, Token, dll)
                            </option>
                        </select>
                        <p
                            v-if="form.errors.type"
                            class="text-xs font-medium text-red-500"
                        >
                            {{ form.errors.type }}
                        </p>
                    </div>

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

        <!-- DIALOG KONFIRMASI HAPUS KATEGORI -->
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
                        Hapus Kategori?
                    </DialogTitle>
                    <DialogDescription>
                        Kategori {{ deleteTarget?.name }} akan dihapus permanen.
                        Pastikan tidak ada produk yang masih memakai kategori
                        ini.
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
                        @click="confirmDeleteCategory"
                    >
                        {{ deleteProcessing ? 'Menghapus...' : 'Ya, Hapus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
