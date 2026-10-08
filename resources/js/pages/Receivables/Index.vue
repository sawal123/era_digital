<script setup>
import { Head, useForm } from '@inertiajs/vue3';
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
    if (!searchQuery.value) {
        return props.transactions;
    }

    const query = searchQuery.value.toLowerCase();

    return props.transactions.filter(
        (t) =>
            t.invoice_number.toLowerCase().includes(query) ||
            (t.customer_name &&
                t.customer_name.toLowerCase().includes(query)) ||
            (t.customer_phone && t.customer_phone.includes(query)),
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
    if (!selectedTransaction.value) {
        return;
    }

    form.post(`/receivables/${selectedTransaction.value.id}/pay`, {
        onSuccess: () => {
            payDialogOpen.value = false;
            form.reset();
            selectedTransaction.value = null;
        },
    });
};

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

const formatDate = (dateString) => {
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    };

    return new Date(dateString).toLocaleDateString('id-ID', options);
};

// Compute sisa tagihan inside dialog in real-time
const realTimeSisa = computed(() => {
    if (!selectedTransaction.value) {
        return 0;
    }

    return Math.max(
        0,
        selectedTransaction.value.sisa_tagihan - form.bayar_nominal,
    );
});
</script>

<template>
    <Head title="Catatan Piutang & Pelunasan" />

    <div class="font-inter flex flex-col gap-6 p-4 pb-8 md:p-6">
        <!-- Header -->
        <div
            class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground"
                >
                    <i class="fas fa-hand-holding-usd text-amber-500"></i>
                    Catatan Piutang & Pelunasan
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola piutang, pelunasan sisa kekurangan, dan riwayat
                    cicilan transaksi pelanggan (DP & Piutang).
                </p>
            </div>
        </div>

        <!-- Summary Widgets (Vibrant Premium Feel) -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div
                class="flex items-center justify-between rounded-2xl border border-amber-500/20 bg-amber-500/10 p-5 shadow-sm"
            >
                <div>
                    <span
                        class="text-xs font-bold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                        >Total Transaksi Piutang/DP</span
                    >
                    <h3 class="mt-1 text-2xl font-black text-foreground">
                        {{ filteredTransactions.length }} Nota
                    </h3>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400"
                >
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
            </div>

            <div
                class="flex items-center justify-between rounded-2xl border border-red-500/20 bg-red-500/10 p-5 shadow-sm"
            >
                <div>
                    <span
                        class="text-xs font-bold tracking-wider text-red-600 uppercase dark:text-red-400"
                        >Total Piutang Belum Lunas</span
                    >
                    <h3
                        class="mt-1 text-2xl font-black text-red-600 dark:text-red-400"
                    >
                        Rp
                        {{
                            formatRupiah(
                                transactions.reduce(
                                    (acc, t) =>
                                        acc + parseFloat(t.sisa_tagihan),
                                    0,
                                ),
                            )
                        }}
                    </h3>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-500/20 text-red-600 dark:text-red-400"
                >
                    <i class="fas fa-exclamation-circle text-xl"></i>
                </div>
            </div>

            <div
                class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-5 shadow-sm"
            >
                <div>
                    <span
                        class="text-xs font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                        >Total Dana Diterima (DP)</span
                    >
                    <h3
                        class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        Rp
                        {{
                            formatRupiah(
                                transactions.reduce(
                                    (acc, t) =>
                                        acc + parseFloat(t.jumlah_dibayar),
                                    0,
                                ),
                            )
                        }}
                    </h3>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400"
                >
                    <i class="fas fa-wallet text-xl"></i>
                </div>
            </div>
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
                    placeholder="Cari No. Invoice / Nama Customer..."
                    class="rounded-xl border-border bg-card pl-10 text-foreground"
                />
            </div>
        </div>

        <!-- Receivables List Table -->
        <div
            class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="p-4 pl-6">Nomor Invoice</th>
                            <th class="p-4">Customer</th>
                            <th class="p-4 text-right">Total Belanja</th>
                            <th class="p-4 text-right">Telah Dibayar</th>
                            <th
                                class="p-4 text-right text-red-500 dark:text-red-400"
                            >
                                Sisa Piutang
                            </th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4">Tanggal Input</th>
                            <th class="p-4 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredTransactions.length === 0">
                            <td
                                colspan="8"
                                class="p-8 text-center text-muted-foreground"
                            >
                                <i
                                    class="fas fa-check-circle mb-2 text-3xl text-emerald-500 opacity-30"
                                ></i>
                                <p>
                                    Tidak ada catatan piutang aktif. Semua
                                    transaksi lunas!
                                </p>
                            </td>
                        </tr>
                        <tr
                            v-for="t in filteredTransactions"
                            :key="t.id"
                            class="transition hover:bg-muted/30"
                        >
                            <td
                                class="p-4 pl-6 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                {{ t.invoice_number }}
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-foreground">
                                    {{ t.customer_name || 'Cash / Umum' }}
                                </div>
                                <div class="text-[10px] text-muted-foreground">
                                    {{ t.customer_phone || '-' }}
                                </div>
                            </td>
                            <td class="p-4 text-right font-mono font-semibold">
                                Rp {{ formatRupiah(t.total_price) }}
                            </td>
                            <td
                                class="p-4 text-right font-mono font-semibold text-emerald-600"
                            >
                                Rp {{ formatRupiah(t.jumlah_dibayar) }}
                            </td>
                            <td
                                class="p-4 text-right font-mono font-bold text-red-500 dark:text-red-400"
                            >
                                Rp {{ formatRupiah(t.sisa_tagihan) }}
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    class="inline-block rounded-full px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                    :class="
                                        t.status_bayar === 'dp'
                                            ? 'border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                            : 'border border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{ t.status_bayar }}
                                </span>
                            </td>
                            <td class="p-4 text-xs text-muted-foreground">
                                {{ formatDate(t.created_at) }}
                            </td>
                            <td class="p-4 pr-6 text-right">
                                <Button
                                    @click="openPayModal(t)"
                                    size="icon-sm"
                                    title="Pelunasan piutang"
                                    aria-label="Pelunasan piutang"
                                    class="ml-auto rounded-xl bg-emerald-600 text-white shadow-sm hover:bg-emerald-700"
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
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[425px]"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-coins text-emerald-500"></i>
                        Input Pembayaran Pelunasan
                    </DialogTitle>
                    <DialogDescription v-if="selectedTransaction">
                        Catat uang pelunasan untuk Nota
                        <strong
                            class="font-mono text-indigo-600 dark:text-indigo-400"
                            >{{ selectedTransaction.invoice_number }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>

                <form
                    v-if="selectedTransaction"
                    @submit.prevent="handlePaySubmit"
                    class="space-y-4 py-2"
                >
                    <!-- Detail Ringkas Piutang -->
                    <div
                        class="space-y-2 rounded-xl border border-border bg-muted/40 p-4 text-xs"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Pelanggan:</span
                            >
                            <span class="font-bold text-foreground">{{
                                selectedTransaction.customer_name ||
                                'Cash / Umum'
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Total Belanjaan:</span
                            >
                            <span
                                class="font-mono font-semibold text-foreground"
                                >Rp
                                {{
                                    formatRupiah(
                                        selectedTransaction.total_price,
                                    )
                                }}</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground"
                                >Telah Dibayar:</span
                            >
                            <span
                                class="font-mono font-semibold text-emerald-600"
                                >Rp
                                {{
                                    formatRupiah(
                                        selectedTransaction.jumlah_dibayar,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            class="flex justify-between border-t border-border pt-2 text-sm font-bold"
                        >
                            <span class="text-red-500">Sisa Piutang:</span>
                            <span class="font-mono text-red-500"
                                >Rp
                                {{
                                    formatRupiah(
                                        selectedTransaction.sisa_tagihan,
                                    )
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- Nominal Uang Pelunasan -->
                    <div class="space-y-2">
                        <Label for="pay-nominal" class="text-xs font-bold"
                            >Nominal Pembayaran Tunai</Label
                        >
                        <div class="relative">
                            <span
                                class="absolute top-2.5 left-3 text-xs font-bold text-muted-foreground"
                                >Rp</span
                            >
                            <Input
                                id="pay-nominal"
                                type="number"
                                v-model.number="form.bayar_nominal"
                                placeholder="Masukkan jumlah pembayaran..."
                                class="rounded-xl border-border bg-background pl-8 text-sm font-bold text-foreground"
                                :max="selectedTransaction.sisa_tagihan"
                                min="1"
                                required
                            />
                        </div>
                        <p
                            v-if="form.errors.bayar_nominal"
                            class="text-xs font-medium text-red-500"
                        >
                            {{ form.errors.bayar_nominal }}
                        </p>
                    </div>

                    <!-- Kalkulator Sisa Real-time -->
                    <div
                        class="mt-1 flex items-center justify-between rounded-lg border border-emerald-500/10 bg-emerald-500/5 p-2 px-1 text-[11px] font-bold"
                    >
                        <span class="text-muted-foreground"
                            >Sisa Piutang Akhir:</span
                        >
                        <span
                            :class="
                                realTimeSisa > 0
                                    ? 'text-amber-500'
                                    : 'font-black text-emerald-500'
                            "
                        >
                            Rp {{ formatRupiah(realTimeSisa) }}
                            {{ realTimeSisa === 0 ? '(LUNAS 🎉)' : '' }}
                        </span>
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
                            class="flex items-center gap-1.5 rounded-xl bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                        >
                            <i class="fas fa-check"></i>
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Pembayaran'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
