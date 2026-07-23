<script setup>
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import flatpickr from 'flatpickr';
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
import 'flatpickr/dist/flatpickr.min.css';

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
    transactions: {
        type: Array,
        default: () => [],
    },
});

const expenseCategories = [
    {
        value: 'hpp_pesanan',
        label: 'HPP/Bahan pesanan',
        icon: 'fas fa-print',
        description: 'Spanduk, baliho, dan bahan cetak langsung untuk pelanggan',
        badgeClass: 'bg-amber-50/5 text-amber-600 dark:text-amber-400 border-amber-200/50',
        iconClass: 'text-amber-500 dark:text-amber-400',
    },
    {
        value: 'pembelian_stok',
        label: 'Pembelian stok',
        icon: 'fas fa-boxes',
        description: 'ATK, plastik, voucher, dan barang yang dijual kembali',
        badgeClass: 'bg-emerald-50/5 text-emerald-600 dark:text-emerald-400 border-emerald-200/50',
        iconClass: 'text-emerald-500 dark:text-emerald-400',
    },
    {
        value: 'operasional_rutin',
        label: 'Operasional rutin',
        icon: 'fas fa-tools',
        description: 'Listrik, internet, transportasi, kebersihan, administrasi',
        badgeClass: 'bg-blue-50/5 text-blue-600 dark:text-blue-400 border-blue-200/50',
        iconClass: 'text-blue-500 dark:text-blue-400',
    },
    {
        value: 'aset_peralatan',
        label: 'Aset/peralatan',
        icon: 'fas fa-screwdriver-wrench',
        description: 'Printer, mesin, gunting besar, dan alat produksi berulang',
        badgeClass: 'bg-violet-50/5 text-violet-600 dark:text-violet-400 border-violet-200/50',
        iconClass: 'text-violet-500 dark:text-violet-400',
    },
    {
        value: 'pribadi_pemilik',
        label: 'Pribadi/penarikan pemilik',
        icon: 'fas fa-user-tie',
        description: 'Penarikan pemilik, bukan pengeluaran usaha biasa',
        badgeClass: 'bg-slate-50/5 text-slate-600 dark:text-slate-300 border-slate-200/50',
        iconClass: 'text-slate-500 dark:text-slate-300',
    },
];

const normalizeCategory = (category) => ({
    stok: 'pembelian_stok',
    operasional: 'operasional_rutin',
    lainnya: 'operasional_rutin',
}[category] ?? category);

const getCategoryConfig = (category) => expenseCategories.find((item) => item.value === normalizeCategory(category)) ?? expenseCategories[2];

const padDatePart = (value) => String(value).padStart(2, '0');
const getTodayString = () => {
    const now = new Date();

    return `${now.getFullYear()}-${padDatePart(now.getMonth() + 1)}-${padDatePart(now.getDate())}`;
};
const getCurrentMonthString = () => {
    const now = new Date();

    return `${now.getFullYear()}-${padDatePart(now.getMonth() + 1)}`;
};
const getCurrentYearString = () => String(new Date().getFullYear());
const todayString = getTodayString();

// Filter state
const filterMode = ref('bulanan');
const filterDateRange = ref(`${todayString} to ${todayString}`);
const filterMonth = ref(getCurrentMonthString());
const filterYear = ref(getCurrentYearString());
const dateRangeInput = ref(null);
let dateRangePickerInstance = null;

const setMode = (mode) => {
    filterMode.value = mode;
};
const toNumber = (value) => Number.parseFloat(value ?? 0) || 0;
const parseDateRange = (value) => {
    const [start = '', end = ''] = String(value ?? '').split(' to ');
    const normalizedStart = start || todayString;
    const normalizedEnd = end || normalizedStart;

    return {
        start: normalizedStart,
        end: normalizedEnd,
    };
};
const selectedDateRange = computed(() => parseDateRange(filterDateRange.value));
const setDateRange = (start, end = start) => {
    filterDateRange.value = end && end !== start ? `${start} to ${end}` : start;

    if (dateRangePickerInstance) {
        dateRangePickerInstance.setDate([start, end || start], false);
    }
};
const initDateRangePicker = () => {
    if (!dateRangeInput.value) {
        return;
    }

    dateRangePickerInstance?.destroy();
    dateRangePickerInstance = flatpickr(dateRangeInput.value, {
        mode: 'range',
        dateFormat: 'Y-m-d',
        defaultDate: [selectedDateRange.value.start, selectedDateRange.value.end],
        onChange: (selectedDates, _dateStr, instance) => {
            if (selectedDates.length === 0) {
                setDateRange(todayString);

                return;
            }

            const [startDate, endDate = startDate] = selectedDates.map((date) => instance.formatDate(date, 'Y-m-d'));
            filterDateRange.value = startDate === endDate ? startDate : `${startDate} to ${endDate}`;
        },
    });
};

// Form modal state
const formOpen = ref(false);
const editTarget = ref(null);

const form = useForm({
    date: new Date().toISOString().split('T')[0],
    name: '',
    amount: '',
    category: 'operasional_rutin',
    transaction_id: '',
    hpp_status: 'belum_masuk_hpp',
    note: '',
});

const resetExpenseForm = () => {
    form.reset();
    form.clearErrors();
    form.date = new Date().toISOString().split('T')[0];
    form.category = 'operasional_rutin';
    form.transaction_id = '';
    form.hpp_status = 'belum_masuk_hpp';
    form.note = '';
};

const openCreateModal = () => {
    editTarget.value = null;
    resetExpenseForm();
    formOpen.value = true;
};

const openEditModal = (expense) => {
    editTarget.value = expense;
    form.clearErrors();
    form.date = expense.date;
    form.name = expense.name;
    form.amount = expense.amount;
    form.category = normalizeCategory(expense.category);
    form.transaction_id = expense.transaction_id || '';
    form.hpp_status = expense.hpp_status || (normalizeCategory(expense.category) === 'hpp_pesanan' ? 'belum_masuk_hpp' : 'not_applicable');
    form.note = expense.note || '';
    formOpen.value = true;
};

const submitForm = () => {
    const request = editTarget.value
        ? form.patch(`/expenses/${editTarget.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
                editTarget.value = null;
                resetExpenseForm();
            },
        })
        : form.post('/expenses', {
            preserveScroll: true,
            onSuccess: () => {
                formOpen.value = false;
                resetExpenseForm();
            },
        });

    return request;
};

const closeFormModal = (open) => {
    formOpen.value = open;

    if (!open) {
        editTarget.value = null;
        resetExpenseForm();
    }
};

const deleteExpense = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus catatan pengeluaran ini?')) {
        form.delete(`/expenses/${id}`, {
            preserveScroll: true,
        });
    }
};

const filteredExpenses = computed(() => {
    const { start, end } = selectedDateRange.value;

    return props.expenses.filter((expense) => {
        const expenseDate = String(expense.date ?? '');

        return filterMode.value === 'harian'
            ? expenseDate >= start && expenseDate <= end
            : filterMode.value === 'bulanan'
                ? expenseDate.slice(0, 7) === filterMonth.value
                : expenseDate.slice(0, 4) === filterYear.value;
    });
});

// Summary metrics
const totalExpenses = computed(() => {
    return filteredExpenses.value.reduce((sum, e) => sum + toNumber(e.amount), 0);
});

const expensesByCategory = computed(() => {
    const categories = Object.fromEntries(expenseCategories.map((category) => [category.value, 0]));
    filteredExpenses.value.forEach(e => {
        const category = normalizeCategory(e.category);

        if (categories[category] !== undefined) {
            categories[category] += toNumber(e.amount);
        }
    });
    return categories;
});

const isDoubleCountRisk = (expense) => normalizeCategory(expense.category) === 'hpp_pesanan'
    && expense.hpp_status === 'sudah_masuk_hpp';
const needsHppReview = (expense) => normalizeCategory(expense.category) === 'hpp_pesanan'
    && expense.hpp_status !== 'sudah_masuk_hpp'
    && expense.transaction
    && toNumber(expense.transaction.total_base_price) > 0;
const affectsProfit = (expense) => normalizeCategory(expense.category) !== 'pribadi_pemilik'
    && !isDoubleCountRisk(expense);
const profitAffectingTotal = computed(() => filteredExpenses.value
    .filter(affectsProfit)
    .reduce((sum, expense) => sum + toNumber(expense.amount), 0));
const doubleCountRisks = computed(() => filteredExpenses.value.filter(isDoubleCountRisk));
const hppReviewItems = computed(() => filteredExpenses.value.filter(needsHppReview));
const selectedTransaction = computed(() => props.transactions.find((transaction) => transaction.id == form.transaction_id));

const getTransactionLabel = (transaction) => {
    const customerName = transaction.customer_name || transaction.customer?.name || 'Cash / Umum';
    const date = String(transaction.created_at ?? '').slice(0, 10);

    return `${transaction.invoice_number} - ${customerName}${date ? ` (${date})` : ''}`;
};

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const yearOptions = Array.from({ length: 5 }, (_, i) => String(new Date().getFullYear() - i));

const filterLabel = computed(() => {
    if (filterMode.value === 'harian') {
        const { start, end } = selectedDateRange.value;

        return start === end ? start : `${start} s/d ${end}`;
    }

    return filterMode.value === 'bulanan' ? filterMonth.value : filterYear.value;
});

watch(() => form.category, (category) => {
    if (category !== 'hpp_pesanan') {
        form.transaction_id = '';
        form.hpp_status = 'not_applicable';

        return;
    }

    if (form.hpp_status === 'not_applicable') {
        form.hpp_status = 'belum_masuk_hpp';
    }
});

watch(filterMode, async (mode) => {
    if (mode !== 'harian') {
        dateRangePickerInstance?.destroy();
        dateRangePickerInstance = null;

        return;
    }

    await nextTick();
    initDateRangePicker();
});

onMounted(() => {
    if (filterMode.value === 'harian') {
        initDateRangePicker();
    }
});

onBeforeUnmount(() => {
    dateRangePickerInstance?.destroy();
});
</script>

<template>
    <Head>
        <title>Biaya Operasional & Pengeluaran | Smart POS System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </Head>

    <div class="flex flex-col gap-6 p-4 md:p-6 pb-8 font-inter">
        <!-- Header + Filter Bar -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">Operasional & Pengeluaran</h1>
                    <p class="text-sm text-muted-foreground">Catat dan pantau pengeluaran bulanan, biaya kulakan barang, listrik, internet, dan kertas fotokopi.</p>
                </div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <span class="text-xs bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20 px-3 py-1.5 rounded-full font-semibold">
                        <i class="fas fa-file-invoice-dollar mr-1"></i>
                        {{ filteredExpenses.length }} pengeluaran &bull; {{ filterLabel }}
                    </span>
                    <Button @click="openCreateModal" class="rounded-xl font-semibold shadow-md bg-red-600 hover:bg-red-700 text-white shrink-0">
                        <i class="fas fa-plus mr-2 text-xs"></i> Catat Pengeluaran
                    </Button>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="bg-card border border-border rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center gap-4 shadow-xs">
                <div class="flex gap-1.5 bg-muted/50 p-1 rounded-xl border border-border/50">
                    <button
                        v-for="m in [
                            { key: 'harian', label: 'Harian', icon: 'fas fa-calendar-day' },
                            { key: 'bulanan', label: 'Bulanan', icon: 'fas fa-calendar-alt' },
                            { key: 'tahunan', label: 'Tahunan', icon: 'fas fa-calendar' },
                        ]"
                        :key="m.key"
                        @click="setMode(m.key)"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-200"
                        :class="filterMode === m.key
                            ? 'bg-red-600 text-white shadow-sm'
                            : 'text-muted-foreground hover:text-foreground hover:bg-muted'"
                    >
                        <i :class="m.icon" class="text-[10px]"></i>
                        {{ m.label }}
                    </button>
                </div>

                <div class="flex-1 flex items-center gap-3">
                    <div v-if="filterMode === 'harian'" class="relative flex-1 max-w-sm">
                        <i class="fas fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <input
                            ref="dateRangeInput"
                            type="text"
                            :value="filterDateRange"
                            placeholder="Pilih rentang tanggal"
                            class="h-9 w-full rounded-xl border border-input bg-background px-3 pl-8 text-sm text-foreground transition focus:outline-none focus:ring-2 focus:ring-red-500"
                        />
                    </div>

                    <div v-else-if="filterMode === 'bulanan'" class="relative flex-1 max-w-xs">
                        <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <input
                            type="month"
                            v-model="filterMonth"
                            class="pl-8 h-9 w-full rounded-xl border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-red-500 transition px-3"
                        />
                    </div>

                    <div v-else class="relative flex-1 max-w-xs">
                        <i class="fas fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <select
                            v-model="filterYear"
                            class="pl-8 h-9 w-full rounded-xl border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-red-500 transition px-3 appearance-none"
                        >
                            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <button
                        @click="
                            filterMode === 'harian' ? setDateRange(todayString) :
                            filterMode === 'bulanan' ? filterMonth = getCurrentMonthString() :
                            filterYear = getCurrentYearString()
                        "
                        class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-300 font-semibold whitespace-nowrap transition underline underline-offset-2"
                    >
                        {{ filterMode === 'harian' ? 'Hari ini' : filterMode === 'bulanan' ? 'Bulan ini' : 'Tahun ini' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <!-- Total Pengeluaran -->
            <Card class="border-border/60 bg-gradient-to-br from-red-500/5 to-orange-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Pengeluaran</span>
                        <i class="fas fa-arrow-down-long text-red-500 dark:text-red-450 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-red-600 dark:text-red-400">Rp {{ formatRupiah(totalExpenses) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">Akumulasi pengeluaran pada periode aktif</p>
                    <p class="text-[11px] text-red-500 mt-1">Berdampak ke laba: Rp {{ formatRupiah(profitAffectingTotal) }}</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-red-500 to-orange-500"></div>
            </Card>

            <Card v-for="category in expenseCategories" :key="category.value" class="border-border/60 bg-card shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>{{ category.label }}</span>
                        <i :class="[category.icon, category.iconClass]" class="text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-xl font-bold text-foreground">Rp {{ formatRupiah(expensesByCategory[category.value] || 0) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">{{ category.description }}</p>
                </CardContent>
            </Card>
        </div>

        <div v-if="doubleCountRisks.length > 0 || hppReviewItems.length > 0" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                <div>
                    <p class="font-bold">{{ doubleCountRisks.length }} biaya HPP sudah ditandai masuk HPP nota, {{ hppReviewItems.length }} biaya perlu dicek ulang.</p>
                    <p class="text-xs mt-0.5">Biaya yang sudah masuk HPP nota tetap tampil sebagai arus keluar, tapi tidak ikut mengurangi laba lagi.</p>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <Card class="border-border/50 shadow-sm overflow-hidden bg-card text-card-foreground">
            <CardHeader class="border-b border-border/40 py-4 bg-muted/10">
                <CardTitle class="text-sm font-bold text-foreground">Riwayat Catatan Biaya</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">Daftar pengeluaran periode {{ filterLabel }}.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="p-4 pl-6">Tanggal</th>
                                <th class="p-4">Kebutuhan / Pengeluaran</th>
                                <th class="p-4">Kategori Biaya</th>
                                <th class="p-4">Nota / Pekerjaan</th>
                                <th class="p-4">Keterangan Catatan</th>
                                <th class="p-4 text-right">Nominal Jumlah</th>
                                <th class="p-4 text-right pr-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="filteredExpenses.length === 0">
                                <td colspan="7" class="p-12 text-center text-muted-foreground">
                                    <i class="fas fa-hand-holding-usd text-4xl mb-3 opacity-20"></i>
                                    <p class="font-medium">Belum ada pengeluaran pada periode ini.</p>
                                    <p class="text-xs text-muted-foreground mt-1">Ubah filter tanggal atau catat pengeluaran baru.</p>
                                </td>
                            </tr>
                            <tr v-for="exp in filteredExpenses" :key="exp.id" class="hover:bg-muted/30 transition">
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
                                        :class="getCategoryConfig(exp.category).badgeClass"
                                    >
                                        {{ getCategoryConfig(exp.category).label }}
                                    </Badge>
                                    <div v-if="isDoubleCountRisk(exp)" class="mt-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400">
                                        Sudah masuk HPP nota
                                    </div>
                                    <div v-else-if="needsHppReview(exp)" class="mt-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400">
                                        Cek HPP nota
                                    </div>
                                </td>
                                <!-- Linked Transaction -->
                                <td class="p-4 text-xs">
                                    <div v-if="exp.transaction" class="space-y-0.5">
                                        <p class="font-mono font-bold text-foreground">{{ exp.transaction.invoice_number }}</p>
                                        <p class="text-muted-foreground truncate max-w-[180px]">{{ exp.transaction.customer_name || exp.transaction.customer?.name || 'Cash / Umum' }}</p>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
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
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Button @click="openEditModal(exp)" variant="ghost" size="icon-sm" title="Edit pengeluaran" aria-label="Edit pengeluaran" class="h-8 rounded-lg text-blue-600 hover:text-blue-800 hover:bg-blue-50 dark:text-blue-400 dark:hover:text-blue-300 dark:hover:bg-blue-950/20">
                                            <i class="fas fa-pen"></i>
                                        </Button>
                                        <Button @click="deleteExpense(exp.id)" variant="ghost" size="icon-sm" title="Hapus pengeluaran" aria-label="Hapus pengeluaran" class="h-8 rounded-lg text-red-650 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/20">
                                            <i class="fas fa-trash-alt"></i>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- DIALOG RECORD FORM -->
        <Dialog :open="formOpen" @update:open="closeFormModal">
            <DialogContent class="sm:max-w-[480px] rounded-2xl bg-card border-border text-foreground">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <i :class="editTarget ? 'fas fa-pen text-blue-500' : 'fas fa-wallet text-red-500'"></i>
                        {{ editTarget ? 'Edit Pengeluaran' : 'Catat Biaya Pengeluaran Baru' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ editTarget ? 'Perbarui kategori, nominal, nota terkait, atau status HPP pengeluaran.' : 'Isi form di bawah ini untuk mencatat pengeluaran operasional toko mandiri Anda secara transparan.' }}
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
                                <option v-for="category in expenseCategories" :key="category.value" :value="category.value">
                                    {{ category.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="form.category === 'hpp_pesanan'" class="space-y-4 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
                        <div class="space-y-1.5">
                            <Label for="exp-transaction">Hubungkan ke Nota / Pekerjaan Pelanggan</Label>
                            <select id="exp-transaction" v-model="form.transaction_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 text-foreground">
                                <option value="">Belum dihubungkan</option>
                                <option v-for="transaction in transactions" :key="transaction.id" :value="transaction.id">
                                    {{ getTransactionLabel(transaction) }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="exp-hpp-status">Status Biaya terhadap HPP Nota</Label>
                            <select id="exp-hpp-status" v-model="form.hpp_status" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 text-foreground">
                                <option value="belum_masuk_hpp">Belum masuk HPP nota, kurangkan dari laba</option>
                                <option value="sudah_masuk_hpp">Sudah masuk HPP nota, jangan kurangkan lagi</option>
                            </select>
                        </div>

                        <div v-if="selectedTransaction" class="rounded-xl border border-border/70 bg-background/60 p-3 text-xs text-muted-foreground">
                            <p class="font-semibold text-foreground">{{ selectedTransaction.invoice_number }}</p>
                            <p>Omset: Rp {{ formatRupiah(selectedTransaction.total_price || 0) }} &bull; HPP nota: Rp {{ formatRupiah(selectedTransaction.total_base_price || 0) }}</p>
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
                            <i class="fas fa-check mr-1.5"></i> {{ editTarget ? 'Simpan Perubahan' : 'Simpan Catatan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
