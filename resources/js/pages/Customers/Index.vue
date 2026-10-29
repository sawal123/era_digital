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
    duplicateGroups: {
        type: Array,
        default: () => [],
    },
});

// Search query
const searchQuery = ref('');
const sortOption = ref('name_asc');

// Filter and sort customers by the selected toolbar options
const filteredCustomers = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    const rows = props.customers.filter((customer) => {
        if (!query) return true;

        return [
            customer.name,
            customer.customer_type,
            customer.phone,
            customer.address,
        ].some((value) =>
            String(value || '')
                .toLowerCase()
                .includes(query),
        );
    });

    return [...rows].sort((first, second) => {
        const nameFallback = String(first.name || '').localeCompare(
            String(second.name || ''),
            'id',
        );

        switch (sortOption.value) {
            case 'transactions_desc':
                return (
                    Number(second.transactions_count || 0) -
                        Number(first.transactions_count || 0) || nameFallback
                );
            case 'total_spent_desc':
                return (
                    Number(second.total_spent || 0) -
                        Number(first.total_spent || 0) || nameFallback
                );
            case 'total_profit_desc':
                return (
                    Number(second.total_profit || 0) -
                        Number(first.total_profit || 0) || nameFallback
                );
            default:
                return nameFallback;
        }
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
const accountsOpen = ref(false);
const selectedAccountCustomer = ref(null);
const mergeOpen = ref(false);
const selectedMergeGroup = ref(null);
const mergeMasterId = ref(null);

const accountForm = useForm({
    type: 'phone',
    account_number: '',
    account_name: '',
});
const editingAccountId = ref(null);

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

const openAccountsModal = (customer) => {
    selectedAccountCustomer.value = customer;
    editingAccountId.value = null;
    accountForm.reset();
    accountForm.clearErrors();
    accountsOpen.value = true;
};

const openCreateAccount = () => {
    editingAccountId.value = null;
    accountForm.reset();
    accountForm.type = 'phone';
    accountForm.clearErrors();
};

const openEditAccount = (account) => {
    editingAccountId.value = account.id;
    accountForm.type = account.type;
    accountForm.account_number = account.account_number;
    accountForm.account_name = account.account_name;
    accountForm.clearErrors();
};

const submitAccount = () => {
    if (!selectedAccountCustomer.value) return;

    const url = editingAccountId.value
        ? `/customers/${selectedAccountCustomer.value.id}/digital-accounts/${editingAccountId.value}`
        : `/customers/${selectedAccountCustomer.value.id}/digital-accounts`;
    const method = editingAccountId.value ? 'put' : 'post';

    accountForm[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            editingAccountId.value = null;
            accountForm.reset();
            accountForm.type = 'phone';
            accountsOpen.value = false;
        },
    });
};

const deleteDigitalAccount = (account) => {
    if (
        !selectedAccountCustomer.value ||
        !window.confirm(`Hapus akun ${account.account_number}?`)
    )
        return;

    router.delete(
        `/customers/${selectedAccountCustomer.value.id}/digital-accounts/${account.id}`,
        {
            preserveScroll: true,
        },
    );
};

const openMergeModal = (group) => {
    selectedMergeGroup.value = group;
    mergeMasterId.value = group.customers[0]?.id || null;
    mergeOpen.value = true;
};

const mergeSources = computed(
    () =>
        selectedMergeGroup.value?.customers.filter(
            (customer) => customer.id !== mergeMasterId.value,
        ) || [],
);

const submitMerge = () => {
    if (!mergeMasterId.value || mergeSources.value.length === 0) return;

    router.post(
        `/customers/${mergeMasterId.value}/merge`,
        {
            source_customer_ids: mergeSources.value.map(
                (customer) => customer.id,
            ),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                mergeOpen.value = false;
                selectedMergeGroup.value = null;
            },
        },
    );
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
    if (deleteProcessing.value) return;
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

        <!-- Filter & Search -->
        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:max-w-sm">
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
            <select
                v-model="sortOption"
                aria-label="Urutkan customer"
                class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm font-semibold text-foreground transition outline-none focus:ring-2 focus:ring-indigo-500/40 sm:w-[240px]"
            >
                <option value="name_asc">Nama A-Z (Default)</option>
                <option value="transactions_desc">Transaksi Terbanyak</option>
                <option value="total_spent_desc">
                    Total Belanja Tertinggi
                </option>
                <option value="total_profit_desc">
                    Total Keuntungan Tertinggi
                </option>
            </select>
        </div>

        <!-- DUPLICATE GROUPS -->
        <div
            v-if="duplicateGroups.length > 0"
            class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 shadow-sm"
        >
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="font-bold text-amber-700 dark:text-amber-300">
                        Perlu Review Customer Duplikat
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Nama yang sama dikelompokkan sebagai kandidat. Sistem
                        tidak menggabungkan otomatis.
                    </p>
                </div>
                <span
                    class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-700 dark:text-amber-300"
                    >{{ duplicateGroups.length }} grup</span
                >
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <div
                    v-for="group in duplicateGroups"
                    :key="group.name_key"
                    class="rounded-xl border border-border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-foreground">
                                {{ group.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ group.customers.length }} data customer
                                dengan nama serupa
                            </p>
                        </div>
                        <Button
                            size="sm"
                            class="rounded-lg bg-amber-600 text-white hover:bg-amber-700"
                            @click="openMergeModal(group)"
                        >
                            <i class="fas fa-code-merge mr-1.5 text-xs"></i>
                            Review / Gabungkan
                        </Button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="customer in group.customers"
                            :key="customer.id"
                            class="rounded-lg bg-muted/30 px-3 py-2 text-xs"
                        >
                            <div class="flex justify-between gap-3">
                                <span class="font-semibold text-foreground"
                                    >#{{ customer.id }}
                                    {{ customer.name }}</span
                                >
                                <span class="text-muted-foreground"
                                    >{{
                                        customer.transactions_count || 0
                                    }}
                                    transaksi</span
                                >
                            </div>
                            <p class="mt-1 text-muted-foreground">
                                {{ customer.phone || 'Tanpa kontak' }} ·
                                {{ customer.digital_accounts?.length || 0 }}
                                akun digital
                            </p>
                        </div>
                    </div>
                </div>
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
                            <th class="p-4">Akun Digital</th>
                            <th class="p-4 text-right">Total Belanja</th>
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
                            <td class="p-4">
                                <div
                                    v-if="c.digital_accounts?.length"
                                    class="flex max-w-xs flex-wrap gap-1.5"
                                >
                                    <span
                                        v-for="account in c.digital_accounts"
                                        :key="account.id"
                                        class="rounded-full border border-blue-500/20 bg-blue-500/10 px-2 py-1 text-[10px] font-semibold text-blue-700 dark:text-blue-300"
                                    >
                                        {{
                                            account.type === 'token'
                                                ? 'Token'
                                                : 'HP'
                                        }}: {{ account.account_number }}
                                    </span>
                                </div>
                                <span v-else class="text-muted-foreground"
                                    >-</span
                                >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="mt-1 h-7 rounded-lg px-2 text-[11px] text-indigo-600 dark:text-indigo-400"
                                    @click="openAccountsModal(c)"
                                >
                                    <i class="fas fa-address-book mr-1"></i>
                                    Kelola akun
                                </Button>
                            </td>
                            <td class="p-4 text-right">
                                <div
                                    class="font-mono font-bold text-foreground"
                                >
                                    Rp {{ formatRupiah(c.total_spent) }}
                                </div>
                                <div class="text-[11px] text-muted-foreground">
                                    {{ c.transactions_count || 0 }} transaksi
                                </div>
                            </td>
                            <td
                                class="p-4 font-mono text-xs text-muted-foreground"
                            >
                                {{ formatDate(c.created_at) }}
                            </td>
                            <td class="p-4 pr-6">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        @click="openHistoryModal(c)"
                                        variant="ghost"
                                        size="icon-sm"
                                        :title="`Lihat riwayat transaksi ${c.name}`"
                                        :aria-label="`Lihat riwayat transaksi ${c.name}`"
                                        class="h-8 w-8 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-0 text-emerald-600 transition hover:bg-emerald-500/15 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                                    >
                                        <i class="fas fa-receipt text-xs"></i>
                                        <span class="sr-only"
                                            >Lihat riwayat transaksi
                                            {{ c.name }}</span
                                        >
                                    </Button>
                                    <Button
                                        @click="openEditModal(c)"
                                        variant="ghost"
                                        size="icon-sm"
                                        :title="`Edit customer ${c.name}`"
                                        :aria-label="`Edit customer ${c.name}`"
                                        class="h-8 w-8 rounded-lg border border-indigo-500/20 bg-indigo-500/5 p-0 text-indigo-600 transition hover:bg-indigo-500/15 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        <i class="fas fa-pen text-xs"></i>
                                        <span class="sr-only"
                                            >Edit customer {{ c.name }}</span
                                        >
                                    </Button>
                                    <Button
                                        @click="openDeleteConfirm(c)"
                                        variant="ghost"
                                        size="icon-sm"
                                        :disabled="
                                            (c.transactions_count || 0) > 0
                                        "
                                        :title="
                                            (c.transactions_count || 0) > 0
                                                ? 'Customer dengan transaksi tidak dapat dihapus'
                                                : `Hapus customer ${c.name}`
                                        "
                                        :aria-label="`Hapus customer ${c.name}`"
                                        class="h-8 w-8 rounded-lg border border-red-500/20 bg-red-500/5 p-0 text-red-600 transition hover:bg-red-500/15 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        <i class="fas fa-trash text-xs"></i>
                                        <span class="sr-only"
                                            >Hapus customer {{ c.name }}</span
                                        >
                                    </Button>
                                </div>
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
                        {{ formatRupiah(selectedTotalSpent) }} dari
                        {{ selectedTransactions.length }} transaksi.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedHistoryCustomer" class="space-y-4 py-2">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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

        <!-- DIALOG KELOLA AKUN DIGITAL -->
        <Dialog :open="accountsOpen" @update:open="accountsOpen = $event">
            <DialogContent
                class="max-h-[85vh] overflow-y-auto rounded-2xl border-border bg-card text-foreground sm:max-w-[620px]"
            >
                <DialogHeader v-if="selectedAccountCustomer">
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-address-book text-indigo-500"></i>
                        Akun Digital {{ selectedAccountCustomer.name }}
                    </DialogTitle>
                    <DialogDescription
                        >Token listrik dan nomor HP disimpan sebagai akun milik
                        customer ini.</DialogDescription
                    >
                </DialogHeader>

                <div v-if="selectedAccountCustomer" class="space-y-4 py-2">
                    <div class="space-y-2">
                        <div
                            v-if="
                                !selectedAccountCustomer.digital_accounts
                                    ?.length
                            "
                            class="rounded-xl border border-dashed border-border p-5 text-center text-sm text-muted-foreground"
                        >
                            Belum ada akun digital.
                        </div>
                        <div
                            v-for="account in selectedAccountCustomer.digital_accounts"
                            :key="account.id"
                            class="flex items-center justify-between gap-3 rounded-xl border border-border bg-background p-3"
                        >
                            <div class="min-w-0">
                                <p class="font-bold text-foreground">
                                    {{
                                        account.type === 'token'
                                            ? 'Token Listrik'
                                            : 'Nomor HP'
                                    }}
                                </p>
                                <p
                                    class="font-mono text-sm text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ account.account_number }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ account.account_name }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-indigo-600 dark:text-indigo-400"
                                    title="Edit akun"
                                    @click="openEditAccount(account)"
                                >
                                    <i class="fas fa-edit"></i>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-red-600 dark:text-red-400"
                                    title="Hapus akun"
                                    @click="deleteDigitalAccount(account)"
                                >
                                    <i class="fas fa-trash"></i>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <form
                        class="space-y-3 rounded-xl border border-border bg-muted/20 p-4"
                        @submit.prevent="submitAccount"
                    >
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-foreground">
                                {{
                                    editingAccountId
                                        ? 'Edit Akun Digital'
                                        : 'Tambah Akun Digital'
                                }}
                            </p>
                            <Button
                                v-if="editingAccountId"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="rounded-lg"
                                @click="openCreateAccount"
                                >Batal edit</Button
                            >
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label>Jenis Akun</Label>
                                <select
                                    v-model="accountForm.type"
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="phone">Nomor HP</option>
                                    <option value="token">Token Listrik</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label>Nomor</Label>
                                <Input
                                    v-model="accountForm.account_number"
                                    placeholder="Nomor meter / HP"
                                    required
                                />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Nama Pemilik / Keterangan</Label>
                            <Input
                                v-model="accountForm.account_name"
                                placeholder="Contoh: Budi"
                                required
                            />
                        </div>
                        <p
                            v-if="
                                accountForm.errors.account_number ||
                                accountForm.errors.account_name ||
                                accountForm.errors.type
                            "
                            class="text-xs text-red-500"
                        >
                            {{
                                accountForm.errors.account_number ||
                                accountForm.errors.account_name ||
                                accountForm.errors.type
                            }}
                        </p>
                        <Button
                            type="submit"
                            :disabled="accountForm.processing"
                            class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            {{
                                accountForm.processing
                                    ? 'Menyimpan...'
                                    : editingAccountId
                                      ? 'Simpan Perubahan'
                                      : 'Tambah Akun'
                            }}
                        </Button>
                    </form>
                </div>

                <DialogFooter>
                    <DialogClose as-child
                        ><Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            >Tutup</Button
                        ></DialogClose
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- DIALOG GABUNG CUSTOMER -->
        <Dialog :open="mergeOpen" @update:open="mergeOpen = $event">
            <DialogContent
                class="max-h-[85vh] overflow-y-auto rounded-2xl border-border bg-card text-foreground sm:max-w-[680px]"
            >
                <DialogHeader v-if="selectedMergeGroup">
                    <DialogTitle class="flex items-center gap-2">
                        <i class="fas fa-code-merge text-amber-500"></i>
                        Gabungkan Customer {{ selectedMergeGroup.name }}
                    </DialogTitle>
                    <DialogDescription
                        >Pilih satu data utama. Data lain akan dipindahkan
                        transaksinya dan akun digitalnya ke data
                        utama.</DialogDescription
                    >
                </DialogHeader>

                <div v-if="selectedMergeGroup" class="space-y-3 py-2">
                    <label
                        v-for="customer in selectedMergeGroup.customers"
                        :key="customer.id"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-background p-4 hover:bg-muted/30"
                    >
                        <input
                            v-model="mergeMasterId"
                            type="radio"
                            :value="customer.id"
                            class="mt-1"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span class="font-bold text-foreground"
                                    >#{{ customer.id }}
                                    {{ customer.name }}</span
                                >
                                <span class="text-xs text-muted-foreground"
                                    >{{
                                        customer.transactions_count || 0
                                    }}
                                    transaksi</span
                                >
                            </span>
                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                                >{{ customer.phone || 'Tanpa kontak' }} ·
                                {{ customer.digital_accounts?.length || 0 }}
                                akun digital</span
                            >
                            <span
                                v-if="customer.digital_accounts?.length"
                                class="mt-2 flex flex-wrap gap-1"
                            >
                                <span
                                    v-for="account in customer.digital_accounts"
                                    :key="account.id"
                                    class="rounded-full bg-blue-500/10 px-2 py-1 text-[10px] text-blue-700 dark:text-blue-300"
                                    >{{ account.account_number }}</span
                                >
                            </span>
                        </span>
                    </label>
                    <p
                        class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300"
                    >
                        Customer utama tidak dihapus. Pastikan pilihan benar
                        sebelum menggabungkan data production.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child
                        ><Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            >Batal</Button
                        ></DialogClose
                    >
                    <Button
                        type="button"
                        :disabled="mergeSources.length === 0"
                        class="rounded-xl bg-amber-600 text-white hover:bg-amber-700"
                        @click="submitMerge"
                        >Gabungkan Data</Button
                    >
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
