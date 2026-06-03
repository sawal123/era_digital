<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Catatan Piutang', href: '/receivables' },
    ],
});

const props = defineProps({
    transactions: Array,
});

// Search query
const searchQuery = ref('');

// Filter transactions by search query
const filteredTransactions = computed(() => {
    if (!searchQuery.value) return props.transactions;
    const query = searchQuery.value.toLowerCase();
    return props.transactions.filter(t =>
        t.invoice_number.toLowerCase().includes(query) ||
        (t.customer_name && t.customer_name.toLowerCase().includes(query)) ||
        (t.customer_phone && t.customer_phone.includes(query))
    );
});

// Pay Form state
const payDialogOpen = ref(false);
const selectedTransaction = ref(null);

const form = useForm({
    bayar_nominal: 0,
});

const openPayModal = (transaction) => {
    selectedTransaction.value = transaction;
    form.bayar_nominal = transaction.sisa_tagihan;
    form.clearErrors();
    payDialogOpen.value = true;
};

const handlePaySubmit = () => {
    if (!selectedTransaction.value) return;
    
    form.post(`/receivables/${selectedTransaction.value.id}/pay`, {
        onSuccess: () => {
            payDialogOpen.value = false;
            form.reset();
            selectedTransaction.value = null;
        }
    });
};

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

// Compute sisa tagihan inside dialog in real-time
const realTimeSisa = computed(() => {
    if (!selectedTransaction.value) return 0;
    return Math.max(0, selectedTransaction.value.sisa_tagihan - form.bayar_nominal);
});
</script>

<template>
    <Head title="Catatan Piutang & Pelunasan" />

    <div class="flex flex-col gap-6 p-4 md:p-6 pb-8 font-inter">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    <i class="fas fa-hand-holding-usd text-amber-500"></i>
                    Catatan Piutang & Pelunasan
                </h1>
                <p class="text-sm text-muted-foreground">Kelola piutang, pelunasan sisa kekurangan, dan riwayat cicilan transaksi pelanggan (DP & Piutang).</p>
            </div>
        </div>

        <!-- Summary Widgets (Vibrant Premium Feel) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Total Transaksi Piutang/DP</span>
                    <h3 class="text-2xl font-black mt-1 text-foreground">{{ filteredTransactions.length }} Nota</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
            </div>

            <div class="bg-red-500/10 border border-red-500/20 rounded-2xl p-5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-wider">Total Piutang Belum Lunas</span>
                    <h3 class="text-2xl font-black mt-1 text-red-600 dark:text-red-400">
                        Rp {{ formatRupiah(transactions.reduce((acc, t) => acc + parseFloat(t.sisa_tagihan), 0)) }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center text-red-600 dark:text-red-400">
                    <i class="fas fa-exclamation-circle text-xl"></i>
                </div>
            </div>

            <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-5 flex items-center justify-between shadow-sm">
                <div>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Total Dana Diterima (DP)</span>
                    <h3 class="text-2xl font-black mt-1 text-emerald-600 dark:text-emerald-400">
                        Rp {{ formatRupiah(transactions.reduce((acc, t) => acc + parseFloat(t.jumlah_dibayar), 0)) }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <i class="fas fa-wallet text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Filter & Search -->
        <div class="flex items-center gap-3 w-full sm:w-80">
            <div class="relative w-full">
                <i class="fas fa-search absolute left-3.5 top-3 text-muted-foreground text-sm"></i>
                <Input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="Cari No. Invoice / Nama Customer..." 
                    class="pl-10 rounded-xl bg-card border-border text-foreground"
                />
            </div>
        </div>

        <!-- Receivables List Table -->
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <th class="p-4 pl-6">Nomor Invoice</th>
                            <th class="p-4">Customer</th>
                            <th class="p-4 text-right">Total Belanja</th>
                            <th class="p-4 text-right">Telah Dibayar</th>
                            <th class="p-4 text-right text-red-500 dark:text-red-400">Sisa Piutang</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4">Tanggal Input</th>
                            <th class="p-4 text-right pr-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredTransactions.length === 0">
                            <td colspan="8" class="p-8 text-center text-muted-foreground">
                                <i class="fas fa-check-circle text-3xl mb-2 opacity-30 text-emerald-500"></i>
                                <p>Tidak ada catatan piutang aktif. Semua transaksi lunas!</p>
                            </td>
                        </tr>
                        <tr v-for="t in filteredTransactions" :key="t.id" class="hover:bg-muted/30 transition">
                            <td class="p-4 pl-6 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                {{ t.invoice_number }}
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-foreground">{{ t.customer_name || 'Cash / Umum' }}</div>
                                <div class="text-[10px] text-muted-foreground">{{ t.customer_phone || '-' }}</div>
                            </td>
                            <td class="p-4 text-right font-mono font-semibold">
                                Rp {{ formatRupiah(t.total_price) }}
                            </td>
                            <td class="p-4 text-right font-mono text-emerald-600 font-semibold">
                                Rp {{ formatRupiah(t.jumlah_dibayar) }}
                            </td>
                            <td class="p-4 text-right font-mono text-red-500 dark:text-red-400 font-bold">
                                Rp {{ formatRupiah(t.sisa_tagihan) }}
                            </td>
                            <td class="p-4 text-center">
                                <span 
                                    class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                    :class="t.status_bayar === 'dp' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20' : 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20'"
                                >
                                    {{ t.status_bayar }}
                                </span>
                            </td>
                            <td class="p-4 text-muted-foreground text-xs">
                                {{ formatDate(t.created_at) }}
                            </td>
                            <td class="p-4 text-right pr-6">
                                <Button 
                                    @click="openPayModal(t)" 
                                    size="icon-sm"
                                    title="Pelunasan piutang"
                                    aria-label="Pelunasan piutang"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl ml-auto shadow-sm"
                                >
                                    <i class="fas fa-hand-holding-usd"></i>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DIALOG FORM (Pelunasan Piutang) -->
        <Dialog :open="payDialogOpen" @update:open="payDialogOpen = $event">
            <DialogContent class="sm:max-w-[425px] rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-coins text-emerald-500"></i>
                        Input Pembayaran Pelunasan
                    </DialogTitle>
                    <DialogDescription v-if="selectedTransaction">
                        Catat uang pelunasan untuk Nota <strong class="text-indigo-600 dark:text-indigo-400 font-mono">{{ selectedTransaction.invoice_number }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <form v-if="selectedTransaction" @submit.prevent="handlePaySubmit" class="space-y-4 py-2">
                    <!-- Detail Ringkas Piutang -->
                    <div class="bg-muted/40 p-4 rounded-xl space-y-2 border border-border text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Pelanggan:</span>
                            <span class="font-bold text-foreground">{{ selectedTransaction.customer_name || 'Cash / Umum' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Total Belanjaan:</span>
                            <span class="font-mono font-semibold text-foreground">Rp {{ formatRupiah(selectedTransaction.total_price) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Telah Dibayar:</span>
                            <span class="font-mono font-semibold text-emerald-600">Rp {{ formatRupiah(selectedTransaction.jumlah_dibayar) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-border pt-2 font-bold text-sm">
                            <span class="text-red-500">Sisa Piutang:</span>
                            <span class="font-mono text-red-500">Rp {{ formatRupiah(selectedTransaction.sisa_tagihan) }}</span>
                        </div>
                    </div>

                    <!-- Nominal Uang Pelunasan -->
                    <div class="space-y-2">
                        <Label for="pay-nominal" class="text-xs font-bold">Nominal Pembayaran Tunai</Label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs text-muted-foreground font-bold">Rp</span>
                            <Input 
                                id="pay-nominal" 
                                type="number" 
                                v-model.number="form.bayar_nominal" 
                                placeholder="Masukkan jumlah pembayaran..."
                                class="pl-8 rounded-xl border-border bg-background text-foreground font-bold text-sm"
                                :max="selectedTransaction.sisa_tagihan"
                                min="1"
                                required
                            />
                        </div>
                        <p v-if="form.errors.bayar_nominal" class="text-xs text-red-500 font-medium">{{ form.errors.bayar_nominal }}</p>
                    </div>

                    <!-- Kalkulator Sisa Real-time -->
                    <div class="flex justify-between items-center text-[11px] font-bold mt-1 px-1 bg-emerald-500/5 p-2 rounded-lg border border-emerald-500/10">
                        <span class="text-muted-foreground">Sisa Piutang Akhir:</span>
                        <span :class="realTimeSisa > 0 ? 'text-amber-500' : 'text-emerald-500 font-black'">
                            Rp {{ formatRupiah(realTimeSisa) }} {{ realTimeSisa === 0 ? '(LUNAS 🎉)' : '' }}
                        </span>
                    </div>

                    <DialogFooter class="pt-4 gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary" class="rounded-xl">Batal</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="form.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold flex items-center gap-1.5">
                            <i class="fas fa-check"></i>
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Pembayaran' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
