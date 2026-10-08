<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
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
                title: 'Master Produk',
                href: '/products',
            },
        ],
    },
});

const props = defineProps({
    products: Array,
    categories: Array,
});

// State Pencarian & Filter
const searchQuery = ref('');
const filterCategory = ref('all');

// Filter produk berdasarkan pencarian dan kategori
const filteredProducts = computed(() => {
    return props.products.filter((p) => {
        const matchesSearch =
            p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (p.sku &&
                p.sku.toLowerCase().includes(searchQuery.value.toLowerCase()));

        const matchesCategory =
            filterCategory.value === 'all' ||
            p.category_id === parseInt(filterCategory.value);

        return matchesSearch && matchesCategory;
    });
});

// ---------- PAGINATION STATE ----------
const currentPage = ref(1);
const itemsPerPage = ref(8);

const totalPages = computed(() => {
    return Math.ceil(filteredProducts.value.length / itemsPerPage.value) || 1;
});

const paginatedProducts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;

    return filteredProducts.value.slice(start, end);
});

const displayedPages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - 2);
    const end = Math.min(totalPages.value, start + maxVisible - 1);

    if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});

// Reset ke halaman 1 jika pencarian atau filter kategori berubah
watch([searchQuery, filterCategory], () => {
    currentPage.value = 1;
});

// Form state
const formOpen = ref(false);
const isEditing = ref(false);
const selectedProductId = ref(null);
const deleteConfirmOpen = ref(false);
const deleteTarget = ref(null);
const deleteProcessing = ref(false);
const deleteError = ref('');

const form = useForm({
    category_id: '',
    sku: '',
    name: '',
    image: null,
    unit: 'pcs',
    base_price: 0,
    selling_price: 0,
    admin_fee: 0,
    stock: 0,
    is_active: 1,
});

const imagePreview = ref(null);

const handleImageChange = (e) => {
    const file = e.target.files[0];

    if (file) {
        form.image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

// Reactively check if the selected category is of type 'fisik'
const selectedCategory = computed(() => {
    return props.categories.find((c) => c.id === parseInt(form.category_id));
});

const isPhysicalProduct = computed(() => {
    return selectedCategory.value && selectedCategory.value.type === 'fisik';
});

// Buka modal tambah
const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    imagePreview.value = null;

    if (props.categories.length > 0) {
        form.category_id = props.categories[0].id;
    }

    form.clearErrors();
    formOpen.value = true;
};

// Buka modal edit
const openEditModal = (product) => {
    isEditing.value = true;
    selectedProductId.value = product.id;
    form.category_id = product.category_id;
    form.sku = product.sku || '';
    form.name = product.name;
    form.image = null;
    imagePreview.value = product.image_path || null;
    form.unit = product.unit || 'pcs';
    form.base_price = parseFloat(product.base_price);
    form.selling_price = parseFloat(product.selling_price);
    form.admin_fee = parseFloat(product.admin_fee || 0);
    form.stock = product.stock !== null ? parseFloat(product.stock) : 0;
    form.is_active = product.is_active ? 1 : 0;
    form.clearErrors();
    formOpen.value = true;
};

// Handle submit
const handleSubmit = () => {
    // If not physical product, force stock to null in submit
    if (!isPhysicalProduct.value) {
        form.stock = null;
    }

    if (isEditing.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(`/products/${selectedProductId.value}`, {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
                imagePreview.value = null;
            },
        });
    } else {
        form.post('/products', {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
                imagePreview.value = null;
            },
        });
    }
};

const openDeleteConfirm = (product) => {
    deleteTarget.value = product;
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
const confirmDeleteProduct = () => {
    if (!deleteTarget.value) {
        return;
    }

    deleteProcessing.value = true;
    router.delete(`/products/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
            deleteError.value = '';
        },
        onError: (errors) => {
            deleteError.value =
                errors.error ||
                'Gagal menghapus produk karena masih memiliki transaksi terkait.';
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
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
</script>

<template>
    <Head title="Master Produk" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Header Halaman -->
        <div
            class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Master Produk
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola semua jenis produk Anda (ATK, Fotokopi, Jasa Jilid,
                    Spanduk, dan Pulsa/Digital).
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700"
            >
                <i class="fas fa-plus text-xs"></i>
                Tambah Produk
            </Button>
        </div>

        <!-- Filter & Search Section -->
        <div class="flex w-full flex-col items-center gap-3 sm:flex-row">
            <!-- Search -->
            <div class="relative w-full sm:w-80">
                <i
                    class="fas fa-search absolute top-3 left-3.5 text-sm text-muted-foreground"
                ></i>
                <Input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Cari nama produk / SKU..."
                    class="rounded-xl border-border bg-card pl-10 text-foreground"
                />
            </div>
            <!-- Category Filter -->
            <div class="w-full sm:w-56">
                <select
                    v-model="filterCategory"
                    class="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                >
                    <option value="all">Semua Kategori</option>
                    <option
                        v-for="cat in categories"
                        :key="cat.id"
                        :value="cat.id"
                    >
                        {{ cat.name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Tampilan Tabel Produk -->
        <div
            class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="p-4 pl-6">SKU / Nama Produk</th>
                            <th class="p-4">Kategori</th>
                            <th class="p-4">Harga Modal</th>
                            <th class="p-4">Harga Jual</th>
                            <th class="p-4">Stok</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredProducts.length === 0">
                            <td
                                colspan="7"
                                class="p-8 text-center text-muted-foreground"
                            >
                                <i
                                    class="fas fa-box-open mb-2 text-3xl opacity-30"
                                ></i>
                                <p>Tidak ada data produk ditemukan.</p>
                            </td>
                        </tr>
                        <tr
                            v-for="product in paginatedProducts"
                            :key="product.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- SKU / Name with Thumbnail -->
                            <td class="flex items-center gap-3 p-4 pl-6">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted"
                                >
                                    <img
                                        v-if="product.image_path"
                                        :src="product.image_path"
                                        class="h-full w-full object-cover"
                                    />
                                    <i
                                        v-else
                                        class="fas fa-box text-xs text-muted-foreground"
                                    ></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-foreground">
                                        {{ product.name }}
                                    </p>
                                    <p
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ product.sku || 'TANPA SKU' }}
                                    </p>
                                </div>
                            </td>
                            <!-- Category -->
                            <td class="p-4">
                                <span
                                    class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                    :class="
                                        getTypeBadgeClass(
                                            product.category?.type,
                                        )
                                    "
                                >
                                    {{ product.category?.name }}
                                </span>
                            </td>
                            <!-- base price -->
                            <td class="p-4 text-muted-foreground">
                                Rp {{ formatRupiah(product.base_price) }}
                            </td>
                            <!-- selling price -->
                            <td class="p-4 font-semibold text-foreground">
                                Rp {{ formatRupiah(product.selling_price) }}
                            </td>
                            <!-- stock -->
                            <td class="p-4">
                                <span
                                    v-if="product.category?.type === 'fisik'"
                                    class="font-medium text-foreground"
                                >
                                    {{ parseFloat(product.stock) }}
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ product.unit }}</span
                                    >
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground italic"
                                >
                                    Unlimited ({{
                                        product.category?.type === 'jasa'
                                            ? 'Jasa'
                                            : 'Digital'
                                    }})
                                </span>
                            </td>
                            <!-- status -->
                            <td class="p-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="
                                        product.is_active
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400'
                                            : 'bg-red-100 text-red-800 dark:bg-red-950/20 dark:text-red-400'
                                    "
                                >
                                    {{
                                        product.is_active ? 'Aktif' : 'Nonaktif'
                                    }}
                                </span>
                            </td>
                            <!-- Actions -->
                            <td
                                class="space-x-1 p-4 pr-6 text-right whitespace-nowrap"
                            >
                                <Button
                                    @click="openEditModal(product)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Edit produk"
                                    aria-label="Edit produk"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    <i class="fas fa-edit"></i>
                                </Button>
                                <Button
                                    @click="openDeleteConfirm(product)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Hapus produk"
                                    aria-label="Hapus produk"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <i class="fas fa-trash-alt"></i>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div
                class="flex flex-col items-center justify-between gap-4 border-t border-border bg-muted/10 px-6 py-4 sm:flex-row"
            >
                <div class="text-xs text-muted-foreground">
                    Menampilkan
                    <span class="font-semibold text-foreground">{{
                        filteredProducts.length === 0
                            ? 0
                            : (currentPage - 1) * itemsPerPage + 1
                    }}</span>
                    sampai
                    <span class="font-semibold text-foreground">{{
                        Math.min(
                            currentPage * itemsPerPage,
                            filteredProducts.length,
                        )
                    }}</span>
                    dari
                    <span class="font-semibold text-foreground">{{
                        filteredProducts.length
                    }}</span>
                    produk
                </div>
                <div class="flex items-center gap-1.5" v-if="totalPages > 1">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === 1"
                        @click="currentPage--"
                        class="h-8 w-8 p-0"
                    >
                        <i class="fas fa-chevron-left text-xs"></i>
                    </Button>
                    <Button
                        v-for="page in displayedPages"
                        :key="page"
                        :variant="currentPage === page ? 'default' : 'outline'"
                        size="sm"
                        @click="currentPage = page"
                        class="h-8 w-8 p-0 text-xs"
                    >
                        {{ page }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === totalPages"
                        @click="currentPage++"
                        class="h-8 w-8 p-0"
                    >
                        <i class="fas fa-chevron-right text-xs"></i>
                    </Button>
                </div>
            </div>
        </div>

        <!-- DIALOG FORM (Tambah & Edit Produk) -->
        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent
                class="custom-scroll max-h-[90vh] overflow-y-auto rounded-2xl border-border bg-card text-foreground sm:max-w-[500px]"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        isEditing ? 'Edit Produk' : 'Tambah Produk Baru'
                    }}</DialogTitle>
                    <DialogDescription>
                        Isi data produk di bawah. Perhatikan bahwa input stok
                        akan aktif otomatis hanya jika Anda memilih Kategori
                        Barang Fisik.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSubmit" class="space-y-4 py-3">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Kategori Dropdown -->
                        <div class="col-span-2 space-y-2">
                            <Label for="category" class="text-foreground"
                                >Kategori Produk</Label
                            >
                            <select
                                id="category"
                                v-model="form.category_id"
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                required
                            >
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }} ({{
                                        cat.type === 'fisik'
                                            ? 'Fisik'
                                            : cat.type === 'jasa'
                                              ? 'Jasa'
                                              : 'Digital'
                                    }})
                                </option>
                            </select>
                            <p
                                v-if="form.errors.category_id"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.category_id }}
                            </p>
                        </div>

                        <!-- Nama Produk -->
                        <div class="col-span-2 space-y-2">
                            <Label for="name" class="text-foreground"
                                >Nama Produk</Label
                            >
                            <Input
                                id="name"
                                type="text"
                                v-model="form.name"
                                placeholder="Contoh: Kertas HVS A4, Cetak Spanduk Flexi"
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

                        <!-- SKU -->
                        <div class="space-y-2">
                            <Label for="sku" class="text-foreground"
                                >SKU (Opsional)</Label
                            >
                            <Input
                                id="sku"
                                type="text"
                                v-model="form.sku"
                                placeholder="Contoh: ATK-HVS-01"
                                class="rounded-xl border-border bg-background text-foreground"
                            />
                            <p
                                v-if="form.errors.sku"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.sku }}
                            </p>
                        </div>

                        <!-- Satuan / Unit -->
                        <div class="space-y-2">
                            <Label for="unit" class="text-foreground"
                                >Satuan (Unit)</Label
                            >
                            <Input
                                id="unit"
                                type="text"
                                v-model="form.unit"
                                placeholder="Contoh: pcs, meter, rim"
                                class="rounded-xl border-border bg-background text-foreground"
                            />
                            <p
                                v-if="form.errors.unit"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.unit }}
                            </p>
                        </div>

                        <!-- Harga Modal -->
                        <div class="space-y-2">
                            <Label for="base_price" class="text-foreground"
                                >Harga Modal (Rp)</Label
                            >
                            <Input
                                id="base_price"
                                type="number"
                                v-model.number="form.base_price"
                                class="rounded-xl border-border bg-background text-foreground"
                                required
                            />
                            <p
                                v-if="form.errors.base_price"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.base_price }}
                            </p>
                        </div>

                        <!-- Harga Jual -->
                        <div class="space-y-2">
                            <Label for="selling_price" class="text-foreground"
                                >Harga Jual (Rp)</Label
                            >
                            <Input
                                id="selling_price"
                                type="number"
                                v-model.number="form.selling_price"
                                class="rounded-xl border-border bg-background text-foreground"
                                required
                            />
                            <p
                                v-if="form.errors.selling_price"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.selling_price }}
                            </p>
                        </div>

                        <!-- Biaya Admin -->
                        <div
                            class="space-y-2"
                            v-if="
                                selectedCategory?.type === 'ppob' ||
                                selectedCategory?.type === 'jasa'
                            "
                        >
                            <Label for="admin_fee" class="text-foreground"
                                >Biaya Admin (Rp)</Label
                            >
                            <Input
                                id="admin_fee"
                                type="number"
                                v-model.number="form.admin_fee"
                                class="rounded-xl border-border bg-background text-foreground"
                                required
                            />
                            <p
                                v-if="form.errors.admin_fee"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.admin_fee }}
                            </p>
                        </div>

                        <!-- Stok (Hanya muncul jika Kategori Fisik) -->
                        <div
                            class="col-span-2 space-y-2 rounded-xl border border-indigo-100/50 bg-indigo-50/20 p-4 transition-all duration-300 dark:border-indigo-900/30 dark:bg-indigo-950/20"
                            v-if="isPhysicalProduct"
                        >
                            <Label
                                for="stock"
                                class="flex items-center gap-2 font-semibold text-foreground"
                            >
                                <i class="fas fa-warehouse text-indigo-500"></i>
                                Jumlah Stok Fisik Awal
                            </Label>
                            <Input
                                id="stock"
                                type="number"
                                v-model.number="form.stock"
                                class="rounded-xl border-border bg-background text-foreground"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                Kategori bertipe Barang Fisik wajib mengelola
                                stok.
                            </p>
                            <p
                                v-if="form.errors.stock"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.stock }}
                            </p>
                        </div>
                        <div
                            class="col-span-2 space-y-2 rounded-xl border border-border bg-muted/40 p-4 transition-all duration-300"
                            v-else
                        >
                            <p
                                class="flex items-center gap-2 text-xs font-medium text-muted-foreground"
                            >
                                <i
                                    class="fas fa-info-circle text-orange-500"
                                ></i>
                                Kategori bertipe
                                <strong>{{
                                    selectedCategory?.type === 'jasa'
                                        ? 'Jasa'
                                        : 'Digital / PPOB'
                                }}</strong>
                                tidak menggunakan stok fisik (Unlimited).
                            </p>
                        </div>

                        <!-- Gambar Produk (Opsional) -->
                        <div
                            class="col-span-2 space-y-2 rounded-xl border border-border bg-muted/20 p-4"
                        >
                            <Label
                                for="image"
                                class="block font-medium text-foreground"
                                >Gambar Produk (Opsional)</Label
                            >
                            <div class="mt-2 flex items-center gap-4">
                                <!-- Preview -->
                                <div
                                    class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-background shadow-inner"
                                >
                                    <img
                                        v-if="imagePreview"
                                        :src="imagePreview"
                                        class="h-full w-full object-cover"
                                    />
                                    <i
                                        v-else
                                        class="fas fa-image text-2xl text-muted-foreground"
                                    ></i>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <!-- File Input -->
                                    <input
                                        id="image"
                                        type="file"
                                        @change="handleImageChange"
                                        class="hidden"
                                        accept="image/*"
                                    />
                                    <label
                                        for="image"
                                        class="inline-flex w-fit cursor-pointer items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-700"
                                    >
                                        <i
                                            class="fas fa-cloud-upload-alt mr-1.5"
                                        ></i>
                                        Pilih Gambar
                                    </label>
                                    <span
                                        v-if="form.image"
                                        class="max-w-[200px] truncate text-[10px] text-muted-foreground"
                                    >
                                        {{ form.image.name }}
                                    </span>
                                </div>
                            </div>
                            <p
                                v-if="form.errors.image"
                                class="mt-1 text-xs font-medium text-red-500"
                            >
                                {{ form.errors.image }}
                            </p>
                        </div>

                        <!-- Status Aktif -->
                        <div class="col-span-2 space-y-2">
                            <Label for="is_active" class="text-foreground"
                                >Status Produk</Label
                            >
                            <select
                                id="is_active"
                                v-model.number="form.is_active"
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <option :value="1">
                                    Aktif (Tampil di POS)
                                </option>
                                <option :value="0">Nonaktif</option>
                            </select>
                            <p
                                v-if="form.errors.is_active"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.is_active }}
                            </p>
                        </div>
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

        <!-- DIALOG KONFIRMASI HAPUS PRODUK -->
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
                        Hapus Produk?
                    </DialogTitle>
                    <DialogDescription>
                        Produk {{ deleteTarget?.name }} akan dihapus permanen.
                        Produk yang sudah masuk transaksi biasanya tidak bisa
                        dihapus.
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
                        @click="confirmDeleteProduct"
                    >
                        {{ deleteProcessing ? 'Menghapus...' : 'Ya, Hapus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
