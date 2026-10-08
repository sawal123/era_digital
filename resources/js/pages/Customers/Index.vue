<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
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

// Search & Sort state
const searchQuery = ref('');
const sortBy = ref('name_asc');

// Filter & Sort customers
const filteredCustomers = computed(() => {
    let list = props.customers || [];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        list = list.filter(
            (c) =>
                (c.name && c.name.toLowerCase().includes(query)) ||
                (c.customer_type &&
                    c.customer_type.toLowerCase().includes(query)) ||
                (c.phone && c.phone.includes(query)) ||
                (c.address && c.address.toLowerCase().includes(query)),
        );
    }

    return list.slice().sort((a, b) => {
        if (sortBy.value === 'transactions_desc') {
            const countA = Number(a.transactions_count) || 0;
            const countB = Number(b.transactions_count) || 0;

            if (countB !== countA) {
                return countB - countA;
            }

            const spentA = Number(a.total_spent) || 0;
            const spentB = Number(b.total_spent) || 0;

            if (spentB !== spentA) {
                return spentB - spentA;
            }

            return (a.name || '').localeCompare(b.name || '', 'id');
        }

        if (sortBy.value === 'spent_desc') {
            const spentA = Number(a.total_spent) || 0;
            const spentB = Number(b.total_spent) || 0;

            if (spentB !== spentA) {
                return spentB - spentA;
            }

            const countA = Number(a.transactions_count) || 0;
            const countB = Number(b.transactions_count) || 0;

            if (countB !== countA) {
                return countB - countA;
            }

            return (a.name || '').localeCompare(b.name || '', 'id');
        }

        if (sortBy.value === 'profit_desc') {
            const profitA = Number(a.total_profit) || 0;
            const profitB = Number(b.total_profit) || 0;

            if (profitB !== profitA) {
                return profitB - profitA;
            }

            const spentA = Number(a.total_spent) || 0;
            const spentB = Number(b.total_spent) || 0;

            if (spentB !== spentA) {
                return spentB - spentA;
            }

            return (a.name || '').localeCompare(b.name || '', 'id');
        }

        // Default: name_asc
        return (a.name || '').localeCompare(b.name || '', 'id');
    });
});

// Form state
const formOpen = ref(false);
const isEditing = ref(false);
const selectedCustomerId = ref(null);
const historyOpen = ref(false);
const selectedHistoryCustomer = ref(null);
const deleteConfirmOpen = ref(false);
const deleteTarget = ref(null);
const deleteProcessing = ref(false);
const deleteError = ref('');

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
            },
        });
    } else {
        form.post('/customers', {
            onSuccess: () => {
                formOpen.value = false;
                form.reset();
            },
        });
    }
};

const openDeleteConfirm = (customer) => {
    deleteTarget.value = customer;
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

const confirmDeleteCustomer = () => {
    if (
        !deleteTarget.value ||
        (deleteTarget.value.transactions_count || 0) > 0
    ) {
        return;
    }

    deleteProcessing.value = true;
    router.delete(`/customers/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
            deleteError.value = '';
        },
        onError: (errors) => {
            deleteError.value = errors.error || 'Customer gagal dihapus.';
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};

const openHistoryModal = (customer) => {
    selectedHistoryCustomer.value = customer;
    historyOpen.value = true;
};

const selectedTransactions = computed(
    () => selectedHistoryCustomer.value?.transactions || [],
);
const selectedTotalSpent = computed(() =>
    selectedTransactions.value.reduce(
        (sum, transaction) => sum + toNumber(transaction.total_price),
        0,
    ),
);
const selectedTotalProfit = computed(() =>
    selectedTransactions.value.reduce(
        (sum, transaction) => sum + toNumber(transaction.total_profit),
        0,
    ),
);

const toNumber = (value) => Number.parseFloat(value ?? 0) || 0;
const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID').format(toNumber(value));

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

const customerTypeLabel = (type) =>
    ({
        general: 'Umum',
        token: 'Token Listrik',
        operator: 'Nomor Operator',
    })[type] || 'Umum';
</script>

<template>
    <Head title="Data Customer" />

    <div class="font-inter flex flex-col gap-6 p-4 pb-8 md:p-6">
        <!-- Header -->
        <div
            class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Data Customer
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola direktori kontak customer Anda untuk keperluan
                    pencatatan transaksi dan cetak nota/invoice.
                </p>
            </div>
            <Button
                @click="openCreateModal"
                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700"
            >
                <i class="fas fa-plus text-xs"></i>
                Tambah Customer
            </Button>
        </div>

        <!-- Filter & Search & Sort -->
        <div
            class="flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-center"
        >
            <div class="relative w-full sm:w-80">
                <i
                    class="fas fa-search absolute top-3 left-3.5 text-sm text-muted-foreground"
                ></i>
                <Input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Cari customer..."
                    class="rounded-xl border-border bg-card pl-10 text-foreground"
                />
            </div>
            <div class="relative w-full sm:w-60">
                <select
                    id="customer-sort-select"
                    v-model="sortBy"
                    class="h-10 w-full rounded-xl border border-input bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="name_asc">Nama A–Z (Default)</option>
                    <option value="transactions_desc">
                        Transaksi Terbanyak
                    </option>
                    <option value="spent_desc">Total Belanja Tertinggi</option>
                    <option value="profit_desc">
                        Total Keuntungan Tertinggi
                    </option>
                </select>
            </div>
        </div>

        <!-- Customer List Table -->
        <div
            class="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="p-4 pl-6">Nama Lengkap</th>
                            <th class="p-4">Tipe Customer</th>
                            <th class="p-4">Nomor HP</th>
                            <th class="p-4">Alamat Rumah</th>
                            <th class="p-4 text-right">
                                Total Belanja & Profit
                            </th>
                            <th class="p-4">Tanggal Input</th>
                            <th class="p-4 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        <tr v-if="filteredCustomers.length === 0">
                            <td
                                colspan="7"
                                class="p-8 text-center text-muted-foreground"
                            >
                                <i
                                    class="fas fa-users-slash mb-2 text-3xl opacity-30"
                                ></i>
                                <p>Tidak ada data customer ditemukan.</p>
                            </td>
                        </tr>
                        <tr
                            v-for="c in filteredCustomers"
                            :key="c.id"
                            class="transition hover:bg-muted/30"
                        >
                            <td class="p-4 pl-6 font-semibold text-foreground">
                                {{ c.name }}
                            </td>
                            <td class="p-4">
                                <span
                                    class="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ customerTypeLabel(c.customer_type) }}
                                </span>
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ c.phone || '-' }}
                            </td>
                            <td
                                class="max-w-xs truncate p-4 text-muted-foreground italic"
                                :title="c.address"
                            >
                                {{ c.address || '-' }}
                            </td>
                            <td class="p-4 text-right">
                                <div
                                    class="font-mono font-bold text-foreground"
                                >
                                    Rp {{ formatRupiah(c.total_spent) }}
                                </div>
                                <div
                                    class="mt-0.5 font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    Profit: Rp
                                    {{ formatRupiah(c.total_profit) }}
                                </div>
                                <div
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    {{ c.transactions_count || 0 }} transaksi
                                </div>
                            </td>
                            <td
                                class="p-4 font-mono text-xs text-muted-foreground"
                            >
                                {{ formatDate(c.created_at) }}
                            </td>
                            <td class="space-x-2 p-4 pr-6 text-right">
                                <Button
                                    @click="openHistoryModal(c)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Riwayat pesanan"
                                    aria-label="Riwayat pesanan"
                                    class="text-emerald-600 hover:text-emerald-900 dark:text-emerald-400 dark:hover:text-emerald-300"
                                >
                                    <i class="fas fa-receipt"></i>
                                </Button>
                                <Button
                                    @click="openEditModal(c)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Edit customer"
                                    aria-label="Edit customer"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    <i class="fas fa-edit"></i>
                                </Button>
                                <Button
                                    @click="openDeleteConfirm(c)"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Hapus customer"
                                    aria-label="Hapus customer"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                >
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
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[425px]"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        isEditing
                            ? 'Edit Data Customer'
                            : 'Tambah Customer Baru'
                    }}</DialogTitle>
                    <DialogDescription>
                        Isi form di bawah ini untuk mencatat identitas customer
                        baru Anda.
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
                        <p
                            v-if="form.errors.name"
                            class="text-xs font-medium text-red-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="cust-type">Tipe Customer</Label>
                        <select
                            id="cust-type"
                            v-model="form.customer_type"
                            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="general">Umum</option>
                            <option value="token">Token Listrik</option>
                            <option value="operator">
                                Nomor Operator / Pulsa
                            </option>
                        </select>
                        <p
                            v-if="form.errors.customer_type"
                            class="text-xs font-medium text-red-500"
                        >
                            {{ form.errors.customer_type }}
                        </p>
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
                            class="flex min-h-[60px] w-full rounded-md border border-border border-input bg-transparent px-3 py-2 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                        ></textarea>
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

        <!-- DIALOG RIWAYAT PESANAN -->
        <Dialog :open="historyOpen" @update:open="historyOpen = $event">
            <DialogContent
                class="max-h-[85vh] overflow-y-auto rounded-2xl border-border bg-card text-foreground sm:max-w-[760px]"
            >
                <DialogHeader v-if="selectedHistoryCustomer">
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-receipt text-emerald-500"></i>
                        Riwayat Pesanan {{ selectedHistoryCustomer.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Total belanja Rp
                        {{ formatRupiah(selectedTotalSpent) }} &bull; Total
                        Keuntungan Rp
                        {{ formatRupiah(selectedTotalProfit) }} dari
                        {{ selectedTransactions.length }} transaksi.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedHistoryCustomer" class="space-y-4 py-2">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <div
                            class="rounded-2xl border border-border bg-muted/20 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Total Belanja
                            </p>
                            <p class="mt-1 text-lg font-black text-foreground">
                                Rp {{ formatRupiah(selectedTotalSpent) }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                            >
                                Total Keuntungan
                            </p>
                            <p
                                class="mt-1 text-lg font-black text-emerald-600 dark:text-emerald-400"
                            >
                                Rp {{ formatRupiah(selectedTotalProfit) }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-border bg-muted/20 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Jumlah Transaksi
                            </p>
                            <p class="mt-1 text-lg font-black text-foreground">
                                {{ selectedTransactions.length }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-border bg-muted/20 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Kontak
                            </p>
                            <p class="mt-1 text-sm font-bold text-foreground">
                                {{ selectedHistoryCustomer.phone || '-' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="selectedTransactions.length === 0"
                        class="rounded-2xl border border-dashed border-border p-8 text-center text-muted-foreground"
                    >
                        <i class="fas fa-receipt text-3xl opacity-25"></i>
                        <p class="mt-2 font-medium">
                            Belum ada riwayat pesanan untuk customer ini.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="transaction in selectedTransactions"
                            :key="transaction.id"
                            class="overflow-hidden rounded-2xl border border-border bg-background"
                        >
                            <div
                                class="flex flex-col gap-2 border-b border-border bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p
                                        class="font-mono text-sm font-black text-foreground"
                                    >
                                        {{ transaction.invoice_number }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            formatDateTime(
                                                transaction.created_at,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p
                                        class="text-sm font-black text-foreground"
                                    >
                                        Rp
                                        {{
                                            formatRupiah(
                                                transaction.total_price,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground capitalize"
                                    >
                                        {{ transaction.payment_method }} &bull;
                                        {{
                                            transaction.status_bayar || 'lunas'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="divide-y divide-border">
                                <div
                                    v-for="item in transaction.items"
                                    :key="item.id"
                                    class="flex items-start justify-between gap-4 p-4 text-sm"
                                >
                                    <div class="min-w-0">
                                        <p class="font-bold text-foreground">
                                            {{ item.item_name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ parseFloat(item.quantity) }}
                                            {{ item.unit || 'pcs' }} &times; Rp
                                            {{
                                                formatRupiah(item.selling_price)
                                            }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="font-bold text-foreground">
                                            Rp
                                            {{
                                                formatRupiah(
                                                    item.subtotal_price,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Modal Rp
                                            {{
                                                formatRupiah(item.subtotal_base)
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            >Tutup</Button
                        >
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG KONFIRMASI HAPUS CUSTOMER -->
        <Dialog
            :open="deleteConfirmOpen"
            @update:open="
                (open) =>
                    open ? (deleteConfirmOpen = true) : closeDeleteConfirm()
            "
        >
            <DialogContent
                class="rounded-2xl border-border bg-card text-foreground sm:max-w-[440px]"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-triangle-exclamation text-red-500"></i>
                        {{
                            (deleteTarget?.transactions_count || 0) > 0
                                ? 'Customer Tidak Bisa Dihapus'
                                : 'Hapus Customer?'
                        }}
                    </DialogTitle>
                    <DialogDescription
                        v-if="(deleteTarget?.transactions_count || 0) > 0"
                    >
                        {{ deleteTarget?.name }} sudah memiliki
                        {{ deleteTarget?.transactions_count || 0 }} transaksi.
                        Data customer tetap disimpan agar riwayat nota dan
                        laporan tidak rusak.
                    </DialogDescription>
                    <DialogDescription v-else>
                        Data customer {{ deleteTarget?.name }} akan dihapus
                        permanen dari daftar customer.
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
                    >
                        {{
                            (deleteTarget?.transactions_count || 0) > 0
                                ? 'Tutup'
                                : 'Batal'
                        }}
                    </Button>
                    <Button
                        v-if="(deleteTarget?.transactions_count || 0) === 0"
                        type="button"
                        :disabled="deleteProcessing"
                        class="rounded-xl bg-red-600 text-white hover:bg-red-700"
                        @click="confirmDeleteCustomer"
                    >
                        {{ deleteProcessing ? 'Menghapus...' : 'Ya, Hapus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
