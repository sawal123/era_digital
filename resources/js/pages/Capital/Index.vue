<script setup lang="ts">
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-nocheck
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
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
            { title: 'Ledger Modal', href: '/capital' },
        ],
    },
});

const props = defineProps({
    accounts: {
        type: Array,
        default: () => [],
    },
    entries: {
        type: Array,
        default: () => [],
    },
    digitalSummary: {
        type: Object,
        default: () => ({
            contributed_capital: 0,
            current_balance: 0,
            total_profit: 0,
            total_usage: 0,
            transaction_count: 0,
        }),
    },
});

const formOpen = ref(false);
const ledgerSearch = ref('');
const ledgerAccountFilter = ref('all');
const ledgerTypeFilter = ref('all');
const ledgerDirectionFilter = ref('all');
const ledgerDateFrom = ref('');
const ledgerDateTo = ref('');
const ledgerDateRangeInput = ref(null);
let ledgerDateRangePicker = null;
const ledgerPage = ref(1);
const ledgerPageSize = ref(10);
const form = useForm({
    capital_account_id: '',
    direction: 'credit',
    amount: '',
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
});

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID').format(Number(value || 0));
const entryTypeLabel = (entry) =>
    ({
        opening: 'Saldo awal',
        manual: 'Manual',
        restock: 'Restock',
        usage: 'Pemakaian',
        adjustment: 'Penyesuaian',
    })[entry.entry_type] || 'Ledger';

const entryDescription = (entry) => entry.description || entryTypeLabel(entry);
const entryDate = (entry) => String(entry.entry_date || '').slice(0, 10);
const isManualEntry = (entry) => entry.reference_type === 'manual';
const ledgerTypeOptions = [
    { value: 'opening', label: 'Saldo awal' },
    { value: 'manual', label: 'Manual' },
    { value: 'restock', label: 'Restock' },
    { value: 'usage', label: 'Pemakaian' },
    { value: 'adjustment', label: 'Penyesuaian' },
];
const filteredEntries = computed(() => {
    const query = ledgerSearch.value.trim().toLowerCase();

    return props.entries.filter((entry) => {
        const searchableText = [
            entry.account?.name,
            entry.description,
            entryTypeLabel(entry),
            entryDate(entry),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return (
            (!query || searchableText.includes(query)) &&
            (ledgerAccountFilter.value === 'all' ||
                String(entry.capital_account_id) ===
                    String(ledgerAccountFilter.value)) &&
            (ledgerTypeFilter.value === 'all' ||
                entry.entry_type === ledgerTypeFilter.value) &&
            (ledgerDirectionFilter.value === 'all' ||
                entry.direction === ledgerDirectionFilter.value) &&
            (!ledgerDateFrom.value ||
                entryDate(entry) >= ledgerDateFrom.value) &&
            (!ledgerDateTo.value || entryDate(entry) <= ledgerDateTo.value)
        );
    });
});
const ledgerTotalPages = computed(() =>
    Math.max(1, Math.ceil(filteredEntries.value.length / ledgerPageSize.value)),
);
const paginatedEntries = computed(() => {
    const start = (ledgerPage.value - 1) * ledgerPageSize.value;

    return filteredEntries.value.slice(start, start + ledgerPageSize.value);
});
const ledgerFirstItem = computed(() =>
    filteredEntries.value.length === 0
        ? 0
        : (ledgerPage.value - 1) * ledgerPageSize.value + 1,
);
const ledgerLastItem = computed(() =>
    Math.min(
        ledgerPage.value * ledgerPageSize.value,
        filteredEntries.value.length,
    ),
);
const hasLedgerFilters = computed(
    () =>
        ledgerSearch.value ||
        ledgerAccountFilter.value !== 'all' ||
        ledgerTypeFilter.value !== 'all' ||
        ledgerDirectionFilter.value !== 'all' ||
        ledgerDateFrom.value ||
        ledgerDateTo.value,
);
watch(
    [
        ledgerSearch,
        ledgerAccountFilter,
        ledgerTypeFilter,
        ledgerDirectionFilter,
        ledgerDateFrom,
        ledgerDateTo,
        ledgerPageSize,
    ],
    () => {
        ledgerPage.value = 1;
    },
);
watch(ledgerTotalPages, (totalPages) => {
    if (ledgerPage.value > totalPages) {
        ledgerPage.value = totalPages;
    }
});
const resetLedgerFilters = () => {
    ledgerSearch.value = '';
    ledgerAccountFilter.value = 'all';
    ledgerTypeFilter.value = 'all';
    ledgerDirectionFilter.value = 'all';
    ledgerDateRangePicker?.clear();
    ledgerDateFrom.value = '';
    ledgerDateTo.value = '';
    ledgerPage.value = 1;
};
const previousLedgerPage = () => {
    ledgerPage.value = Math.max(1, ledgerPage.value - 1);
};
const nextLedgerPage = () => {
    ledgerPage.value = Math.min(ledgerTotalPages.value, ledgerPage.value + 1);
};
const initLedgerDateRangePicker = () => {
    if (!ledgerDateRangeInput.value) {
        return;
    }

    ledgerDateRangePicker?.destroy();
    ledgerDateRangePicker = flatpickr(ledgerDateRangeInput.value, {
        mode: 'range',
        dateFormat: 'Y-m-d',
        onChange: (selectedDates, _dateStr, instance) => {
            if (selectedDates.length === 0) {
                ledgerDateFrom.value = '';
                ledgerDateTo.value = '';

                return;
            }

            const [startDate, endDate = startDate] = selectedDates.map((date) =>
                instance.formatDate(date, 'Y-m-d'),
            );
            ledgerDateFrom.value = startDate;
            ledgerDateTo.value = endDate;
        },
    });
};
onMounted(initLedgerDateRangePicker);
onBeforeUnmount(() => ledgerDateRangePicker?.destroy());
const inventoryAccounts = computed(() =>
    props.accounts.filter((account) => account.type !== 'digital'),
);
const selectedAccount = computed(() =>
    props.accounts.find(
        (account) => String(account.id) === String(form.capital_account_id),
    ),
);
const selectedAccountBalance = computed(() =>
    Math.max(0, Number(selectedAccount.value?.current_balance || 0)),
);

const openCreateModal = () => {
    form.reset();
    form.capital_account_id = props.accounts[0]?.id || '';
    form.direction = 'credit';
    form.entry_date = new Date().toISOString().slice(0, 10);
    formOpen.value = true;
};

const closeFormModal = (open) => {
    formOpen.value = open;

    if (!open) {
        form.reset();
    }
};

const tarikSemua = () => {
    if (form.direction !== 'debit' || selectedAccountBalance.value <= 0) {
        return;
    }

    form.amount = selectedAccountBalance.value.toFixed(2);
};

const submitForm = () => {
    form.post('/capital/entries', {
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    });
};

const deleteEntry = (entry) => {
    if (!isManualEntry(entry)) {
        return;
    }

    if (
        !window.confirm(
            'Hapus catatan modal manual ini? Saldo akun akan dikembalikan.',
        )
    ) {
        return;
    }

    form.delete(`/capital/entries/${entry.id}`);
};
</script>

<template>
    <Head title="Ledger Modal" />

    <div class="font-inter flex flex-col gap-6 p-4 pb-10 md:p-6">
        <div
            class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div>
                <h1 class="text-2xl font-black tracking-tight text-foreground">
                    Ledger Modal
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Modal disetor tetap terpisah dari saldo digital yang
                    diputar.
                </p>
            </div>
            <Button
                class="rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                @click="openCreateModal"
            >
                <i class="fas fa-plus mr-2 text-xs"></i>
                Tambah Catatan Modal
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card
                class="border-border/60 bg-gradient-to-br from-indigo-500/10 to-purple-500/5"
            >
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-xs tracking-wider text-muted-foreground uppercase"
                        >Modal Disetor</CardTitle
                    ></CardHeader
                >
                <CardContent
                    ><div
                        class="text-2xl font-black text-indigo-600 dark:text-indigo-400"
                    >
                        Rp
                        {{ formatRupiah(digitalSummary.contributed_capital) }}
                    </div></CardContent
                >
            </Card>
            <Card
                class="border-border/60 bg-gradient-to-br from-emerald-500/10 to-teal-500/5"
            >
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-xs tracking-wider text-muted-foreground uppercase"
                        >Saldo Digital Tersedia</CardTitle
                    ></CardHeader
                >
                <CardContent
                    ><div
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        Rp {{ formatRupiah(digitalSummary.current_balance) }}
                    </div></CardContent
                >
            </Card>
            <Card
                class="border-border/60 bg-gradient-to-br from-amber-500/10 to-orange-500/5"
            >
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-xs tracking-wider text-muted-foreground uppercase"
                        >Keuntungan Digital</CardTitle
                    ></CardHeader
                >
                <CardContent
                    ><div
                        class="text-2xl font-black text-amber-600 dark:text-amber-400"
                    >
                        Rp {{ formatRupiah(digitalSummary.total_profit) }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ digitalSummary.transaction_count }} transaksi
                        PPOB/digital
                    </p></CardContent
                >
            </Card>
            <Card
                class="border-border/60 bg-gradient-to-br from-rose-500/10 to-red-500/5"
            >
                <CardHeader class="pb-2"
                    ><CardTitle
                        class="text-xs tracking-wider text-muted-foreground uppercase"
                        >Total Saldo Terpakai</CardTitle
                    ></CardHeader
                >
                <CardContent
                    ><div
                        class="text-2xl font-black text-rose-600 dark:text-rose-400"
                    >
                        Rp {{ formatRupiah(digitalSummary.total_usage) }}
                    </div></CardContent
                >
            </Card>
        </div>

        <div v-if="inventoryAccounts.length" class="space-y-3">
            <div>
                <h2 class="text-base font-black text-foreground">
                    Modal Persediaan
                </h2>
                <p class="text-xs text-muted-foreground">
                    Nilai modal barang yang masih tersimpan di stok per
                    kategori.
                </p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="account in inventoryAccounts"
                    :key="account.id"
                    class="border-border/60 bg-card shadow-sm"
                >
                    <CardContent
                        class="flex items-start justify-between gap-4 p-5"
                    >
                        <div>
                            <p
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                {{
                                    account.type === 'digital'
                                        ? 'Saldo Digital'
                                        : 'Modal Persediaan'
                                }}
                            </p>
                            <h2 class="mt-1 text-lg font-black text-foreground">
                                {{ account.name }}
                            </h2>
                            <p
                                class="mt-2 text-2xl font-black"
                                :class="
                                    Number(account.current_balance) >= 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-red-600 dark:text-red-400'
                                "
                            >
                                Rp {{ formatRupiah(account.current_balance) }}
                            </p>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500"
                        >
                            <i
                                :class="
                                    account.type === 'digital'
                                        ? 'fas fa-mobile-screen-button'
                                        : 'fas fa-boxes-stacked'
                                "
                            ></i>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Card class="overflow-hidden border-border/60 bg-card shadow-sm">
            <CardHeader class="border-b border-border/60">
                <CardTitle class="text-base font-black"
                    >Riwayat Ledger Modal</CardTitle
                >
                <p class="text-xs text-muted-foreground">
                    Restock dan pemakaian penjualan tercatat otomatis. Catatan
                    manual digunakan untuk tambah atau tarik modal dan dapat
                    dihapus.
                </p>
            </CardHeader>
            <div
                class="flex flex-col gap-3 border-b border-border/60 bg-muted/10 p-4 md:flex-row md:flex-wrap"
            >
                <Input
                    v-model="ledgerSearch"
                    type="search"
                    placeholder="Cari keterangan, akun, atau tanggal..."
                    class="bg-background text-foreground md:min-w-64 md:flex-1"
                />
                <div class="relative md:w-64">
                    <i
                        class="fas fa-calendar-days pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                    ></i>
                    <input
                        ref="ledgerDateRangeInput"
                        type="text"
                        readonly
                        aria-label="Rentang tanggal"
                        placeholder="Pilih rentang tanggal"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 pl-9 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                </div>
                <select
                    v-model="ledgerAccountFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground md:w-52"
                >
                    <option value="all">Semua akun modal</option>
                    <option
                        v-for="account in accounts"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ account.name }}
                    </option>
                </select>
                <select
                    v-model="ledgerTypeFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground md:w-44"
                >
                    <option value="all">Semua jenis</option>
                    <option
                        v-for="type in ledgerTypeOptions"
                        :key="type.value"
                        :value="type.value"
                    >
                        {{ type.label }}
                    </option>
                </select>
                <select
                    v-model="ledgerDirectionFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground md:w-40"
                >
                    <option value="all">Semua arah</option>
                    <option value="credit">Modal masuk</option>
                    <option value="debit">Modal keluar</option>
                </select>
                <Button
                    v-if="hasLedgerFilters"
                    type="button"
                    variant="outline"
                    class="shrink-0 rounded-lg"
                    @click="resetLedgerFilters"
                >
                    Reset
                </Button>
            </div>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="bg-muted/30 text-left text-xs tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="p-4">Tanggal</th>
                                <th class="p-4">Akun</th>
                                <th class="p-4">Jenis</th>
                                <th class="p-4">Keterangan</th>
                                <th class="p-4 text-right">Nominal</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredEntries.length === 0">
                                <td
                                    colspan="6"
                                    class="p-10 text-center text-sm text-muted-foreground"
                                >
                                    {{
                                        entries.length === 0
                                            ? 'Belum ada riwayat modal.'
                                            : 'Tidak ada riwayat yang cocok dengan filter.'
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="entry in paginatedEntries"
                                :key="entry.id"
                                class="border-t border-border/60"
                            >
                                <td
                                    class="p-4 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ entryDate(entry) }}
                                </td>
                                <td class="p-4 font-bold text-foreground">
                                    {{ entry.account?.name || '-' }}
                                </td>
                                <td class="p-4">
                                    <span
                                        class="rounded-full border px-2 py-1 text-[10px] font-bold"
                                        :class="
                                            entry.direction === 'credit'
                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                : 'border-rose-500/20 bg-rose-500/10 text-rose-600'
                                        "
                                        >{{ entryTypeLabel(entry) }}</span
                                    >
                                </td>
                                <td
                                    class="max-w-[360px] p-4 text-muted-foreground"
                                >
                                    {{ entryDescription(entry) }}
                                </td>
                                <td
                                    class="p-4 text-right font-mono font-bold"
                                    :class="
                                        entry.direction === 'credit'
                                            ? 'text-emerald-600'
                                            : 'text-rose-600'
                                    "
                                >
                                    {{
                                        entry.direction === 'credit' ? '+' : '-'
                                    }}
                                    Rp {{ formatRupiah(entry.amount) }}
                                </td>
                                <td class="p-4 text-center">
                                    <Button
                                        v-if="isManualEntry(entry)"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="text-rose-600 hover:bg-rose-500/10"
                                        title="Hapus catatan manual"
                                        @click="deleteEntry(entry)"
                                    >
                                        <i class="fas fa-trash text-xs"></i>
                                    </Button>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >Otomatis</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="filteredEntries.length > 0"
                    class="flex flex-col gap-3 border-t border-border/60 p-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <span>
                            Menampilkan {{ ledgerFirstItem }}–{{
                                ledgerLastItem
                            }}
                            dari {{ filteredEntries.length }} data
                        </span>
                        <select
                            v-model.number="ledgerPageSize"
                            class="h-8 rounded-md border border-input bg-background px-2 text-foreground"
                            aria-label="Jumlah data per halaman"
                        >
                            <option :value="10">10 / halaman</option>
                            <option :value="25">25 / halaman</option>
                            <option :value="50">50 / halaman</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="rounded-lg"
                            :disabled="ledgerPage <= 1"
                            @click="previousLedgerPage"
                        >
                            Sebelumnya
                        </Button>
                        <span class="min-w-20 text-center">
                            Halaman {{ ledgerPage }} / {{ ledgerTotalPages }}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="rounded-lg"
                            :disabled="ledgerPage >= ledgerTotalPages"
                            @click="nextLedgerPage"
                        >
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <Dialog :open="formOpen" @update:open="closeFormModal">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Tambah Catatan Modal</DialogTitle>
                <DialogDescription
                    >Gunakan untuk modal awal, tambahan modal, atau penyesuaian
                    manual.</DialogDescription
                >
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitForm">
                <div class="space-y-1.5">
                    <Label for="capital-account">Akun Modal</Label>
                    <select
                        id="capital-account"
                        v-model="form.capital_account_id"
                        class="h-10 w-full rounded-xl border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option value="" disabled>Pilih akun modal</option>
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.capital_account_id"
                        class="text-xs text-red-500"
                    >
                        {{ form.errors.capital_account_id }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="rounded-xl border px-3 py-2 text-left text-sm"
                        :class="
                            form.direction === 'credit'
                                ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600'
                                : 'border-border text-muted-foreground'
                        "
                        @click="form.direction = 'credit'"
                    >
                        <span class="block font-bold">Tambah modal</span>
                        <span class="text-xs opacity-80">Saldo bertambah</span>
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border px-3 py-2 text-left text-sm"
                        :class="
                            form.direction === 'debit'
                                ? 'border-rose-500 bg-rose-500/10 text-rose-600'
                                : 'border-border text-muted-foreground'
                        "
                        @click="form.direction = 'debit'"
                    >
                        <span class="block font-bold">Kurangi modal</span>
                        <span class="text-xs opacity-80"
                            >Penarikan/koreksi</span
                        >
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-3">
                            <Label for="capital-amount">Nominal (Rp)</Label>
                            <Button
                                v-if="form.direction === 'debit'"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 rounded-lg px-2 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                :disabled="selectedAccountBalance <= 0"
                                @click="tarikSemua"
                            >
                                <i class="fas fa-wallet mr-1.5 text-[10px]"></i>
                                Tarik semua
                            </Button>
                        </div>
                        <Input
                            id="capital-amount"
                            v-model="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                        />
                        <p
                            v-if="form.errors.amount"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.amount }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="capital-date">Tanggal</Label>
                        <Input
                            id="capital-date"
                            v-model="form.entry_date"
                            type="date"
                            required
                        />
                        <p
                            v-if="form.errors.entry_date"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.entry_date }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="capital-description">Keterangan</Label>
                    <textarea
                        id="capital-description"
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground"
                        placeholder="Contoh: Modal awal saldo pulsa bulan Oktober"
                    ></textarea>
                    <p
                        v-if="form.errors.description"
                        class="text-xs text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="formOpen = false"
                        >Batal</Button
                    >
                    <Button
                        type="submit"
                        class="bg-indigo-600 text-white hover:bg-indigo-700"
                        :disabled="form.processing"
                        >{{
                            form.processing ? 'Menyimpan...' : 'Simpan Catatan'
                        }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
