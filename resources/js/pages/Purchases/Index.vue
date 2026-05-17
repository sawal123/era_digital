<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
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

// Form state
const formOpen = ref(false);

const form = useForm({
    product_id: '',
    quantity: '',
    cost_price: '',
    purchase_date: new Date().toISOString().split('T')[0],
    note: '',
});

// Watch product selection to auto-prefill the current cost price (base_price)
watch(() => form.product_id, (newProdId) => {
    if (newProdId) {
        const product = props.products.find(p => p.id == newProdId);
        if (product) {
            form.cost_price = product.base_price;
        }
    } else {
        form.cost_price = '';
    }
});

const openCreateModal = () => {
    form.reset();
    form.purchase_date = new Date().toISOString().split('T')[0];
    formOpen.value = true;
};

const submitForm = () => {
    form.post('/purchases', {
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    });
};

// Summary metrics
const totalPurchaseAmount = computed(() => {
    return props.purchases.reduce((sum, p) => sum + parseFloat(p.total_price), 0);
});

const totalItemsRestocked = computed(() => {
    return props.purchases.reduce((sum, p) => sum + parseFloat(p.quantity), 0);
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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </Head>

    <div class="flex flex-col gap-6 p-4 md:p-6 pb-8 font-inter">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Restock & Stok Masuk</h1>
                <p class="text-sm text-muted-foreground">Catat kedatangan stok ATK baru dari supplier. Sistem akan mengupdate jumlah stok, mengubah harga modal, dan otomatis mencatat pengeluaran belanja.</p>
            </div>
            <Button @click="openCreateModal" class="rounded-xl font-semibold shadow-md bg-indigo-600 hover:bg-indigo-700 text-white shrink-0">
                <i class="fas fa-plus mr-2 text-xs"></i> Restock Barang
            </Button>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Total Anggaran Restock -->
            <Card class="border-border/60 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Biaya Restock (All-Time)</span>
                        <i class="fas fa-file-invoice-dollar text-indigo-500 dark:text-indigo-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-foreground">Rp {{ formatRupiah(totalPurchaseAmount) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Total pengeluaran belanja stok produk fisik</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
            </Card>

            <!-- Total Kuantitas Restock -->
            <Card class="border-border/60 bg-gradient-to-br from-emerald-500/5 to-teal-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Kuantitas Masuk</span>
                        <i class="fas fa-boxes text-emerald-500 dark:text-emerald-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ parseFloatAsString(totalItemsRestocked) }} Unit</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Akumulasi jumlah seluruh unit ATK masuk</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
            </Card>

            <!-- Jumlah Transaksi Restock -->
            <Card class="border-border/60 bg-gradient-to-br from-purple-500/5 to-pink-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Frekuensi Restock</span>
                        <i class="fas fa-history text-purple-500 dark:text-purple-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-foreground">{{ purchases.length }} Kali</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Jumlah kali pencatatan belanja stok dari supplier</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-purple-500 to-pink-500"></div>
            </Card>
        </div>

        <!-- History Table -->
        <Card class="border-border/50 shadow-sm overflow-hidden bg-card text-card-foreground">
            <CardHeader class="border-b border-border/40 py-4 bg-muted/10">
                <CardTitle class="text-sm font-bold text-foreground">Riwayat Penerimaan & Belanja Stok</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">Catatan kronologis penerimaan barang fisik beserta nominal pengeluaran kulakan.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="p-4 pl-6">Tanggal Masuk</th>
                                <th class="p-4">Nama Produk / SKU</th>
                                <th class="p-4">Kategori</th>
                                <th class="p-4 text-center">Jumlah Restock</th>
                                <th class="p-4 text-right">Harga Modal Beli</th>
                                <th class="p-4 text-right">Total Belanja</th>
                                <th class="p-4 pr-6">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="purchases.length === 0">
                                <td colspan="7" class="p-12 text-center text-muted-foreground">
                                    <i class="fas fa-truck-loading text-4xl mb-3 opacity-20"></i>
                                    <p class="font-medium">Belum ada riwayat restock barang.</p>
                                    <p class="text-xs text-muted-foreground mt-1">Klik tombol "Restock Barang" di atas untuk mencatat stok masuk baru.</p>
                                </td>
                            </tr>
                            <tr v-for="pc in purchases" :key="pc.id" class="hover:bg-muted/30 transition">
                                <!-- Date -->
                                <td class="p-4 pl-6 text-muted-foreground font-mono text-xs">
                                    {{ formatDate(pc.purchase_date) }}
                                </td>
                                <!-- Product Name -->
                                <td class="p-4">
                                    <div class="font-bold text-foreground">{{ pc.product ? pc.product.name : 'Produk Dihapus' }}</div>
                                    <div class="text-xs text-muted-foreground font-mono mt-0.5">{{ pc.product ? pc.product.sku : '-' }}</div>
                                </td>
                                <!-- Category -->
                                <td class="p-4">
                                    <Badge variant="outline" class="bg-indigo-50/5 text-indigo-600 dark:text-indigo-400 border-indigo-200/50 capitalize rounded-full text-[10px] font-semibold">
                                        {{ pc.product && pc.product.category ? pc.product.category.name : 'Fisik' }}
                                    </Badge>
                                </td>
                                <!-- Quantity -->
                                <td class="p-4 text-center font-bold text-foreground">
                                    {{ parseFloatAsString(pc.quantity) }} {{ pc.product ? pc.product.unit : 'pcs' }}
                                </td>
                                <!-- Cost Price -->
                                <td class="p-4 text-right text-muted-foreground font-mono">
                                    Rp {{ formatRupiah(pc.cost_price) }}
                                </td>
                                <!-- Total Price -->
                                <td class="p-4 text-right font-bold text-red-600 dark:text-red-450 font-mono">
                                    Rp {{ formatRupiah(pc.total_price) }}
                                </td>
                                <!-- Note -->
                                <td class="p-4 text-xs italic text-muted-foreground max-w-[180px] truncate pr-6" :title="pc.note">
                                    {{ pc.note || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- DIALOG FORM RESTOCK -->
        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent class="sm:max-w-[480px] rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-truck-loading text-indigo-500"></i>
                        Catat Restock Barang Masuk
                    </DialogTitle>
                    <DialogDescription>
                        Catat pembelian barang dari supplier. Jumlah stok akan otomatis bertambah dan pengeluaran belanja akan otomatis dijurnal ke sistem.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4 my-2">
                    <!-- Product Dropdown -->
                    <div class="space-y-1.5">
                        <Label for="restock-prod">Pilih Produk Fisik</Label>
                        <select id="restock-prod" v-model="form.product_id" required class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 text-foreground">
                            <option value="" disabled>-- Pilih Barang ATK --</option>
                            <option v-for="prod in products" :key="prod.id" :value="prod.id">
                                {{ prod.name }} (Stok saat ini: {{ parseFloatAsString(prod.stock) }} | Modal: Rp {{ formatRupiah(prod.base_price) }})
                            </option>
                        </select>
                    </div>

                    <!-- Quantity & Cost Price Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Quantity -->
                        <div class="space-y-1.5">
                            <Label for="restock-qty">Jumlah Masuk</Label>
                            <Input id="restock-qty" type="number" v-model="form.quantity" placeholder="0" min="0.01" step="any" required class="bg-background border-border text-foreground" />
                        </div>
                        <!-- Cost Price per unit -->
                        <div class="space-y-1.5">
                            <Label for="restock-cost">Harga Beli Per Unit (Rp)</Label>
                            <Input id="restock-cost" type="number" v-model="form.cost_price" placeholder="0" min="0" required class="bg-background border-border text-foreground" />
                        </div>
                    </div>

                    <!-- Date -->
                    <div class="space-y-1.5">
                        <Label for="restock-date">Tanggal Kedatangan Stok</Label>
                        <Input id="restock-date" type="date" v-model="form.purchase_date" required class="bg-background border-border text-foreground" />
                    </div>

                    <!-- Note -->
                    <div class="space-y-1.5">
                        <Label for="restock-note">Catatan Supplier / Nota (Opsional)</Label>
                        <textarea id="restock-note" v-model="form.note" rows="3" placeholder="Misal: Supplier Indogrosir, Nota INV-01923" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-foreground dark:bg-input/30 border-border"></textarea>
                    </div>

                    <DialogFooter class="pt-4 border-t border-border gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary" class="rounded-xl">Batal</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="form.processing" class="rounded-xl font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm">
                            <i class="fas fa-check mr-1.5"></i> Simpan Belanja
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
