<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Laporan Penjualan', href: '/reports' },
        ],
    },
});

const props = defineProps({ transactions: Array });

// ─── FILTER STATE ───────────────────────────────────────────────
const filterMode  = ref('harian');   // 'harian' | 'bulanan' | 'tahunan'
const filterDate  = ref(new Date().toISOString().slice(0, 10));    // YYYY-MM-DD
const filterMonth = ref(new Date().toISOString().slice(0, 7));     // YYYY-MM
const filterYear  = ref(String(new Date().getFullYear()));          // YYYY

const setMode = (mode) => { filterMode.value = mode; };

// ─── FILTERED LIST ───────────────────────────────────────────────
const filteredTransactions = computed(() => {
    return props.transactions.filter(t => {
        const d = new Date(t.created_at);
        if (filterMode.value === 'harian') {
            return t.created_at.slice(0, 10) === filterDate.value;
        } else if (filterMode.value === 'bulanan') {
            return t.created_at.slice(0, 7) === filterMonth.value;
        } else {
            return t.created_at.slice(0, 4) === filterYear.value;
        }
    });
});

// ─── SUMMARY METRICS ─────────────────────────────────────────────
const totalSales  = computed(() => filteredTransactions.value.reduce((s, t) => s + parseFloat(t.total_price),      0));
const totalBase   = computed(() => filteredTransactions.value.reduce((s, t) => s + parseFloat(t.total_base_price),  0));
const totalProfit = computed(() => filteredTransactions.value.reduce((s, t) => s + parseFloat(t.total_profit),      0));

// ─── DETAIL DIALOG ────────────────────────────────────────────────
const selectedTransaction = ref(null);
const detailOpen = ref(false);
const openDetail = (trx) => { selectedTransaction.value = trx; detailOpen.value = true; };

// ─── HELPERS ─────────────────────────────────────────────────────
const formatRupiah = (n) => new Intl.NumberFormat('id-ID').format(n);
const formatDate   = (s) => new Date(s).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
const copyToClipboard = (text) => { navigator.clipboard.writeText(text); alert(`Nota ${text} disalin!`); };

// Generate daftar tahun (5 tahun ke belakang)
const yearOptions = Array.from({ length: 5 }, (_, i) => String(new Date().getFullYear() - i));

// Label badge pada mode aktif
const filterLabel = computed(() => {
    if (filterMode.value === 'harian')  return filterDate.value;
    if (filterMode.value === 'bulanan') return filterMonth.value;
    return filterYear.value;
});

// ─── DELETE ───────────────────────────────────────────────────────
const deleteTarget    = ref(null);
const deleteConfirmOpen = ref(false);
const isDeleting      = ref(false);

const openDeleteConfirm = (trx) => {
    deleteTarget.value = trx;
    deleteConfirmOpen.value = true;
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;
    isDeleting.value = true;
    router.delete(`/reports/${deleteTarget.value.id}`, {
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
            isDeleting.value = false;
        },
        onError: () => { isDeleting.value = false; },
    });
};

// ─── FLASH ───────────────────────────────────────────────────────
const flash = computed(() => usePage().props.flash ?? {});
</script>

<template>
    <Head>
        <title>Laporan Penjualan | Smart POS System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </Head>

    <div class="flex flex-col gap-6 p-4 md:p-6 pb-8 font-inter">

        <!-- Flash Notifications -->
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="flash.success" class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 rounded-2xl px-4 py-3 text-sm font-semibold">
                <i class="fas fa-check-circle text-emerald-500"></i>
                {{ flash.success }}
            </div>
        </transition>
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="flash.error" class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-400 rounded-2xl px-4 py-3 text-sm font-semibold">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                {{ flash.error }}
            </div>
        </transition>

        <!-- Header + Filter Bar -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">Laporan Penjualan & Keuntungan</h1>
                    <p class="text-sm text-muted-foreground">Analisis performa penjualan dengan filter periode waktu.</p>
                </div>
                <!-- Result count badge -->
                <span class="text-xs bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 px-3 py-1.5 rounded-full font-semibold">
                    <i class="fas fa-receipt mr-1"></i>
                    {{ filteredTransactions.length }} transaksi • {{ filterLabel }}
                </span>
            </div>

            <!-- ── FILTER PANEL ── -->
            <div class="bg-card border border-border rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center gap-4 shadow-xs">
                <!-- Mode Tabs -->
                <div class="flex gap-1.5 bg-muted/50 p-1 rounded-xl border border-border/50">
                    <button
                        v-for="m in [
                            { key: 'harian',  label: 'Harian',  icon: 'fas fa-calendar-day' },
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

                <!-- Input sesuai mode -->
                <div class="flex-1 flex items-center gap-3">
                    <!-- Harian: date picker -->
                    <div v-if="filterMode === 'harian'" class="relative flex-1 max-w-xs">
                        <i class="fas fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <input
                            type="date"
                            v-model="filterDate"
                            class="pl-8 h-9 w-full rounded-xl border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 transition px-3"
                        />
                    </div>

                    <!-- Bulanan: month picker -->
                    <div v-else-if="filterMode === 'bulanan'" class="relative flex-1 max-w-xs">
                        <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <input
                            type="month"
                            v-model="filterMonth"
                            class="pl-8 h-9 w-full rounded-xl border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 transition px-3"
                        />
                    </div>

                    <!-- Tahunan: select tahun -->
                    <div v-else class="relative flex-1 max-w-xs">
                        <i class="fas fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <select
                            v-model="filterYear"
                            class="pl-8 h-9 w-full rounded-xl border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 transition px-3 appearance-none"
                        >
                            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <!-- Quick shortcut: Hari ini / Bulan ini / Tahun ini -->
                    <button
                        @click="
                            filterMode === 'harian'  ? filterDate  = new Date().toISOString().slice(0,10) :
                            filterMode === 'bulanan' ? filterMonth = new Date().toISOString().slice(0,7)  :
                                                       filterYear  = String(new Date().getFullYear())
                        "
                        class="text-xs text-indigo-500 hover:text-indigo-700 dark:hover:text-indigo-300 font-semibold whitespace-nowrap transition underline underline-offset-2"
                    >
                        {{ filterMode === 'harian' ? 'Hari ini' : filterMode === 'bulanan' ? 'Bulan ini' : 'Tahun ini' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <Card class="border-border/60 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Pendapatan (Omset)</span>
                        <i class="fas fa-wallet text-indigo-500 dark:text-indigo-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-foreground">Rp {{ formatRupiah(totalSales) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">{{ filteredTransactions.length }} transaksi pada periode ini</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
            </Card>

            <Card class="border-border/60 bg-gradient-to-br from-amber-500/5 to-orange-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Harga Modal</span>
                        <i class="fas fa-box-open text-amber-500 dark:text-amber-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-foreground">Rp {{ formatRupiah(totalBase) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Biaya modal pokok produk fisik/kulakan</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
            </Card>

            <Card class="border-border/60 bg-gradient-to-br from-emerald-500/5 to-teal-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Keuntungan Bersih (Profit)</span>
                        <i class="fas fa-chart-line text-emerald-500 dark:text-emerald-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(totalProfit) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Selisih harga jual dikurangi modal pokok</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
            </Card>
        </div>

        <!-- History Table -->
        <Card class="border-border/50 shadow-sm overflow-hidden bg-card text-card-foreground">
            <CardHeader class="border-b border-border/40 py-4 bg-muted/10">
                <CardTitle class="text-sm font-bold text-foreground">Riwayat Nota Penjualan</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">
                    Daftar transaksi periode
                    <strong class="text-foreground">
                        {{ filterMode === 'harian' ? filterDate : filterMode === 'bulanan' ? filterMonth : filterYear }}
                    </strong>
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="p-4 pl-6">Nomor Nota</th>
                                <th class="p-4">Tanggal Transaksi</th>
                                <th class="p-4">Metode Bayar</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Total Belanja</th>
                                <th class="p-4 text-right">Keuntungan</th>
                                <th class="p-4 text-center pr-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <!-- Empty state -->
                            <tr v-if="filteredTransactions.length === 0">
                                <td colspan="7" class="p-14 text-center text-muted-foreground">
                                    <i class="fas fa-filter text-4xl mb-3 opacity-20 block"></i>
                                    <p class="font-semibold text-sm">Tidak ada transaksi pada periode ini.</p>
                                    <p class="text-xs text-muted-foreground mt-1 opacity-70">Coba ubah filter tanggal, bulan, atau tahun.</p>
                                </td>
                            </tr>

                            <!-- Data rows -->
                            <tr v-for="trx in filteredTransactions" :key="trx.id" class="hover:bg-muted/30 transition">
                                <td class="p-4 pl-6 font-mono font-semibold text-foreground">
                                    <div class="flex items-center gap-1.5">
                                        {{ trx.invoice_number }}
                                        <Button @click="copyToClipboard(trx.invoice_number)" variant="ghost" size="xs" class="h-6 w-6 p-0 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted">
                                            <i class="fas fa-copy text-[10px]"></i>
                                        </Button>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground text-xs">{{ formatDate(trx.created_at) }}</td>
                                <td class="p-4">
                                    <Badge variant="secondary" class="capitalize px-2 py-0.5 rounded-full text-[11px] font-medium border"
                                        :class="{
                                            'bg-emerald-100/40 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400 border-emerald-500/20': trx.payment_method === 'cash',
                                            'bg-blue-100/40 text-blue-800 dark:bg-blue-950/20 dark:text-blue-400 border-blue-500/20': trx.payment_method === 'transfer',
                                            'bg-purple-100/40 text-purple-800 dark:bg-purple-950/20 dark:text-purple-400 border-purple-500/20': trx.payment_method === 'qris',
                                        }"
                                    >
                                        <i class="mr-1 text-[10px]" :class="{
                                            'fas fa-money-bill-wave': trx.payment_method === 'cash',
                                            'fas fa-university':      trx.payment_method === 'transfer',
                                            'fas fa-qrcode':          trx.payment_method === 'qris',
                                        }"></i>
                                        {{ trx.payment_method }}
                                    </Badge>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                                        :class="{
                                            'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20': trx.status_bayar === 'lunas' || !trx.status_bayar,
                                            'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20': trx.status_bayar === 'dp',
                                            'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20': trx.status_bayar === 'piutang',
                                        }"
                                    >
                                        {{ trx.status_bayar || 'lunas' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right font-bold text-foreground font-mono">Rp {{ formatRupiah(trx.total_price) }}</td>
                                <td class="p-4 text-right font-bold text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ formatRupiah(trx.total_profit) }}</td>
                                <td class="p-4 text-right pr-6">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Button @click="openDetail(trx)" variant="ghost" size="xs" class="h-8 rounded-xl text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-semibold px-2">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </Button>
                                        <a :href="`/pos/print/${trx.invoice_number}`" target="_blank"
                                            class="inline-flex items-center justify-center rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-neutral-200 font-bold text-xs h-8 px-3 shadow-xs transition">
                                            <i class="fas fa-print mr-1"></i> Cetak
                                        </a>
                                        <Button @click="openDeleteConfirm(trx)" variant="ghost" size="xs" class="h-8 rounded-xl text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30 font-semibold px-2">
                                            <i class="fas fa-trash-alt mr-1"></i> Hapus
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- DIALOG DETAIL NOTA -->
        <Dialog :open="detailOpen" @update:open="detailOpen = $event">
            <DialogContent class="sm:max-w-[600px] rounded-2xl bg-card border-border text-foreground overflow-y-auto max-h-[85vh]">
                <DialogHeader v-if="selectedTransaction">
                    <DialogTitle class="flex items-center gap-2 font-mono">
                        <i class="fas fa-receipt text-indigo-500"></i>
                        {{ selectedTransaction.invoice_number }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">Dicatat pada {{ formatDate(selectedTransaction.created_at) }}</DialogDescription>
                </DialogHeader>

                <div v-if="selectedTransaction" class="space-y-4 my-2 text-sm">
                    <!-- Pelanggan & Catatan -->
                    <div class="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-indigo-50/5 dark:bg-indigo-950/20 border border-indigo-100/10 text-xs">
                        <div>
                            <span class="text-muted-foreground block">Pelanggan:</span>
                            <span class="font-bold text-foreground text-sm mt-0.5">{{ selectedTransaction.customer?.name || selectedTransaction.customer_name || 'Cash / Umum' }}</span>
                            <span v-if="selectedTransaction.customer?.phone || selectedTransaction.customer_phone" class="text-[10px] text-muted-foreground block">
                                Telp: {{ selectedTransaction.customer?.phone || selectedTransaction.customer_phone }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block">Catatan / Keterangan:</span>
                            <span class="font-medium text-foreground block mt-0.5 whitespace-pre-line">{{ selectedTransaction.keterangan || '-' }}</span>
                        </div>
                    </div>

                    <!-- Status & Metode -->
                    <div class="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-muted/30 border border-border/50">
                        <div>
                            <p class="text-xs text-muted-foreground">Metode Pembayaran</p>
                            <p class="text-sm font-bold capitalize text-foreground mt-0.5">{{ selectedTransaction.payment_method }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Status Transaksi</p>
                            <p class="text-sm font-black mt-0.5 uppercase tracking-wider"
                                :class="selectedTransaction.status_bayar === 'lunas' || !selectedTransaction.status_bayar ? 'text-emerald-600 dark:text-emerald-400' : (selectedTransaction.status_bayar === 'dp' ? 'text-amber-600 dark:text-amber-400' : 'text-red-500')"
                            >{{ selectedTransaction.status_bayar || 'lunas' }}</p>
                        </div>
                    </div>

                    <!-- Riwayat Pembayaran -->
                    <div v-if="selectedTransaction.payment_histories?.length > 0" class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                            <span>Riwayat Pembayaran Kronologis</span>
                            <span class="text-[10px] font-normal">({{ selectedTransaction.payment_histories.length }} pembayaran)</span>
                        </h4>
                        <div class="border border-border rounded-2xl overflow-hidden bg-background divide-y divide-border/60">
                            <div v-for="(pay, pIdx) in selectedTransaction.payment_histories" :key="pay.id" class="p-3 flex justify-between items-center text-xs">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold text-[10px]">{{ pIdx + 1 }}</div>
                                    <div>
                                        <p class="font-bold text-foreground">{{ pay.keterangan || 'Pembayaran' }}</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5">{{ formatDate(pay.tanggal_bayar) }} &bull; <span class="capitalize">{{ pay.metode_bayar }}</span></p>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(pay.jumlah_bayar) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Item Belanja -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Item yang Dibeli</h4>
                        <div class="divide-y divide-border border border-border rounded-2xl overflow-hidden bg-background">
                            <div v-for="item in selectedTransaction.items" :key="item.id" class="p-3 flex justify-between items-center text-xs">
                                <div>
                                    <p class="font-bold text-foreground text-sm leading-tight">{{ item.item_name }}</p>
                                    <p class="text-[11px] text-muted-foreground mt-0.5">
                                        {{ parseFloat(item.quantity) }} {{ item.unit || 'pcs' }} &times; Rp {{ formatRupiah(item.selling_price) }}
                                    </p>
                                    <p v-if="item.metadata?.detail" class="text-[10px] text-indigo-500 font-mono mt-0.5">{{ item.metadata.detail }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-foreground text-sm">Rp {{ formatRupiah(item.subtotal_price) }}</p>
                                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">Untung: Rp {{ formatRupiah(item.profit) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Keuangan -->
                    <div class="bg-muted/20 p-4 rounded-2xl border border-border/50 text-xs space-y-1.5">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Total Belanjaan:</span>
                            <span class="font-bold font-mono text-foreground">Rp {{ formatRupiah(selectedTransaction.total_price) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span class="text-emerald-600 dark:text-emerald-400">Total Uang Masuk:</span>
                            <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(selectedTransaction.jumlah_dibayar || 0) }}</span>
                        </div>
                        <div v-if="selectedTransaction.sisa_tagihan > 0" class="flex justify-between font-bold text-red-500">
                            <span>Sisa Kurang / Piutang:</span>
                            <span class="font-mono">Rp {{ formatRupiah(selectedTransaction.sisa_tagihan) }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-border flex justify-between items-center">
                        <span class="text-sm font-medium text-muted-foreground">Total Keuntungan Bersih:</span>
                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(selectedTransaction.total_profit) }}</span>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="rounded-xl">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG KONFIRMASI HAPUS -->
        <Dialog :open="deleteConfirmOpen" @update:open="deleteConfirmOpen = $event">
            <DialogContent class="sm:max-w-[420px] rounded-3xl bg-card border-border text-foreground shadow-2xl p-6">
                <div class="flex flex-col items-center text-center space-y-4">
                    <div class="w-16 h-16 rounded-full bg-red-500/10 dark:bg-red-500/20 text-red-500 flex items-center justify-center text-3xl">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="space-y-1.5">
                        <DialogTitle class="text-lg font-black text-foreground">Hapus Transaksi?</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground leading-relaxed px-2">
                            Anda akan menghapus nota
                            <strong class="font-mono text-foreground">{{ deleteTarget?.invoice_number }}</strong>
                            secara permanen. Stok produk fisik akan dikembalikan. Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </div>
                </div>
                <DialogFooter class="grid grid-cols-2 gap-3 mt-5">
                    <Button
                        @click="deleteConfirmOpen = false"
                        variant="outline"
                        class="rounded-xl font-bold border-border hover:bg-muted text-foreground"
                        :disabled="isDeleting"
                    >
                        Batal
                    </Button>
                    <Button
                        @click="confirmDelete"
                        class="rounded-xl font-bold bg-red-600 hover:bg-red-700 text-white shadow-sm flex items-center justify-center gap-2"
                        :disabled="isDeleting"
                    >
                        <i v-if="!isDeleting" class="fas fa-trash-alt text-xs"></i>
                        <i v-else class="fas fa-circle-notch fa-spin text-xs"></i>
                        {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
