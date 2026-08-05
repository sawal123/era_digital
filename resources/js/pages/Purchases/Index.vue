<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import flatpickr from 'flatpickr';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
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
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Restock & Stok Masuk',
                href: '/purchases',
            },
        ],
    },
});

const props = defineProps({
    products: Array,
    purchases: Array,
});

const padDatePart = (value) => String(value).padStart(2, '0');
const getTodayString = () => {
    const now = new Date();

    return `${now.getFullYear()}-${padDatePart(now.getMonth() + 1)}-${padDatePart(now.getDate())}`;
};
const getCurrentMonthString = () => {
    const now = new Date();

    return `${now.getFullYear()}-${padDatePart(now.getMonth() + 1)}`;
};
const getCurrentYearString = () => String(new Date().getFullYear());
const todayString = getTodayString();

// Filter state (template from Reports)
const filterMode = ref('harian'); // 'harian' | 'bulanan' | 'tahunan'
const filterDateRange = ref(`${todayString} to ${todayString}`);
const filterMonth = ref(getCurrentMonthString());
const filterYear = ref(getCurrentYearString());
const dateRangeInput = ref(null);
let dateRangePickerInstance = null;

const setMode = (mode) => {
    filterMode.value = mode;
};

const parseDateRange = (value) => {
    const [start = '', end = ''] = String(value ?? '').split(' to ');
    const normalizedStart = start || todayString;
    const normalizedEnd = end || normalizedStart;

    return {
        start: normalizedStart,
        end: normalizedEnd,
    };
};

const selectedDateRange = computed(() => parseDateRange(filterDateRange.value));
const setDateRange = (start, end = start) => {
    filterDateRange.value = end && end !== start ? `${start} to ${end}` : start;

    if (dateRangePickerInstance) {
        dateRangePickerInstance.setDate([start, end || start], false);
    }
};

const initDateRangePicker = () => {
    if (!dateRangeInput.value) return;

    dateRangePickerInstance?.destroy();
    dateRangePickerInstance = flatpickr(dateRangeInput.value, {
        mode: 'range',
        dateFormat: 'Y-m-d',
        defaultDate: [selectedDateRange.value.start, selectedDateRange.value.end],
        onChange: (selectedDates, _dateStr, instance) => {
            if (selectedDates.length === 0) {
                setDateRange(todayString);

                return;
            }

            const [startDate, endDate = startDate] = selectedDates.map((date) => instance.formatDate(date, 'Y-m-d'));
            filterDateRange.value = startDate === endDate ? startDate : `${startDate} to ${endDate}`;
        },
    });
};

watch(filterMode, async (mode) => {
    if (mode !== 'harian') {
        dateRangePickerInstance?.destroy();
        dateRangePickerInstance = null;

        return;
    }

    await nextTick();
    initDateRangePicker();
});

onMounted(() => {
    if (filterMode.value === 'harian') {
        initDateRangePicker();
    }
});

onBeforeUnmount(() => {
    dateRangePickerInstance?.destroy();
});

// Filtered purchases
const filteredPurchases = computed(() => {
    const { start, end } = selectedDateRange.value;

    return props.purchases.filter((p) => {
        const purchaseDate = String(p.purchase_date ?? '').slice(0, 10);

        return filterMode.value === 'harian'
            ? purchaseDate >= start && purchaseDate <= end
            : filterMode.value === 'bulanan'
                ? String(p.purchase_date ?? '').slice(0, 7) === filterMonth.value
                : String(p.purchase_date ?? '').slice(0, 4) === filterYear.value;
    });
});

// Form state
const formOpen = ref(false);

const form = useForm({
    product_id: '',
    quantity: '',
    cost_price: '',
    purchase_date: new Date().toISOString().split('T')[0],
    note: '',
});

const isEditing = ref(false);
const editingId = ref(null);

// Watch product selection to auto-prefill the current cost price (base_price)
watch(
    () => form.product_id,
    (newProdId) => {
        if (newProdId) {
            const product = props.products.find((p) => p.id == newProdId);
            if (product) {
                form.cost_price = product.base_price;
            }
        } else {
            form.cost_price = '';
        }
    },
);

const openCreateModal = () => {
    form.reset();
    form.purchase_date = new Date().toISOString().split('T')[0];
    isEditing.value = false;
    editingId.value = null;
    formOpen.value = true;
};

const submitForm = () => {
    if (isEditing.value && editingId.value) {
        form.patch(`/purchases/${editingId.value}`, {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
                isEditing.value = false;
                editingId.value = null;
            },
        });
    } else {
        form.post('/purchases', {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    }
};

const editPurchase = (pc) => {
    isEditing.value = true;
    editingId.value = pc.id;
    form.product_id = pc.product_id;
    form.quantity = pc.quantity;
    form.cost_price = pc.cost_price;
    form.purchase_date = pc.purchase_date
        ? pc.purchase_date.split('T')[0]
        : pc.purchase_date;
    form.note = pc.note || '';
    formOpen.value = true;
};

const deletePurchase = (pc) => {
    if (
        !confirm(
            'Hapus pencatatan restock ini? Aksi ini akan mengurangi stok produk dan menghapus catatan pembelian.',
        )
    )
        return;

    form.delete(`/purchases/${pc.id}`, {
        onSuccess: () => {
            // no-op, page will refresh via inertia
        },
    });
};

// Summary metrics
const totalPurchaseAmount = computed(() => {
    return filteredPurchases.value.reduce(
        (sum, p) => sum + parseFloat(p.total_price || 0),
        0,
    );
});

const totalItemsRestocked = computed(() => {
    return filteredPurchases.value.reduce((sum, p) => sum + parseFloat(p.quantity || 0), 0);
});

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const parseFloatAsString = (val) => {
    return parseFloat(val);
};
</script>

<template>
    <Head>
        <title>Restock Barang & Stok Masuk | Smart POS System</title>
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
        />
    </Head>

    <div class="font-inter flex flex-col gap-6 p-4 pb-8 md:p-6">
        <!-- Header -->
        <div
            class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Restock & Stok Masuk
                </h1>
                <p class="text-sm text-muted-foreground">
                    Catat kedatangan stok ATK baru dari supplier. Sistem akan
                    mengupdate jumlah stok, mengubah harga modal, dan otomatis
                    mencatat pengeluaran belanja.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="shrink-0 rounded-xl bg-indigo-600 font-semibold text-white shadow-md hover:bg-indigo-700"
            >
                <i class="fas fa-plus mr-2 text-xs"></i> Restock Barang
            </Button>
        </div>
            <!-- Filter Panel (Hari / Bulan / Tahun) -->
            <div class="bg-card border border-border rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center gap-4 shadow-xs">
                <div class="flex gap-1.5 bg-muted/50 p-1 rounded-xl border border-border/50">
                    <button
                        v-for="m in [
                            { key: 'harian', label: 'Harian', icon: 'fas fa-calendar-day' },
                            { key: 'bulanan', label: 'Bulanan', icon: 'fas fa-calendar-alt' },
                            { key: 'tahunan', label: 'Tahunan', icon: 'fas fa-calendar' },
                        ]"
                        :key="m.key"
                        @click="setMode(m.key)"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-200"
                        :class="filterMode === m.key
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-muted-foreground hover:text-foreground hover:bg-muted'"
                    >
                        <i :class="m.icon" class="text-[10px]"></i>
                        {{ m.label }}
                    </button>
                </div>

                <div class="flex-1 flex items-center gap-3">
                    <div v-if="filterMode === 'harian'" class="relative flex-1 max-w-sm">
                        <i class="fas fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <input
                            ref="dateRangeInput"
                            type="text"
                            :value="filterDateRange"
                            placeholder="Pilih rentang tanggal"
                            class="h-9 w-full rounded-xl border border-input bg-background px-3 pl-8 text-sm text-foreground transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <div v-else-if="filterMode === 'bulanan'" class="relative flex-1 max-w-xs">
                        <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <input
                            type="month"
                            v-model="filterMonth"
                            class="pl-8 h-9 w-full rounded-xl border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 transition px-3"
                        />
                    </div>

                    <div v-else class="relative flex-1 max-w-xs">
                        <i class="fas fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <select
                            v-model="filterYear"
                            class="pl-8 h-9 w-full rounded-xl border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 transition px-3 appearance-none"
                        >
                            <option v-for="y in [getCurrentYearString(), String(Number(getCurrentYearString())-1), String(Number(getCurrentYearString())-2)]" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <button
                        @click="filterMode === 'harian' ? setDateRange(todayString) : filterMode === 'bulanan' ? filterMonth = getCurrentMonthString() : filterYear = getCurrentYearString()"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold whitespace-nowrap transition underline underline-offset-2"
                    >
                        {{ filterMode === 'harian' ? 'Hari ini' : filterMode === 'bulanan' ? 'Bulan ini' : 'Tahun ini' }}
                    </button>
                </div>
            </div>

            <!-- Metrics Grid -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Total Anggaran Restock -->
            <Card
                class="relative overflow-hidden border-border/60 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 shadow-sm"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <span>Total Biaya Restock (All-Time)</span>
                        <i
                            class="fas fa-file-invoice-dollar text-sm text-indigo-500 dark:text-indigo-400"
                        ></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-foreground">
                        Rp {{ formatRupiah(totalPurchaseAmount) }}
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Total pengeluaran belanja stok produk fisik
                    </p>
                </CardContent>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-500"
                ></div>
            </Card>

            <!-- Total Kuantitas Restock -->
            <Card
                class="relative overflow-hidden border-border/60 bg-gradient-to-br from-emerald-500/5 to-teal-500/5 shadow-sm"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <span>Total Kuantitas Masuk</span>
                        <i
                            class="fas fa-boxes text-sm text-emerald-500 dark:text-emerald-400"
                        ></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ parseFloatAsString(totalItemsRestocked) }} Unit
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Akumulasi jumlah seluruh unit ATK masuk
                    </p>
                </CardContent>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"
                ></div>
            </Card>

            <!-- Jumlah Transaksi Restock -->
            <Card
                class="relative overflow-hidden border-border/60 bg-gradient-to-br from-purple-500/5 to-pink-500/5 shadow-sm"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <span>Frekuensi Restock</span>
                        <i
                            class="fas fa-history text-sm text-purple-500 dark:text-purple-400"
                        ></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-foreground">
                        {{ filteredPurchases.length }} Kali
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Jumlah kali pencatatan belanja stok dari supplier
                    </p>
                </CardContent>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-gradient-to-r from-purple-500 to-pink-500"
                ></div>
            </Card>
        </div>

        <!-- History Table -->
        <Card
            class="overflow-hidden border-border/50 bg-card text-card-foreground shadow-sm"
        >
            <CardHeader class="border-b border-border/40 bg-muted/10 py-4">
                <CardTitle class="text-sm font-bold text-foreground"
                    >Riwayat Penerimaan & Belanja Stok</CardTitle
                >
                <CardDescription class="text-xs text-muted-foreground"
                    >Catatan kronologis penerimaan barang fisik beserta nominal
                    pengeluaran kulakan.</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr
                                class="border-b border-border bg-muted/30 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                <th class="p-4 pl-6">Tanggal Masuk</th>
                                <th class="p-4">Nama Produk / SKU</th>
                                <th class="p-4">Kategori</th>
                                <th class="p-4 text-center">Jumlah Restock</th>
                                <th class="p-4 text-right">Harga Modal Beli</th>
                                <th class="p-4 text-right">Total Belanja</th>
                                <th class="p-4 pr-6">Catatan</th>
                                <th class="p-4 pr-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="filteredPurchases.length === 0">
                                <td
                                    colspan="8"
                                    class="p-12 text-center text-muted-foreground"
                                >
                                    <i
                                        class="fas fa-truck-loading mb-3 text-4xl opacity-20"
                                    ></i>
                                    <p class="font-medium">
                                        Belum ada riwayat restock barang.
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Klik tombol "Restock Barang" di atas
                                        untuk mencatat stok masuk baru.
                                    </p>
                                </td>
                            </tr>
                            <tr
                                v-for="pc in filteredPurchases"
                                :key="pc.id"
                                class="transition hover:bg-muted/30"
                            >
                                <!-- Date -->
                                <td
                                    class="p-4 pl-6 font-mono text-xs text-muted-foreground"
                                >
                                    {{ formatDate(pc.purchase_date) }}
                                </td>
                                <!-- Product Name -->
                                <td class="p-4">
                                    <div class="font-bold text-foreground">
                                        {{
                                            pc.product
                                                ? pc.product.name
                                                : 'Produk Dihapus'
                                        }}
                                    </div>
                                    <div
                                        class="mt-0.5 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ pc.product ? pc.product.sku : '-' }}
                                    </div>
                                </td>
                                <!-- Category -->
                                <td class="p-4">
                                    <Badge
                                        variant="outline"
                                        class="rounded-full border-indigo-200/50 bg-indigo-50/5 text-[10px] font-semibold text-indigo-600 capitalize dark:text-indigo-400"
                                    >
                                        {{
                                            pc.product && pc.product.category
                                                ? pc.product.category.name
                                                : 'Fisik'
                                        }}
                                    </Badge>
                                </td>
                                <!-- Quantity -->
                                <td
                                    class="p-4 text-center font-bold text-foreground"
                                >
                                    {{ parseFloatAsString(pc.quantity) }}
                                    {{ pc.product ? pc.product.unit : 'pcs' }}
                                </td>
                                <!-- Cost Price -->
                                <td
                                    class="p-4 text-right font-mono text-muted-foreground"
                                >
                                    Rp {{ formatRupiah(pc.cost_price) }}
                                </td>
                                <!-- Total Price -->
                                <td
                                    class="dark:text-red-450 p-4 text-right font-mono font-bold text-red-600"
                                >
                                    Rp {{ formatRupiah(pc.total_price) }}
                                </td>
                                <!-- Note -->
                                <td
                                    class="max-w-[180px] truncate p-4 pr-6 text-xs text-muted-foreground italic"
                                    :title="pc.note"
                                >
                                    {{ pc.note || '-' }}
                                </td>
                                <!-- Actions -->
                                <td class="p-4 pr-6 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="text-blue-600"
                                            @click.prevent="editPurchase(pc)"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="text-red-600"
                                            @click.prevent="deletePurchase(pc)"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- DIALOG FORM RESTOCK -->
        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[480px]"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-truck-loading text-indigo-500"></i>
                        <span v-if="!isEditing"
                            >Catat Restock Barang Masuk</span
                        >
                        <span v-else>Edit Catatan Restock</span>
                    </DialogTitle>
                    <DialogDescription>
                        Catat pembelian barang dari supplier. Jumlah stok akan
                        otomatis bertambah dan pengeluaran belanja akan otomatis
                        dijurnal ke sistem.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="my-2 space-y-4">
                    <!-- Product Dropdown -->
                    <div class="space-y-1.5">
                        <Label for="restock-prod">Pilih Produk Fisik</Label>
                        <select
                            id="restock-prod"
                            v-model="form.product_id"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option value="" disabled>
                                -- Pilih Barang ATK --
                            </option>
                            <option
                                v-for="prod in products"
                                :key="prod.id"
                                :value="prod.id"
                            >
                                {{ prod.name }} (Stok saat ini:
                                {{ parseFloatAsString(prod.stock) }} | Modal: Rp
                                {{ formatRupiah(prod.base_price) }})
                            </option>
                        </select>
                    </div>

                    <!-- Quantity & Cost Price Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Quantity -->
                        <div class="space-y-1.5">
                            <Label for="restock-qty">Jumlah Masuk</Label>
                            <Input
                                id="restock-qty"
                                type="number"
                                v-model="form.quantity"
                                placeholder="0"
                                min="0.01"
                                step="any"
                                required
                                class="border-border bg-background text-foreground"
                            />
                        </div>
                        <!-- Cost Price per unit -->
                        <div class="space-y-1.5">
                            <Label for="restock-cost"
                                >Harga Beli Per Unit (Rp)</Label
                            >
                            <Input
                                id="restock-cost"
                                type="number"
                                v-model="form.cost_price"
                                placeholder="0"
                                min="0"
                                required
                                class="border-border bg-background text-foreground"
                            />
                        </div>
                    </div>

                    <!-- Date -->
                    <div class="space-y-1.5">
                        <Label for="restock-date"
                            >Tanggal Kedatangan Stok</Label
                        >
                        <Input
                            id="restock-date"
                            type="date"
                            v-model="form.purchase_date"
                            required
                            class="border-border bg-background text-foreground"
                        />
                    </div>

                    <!-- Note -->
                    <div class="space-y-1.5">
                        <Label for="restock-note"
                            >Catatan Supplier / Nota (Opsional)</Label
                        >
                        <textarea
                            id="restock-note"
                            v-model="form.note"
                            rows="3"
                            placeholder="Misal: Supplier Indogrosir, Nota INV-01923"
                            class="flex min-h-[60px] w-full rounded-md border border-border border-input bg-transparent px-3 py-2 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                        ></textarea>
                    </div>

                    <DialogFooter class="gap-2 border-t border-border pt-4">
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
                            class="rounded-xl bg-indigo-600 font-bold text-white shadow-sm hover:bg-indigo-700"
                        >
                            <i class="fas fa-check mr-1.5"></i>
                            <span v-if="!isEditing">Simpan Belanja</span>
                            <span v-else>Simpan Perubahan</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
