<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import RichTextEditor from '@/components/RichTextEditor.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Undangan', href: '/undangan' },
        ],
    },
});

const props = defineProps({
    undangan: Array, // semua data undangan (flat array dari API)
    jenisUndangan: Array, // daftar jenis dari API /jenis-undangan
    apiError: String,
});

// ------------------------------------------------------------------
// HELPERS
// ------------------------------------------------------------------
const formatRupiah = (angka) => {
    const n = Number(angka) || 0;
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(n);
};

const isFavorite = (item) => Number(item.favorite) === 1;

const storageBase = (item) => {
    if (!item.thumbnail_url) return null;
    const match = item.thumbnail_url.match(/^(https?:\/\/[^/]+)\/storage\//);
    return match ? `${match[1]}/storage/` : null;
};

const itemImages = (item) => {
    if (Array.isArray(item.image_urls) && item.image_urls.length) return item.image_urls;
    const base = storageBase(item);
    if (base && Array.isArray(item.gambar) && item.gambar.length) {
        return item.gambar.map((path) => (path.startsWith('http') ? path : base + path.replace(/^\//, '')));
    }
    if (item.thumbnail_url) return [item.thumbnail_url];
    return [];
};

const hasPromo = (item) => Number(item.promo) > 0;

// ------------------------------------------------------------------
// STATE FILTER & PAGINATION (client-side)
// ------------------------------------------------------------------
const allItems = computed(() => props.undangan || []);

const searchQuery = ref('');
const filterJenis = ref('all');
const filterFavorite = ref('all');
const filterPromo = ref('all');
const sortValue = ref('id:desc');
const perPage = ref(50);
const currentPage = ref(1);

const jenisOptions = computed(() => {
    if (Array.isArray(props.jenisUndangan) && props.jenisUndangan.length) {
        return [...props.jenisUndangan].sort((a, b) => String(a.jenis).localeCompare(String(b.jenis)));
    }
    return [];
});

const sortOptions = [
    { label: 'Terbaru', value: 'id:desc' },
    { label: 'Terlama', value: 'id:asc' },
    { label: 'Nama A-Z', value: 'nama:asc' },
    { label: 'Nama Z-A', value: 'nama:desc' },
    { label: 'Harga Terendah', value: 'harga:asc' },
    { label: 'Harga Tertinggi', value: 'harga:desc' },
    { label: 'Stok Terbanyak', value: 'stok:desc' },
    { label: 'Terjual Terbanyak', value: 'terjual:desc' },
];

const filteredItems = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const [sort_by, sort_dir] = sortValue.value.split(':');

    let result = allItems.value.filter((item) => {
        if (query && !(item.nama || '').toLowerCase().includes(query)) return false;
        if (filterJenis.value !== 'all' && String(item.jenis_id) !== String(filterJenis.value)) return false;

        const fav = isFavorite(item);
        if (filterFavorite.value === '1' && !fav) return false;
        if (filterFavorite.value === '0' && fav) return false;

        const promo = hasPromo(item);
        if (filterPromo.value === '1' && !promo) return false;
        if (filterPromo.value === '0' && promo) return false;

        return true;
    });

    const dir = sort_dir === 'asc' ? 1 : -1;
    result = [...result].sort((a, b) => {
        switch (sort_by) {
            case 'nama':
                return String(a.nama || '').localeCompare(String(b.nama || '')) * dir;
            case 'harga':
                return (Number(a.harga) - Number(b.harga)) * dir;
            case 'stok':
                return (Number(a.stok) - Number(b.stok)) * dir;
            case 'terjual':
                return (Number(a.terjual) - Number(b.terjual)) * dir;
            default:
                return (Number(a.id) - Number(b.id)) * dir;
        }
    });

    return result;
});

const totalItems = computed(() => filteredItems.value.length);

// perPage = 0 artinya tampilkan SEMUA data
const pageSize = computed(() => (perPage.value > 0 ? perPage.value : totalItems.value));

const totalPages = computed(() => Math.ceil(totalItems.value / pageSize.value) || 1);

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return filteredItems.value.slice(start, start + pageSize.value);
});

const pageFrom = computed(() => (totalItems.value === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1));
const pageTo = computed(() => Math.min(currentPage.value * pageSize.value, totalItems.value));

const displayedPages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - 2);
    let end = Math.min(totalPages.value, start + maxVisible - 1);
    if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1);
    }
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
});

const goToPage = (page) => {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
};

// Reset ke halaman 1 jika pencarian/filter/urutkan/ukuran halaman berubah
watch([searchQuery, filterJenis, filterFavorite, filterPromo, sortValue, perPage], () => {
    currentPage.value = 1;
});

// ------------------------------------------------------------------
// DETAIL MODAL
// ------------------------------------------------------------------
const detailOpen = ref(false);
const detailItem = ref(null);
const detailImageIndex = ref(0);

const openDetail = (item) => {
    detailItem.value = item;
    detailImageIndex.value = 0;
    detailOpen.value = true;
};

const detailImages = computed(() => (detailItem.value ? itemImages(detailItem.value) : []));

const closeDetail = () => {
    detailOpen.value = false;
    detailItem.value = null;
};

// ------------------------------------------------------------------
// FORM MODAL (TAMBAH / EDIT)
// ------------------------------------------------------------------
const formOpen = ref(false);
const isEditing = ref(false);
const selectedId = ref(null);

const form = useForm({
    nama: '',
    jenis_id: '',
    stok: '',
    harga: '',
    terjual: '',
    harga_modal: '',
    ukuran_opp: '',
    promo: '',
    favorite: false,
    deskripsi: '',
    gambar: [],
    hapus_gambar: [],
    hapus_gambar_lama: false,
});

// Edit: daftar gambar lama { url, removed } + file baru { file, url }
const existingImages = ref([]);
const newFiles = ref([]);

const openCreateModal = () => {
    isEditing.value = false;
    selectedId.value = null;
    form.reset();
    form.gambar = [];
    form.hapus_gambar = [];
    form.hapus_gambar_lama = false;
    existingImages.value = [];
    newFiles.value = [];
    form.clearErrors();
    formOpen.value = true;
};

const openEditModal = (item) => {
    isEditing.value = true;
    selectedId.value = item.id;
    form.nama = item.nama;
    form.jenis_id = item.jenis_id ?? '';
    form.stok = item.stok ?? '';
    form.harga = item.harga ?? '';
    form.terjual = item.terjual ?? '';
    form.harga_modal = item.harga_modal ?? '';
    form.ukuran_opp = item.ukuran_opp ?? '';
    form.promo = item.promo ?? '';
    form.favorite = isFavorite(item);
    form.deskripsi = item.deskripsi || '';
    form.gambar = [];
    form.hapus_gambar = [];
    form.hapus_gambar_lama = false;
    existingImages.value = itemImages(item).map((url) => ({ url, removed: false }));
    newFiles.value = [];
    form.clearErrors();
    formOpen.value = true;
};

const closeForm = () => {
    if (form.processing) return;
    formOpen.value = false;
    form.reset();
};

const onFilesSelected = (event) => {
    const files = Array.from(event.target.files || []);
    files.forEach((file) => {
        newFiles.value.push({ file, url: URL.createObjectURL(file) });
    });
    form.gambar = newFiles.value.map((n) => n.file);
    event.target.value = '';
};

const removeNewFile = (index) => {
    newFiles.value.splice(index, 1);
    form.gambar = newFiles.value.map((n) => n.file);
};

const markExistingRemoved = (index) => {
    existingImages.value[index].removed = true;
};

const restoreExisting = (index) => {
    existingImages.value[index].removed = false;
};

const undanganGambarInput = ref(null);

const handleSubmit = () => {
    const hapusGambar = existingImages.value
        .map((img, i) => (img.removed ? i : null))
        .filter((v) => v !== null);

    form.transform((data) => {
        const payload = {
            ...data,
            favorite: data.favorite ? 1 : 0,
            // field numerik opsional: kirim 0 jika kosong (hindari error integer)
            terjual: data.terjual === '' ? 0 : data.terjual,
            harga_modal: data.harga_modal === '' ? 0 : data.harga_modal,
            promo: data.promo === '' ? 0 : data.promo,
            hapus_gambar: hapusGambar,
        };
        if (isEditing.value) payload._method = 'PUT';
        return payload;
    });

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
            existingImages.value = [];
            newFiles.value = [];
        },
    };

    if (isEditing.value) {
        form.post(`/undangan/${selectedId.value}`, options);
    } else {
        form.post('/undangan', options);
    }
};

// ------------------------------------------------------------------
// DELETE MODAL
// ------------------------------------------------------------------
const deleteConfirmOpen = ref(false);
const deleteTarget = ref(null);
const deleteProcessing = ref(false);
const deleteError = ref('');

const openDeleteConfirm = (item) => {
    deleteTarget.value = item;
    deleteError.value = '';
    deleteConfirmOpen.value = true;
};

const closeDeleteConfirm = () => {
    if (deleteProcessing.value) return;
    deleteConfirmOpen.value = false;
    deleteTarget.value = null;
    deleteError.value = '';
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;

    deleteProcessing.value = true;
    router.delete(`/undangan/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
            deleteError.value = '';
        },
        onError: (errors) => {
            deleteError.value = errors.error || 'Gagal menghapus undangan.';
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};
</script>

<template>
    <Head title="Undangan" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Katalog Undangan Cetak</h1>
                <p class="text-sm text-muted-foreground">Data diambil langsung dari API Undangan (wayaenikah.com).</p>
            </div>
            <Button @click="openCreateModal" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl px-4 py-2 flex items-center gap-2">
                <i class="fas fa-plus text-xs"></i>
                Tambah Undangan
            </Button>
        </div>

        <!-- Banner error API -->
        <div v-if="apiError" class="rounded-2xl border border-red-300 bg-red-50 dark:bg-red-950/20 dark:border-red-900 p-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 text-sm text-red-700 dark:text-red-400">
                <i class="fas fa-triangle-exclamation"></i>
                <span>{{ apiError }}</span>
            </div>
            <Button variant="outline" size="sm" class="rounded-xl shrink-0" @click="window.location.reload()">
                <i class="fas fa-rotate-right text-xs mr-1.5"></i>
                Coba Lagi
            </Button>
        </div>

        <!-- Filter & Search -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="relative col-span-2 md:col-span-3 xl:col-span-2">
                <i class="fas fa-search absolute left-3.5 top-3 text-muted-foreground text-sm"></i>
                <Input v-model="searchQuery" placeholder="Cari nama undangan..." class="pl-10 rounded-xl bg-card border-border text-foreground" />
            </div>

            <div class="col-span-1">
                <select v-model="filterJenis" class="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="all">Semua Jenis</option>
                    <option v-for="jenis in jenisOptions" :key="jenis.id" :value="jenis.id">{{ jenis.jenis }}</option>
                </select>
            </div>

            <div class="col-span-1">
                <select v-model="filterFavorite" class="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="all">Favorit: Semua</option>
                    <option value="1">Favorit: Ya</option>
                    <option value="0">Favorit: Tidak</option>
                </select>
            </div>

            <div class="col-span-1">
                <select v-model="filterPromo" class="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="all">Promo: Semua</option>
                    <option value="1">Promo: Ada</option>
                    <option value="0">Promo: Tidak</option>
                </select>
            </div>

            <div class="col-span-1">
                <select v-model="sortValue" class="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                    <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>

            <div class="col-span-2 md:col-span-3 xl:col-span-1">
                <select v-model.number="perPage" class="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                    <option :value="50">50 / halaman</option>
                    <option :value="100">100 / halaman</option>
                    <option :value="0">Tampilkan Semua</option>
                </select>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <th class="p-4 pl-6">Undangan</th>
                            <th class="p-4">Stok</th>
                            <th class="p-4">Terjual</th>
                            <th class="p-4">Harga</th>
                            <th class="p-4">Modal</th>
                            <th class="p-4 text-center">Favorit</th>
                            <th class="p-4 text-right pr-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="allItems.length === 0">
                            <td colspan="7" class="p-8 text-center text-muted-foreground">
                                <i class="fas fa-envelope-open-text text-3xl mb-2 opacity-30"></i>
                                <p>Tidak ada data undangan ditemukan.</p>
                            </td>
                        </tr>
                        <tr v-else-if="paginatedItems.length === 0">
                            <td colspan="7" class="p-8 text-center text-muted-foreground">
                                <i class="fas fa-filter-circle-xmark text-3xl mb-2 opacity-30"></i>
                                <p>Tidak ada data yang cocok dengan pencarian/filter.</p>
                            </td>
                        </tr>
                        <tr v-for="item in paginatedItems" :key="item.id" class="hover:bg-muted/30 transition">
                            <td class="p-4 pl-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-muted border border-border flex items-center justify-center overflow-hidden shrink-0">
                                        <img v-if="itemImages(item)[0]" :src="itemImages(item)[0]" :alt="item.nama" class="w-full h-full object-cover" />
                                        <i v-else class="fas fa-envelope text-muted-foreground"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-foreground">{{ item.nama }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ item.jenis_undangan?.jenis || 'Tanpa jenis' }}
                                            <span v-if="item.ukuran_opp" class="ml-1">· {{ item.ukuran_opp }}</span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="font-medium" :class="Number(item.stok) === 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                                    {{ Number(item.stok) }}
                                </span>
                            </td>
                            <td class="p-4 text-muted-foreground">{{ Number(item.terjual) }}</td>
                            <td class="p-4">
                                <div class="flex flex-col">
                                    <span v-if="hasPromo(item)" class="text-xs text-muted-foreground line-through">Rp {{ formatRupiah(item.harga) }}</span>
                                    <span class="font-semibold" :class="hasPromo(item) ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'">
                                        Rp {{ formatRupiah(hasPromo(item) ? item.promo : item.harga) }}
                                    </span>
                                    <span v-if="hasPromo(item)" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                        <i class="fas fa-tags"></i> PROMO
                                    </span>
                                </div>
                            </td>
                            <td class="p-4 text-muted-foreground">Rp {{ formatRupiah(item.harga_modal) }}</td>
                            <td class="p-4 text-center">
                                <span v-if="isFavorite(item)" class="text-amber-500" title="Favorit"><i class="fas fa-star"></i></span>
                                <span v-else class="text-muted-foreground/40"><i class="far fa-star"></i></span>
                            </td>
                            <td class="p-4 text-right pr-6 space-x-1 whitespace-nowrap">
                                <Button @click="openDetail(item)" variant="ghost" size="icon-sm" title="Lihat detail" aria-label="Lihat detail" class="text-sky-600 hover:text-sky-900 dark:text-sky-400 dark:hover:text-sky-300">
                                    <i class="fas fa-eye"></i>
                                </Button>
                                <Button @click="openEditModal(item)" variant="ghost" size="icon-sm" title="Edit undangan" aria-label="Edit undangan" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    <i class="fas fa-edit"></i>
                                </Button>
                                <Button @click="openDeleteConfirm(item)" variant="ghost" size="icon-sm" title="Hapus undangan" aria-label="Hapus undangan" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                    <i class="fas fa-trash-alt"></i>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row items-center justify-between px-6 py-4 border-t border-border bg-muted/10 gap-4">
                <div class="text-xs text-muted-foreground">
                    Menampilkan
                    <span class="font-semibold text-foreground">{{ pageFrom }}</span>
                    sampai
                    <span class="font-semibold text-foreground">{{ pageTo }}</span>
                    dari
                    <span class="font-semibold text-foreground">{{ totalItems }}</span>
                    undangan
                </div>
                <div class="flex items-center gap-1.5" v-if="totalPages > 1">
                    <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)" class="h-8 w-8 p-0 rounded-lg">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </Button>
                    <Button
                        v-for="page in displayedPages"
                        :key="page"
                        :variant="currentPage === page ? 'default' : 'outline'"
                        size="sm"
                        @click="goToPage(page)"
                        class="h-8 w-8 p-0 text-xs rounded-lg"
                    >
                        {{ page }}
                    </Button>
                    <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)" class="h-8 w-8 p-0 rounded-lg">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </Button>
                </div>
            </div>
        </div>

        <!-- ================= DETAIL MODAL ================= -->
        <Dialog :open="detailOpen" @update:open="(open) => !open && closeDetail()">
            <DialogContent class="sm:max-w-140 rounded-2xl bg-card border-border text-foreground overflow-y-auto max-h-[90vh] custom-scroll">
                <DialogHeader v-if="detailItem">
                    <DialogTitle class="flex items-center gap-2 pr-8">
                        {{ detailItem.nama }}
                        <span v-if="isFavorite(detailItem)" class="text-amber-500"><i class="fas fa-star text-sm"></i></span>
                    </DialogTitle>
                    <DialogDescription>{{ detailItem.jenis_undangan?.jenis || 'Tanpa jenis' }}</DialogDescription>
                </DialogHeader>

                <div v-if="detailItem" class="space-y-4">
                    <!-- Galeri gambar -->
                    <div v-if="detailImages.length" class="space-y-3">
                        <div class="rounded-xl overflow-hidden border border-border bg-muted flex items-center justify-center aspect-4/3">
                            <img :src="detailImages[detailImageIndex]" :alt="detailItem.nama" class="w-full h-full object-contain" />
                        </div>
                        <div v-if="detailImages.length > 1" class="flex gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="(img, i) in detailImages"
                                :key="i"
                                @click="detailImageIndex = i"
                                class="w-16 h-16 rounded-lg overflow-hidden border-2 shrink-0 transition"
                                :class="i === detailImageIndex ? 'border-indigo-500' : 'border-border opacity-70 hover:opacity-100'"
                            >
                                <img :src="img" :alt="`Gambar ${i + 1}`" class="w-full h-full object-cover" />
                            </button>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-border p-8 text-center text-muted-foreground text-sm">
                        <i class="fas fa-image text-2xl mb-2 opacity-30"></i>
                        <p>Tidak ada gambar.</p>
                    </div>

                    <!-- Info -->
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-muted/40 border border-border p-3">
                            <p class="text-xs text-muted-foreground">Harga</p>
                            <p class="font-bold text-foreground">
                                <span v-if="hasPromo(detailItem)" class="text-xs text-muted-foreground line-through mr-1">Rp {{ formatRupiah(detailItem.harga) }}</span>
                                Rp {{ formatRupiah(hasPromo(detailItem) ? detailItem.promo : detailItem.harga) }}
                            </p>
                            <span v-if="hasPromo(detailItem)" class="inline-block mt-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 rounded-full px-2 py-0.5">
                                <i class="fas fa-tags mr-1"></i>PROMO
                            </span>
                        </div>
                        <div class="rounded-xl bg-muted/40 border border-border p-3">
                            <p class="text-xs text-muted-foreground">Harga Modal</p>
                            <p class="font-bold text-foreground">Rp {{ formatRupiah(detailItem.harga_modal) }}</p>
                        </div>
                        <div class="rounded-xl bg-muted/40 border border-border p-3">
                            <p class="text-xs text-muted-foreground">Stok</p>
                            <p class="font-bold" :class="Number(detailItem.stok) === 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">{{ Number(detailItem.stok) }}</p>
                        </div>
                        <div class="rounded-xl bg-muted/40 border border-border p-3">
                            <p class="text-xs text-muted-foreground">Terjual</p>
                            <p class="font-bold text-foreground">{{ Number(detailItem.terjual) }}</p>
                        </div>
                        <div v-if="detailItem.ukuran_opp" class="rounded-xl bg-muted/40 border border-border p-3">
                            <p class="text-xs text-muted-foreground">Ukuran OPP</p>
                            <p class="font-bold text-foreground">{{ detailItem.ukuran_opp }}</p>
                        </div>
                        <div class="rounded-xl bg-muted/40 border border-border p-3">
                            <p class="text-xs text-muted-foreground">Jenis</p>
                            <p class="font-bold text-foreground">{{ detailItem.jenis_undangan?.jenis || '-' }}</p>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div v-if="detailItem.deskripsi" class="rounded-xl border border-border p-4 text-sm text-muted-foreground prose-sm max-w-none">
                        <div v-html="detailItem.deskripsi"></div>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary" class="rounded-xl">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ================= FORM MODAL (TAMBAH / EDIT) ================= -->
        <Dialog :open="formOpen" @update:open="(open) => (open ? (formOpen = true) : closeForm())">
            <DialogContent class="sm:max-w-155 rounded-2xl bg-card border-border text-foreground overflow-y-auto max-h-[90vh] custom-scroll">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Undangan' : 'Tambah Undangan Baru' }}</DialogTitle>
                    <DialogDescription>
                        Data akan dikirim dan disimpan melalui API Undangan Cetak.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSubmit" class="space-y-4 py-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="undangan-nama">Nama Undangan <span class="text-red-500">*</span></Label>
                            <Input id="undangan-nama" v-model="form.nama" class="rounded-xl" placeholder="cth: Maliq 112" required />
                            <p v-if="form.errors.nama" class="text-xs text-red-500">{{ form.errors.nama }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="undangan-jenis">Jenis <span class="text-red-500">*</span></Label>
                            <select id="undangan-jenis" v-model="form.jenis_id" class="w-full rounded-xl border border-input bg-card px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring" required>
                                <option value="" disabled>Pilih jenis</option>
                                <option v-for="jenis in jenisOptions" :key="jenis.id" :value="jenis.id">{{ jenis.jenis }}</option>
                            </select>
                            <p v-if="form.errors.jenis_id" class="text-xs text-red-500">{{ form.errors.jenis_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="undangan-stok">Stok <span class="text-red-500">*</span></Label>
                            <Input id="undangan-stok" v-model="form.stok" type="number" min="0" class="rounded-xl" placeholder="0" />
                            <p v-if="form.errors.stok" class="text-xs text-red-500">{{ form.errors.stok }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="undangan-harga">Harga (Rp) <span class="text-red-500">*</span></Label>
                            <Input id="undangan-harga" v-model="form.harga" type="number" min="0" class="rounded-xl" placeholder="0" />
                            <p v-if="form.errors.harga" class="text-xs text-red-500">{{ form.errors.harga }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="undangan-terjual">Terjual</Label>
                            <Input id="undangan-terjual" v-model="form.terjual" type="number" min="0" class="rounded-xl" placeholder="0" />
                            <p v-if="form.errors.terjual" class="text-xs text-red-500">{{ form.errors.terjual }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="undangan-modal">Harga Modal (Rp)</Label>
                            <Input id="undangan-modal" v-model="form.harga_modal" type="number" min="0" class="rounded-xl" placeholder="0" />
                            <p v-if="form.errors.harga_modal" class="text-xs text-red-500">{{ form.errors.harga_modal }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="undangan-ukuran">Ukuran OPP</Label>
                            <Input id="undangan-ukuran" v-model="form.ukuran_opp" class="rounded-xl" placeholder="cth: 14,5 x 22" />
                            <p v-if="form.errors.ukuran_opp" class="text-xs text-red-500">{{ form.errors.ukuran_opp }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="undangan-promo">Harga Promo (Rp)</Label>
                            <Input id="undangan-promo" v-model="form.promo" type="number" min="0" class="rounded-xl" placeholder="0 = tanpa promo" />
                            <p v-if="form.errors.promo" class="text-xs text-red-500">{{ form.errors.promo }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Deskripsi</Label>
                        <RichTextEditor
                            v-model="form.deskripsi"
                            placeholder="Tulis deskripsi produk... Bisa tebal, miring, daftar, kutipan, tautan, dll."
                            min-height="180px"
                        />
                        <p v-if="form.errors.deskripsi" class="text-xs text-red-500">{{ form.errors.deskripsi }}</p>
                    </div>

                    <div class="flex items-center gap-2 rounded-xl border border-border bg-muted/20 px-4 py-3">
                        <Checkbox id="undangan-favorite" :checked="form.favorite" @update:checked="form.favorite = $event" />
                        <Label for="undangan-favorite" class="cursor-pointer font-medium text-sm">Tandai sebagai Favorit</Label>
                    </div>

                    <!-- Gambar -->
                    <div class="space-y-3 rounded-xl border border-border p-4">
                        <div class="flex items-center justify-between">
                            <Label class="font-semibold">Gambar Produk</Label>
                            <span class="text-xs text-muted-foreground">JPG/PNG/GIF/WEBP, maks 2MB per file</span>
                        </div>

                        <!-- Gambar lama (mode edit) -->
                        <div v-if="isEditing && existingImages.length" class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Gambar Saat Ini</p>
                            <div class="flex flex-wrap gap-3">
                                <div v-for="(img, i) in existingImages" :key="i" class="relative group">
                                    <div class="w-20 h-20 rounded-lg overflow-hidden border border-border" :class="img.removed ? 'opacity-30 grayscale' : ''">
                                        <img :src="img.url" :alt="`Gambar ${i + 1}`" class="w-full h-full object-cover" />
                                    </div>
                                    <button
                                        v-if="!img.removed"
                                        type="button"
                                        @click="markExistingRemoved(i)"
                                        class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-600 text-white text-xs flex items-center justify-center shadow hover:bg-red-700 transition"
                                        title="Hapus gambar ini"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        @click="restoreExisting(i)"
                                        class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/50 text-white text-[10px] font-bold hover:bg-black/60 transition"
                                    >
                                        <span class="flex items-center gap-1"><i class="fas fa-rotate-left text-[10px]"></i> Batal</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Opsi ganti semua (mode edit) -->
                        <label v-if="isEditing" class="flex items-center gap-2 cursor-pointer text-sm">
                            <Checkbox :checked="form.hapus_gambar_lama" @update:checked="form.hapus_gambar_lama = $event" />
                            <span>Ganti <strong>semua</strong> gambar lama dengan gambar baru di bawah</span>
                        </label>

                        <!-- Upload file baru -->
                        <div>
                            <input ref="undanganGambarInput" type="file" multiple accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" id="undangan-gambar" @change="onFilesSelected" />
                            <Button type="button" variant="outline" class="rounded-xl" @click="undanganGambarInput?.click()">
                                <i class="fas fa-cloud-arrow-up text-xs"></i>
                                Pilih Gambar
                            </Button>
                        </div>

                        <!-- Preview file baru -->
                        <div v-if="newFiles.length" class="flex flex-wrap gap-3">
                            <div v-for="(nf, i) in newFiles" :key="i" class="relative">
                                <div class="w-20 h-20 rounded-lg overflow-hidden border border-border">
                                    <img :src="nf.url" alt="Preview" class="w-full h-full object-cover" />
                                </div>
                                <button
                                    type="button"
                                    @click="removeNewFile(i)"
                                    class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-600 text-white text-xs flex items-center justify-center shadow hover:bg-red-700 transition"
                                    title="Batalkan file ini"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <p v-if="form.errors.gambar" class="text-xs text-red-500">{{ form.errors.gambar }}</p>
                        <p v-if="form.errors['gambar.0']" class="text-xs text-red-500">{{ form.errors['gambar.0'] }}</p>
                    </div>

                    <DialogFooter class="pt-3 gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary" class="rounded-xl" :disabled="form.processing">Batal</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="form.processing" class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700">
                            <i v-if="form.processing" class="fas fa-circle-notch fa-spin text-xs"></i>
                            <span v-else class="flex items-center gap-1.5"><i class="fas fa-save text-xs"></i> Simpan</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ================= DELETE CONFIRM ================= -->
        <Dialog :open="deleteConfirmOpen" @update:open="(open) => (open ? (deleteConfirmOpen = true) : closeDeleteConfirm())">
            <DialogContent class="sm:max-w-105 rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-950/30 text-red-600 dark:text-red-400 flex items-center justify-center">
                            <i class="fas fa-trash-alt text-sm"></i>
                        </span>
                        Hapus Undangan
                    </DialogTitle>
                    <DialogDescription>
                        Yakin ingin menghapus <strong class="text-foreground">{{ deleteTarget?.nama }}</strong>? Semua gambar terkait juga akan ikut terhapus.
                    </DialogDescription>
                </DialogHeader>

                <p v-if="deleteError" class="text-xs text-red-500 bg-red-50 dark:bg-red-950/20 rounded-lg px-3 py-2">{{ deleteError }}</p>

                <DialogFooter class="pt-3 gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="rounded-xl" :disabled="deleteProcessing">Batal</Button>
                    </DialogClose>
                    <Button type="button" variant="destructive" class="rounded-xl" :disabled="deleteProcessing" @click="confirmDelete">
                        <i v-if="deleteProcessing" class="fas fa-circle-notch fa-spin text-xs"></i>
                        <span v-else class="flex items-center gap-1.5"><i class="fas fa-trash-alt text-xs"></i> Hapus</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
