<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
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
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Data Customer', href: '/customers' },
        ],
    },
});

const props = defineProps({
    customers: Array,
});

// Search query
const searchQuery = ref('');

// Filter customers by search query
const filteredCustomers = computed(() => {
    if (!searchQuery.value) return props.customers;
    return props.customers.filter(c =>
        c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        (c.customer_type && c.customer_type.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
        (c.phone && c.phone.includes(searchQuery.value)) ||
        (c.address && c.address.toLowerCase().includes(searchQuery.value.toLowerCase()))
    );
});

// Form state
const formOpen = ref(false);
const isEditing = ref(false);
const selectedCustomerId = ref(null);
const historyOpen = ref(false);
const selectedHistoryCustomer = ref(null);

const form = useForm({
    name: '',
    customer_type: 'general',
    phone: '',
    address: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
};

const openEditModal = (customer) => {
    isEditing.value = true;
    selectedCustomerId.value = customer.id;
    form.name = customer.name;
    form.customer_type = customer.customer_type || 'general';
    form.phone = customer.phone || '';
    form.address = customer.address || '';
    form.clearErrors();
    formOpen.value = true;
};

const handleSubmit = () => {
    if (isEditing.value) {
        form.put(`/customers/${selectedCustomerId.value}`, {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            }
        });
    } else {
        form.post('/customers', {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            }
        });
    }
};

const deleteCustomer = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus data customer ini?')) {
        router.delete(`/customers/${id}`);
    }
};

const openHistoryModal = (customer) => {
    selectedHistoryCustomer.value = customer;
    historyOpen.value = true;
};

const selectedTransactions = computed(() => selectedHistoryCustomer.value?.transactions || []);
const selectedTotalSpent = computed(() => selectedTransactions.value.reduce((sum, transaction) => sum + toNumber(transaction.total_price), 0));

const toNumber = (value) => Number.parseFloat(value ?? 0) || 0;
const formatRupiah = (value) => new Intl.NumberFormat('id-ID').format(toNumber(value));

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const customerTypeLabel = (type) => ({
    general: 'Umum',
    token: 'Token Listrik',
    operator: 'Nomor Operator',
}[type] || 'Umum');
</script>

<template>
    <Head title="Data Customer" />

    <div class="flex flex-col gap-6 p-4 md:p-6 pb-8 font-inter">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Data Customer</h1>
                <p class="text-sm text-muted-foreground">Kelola direktori kontak customer Anda untuk keperluan pencatatan transaksi dan cetak nota/invoice.</p>
            </div>
            <Button @click="openCreateModal" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl px-4 py-2 flex items-center gap-2">
                <i class="fas fa-plus text-xs"></i>
                Tambah Customer
            </Button>
        </div>

        <!-- Filter & Search -->
        <div class="flex items-center gap-3 w-full sm:w-80">
            <div class="relative w-full">
                <i class="fas fa-search absolute left-3.5 top-3 text-muted-foreground text-sm"></i>
                <Input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="Cari customer..." 
                    class="pl-10 rounded-xl bg-card border-border text-foreground"
                />
            </div>
        </div>

        <!-- Customer List Table -->
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <th class="p-4 pl-6">Nama Lengkap</th>
                            <th class="p-4">Tipe Customer</th>
                            <th class="p-4">Nomor HP</th>
                            <th class="p-4">Alamat Rumah</th>
                            <th class="p-4 text-right">Total Belanja</th>
                            <th class="p-4">Tanggal Input</th>
                            <th class="p-4 text-right pr-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredCustomers.length === 0">
                            <td colspan="7" class="p-8 text-center text-muted-foreground">
                                <i class="fas fa-users-slash text-3xl mb-2 opacity-30"></i>
                                <p>Tidak ada data customer ditemukan.</p>
                            </td>
                        </tr>
                        <tr v-for="c in filteredCustomers" :key="c.id" class="hover:bg-muted/30 transition">
                            <td class="p-4 pl-6 font-semibold text-foreground">
                                {{ c.name }}
                            </td>
                            <td class="p-4">
                                <span class="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                    {{ customerTypeLabel(c.customer_type) }}
                                </span>
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ c.phone || '-' }}
                            </td>
                            <td class="p-4 text-muted-foreground italic max-w-xs truncate" :title="c.address">
                                {{ c.address || '-' }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="font-bold text-foreground font-mono">Rp {{ formatRupiah(c.total_spent) }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ c.transactions_count || 0 }} transaksi</div>
                            </td>
                            <td class="p-4 text-muted-foreground font-mono text-xs">
                                {{ formatDate(c.created_at) }}
                            </td>
                            <td class="p-4 text-right pr-6 space-x-2">
                                <Button @click="openHistoryModal(c)" variant="ghost" size="icon-sm" title="Riwayat pesanan" aria-label="Riwayat pesanan" class="text-emerald-600 hover:text-emerald-900 dark:text-emerald-400 dark:hover:text-emerald-300">
                                    <i class="fas fa-receipt"></i>
                                </Button>
                                <Button @click="openEditModal(c)" variant="ghost" size="icon-sm" title="Edit customer" aria-label="Edit customer" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    <i class="fas fa-edit"></i>
                                </Button>
                                <Button @click="deleteCustomer(c.id)" variant="ghost" size="icon-sm" title="Hapus customer" aria-label="Hapus customer" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                    <i class="fas fa-trash-alt"></i>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DIALOG FORM (Tambah & Edit Customer) -->
        <Dialog :open="formOpen" @update:open="formOpen = $event">
            <DialogContent class="sm:max-w-[425px] rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Data Customer' : 'Tambah Customer Baru' }}</DialogTitle>
                    <DialogDescription>
                        Isi form di bawah ini untuk mencatat identitas customer baru Anda.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSubmit" class="space-y-4 py-2">
                    <!-- Nama Customer -->
                    <div class="space-y-2">
                        <Label for="cust-name">Nama Lengkap</Label>
                        <Input 
                            id="cust-name" 
                            type="text" 
                            v-model="form.name" 
                            placeholder="Contoh: Budi Santoso"
                            class="rounded-xl border-border bg-background text-foreground"
                            required
                        />
                        <p v-if="form.errors.name" class="text-xs text-red-500 font-medium">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="cust-type">Tipe Customer</Label>
                        <select id="cust-type" v-model="form.customer_type" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground">
                            <option value="general">Umum</option>
                            <option value="token">Token Listrik</option>
                            <option value="operator">Nomor Operator / Pulsa</option>
                        </select>
                        <p v-if="form.errors.customer_type" class="text-xs text-red-500 font-medium">{{ form.errors.customer_type }}</p>
                    </div>

                    <!-- Nomor HP -->
                    <div class="space-y-2">
                        <Label for="cust-phone">Nomor Telepon/HP</Label>
                        <Input 
                            id="cust-phone" 
                            type="text" 
                            v-model="form.phone" 
                            placeholder="Contoh: 0812345678"
                            class="rounded-xl border-border bg-background text-foreground"
                        />
                    </div>

                    <!-- Alamat -->
                    <div class="space-y-2">
                        <Label for="cust-address">Alamat Lengkap</Label>
                        <textarea 
                            id="cust-address" 
                            v-model="form.address" 
                            rows="3"
                            placeholder="Tulis alamat rumah..."
                            class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-foreground dark:bg-input/30 border-border"
                        ></textarea>
                    </div>

                    <DialogFooter class="pt-4 gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary" class="rounded-xl">Batal</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- DIALOG RIWAYAT PESANAN -->
        <Dialog :open="historyOpen" @update:open="historyOpen = $event">
            <DialogContent class="sm:max-w-[760px] rounded-2xl bg-card border-border text-foreground overflow-y-auto max-h-[85vh]">
                <DialogHeader v-if="selectedHistoryCustomer">
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-receipt text-emerald-500"></i>
                        Riwayat Pesanan {{ selectedHistoryCustomer.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Total belanja Rp {{ formatRupiah(selectedTotalSpent) }} dari {{ selectedTransactions.length }} transaksi.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedHistoryCustomer" class="space-y-4 py-2">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-border bg-muted/20 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Total Belanja</p>
                            <p class="mt-1 text-lg font-black text-foreground">Rp {{ formatRupiah(selectedTotalSpent) }}</p>
                        </div>
                        <div class="rounded-2xl border border-border bg-muted/20 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Jumlah Transaksi</p>
                            <p class="mt-1 text-lg font-black text-foreground">{{ selectedTransactions.length }}</p>
                        </div>
                        <div class="rounded-2xl border border-border bg-muted/20 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Kontak</p>
                            <p class="mt-1 text-sm font-bold text-foreground">{{ selectedHistoryCustomer.phone || '-' }}</p>
                        </div>
                    </div>

                    <div v-if="selectedTransactions.length === 0" class="rounded-2xl border border-dashed border-border p-8 text-center text-muted-foreground">
                        <i class="fas fa-receipt text-3xl opacity-25"></i>
                        <p class="mt-2 font-medium">Belum ada riwayat pesanan untuk customer ini.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="transaction in selectedTransactions" :key="transaction.id" class="rounded-2xl border border-border bg-background overflow-hidden">
                            <div class="flex flex-col gap-2 border-b border-border bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-mono text-sm font-black text-foreground">{{ transaction.invoice_number }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatDateTime(transaction.created_at) }}</p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p class="text-sm font-black text-foreground">Rp {{ formatRupiah(transaction.total_price) }}</p>
                                    <p class="text-[11px] capitalize text-muted-foreground">{{ transaction.payment_method }} &bull; {{ transaction.status_bayar || 'lunas' }}</p>
                                </div>
                            </div>

                            <div class="divide-y divide-border">
                                <div v-for="item in transaction.items" :key="item.id" class="flex items-start justify-between gap-4 p-4 text-sm">
                                    <div class="min-w-0">
                                        <p class="font-bold text-foreground">{{ item.item_name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ parseFloat(item.quantity) }} {{ item.unit || 'pcs' }} &times; Rp {{ formatRupiah(item.selling_price) }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="font-bold text-foreground">Rp {{ formatRupiah(item.subtotal_price) }}</p>
                                        <p class="text-[11px] text-muted-foreground">Modal Rp {{ formatRupiah(item.subtotal_base) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="rounded-xl">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
