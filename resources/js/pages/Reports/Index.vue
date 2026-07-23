<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import flatpickr from 'flatpickr';
import html2pdf from 'html2pdf.js';
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Laporan Penjualan', href: '/reports' },
        ],
    },
});

const props = defineProps({
    transactions: {
        type: Array,
        default: () => [],
    },
    expenses: {
        type: Array,
        default: () => [],
    },
});

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

// ─── FILTER STATE ───────────────────────────────────────────────
const filterMode  = ref('harian');   // 'harian' | 'bulanan' | 'tahunan'
const filterDateRange = ref(`${todayString} to ${todayString}`);
const filterMonth = ref(getCurrentMonthString());
const filterYear  = ref(getCurrentYearString());
const searchQuery = ref('');
const perPage = ref(10);
const currentPage = ref(1);
const exportPdfRef = ref(null);
const dateRangeInput = ref(null);
const copiedInvoice = ref('');
let copyFeedbackTimer = null;
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

// ─── FILTERED LIST ───────────────────────────────────────────────
const filteredTransactions = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    const { start, end } = selectedDateRange.value;

    return props.transactions.filter(t => {
        const matchesPeriod = filterMode.value === 'harian'
            ? t.created_at.slice(0, 10) >= start && t.created_at.slice(0, 10) <= end
            : filterMode.value === 'bulanan'
                ? t.created_at.slice(0, 7) === filterMonth.value
                : t.created_at.slice(0, 4) === filterYear.value;

        if (!matchesPeriod) {
            return false;
        }

        if (!query) {
            return true;
        }

        const customerName = t.customer_name || t.customer?.name || 'cash / umum';
        const paymentMethod = t.payment_method || '';

        return [
            t.invoice_number,
            customerName,
            paymentMethod,
        ].some(value => String(value).toLowerCase().includes(query));
    });
});
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

const totalPages = computed(() => Math.max(1, Math.ceil(filteredTransactions.value.length / perPage.value)));
const paginatedTransactions = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;

    return filteredTransactions.value.slice(start, start + perPage.value);
});
const paginationStart = computed(() => filteredTransactions.value.length === 0 ? 0 : ((currentPage.value - 1) * perPage.value) + 1);
const paginationEnd = computed(() => Math.min(currentPage.value * perPage.value, filteredTransactions.value.length));
const visiblePages = computed(() => {
    const pages = [];
    const start = Math.max(1, currentPage.value - 2);
    const end = Math.min(totalPages.value, start + 4);

    for (let page = Math.max(1, end - 4); page <= end; page += 1) {
        pages.push(page);
    }

    return pages;
});

watch([filterMode, filterDateRange, filterMonth, filterYear, searchQuery, perPage], () => {
    currentPage.value = 1;
});

watch(totalPages, (pages) => {
    if (currentPage.value > pages) {
        currentPage.value = pages;
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
    clearTimeout(copyFeedbackTimer);
    dateRangePickerInstance?.destroy();
});

const goToPage = (page) => {
    currentPage.value = Math.min(Math.max(1, page), totalPages.value);
};

const getCustomerName = (transaction) => transaction.customer_name || transaction.customer?.name || 'Cash / Umum';

const downloadBlob = (content, type, filename) => {
    const url = URL.createObjectURL(new Blob([content], { type }));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
};

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');

const exportExcel = () => {
    const rows = filteredTransactions.value.map(transaction => `
        <tr>
            <td>${escapeHtml(transaction.invoice_number)}</td>
            <td>${escapeHtml(formatDate(transaction.created_at))}</td>
            <td>${escapeHtml(getCustomerName(transaction))}</td>
            <td>${escapeHtml(transaction.payment_method)}</td>
            <td>${escapeHtml(transaction.status_bayar || 'lunas')}</td>
            <td>${Number(transaction.total_price || 0)}</td>
            <td>${Number(transaction.total_profit || 0)}</td>
        </tr>
    `).join('');
    const businessRows = businessCategoryRows.value.map(category => `
        <tr>
            <td>${escapeHtml(category.label)}</td>
            <td>${Number(category.omzet || 0)}</td>
            <td>${Number(category.modal || 0)}</td>
            <td>${Number(category.laba || 0)}</td>
            <td>${Number(category.margin || 0).toFixed(1)}%</td>
            <td>${Number(category.transactionCount || 0)}</td>
        </tr>
    `).join('');

    const workbook = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
            <head><meta charset="UTF-8"></head>
            <body>
                <table border="1">
                    <tr><th colspan="7">Laporan Penjualan - ${escapeHtml(filterLabel.value)}</th></tr>
                    <tr><td colspan="7">Pengeluaran: ${Number(totalExpenses.value)} | Pendapatan Bersih: ${Number(netProfit.value)}</td></tr>
                    <tr><td colspan="7"></td></tr>
                    <tr><th colspan="6">Performa per Jenis Usaha</th></tr>
                    <tr>
                        <th>Jenis Usaha</th><th>Omzet</th><th>Modal / HPP</th><th>Laba</th><th>Margin</th><th>Transaksi</th>
                    </tr>
                    ${businessRows}
                    <tr><td colspan="7"></td></tr>
                    <tr>
                        <th>Nomor Invoice</th><th>Tanggal</th><th>Customer</th><th>Metode Pembayaran</th>
                        <th>Status</th><th>Total Belanja</th><th>Keuntungan</th>
                    </tr>
                    ${rows}
                </table>
            </body>
        </html>
    `;

    downloadBlob(workbook, 'application/vnd.ms-excel;charset=utf-8;', `laporan-penjualan-${filterFileLabel.value}.xls`);
};

const exportPdf = async () => {
    if (filteredTransactions.value.length === 0) {
return;
}

    await nextTick();
    await html2pdf().set({
        margin: 8,
        filename: `laporan-penjualan-${filterFileLabel.value}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, backgroundColor: '#ffffff' },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
    }).from(exportPdfRef.value).save();
};

// ─── SUMMARY METRICS ─────────────────────────────────────────────
const totalSales  = computed(() => filteredTransactions.value.reduce((sum, transaction) => sum + toNumber(transaction.total_price), 0));
const totalBase   = computed(() => filteredTransactions.value.reduce((sum, transaction) => sum + toNumber(transaction.total_base_price), 0));
const totalTransactionProfit = computed(() => filteredTransactions.value.reduce((sum, transaction) => sum + toNumber(transaction.total_profit), 0));
const affectsProfit = (expense) => expense.category !== 'pribadi_pemilik'
    && !(expense.category === 'hpp_pesanan' && expense.hpp_status === 'sudah_masuk_hpp');
const profitAffectingExpenses = computed(() => filteredExpenses.value.filter(affectsProfit));
const totalExpenses = computed(() => profitAffectingExpenses.value.reduce((sum, expense) => sum + toNumber(expense.amount), 0));
const netProfit = computed(() => totalSales.value - totalBase.value - totalExpenses.value);

const businessCategories = [
    {
        key: 'saldo_digital',
        label: 'Saldo digital',
        icon: 'fas fa-bolt',
        description: 'Pulsa, token, top-up',
        accentClass: 'from-violet-500 to-indigo-500',
        iconClass: 'text-violet-500 dark:text-violet-400',
    },
    {
        key: 'atk',
        label: 'ATK',
        icon: 'fas fa-pen-ruler',
        description: 'Pulpen, kertas, alat sekolah',
        accentClass: 'from-emerald-500 to-teal-500',
        iconClass: 'text-emerald-500 dark:text-emerald-400',
    },
    {
        key: 'percetakan',
        label: 'Percetakan',
        icon: 'fas fa-print',
        description: 'Spanduk, undangan, fotokopi',
        accentClass: 'from-amber-500 to-orange-500',
        iconClass: 'text-amber-500 dark:text-amber-400',
    },
    {
        key: 'jasa',
        label: 'Jasa',
        icon: 'fas fa-file-signature',
        description: 'Desain, pengetikan, edit dokumen',
        accentClass: 'from-sky-500 to-cyan-500',
        iconClass: 'text-sky-500 dark:text-sky-400',
    },
];

const includesAny = (value, keywords) => keywords.some((keyword) => value.includes(keyword));
const normalizeText = (value) => String(value ?? '').toLowerCase();
const getItemSearchText = (item) => [
    item.item_name,
    item.product?.name,
    item.product?.sku,
    item.product?.category?.name,
    item.product?.category?.slug,
    item.product?.category?.type,
    item.type,
].map(normalizeText).join(' ');

const classifyBusinessItem = (item) => {
    const searchText = getItemSearchText(item);
    const categoryType = item.product?.category?.type || item.type;

    if (categoryType === 'ppob' || item.type === 'ppob' || includesAny(searchText, ['pulsa', 'token', 'topup', 'top-up', 'e-wallet', 'saldo digital'])) {
        return 'saldo_digital';
    }

    if (includesAny(searchText, ['desain', 'design', 'pengetikan', 'ketik', 'edit dokumen', 'edit file', 'layout'])) {
        return 'jasa';
    }

    if (categoryType === 'fisik' || item.type === 'fisik' || includesAny(searchText, ['atk', 'pulpen', 'pena', 'kertas', 'buku', 'alat sekolah', 'kalkulator', 'plastik'])) {
        return 'atk';
    }

    if (includesAny(searchText, ['spanduk', 'baliho', 'undangan', 'fotokopi', 'photo copy', 'cetak', 'print', 'brosur', 'stiker', 'banner', 'jilid', 'laminating'])) {
        return 'percetakan';
    }

    return categoryType === 'jasa' || item.type === 'jasa' ? 'percetakan' : 'jasa';
};

const classifyExpenseBusinessCategory = (expense) => {
    const searchText = [expense.name, expense.note, expense.transaction?.invoice_number]
        .map(normalizeText)
        .join(' ');

    if (includesAny(searchText, ['pulsa', 'token', 'topup', 'top-up', 'e-wallet', 'saldo digital'])) {
        return 'saldo_digital';
    }

    if (includesAny(searchText, ['atk', 'pulpen', 'pena', 'kertas', 'buku', 'alat sekolah', 'kalkulator', 'plastik'])) {
        return 'atk';
    }

    if (includesAny(searchText, ['desain', 'design', 'pengetikan', 'ketik', 'edit dokumen', 'edit file', 'layout'])) {
        return 'jasa';
    }

    if (includesAny(searchText, ['spanduk', 'baliho', 'undangan', 'fotokopi', 'photo copy', 'cetak', 'print', 'brosur', 'stiker', 'banner', 'jilid', 'laminating'])) {
        return 'percetakan';
    }

    const linkedTransaction = filteredTransactions.value.find((transaction) => transaction.id === expense.transaction_id);
    const dominantCategory = linkedTransaction?.items?.reduce((best, item) => {
        const key = classifyBusinessItem(item);
        const omzet = toNumber(item.subtotal_price);

        if (!best || omzet > best.omzet) {
            return { key, omzet };
        }

        return best;
    }, null);

    return dominantCategory?.key || 'percetakan';
};

const businessCategoryRows = computed(() => {
    const rows = Object.fromEntries(businessCategories.map((category) => [
        category.key,
        {
            ...category,
            omzet: 0,
            modal: 0,
            laba: 0,
            margin: 0,
            transactionIds: new Set(),
            adjustmentTotal: 0,
        },
    ]));

    filteredTransactions.value.forEach((transaction) => {
        (transaction.items || []).forEach((item) => {
            const key = classifyBusinessItem(item);
            const row = rows[key] || rows.jasa;

            row.omzet += toNumber(item.subtotal_price);
            row.modal += toNumber(item.subtotal_base);
            row.transactionIds.add(transaction.id);
        });
    });

    filteredExpenses.value
        .filter((expense) => expense.category === 'hpp_pesanan' && expense.hpp_status !== 'sudah_masuk_hpp')
        .forEach((expense) => {
            const key = classifyExpenseBusinessCategory(expense);
            const row = rows[key] || rows.percetakan;
            const amount = toNumber(expense.amount);

            row.modal += amount;
            row.adjustmentTotal += amount;

            if (expense.transaction_id) {
                row.transactionIds.add(expense.transaction_id);
            }
        });

    return businessCategories.map((category) => {
        const row = rows[category.key];
        const laba = row.omzet - row.modal;

        return {
            ...row,
            laba,
            margin: row.omzet > 0 ? (laba / row.omzet) * 100 : 0,
            transactionCount: row.transactionIds.size,
        };
    });
});

// ─── DETAIL DIALOG ────────────────────────────────────────────────
const selectedTransaction = ref(null);
const detailOpen = ref(false);
const invoiceRecipientName = ref('');
const invoiceRecipientPhone = ref('');
const isSavingInvoiceRecipient = ref(false);

const openDetail = (trx) => {
    selectedTransaction.value = trx;
    invoiceRecipientName.value = trx.customer_name || trx.customer?.name || '';
    invoiceRecipientPhone.value = trx.customer_phone || trx.customer?.phone || '';
    detailOpen.value = true;
};

const saveInvoiceRecipient = () => {
    if (!selectedTransaction.value) {
return;
}

    isSavingInvoiceRecipient.value = true;
    router.patch(`/reports/${selectedTransaction.value.id}/invoice-recipient`, {
        customer_name: invoiceRecipientName.value.trim() || null,
        customer_phone: invoiceRecipientPhone.value.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedTransaction.value.customer_name = invoiceRecipientName.value.trim() || null;
            selectedTransaction.value.customer_phone = invoiceRecipientPhone.value.trim() || null;
            isSavingInvoiceRecipient.value = false;
        },
        onError: () => {
 isSavingInvoiceRecipient.value = false; 
},
    });
};

// ─── HELPERS ─────────────────────────────────────────────────────
const formatRupiah = (n) => new Intl.NumberFormat('id-ID').format(n);
const formatDate   = (s) => new Date(s).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedInvoice.value = text;
        toast.success(`Invoice ${text} berhasil disalin.`, { duration: 2500 });

        clearTimeout(copyFeedbackTimer);
        copyFeedbackTimer = setTimeout(() => {
            copiedInvoice.value = '';
        }, 2500);
    } catch {
        toast.error('Invoice gagal disalin.', { duration: 2500 });
    }
};

// Generate daftar tahun (5 tahun ke belakang)
const yearOptions = Array.from({ length: 5 }, (_, i) => String(new Date().getFullYear() - i));

// Label badge pada mode aktif
const filterLabel = computed(() => {
    if (filterMode.value === 'harian') {
        const { start, end } = selectedDateRange.value;

        return start === end ? start : `${start} s/d ${end}`;
    }

    if (filterMode.value === 'bulanan') {
return filterMonth.value;
}

    return filterYear.value;
});
const filterFileLabel = computed(() => {
    if (filterMode.value === 'harian') {
        const { start, end } = selectedDateRange.value;

        return start === end ? start : `${start}_sd_${end}`;
    }

    return filterMode.value === 'bulanan' ? filterMonth.value : filterYear.value;
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
    if (!deleteTarget.value) {
return;
}

    isDeleting.value = true;
    router.delete(`/reports/${deleteTarget.value.id}`, {
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deleteTarget.value = null;
            isDeleting.value = false;
        },
        onError: () => {
 isDeleting.value = false; 
},
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
                    <!-- Harian: flatpickr range -->
                    <div v-if="filterMode === 'harian'" class="relative flex-1 max-w-sm">
                        <i class="fas fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                        <input
                            ref="dateRangeInput"
                            type="text"
                            :value="filterDateRange"
                            placeholder="Pilih rentang tanggal"
                            class="h-9 w-full rounded-xl border border-input bg-background px-3 pl-8 text-sm text-foreground transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                            filterMode === 'harian'  ? setDateRange(todayString) :
                            filterMode === 'bulanan' ? filterMonth = getCurrentMonthString() :
                                                       filterYear  = getCurrentYearString()
                        "
                        class="text-xs text-indigo-500 hover:text-indigo-700 dark:hover:text-indigo-300 font-semibold whitespace-nowrap transition underline underline-offset-2"
                    >
                        {{ filterMode === 'harian' ? 'Hari ini' : filterMode === 'bulanan' ? 'Bulan ini' : 'Tahun ini' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
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

            <Card class="border-border/60 bg-gradient-to-br from-rose-500/5 to-red-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Total Pengeluaran</span>
                        <i class="fas fa-file-invoice-dollar text-rose-500 dark:text-rose-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-rose-600 dark:text-rose-400">Rp {{ formatRupiah(totalExpenses) }}</div>
                    <p class="text-[11px] text-muted-foreground mt-1">{{ profitAffectingExpenses.length }} pengeluaran berdampak laba pada periode ini</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-red-500"></div>
            </Card>

            <Card class="border-border/60 bg-gradient-to-br from-emerald-500/5 to-teal-500/5 relative overflow-hidden shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                        <span>Laba Bersih</span>
                        <i class="fas fa-chart-line text-emerald-500 dark:text-emerald-400 text-sm"></i>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black" :class="netProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                        Rp {{ formatRupiah(netProfit) }}
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-1">Omset dikurangi modal/HPP dan pengeluaran sesuai filter aktif</p>
                </CardContent>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
            </Card>
        </div>

        <Card class="border-border/50 shadow-sm overflow-hidden bg-card text-card-foreground">
            <CardHeader class="border-b border-border/40 py-4 bg-muted/10">
                <div class="flex flex-col gap-1">
                    <CardTitle class="text-sm font-bold text-foreground">Performa per Jenis Usaha</CardTitle>
                    <CardDescription class="text-xs text-muted-foreground">Breakdown omzet, modal, laba, margin, dan jumlah transaksi periode {{ filterLabel }}.</CardDescription>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[780px] text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="p-4 pl-6">Jenis Usaha</th>
                                <th class="p-4 text-right">Omzet</th>
                                <th class="p-4 text-right">Modal / HPP</th>
                                <th class="p-4 text-right">Laba</th>
                                <th class="p-4 text-right">Margin</th>
                                <th class="p-4 text-right pr-6">Transaksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-for="category in businessCategoryRows" :key="category.key" class="hover:bg-muted/30 transition">
                                <td class="p-4 pl-6">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-muted/60 border border-border/60">
                                            <i :class="[category.icon, category.iconClass]" class="text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-foreground">{{ category.label }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ category.description }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-right font-bold font-mono text-foreground">Rp {{ formatRupiah(category.omzet) }}</td>
                                <td class="p-4 text-right font-mono text-muted-foreground">
                                    <div>Rp {{ formatRupiah(category.modal) }}</div>
                                    <div v-if="category.adjustmentTotal > 0" class="text-[10px] text-amber-600 dark:text-amber-400">+HPP eksternal Rp {{ formatRupiah(category.adjustmentTotal) }}</div>
                                </td>
                                <td class="p-4 text-right font-bold font-mono" :class="category.laba >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    Rp {{ formatRupiah(category.laba) }}
                                </td>
                                <td class="p-4 text-right font-bold font-mono" :class="category.margin >= 0 ? 'text-foreground' : 'text-red-600 dark:text-red-400'">
                                    {{ category.margin.toFixed(1) }}%
                                </td>
                                <td class="p-4 text-right pr-6 font-bold text-foreground">{{ category.transactionCount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- History Table -->
        <Card class="border-border/50 shadow-sm overflow-hidden bg-card text-card-foreground">
            <CardHeader class="border-b border-border/40 py-4 bg-muted/10 gap-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <CardTitle class="text-sm font-bold text-foreground">Riwayat Nota Penjualan</CardTitle>
                        <CardDescription class="text-xs text-muted-foreground">
                            Daftar transaksi periode
                            <strong class="text-foreground">
                                {{ filterLabel }}
                            </strong>
                        </CardDescription>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" class="rounded-xl text-xs text-emerald-600 dark:text-emerald-400" :disabled="filteredTransactions.length === 0" @click="exportExcel">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </Button>
                        <Button type="button" variant="outline" size="sm" class="rounded-xl text-xs text-red-500" :disabled="filteredTransactions.length === 0" @click="exportPdf">
                            <i class="fas fa-file-pdf"></i>
                            Export PDF
                        </Button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-md">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"></i>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari invoice, nama customer, atau metode pembayaran..."
                            class="h-9 w-full rounded-xl border border-input bg-background pl-8 pr-3 text-xs text-foreground outline-none transition focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <span>Tampilkan</span>
                        <select v-model.number="perPage" class="h-9 rounded-xl border border-input bg-background px-3 text-xs text-foreground outline-none focus:ring-2 focus:ring-indigo-500">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                        <span>data</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="p-4 pl-6">Nomor Nota</th>
                                <th class="p-4">Tanggal Transaksi</th>
                                <th class="p-4">Customer</th>
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
                                <td colspan="8" class="p-14 text-center text-muted-foreground">
                                    <i class="fas fa-filter text-4xl mb-3 opacity-20 block"></i>
                                    <p class="font-semibold text-sm">Tidak ada transaksi pada periode ini.</p>
                                    <p class="text-xs text-muted-foreground mt-1 opacity-70">Coba ubah filter tanggal, bulan, atau tahun.</p>
                                </td>
                            </tr>

                            <!-- Data rows -->
                            <tr v-for="trx in paginatedTransactions" :key="trx.id" class="hover:bg-muted/30 transition">
                                <td class="p-4 pl-6 font-mono font-semibold text-foreground">
                                    <div class="flex items-center gap-1.5">
                                        {{ trx.invoice_number }}
                                        <Button
                                            @click="copyToClipboard(trx.invoice_number)"
                                            variant="ghost"
                                            size="xs"
                                            data-click-feedback="none"
                                            title="Salin nomor invoice"
                                            aria-label="Salin nomor invoice"
                                            class="h-6 w-6 p-0 rounded-full transition-colors"
                                            :class="copiedInvoice === trx.invoice_number
                                                ? 'bg-emerald-500/15 text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400'
                                                : 'text-muted-foreground hover:text-foreground hover:bg-muted'"
                                        >
                                            <i :class="copiedInvoice === trx.invoice_number ? 'fas fa-check' : 'fas fa-copy'" class="text-[10px]"></i>
                                        </Button>
                                    </div>
                                </td>
                                <td class="p-4 text-muted-foreground text-xs">{{ formatDate(trx.created_at) }}</td>
                                <td class="p-4 text-xs font-semibold text-foreground">{{ getCustomerName(trx) }}</td>
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
                                        <Button @click="openDetail(trx)" variant="ghost" size="icon-sm" title="Detail transaksi" aria-label="Detail transaksi" class="h-8 rounded-xl text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                                            <i class="fas fa-eye"></i>
                                        </Button>
                                        <a :href="`/pos/print/${trx.invoice_number}`" target="_blank"
                                            data-click-feedback="action"
                                            title="Cetak transaksi" aria-label="Cetak transaksi"
                                            class="inline-flex items-center justify-center rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-neutral-200 h-8 w-8 shadow-xs transition">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <Button @click="openDeleteConfirm(trx)" variant="ghost" size="icon-sm" title="Hapus transaksi" aria-label="Hapus transaksi" class="h-8 rounded-xl text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30">
                                            <i class="fas fa-trash-alt"></i>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-border/50 px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <span>Menampilkan {{ paginationStart }} - {{ paginationEnd }} dari {{ filteredTransactions.length }} transaksi</span>
                    <div class="flex items-center gap-1">
                        <Button type="button" variant="outline" size="sm" data-click-feedback="none" class="h-8 rounded-lg px-3 text-xs" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
                            Sebelumnya
                        </Button>
                        <Button
                            v-for="page in visiblePages"
                            :key="page"
                            type="button"
                            size="sm"
                            class="h-8 w-8 rounded-lg p-0 text-xs"
                            :variant="page === currentPage ? 'default' : 'outline'"
                            data-click-feedback="none"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </Button>
                        <Button type="button" variant="outline" size="sm" data-click-feedback="none" class="h-8 rounded-lg px-3 text-xs" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="fixed left-[-10000px] top-0 w-[1100px] bg-white p-8 text-black">
            <div ref="exportPdfRef">
                <h1 class="mb-1 text-xl font-bold">Laporan Penjualan</h1>
                <p class="mb-5 text-xs">Periode: {{ filterLabel }} | Jumlah transaksi: {{ filteredTransactions.length }}</p>
                <p class="mb-3 text-xs">Pengeluaran: Rp {{ formatRupiah(totalExpenses) }} | Pendapatan bersih: Rp {{ formatRupiah(netProfit) }}</p>
                <h2 class="mb-2 text-sm font-bold">Performa per Jenis Usaha</h2>
                <table class="mb-5 w-full border-collapse text-xs">
                    <thead>
                        <tr>
                            <th class="border border-gray-400 p-2 text-left">Jenis Usaha</th>
                            <th class="border border-gray-400 p-2 text-right">Omzet</th>
                            <th class="border border-gray-400 p-2 text-right">Modal / HPP</th>
                            <th class="border border-gray-400 p-2 text-right">Laba</th>
                            <th class="border border-gray-400 p-2 text-right">Margin</th>
                            <th class="border border-gray-400 p-2 text-right">Transaksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="category in businessCategoryRows" :key="`pdf-category-${category.key}`">
                            <td class="border border-gray-300 p-2">{{ category.label }}</td>
                            <td class="border border-gray-300 p-2 text-right">Rp {{ formatRupiah(category.omzet) }}</td>
                            <td class="border border-gray-300 p-2 text-right">Rp {{ formatRupiah(category.modal) }}</td>
                            <td class="border border-gray-300 p-2 text-right">Rp {{ formatRupiah(category.laba) }}</td>
                            <td class="border border-gray-300 p-2 text-right">{{ category.margin.toFixed(1) }}%</td>
                            <td class="border border-gray-300 p-2 text-right">{{ category.transactionCount }}</td>
                        </tr>
                    </tbody>
                </table>
                <table class="w-full border-collapse text-xs">
                    <thead>
                        <tr>
                            <th class="border border-gray-400 p-2 text-left">Invoice</th>
                            <th class="border border-gray-400 p-2 text-left">Tanggal</th>
                            <th class="border border-gray-400 p-2 text-left">Customer</th>
                            <th class="border border-gray-400 p-2 text-left">Metode</th>
                            <th class="border border-gray-400 p-2 text-left">Status</th>
                            <th class="border border-gray-400 p-2 text-right">Total Belanja</th>
                            <th class="border border-gray-400 p-2 text-right">Keuntungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="transaction in filteredTransactions" :key="`pdf-${transaction.id}`">
                            <td class="border border-gray-300 p-2">{{ transaction.invoice_number }}</td>
                            <td class="border border-gray-300 p-2">{{ formatDate(transaction.created_at) }}</td>
                            <td class="border border-gray-300 p-2">{{ getCustomerName(transaction) }}</td>
                            <td class="border border-gray-300 p-2">{{ transaction.payment_method }}</td>
                            <td class="border border-gray-300 p-2">{{ transaction.status_bayar || 'lunas' }}</td>
                            <td class="border border-gray-300 p-2 text-right">Rp {{ formatRupiah(transaction.total_price) }}</td>
                            <td class="border border-gray-300 p-2 text-right">Rp {{ formatRupiah(transaction.total_profit) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td colspan="5" class="border border-gray-400 p-2 text-right">TOTAL</td>
                            <td class="border border-gray-400 p-2 text-right">Rp {{ formatRupiah(totalSales) }}</td>
                            <td class="border border-gray-400 p-2 text-right">Rp {{ formatRupiah(totalTransactionProfit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

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
                            <span class="font-bold text-foreground text-sm mt-0.5">{{ selectedTransaction.customer_name || selectedTransaction.customer?.name || 'Cash / Umum' }}</span>
                            <span v-if="selectedTransaction.customer_phone || selectedTransaction.customer?.phone" class="text-[10px] text-muted-foreground block">
                                Telp: {{ selectedTransaction.customer_phone || selectedTransaction.customer?.phone }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block">Catatan / Keterangan:</span>
                            <span class="font-medium text-foreground block mt-0.5 whitespace-pre-line">{{ selectedTransaction.keterangan || '-' }}</span>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4">
                        <div>
                            <p class="text-xs font-bold text-foreground">Penerima Invoice</p>
                            <p class="text-[10px] text-muted-foreground">Bisa diisi untuk customer sekali beli tanpa menyimpan ke master pelanggan.</p>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <input v-model="invoiceRecipientName" type="text" placeholder="Nama penerima invoice" class="h-9 rounded-xl border border-input bg-background px-3 text-xs text-foreground outline-none focus:ring-1 focus:ring-ring" />
                            <input v-model="invoiceRecipientPhone" type="text" placeholder="Nomor telepon (opsional)" class="h-9 rounded-xl border border-input bg-background px-3 text-xs text-foreground outline-none focus:ring-1 focus:ring-ring" />
                        </div>
                        <Button type="button" size="sm" class="rounded-xl" :disabled="isSavingInvoiceRecipient" @click="saveInvoiceRecipient">
                            <i class="fas fa-save text-xs"></i>
                            Simpan Penerima Invoice
                        </Button>
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
                                    <p v-if="item.print_vendor" class="text-[10px] text-orange-500 mt-0.5">Mitra internal: {{ item.print_vendor.name }}</p>
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
                        <span class="text-sm font-medium text-muted-foreground">Total Profit Transaksi:</span>
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
