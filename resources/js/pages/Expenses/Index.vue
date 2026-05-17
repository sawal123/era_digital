<script setup>
import { ref, computed } from 'vue';
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
                title: 'Operasional & Pengeluaran',
                href: '/expenses',
            },
        ],
    },
});

const props = defineProps({
    expenses: Array,
});

// Form modal state
const formOpen = ref(false);

const form = useForm({
    date: new Date().toISOString().split('T')[0],
    name: '',
    amount: '',
    category: 'operasional',
    note: '',
});

const openCreateModal = () => {
    form.reset();
    form.date = new Date().toISOString().split('T')[0];
    formOpen.value = true;
};

const submitForm = () => {
    form.post('/expenses', {
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    });
};

const deleteExpense = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus catatan pengeluaran ini?')) {
        form.delete(`/expenses/${id}`);
    }
};

// Summary metrics
const totalExpenses = computed(() => {
    return props.expenses.reduce((sum, e) => sum + parseFloat(e.amount), 0);
});

const expensesByCategory = computed(() => {
    const categories = { operasional: 0, stok: 0, lainnya: 0 };
    props.expenses.forEach(e => {
        if (categories[e.category] !== undefined) {
            categories[e.category] += parseFloat(e.amount);
        }
    });
    return categories;
});

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};
</script>

<template>
    <Head>
        <title>Biaya Operasional & Pengeluaran | Smart POS System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </Head>

    <div class="flex flex-col gap-6 p-4 md:p-6 pb-8 font-inter">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Operasional & Pengeluaran</h1>
                <p class="text-sm text-muted-foreground">Catat dan pantau pengeluaran bulanan, biaya kulakan barang, listrik, internet, dan kertas fotokopi.</p>
            </div>
            <Button @click="openCreateModal" class="rounded-xl font-semibold shadow-md bg-red-600 hover:bg-red-700 text-white shrink-0">
                <i class="fas fa-plus mr-2 text-xs"></i> Catat Pengeluaran
            </Button>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Total Pengeluaran -->
            <Card class="border-border/60 bg-gradient-to-br from-red-500/5 to-orange-500/5 relative overflow-hidden shadow-sm md:col-span-1">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Pengeluaran</span>
                        <i class="fas fa-arrow-down-long text-red-500 dark:text-red-450 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-red-600 dark:text-red-400">Rp {{ formatRupiah(totalExpenses) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Akumulasi pengeluaran biaya operasional</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-red-500 to-orange-500"></div>
            </Card>

            <!-- Biaya Operasional -->
            <Card class="border-border/60 bg-card shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Operasional Harian</span>
                        <i class="fas fa-tools text-blue-500 dark:text-blue-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-xl font-bold text-foreground">Rp {{ formatRupiah(expensesByCategory.operasional) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Tagihan listrik, WiFi, kertas & servis mesin</p>
                </CardContent>
            </Card>

            <!-- Biaya Kulakan Stok -->
            <Card class="border-border/60 bg-card shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Kulakan Stok Barang</span>
                        <i class="fas fa-boxes text-emerald-500 dark:text-emerald-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-xl font-bold text-foreground">Rp {{ formatRupiah(expensesByCategory.stok) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Biaya restock pulpen, rim kertas, buku tulis</p>
                </CardContent>
            </Card>

            <!-- Lainnya -->
            <Card class="border-border/60 bg-card shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Lain-lain</span>
                        <i class="fas fa-ellipsis-h text-purple-500 dark:text-purple-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-xl font-bold text-foreground">Rp {{ formatRupiah(expensesByCategory.lainnya) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Pengeluaran lain di luar operasional utama</p>
                </CardContent>
            </Card>
        </div>

        <!-- History Table -->
        <Card class="border-border/50 shadow-sm overflow-hidden bg-card text-card-foreground">
            <CardHeader class="border-b border-border/40 py-4 bg-muted/10">
                <CardTitle class="text-sm font-bold text-foreground">Riwayat Catatan Biaya</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">Semua pos anggaran pengeluaran yang terdokumentasi.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="p-4 pl-6">Tanggal</th>
                                <th class="p-4">Kebutuhan / Pengeluaran</th>
                                <th class="p-4">Kategori Biaya</th>
                                <th class="p-4">Keterangan Catatan</th>
                                <th class="p-4 text-right">Nominal Jumlah</th>
                                <th class="p-4 text-right pr-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="expenses.length === 0">
                                <td colspan="6" class="p-12 text-center text-muted-foreground">
                                    <i class="fas fa-hand-holding-usd text-4xl mb-3 opacity-20"></i>
                                    <p class="font-medium">Belum ada pengeluaran dicatat.</p>
                                    <p class="text-xs text-muted-foreground mt-1">Gunakan tombol "Catat Pengeluaran" di atas untuk menambahkan data.</p>
                                </td>
                            </tr>
                            <tr v-for="exp in expenses" :key="exp.id" class="hover:bg-muted/30 transition">
                                <!-- Date -->
                                <td class="p-4 pl-6 text-muted-foreground font-mono text-xs">
                                    {{ formatDate(exp.date) }}
                                </td>
                                <!-- Name -->
                                <td class="p-4 font-semibold text-foreground">
                                    {{ exp.name }}
                                </td>
                                <!-- Category -->
                                <td class="p-4">
                                    <Badge 
                                        variant="outline" 
                                        class="capitalize px-2.5 py-0.5 rounded-full text-[11px] font-semibold border"
                                        :class="{
                                            'bg-blue-50/5 text-blue-600 dark:text-blue-400 border-blue-200/50': exp.category === 'operasional',
                                            'bg-emerald-50/5 text-emerald-600 dark:text-emerald-400 border-emerald-200/50': exp.category === 'stok',
                                            'bg-purple-50/5 text-purple-600 dark:text-purple-400 border-purple-200/50': exp.category === 'lainnya'
                                        }"
                                    >
                                        {{ exp.category }}
                                    </Badge>
                                </td>
                                <!-- Note -->
                                <td class="p-4 text-muted-foreground text-xs italic max-w-[200px] truncate" :title="exp.note">
                                    {{ exp.note || '-' }}
                                </td>
                                <!-- Amount -->
                                <td class="p-4 text-right font-bold text-red-600 dark:text-red-400">
                                    Rp {{ formatRupiah(exp.amount) }}
                                </td>
                                <!-- Actions -->
                                <td class="p-4 text-right pr-6">
                                    <Button @click="deleteExpense(exp.id)" variant="ghost" size="sm" class="h-8 rounded-lg text-red-650 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/20">
                                        <i class="fas fa-trash-alt mr-1"></i> Hapus
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- DIALOG RECORD FORM -->
        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent class="sm:max-w-[480px] rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-wallet text-red-500"></i>
                        Catat Biaya Pengeluaran Baru
                    </DialogTitle>
                    <DialogDescription>
                        Isi form di bawah ini untuk mencatat pengeluaran operasional toko mandiri Anda secara transparan.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4 my-2">
                    <!-- Date -->
                    <div class="space-y-1.5">
                        <Label for="exp-date">Tanggal Pengeluaran</Label>
                        <Input id="exp-date" type="date" v-model="form.date" required class="bg-background border-border text-foreground" />
                    </div>

                    <!-- Name -->
                    <div class="space-y-1.5">
                        <Label for="exp-name">Nama Keperluan / Detail</Label>
                        <Input id="exp-name" type="text" v-model="form.name" placeholder="Misal: Kertas HVS 1 Rim, Bayar WiFi, token listrik" required class="bg-background border-border text-foreground" />
                    </div>

                    <!-- Amount & Category Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Amount -->
                        <div class="space-y-1.5">
                            <Label for="exp-amount">Nominal Biaya (Rp)</Label>
                            <Input id="exp-amount" type="number" v-model="form.amount" placeholder="0" min="0" required class="bg-background border-border text-foreground" />
                        </div>
                        <!-- Category -->
                        <div class="space-y-1.5">
                            <Label for="exp-cat">Kategori Anggaran</Label>
                            <select id="exp-cat" v-model="form.category" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 text-foreground">
                                <option value="operasional">Operasional</option>
                                <option value="stok">Kulakan Stok</option>
                                <option value="lainnya">Lain-lain</option>
                            </select>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="space-y-1.5">
                        <Label for="exp-note">Catatan Tambahan (Opsional)</Label>
                        <textarea id="exp-note" v-model="form.note" rows="3" placeholder="Tambahkan catatan khusus..." class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-foreground dark:bg-input/30 border-border"></textarea>
                    </div>

                    <DialogFooter class="pt-4 border-t border-border gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary" class="rounded-xl">Batal</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="form.processing" class="rounded-xl font-bold bg-red-600 hover:bg-red-700 text-white shadow-sm">
                            <i class="fas fa-check mr-1.5"></i> Simpan Catatan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
