<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
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
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Laporan Penjualan',
                href: '/reports',
            },
        ],
    },
});

const props = defineProps({
    transactions: Array,
});

// State Detail Dialog
const selectedTransaction = ref(null);
const detailOpen = ref(false);

const openDetail = (trx) => {
    selectedTransaction.value = trx;
    detailOpen.value = true;
};

// Summary metrics
const totalSales = computed(() => {
    return props.transactions.reduce((sum, t) => sum + parseFloat(t.total_price), 0);
});

const totalBase = computed(() => {
    return props.transactions.reduce((sum, t) => sum + parseFloat(t.total_base_price), 0);
});

const totalProfit = computed(() => {
    return props.transactions.reduce((sum, t) => sum + parseFloat(t.total_profit), 0);
});

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    alert(`Nota ${text} berhasil disalin!`);
};
</script>

<template>
    <Head>
        <title>Laporan Penjualan | Smart POS System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </Head>

    <div class="flex flex-col gap-6 p-4 md:p-6 pb-8 font-inter">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Laporan Penjualan & Keuntungan</h1>
                <p class="text-sm text-muted-foreground">Analisis performa penjualan, total modal, dan keuntungan bersih toko Anda secara real-time.</p>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Omset -->
            <Card class="border-border/60 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Pendapatan (Omset)</span>
                        <i class="fas fa-wallet text-indigo-500 dark:text-indigo-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-foreground">Rp {{ formatRupiah(totalSales) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Total nilai penjualan barang & layanan jasa</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
            </Card>

            <!-- Modal -->
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

            <!-- Keuntungan -->
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
                <CardDescription class="text-xs text-muted-foreground">Daftar semua transaksi yang berhasil dicatat oleh sistem kasir POS.</CardDescription>
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
                                <th class="p-4 text-center pr-6">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="transactions.length === 0">
                                <td colspan="7" class="p-12 text-center text-muted-foreground">
                                    <i class="fas fa-file-invoice text-4xl mb-3 opacity-20"></i>
                                    <p class="font-medium">Belum ada riwayat transaksi.</p>
                                    <p class="text-xs text-muted-foreground mt-1">Lakukan penjualan di POS Kasir untuk mengisi laporan.</p>
                                </td>
                            </tr>
                            <tr v-for="trx in transactions" :key="trx.id" class="hover:bg-muted/30 transition">
                                <!-- Invoice Number -->
                                <td class="p-4 pl-6 font-mono font-semibold text-foreground flex items-center gap-1.5">
                                    {{ trx.invoice_number }}
                                    <Button @click="copyToClipboard(trx.invoice_number)" variant="ghost" size="xs" class="h-6 w-6 p-0 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted">
                                        <i class="fas fa-copy text-[10px]"></i>
                                    </Button>
                                </td>
                                <!-- Date -->
                                <td class="p-4 text-muted-foreground">
                                    {{ formatDate(trx.created_at) }}
                                </td>
                                <!-- Payment Method -->
                                <td class="p-4">
                                    <Badge 
                                        variant="secondary" 
                                        class="capitalize px-2 py-0.5 rounded-full text-[11px] font-medium border"
                                        :class="{
                                            'bg-emerald-100/40 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400 border-emerald-500/20': trx.payment_method === 'cash',
                                            'bg-blue-100/40 text-blue-800 dark:bg-blue-950/20 dark:text-blue-400 border-blue-500/20': trx.payment_method === 'transfer',
                                            'bg-purple-100/40 text-purple-800 dark:bg-purple-950/20 dark:text-purple-400 border-purple-500/20': trx.payment_method === 'qris'
                                        }"
                                    >
                                        <i class="mr-1 text-[10px]" :class="{
                                            'fas fa-money-bill-wave': trx.payment_method === 'cash',
                                            'fas fa-university': trx.payment_method === 'transfer',
                                            'fas fa-qrcode': trx.payment_method === 'qris'
                                        }"></i>
                                        {{ trx.payment_method }}
                                    </Badge>
                                </td>
                                <!-- Status -->
                                <td class="p-4 text-center">
                                    <span 
                                        class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                                        :class="{
                                            'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20': trx.status_bayar === 'lunas' || !trx.status_bayar,
                                            'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20': trx.status_bayar === 'dp',
                                            'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20': trx.status_bayar === 'piutang',
                                        }"
                                    >
                                        {{ trx.status_bayar || 'lunas' }}
                                    </span>
                                </td>
                                <!-- Total Price -->
                                <td class="p-4 text-right font-bold text-foreground">
                                    Rp {{ formatRupiah(trx.total_price) }}
                                </td>
                                <!-- Total Profit -->
                                <td class="p-4 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ formatRupiah(trx.total_profit) }}
                                </td>
                                <!-- Action -->
                                <td class="p-4 text-right pr-6">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Button @click="openDetail(trx)" variant="ghost" size="xs" class="h-8 rounded-xl text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-semibold px-2">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </Button>
                                        <a 
                                            :href="`/pos/print/${trx.invoice_number}`" 
                                            target="_blank" 
                                            class="inline-flex items-center justify-center rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-neutral-200 font-bold text-xs h-8 px-3 shadow-xs transition"
                                        >
                                            <i class="fas fa-print mr-1"></i> Cetak
                                        </a>
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
                    <DialogDescription class="text-xs">
                        Dicatat pada {{ formatDate(selectedTransaction.created_at) }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedTransaction" class="space-y-4 my-2 text-sm">
                    <!-- Detail Pelanggan & Catatan -->
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

                    <!-- Ringkasan Struk -->
                    <div class="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-muted/30 border border-border/50">
                        <div>
                            <p class="text-xs text-muted-foreground">Metode Pembayaran</p>
                            <p class="text-sm font-bold capitalize text-foreground mt-0.5">{{ selectedTransaction.payment_method }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Status Transaksi</p>
                            <p 
                                class="text-sm font-black mt-0.5 uppercase tracking-wider"
                                :class="selectedTransaction.status_bayar === 'lunas' || !selectedTransaction.status_bayar ? 'text-emerald-600 dark:text-emerald-400' : (selectedTransaction.status_bayar === 'dp' ? 'text-amber-600 dark:text-amber-400' : 'text-red-500')"
                            >
                                {{ selectedTransaction.status_bayar || 'lunas' }}
                            </p>
                        </div>
                    </div>

                    <!-- Riwayat Pembayaran Kronologis (Payment History Logs) -->
                    <div v-if="selectedTransaction.payment_histories && selectedTransaction.payment_histories.length > 0" class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                            <span>Riwayat Pembayaran & Pelunasan Kronologis</span>
                            <span class="text-[10px] font-normal text-muted-foreground">({{ selectedTransaction.payment_histories.length }} pembayaran)</span>
                        </h4>
                        <div class="border border-border rounded-2xl overflow-hidden bg-background divide-y divide-border/60">
                            <div v-for="(pay, pIdx) in selectedTransaction.payment_histories" :key="pay.id" class="p-3 flex justify-between items-center text-xs">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold text-[10px]">
                                        {{ pIdx + 1 }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-foreground">{{ pay.keterangan || 'Pembayaran' }}</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5">
                                            {{ formatDate(pay.tanggal_bayar) }} &bull; Metode: <span class="capitalize">{{ pay.metode_bayar }}</span>
                                        </p>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(pay.jumlah_bayar) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Item Belanja -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Item yang Dibeli</h4>
                        <div class="divide-y divide-border border border-border rounded-2xl overflow-hidden bg-background">
                            <div v-for="item in selectedTransaction.items" :key="item.id" class="p-3 flex justify-between items-center text-xs">
                                <div>
                                    <p class="font-bold text-foreground text-sm leading-tight">{{ item.item_name }}</p>
                                    <p class="text-[11px] text-muted-foreground mt-0.5">
                                        {{ parseFloat(item.quantity) }} {{ item.unit || 'pcs' }} &times; Rp {{ formatRupiah(item.selling_price) }}
                                    </p>
                                    <p v-if="item.metadata?.detail" class="text-[10px] text-indigo-500 font-mono mt-0.5">
                                        {{ item.metadata.detail }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-foreground text-sm">Rp {{ formatRupiah(item.subtotal_price) }}</p>
                                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">Untung: Rp {{ formatRupiah(item.profit) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Keuangan Nota -->
                    <div class="bg-muted/20 p-4 rounded-2xl border border-border/50 text-xs space-y-1.5">
                        <div class="flex justify-between items-center">
                            <span class="text-muted-foreground">Total Belanjaan:</span>
                            <span class="font-bold text-foreground font-mono">Rp {{ formatRupiah(selectedTransaction.total_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center font-semibold">
                            <span class="text-emerald-600 dark:text-emerald-400">Total Uang Masuk:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ formatRupiah(selectedTransaction.jumlah_dibayar || 0) }}</span>
                        </div>
                        <div v-if="selectedTransaction.sisa_tagihan > 0" class="flex justify-between items-center font-bold text-red-500">
                            <span>Sisa Kurang / Piutang:</span>
                            <span class="font-mono">Rp {{ formatRupiah(selectedTransaction.sisa_tagihan) }}</span>
                        </div>
                    </div>

                    <!-- Total Perhitungan Keuntungan -->
                    <div class="pt-2 border-t border-border flex justify-between items-center">
                        <span class="text-sm font-medium text-muted-foreground">Total Keuntungan Bersih:</span>
                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(selectedTransaction.total_profit) }}</span>
                    </div>
                </div>

                <DialogFooter class="sm:justify-end">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="rounded-xl">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
