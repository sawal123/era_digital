<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps({
    stats: Object,
    chartData: Array,
    criticalProducts: Array,
});

// Format Rupiah helper
const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID').format(angka);
};

// --- SVG CHART LOGIC (100% PURE REAKTIF SVG) ---
const chartWidth = 600;
const chartHeight = 220;
const paddingX = 40;
const paddingY = 25;

// Find max and min values for vertical scaling
const maxProfit = computed(() => {
    const values = props.chartData.map((d) => d.profit);
    const max = Math.max(...values, 10000); // Fallback ceiling
    return max * 1.15; // 15% top margin
});

const minProfit = computed(() => {
    const values = props.chartData.map((d) => d.profit);
    const min = Math.min(...values, 0); // Scale floor
    return min < 0 ? min * 1.15 : 0;
});

// Calculate coordinate points for the SVG path
const chartPoints = computed(() => {
    if (!props.chartData || props.chartData.length === 0) return [];

    const range = maxProfit.value - minProfit.value;
    const count = props.chartData.length;

    return props.chartData.map((d, i) => {
        // Horizontal scaling
        const x = paddingX + (i * (chartWidth - 2 * paddingX)) / (count - 1);
        // Vertical scaling
        const y =
            chartHeight -
            paddingY -
            ((d.profit - minProfit.value) * (chartHeight - 2 * paddingY)) /
                (range || 1);
        return { x, y, ...d };
    });
});

// Path string for the line
const linePath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    return chartPoints.value.reduce((path, p, i) => {
        return i === 0 ? `M ${p.x} ${p.y}` : `${path} L ${p.x} ${p.y}`;
    }, '');
});

// Path string for the filled area under the line
const areaPath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    const first = chartPoints.value[0];
    const last = chartPoints.value[chartPoints.value.length - 1];
    const baselineY = chartHeight - paddingY;

    return `${linePath.value} L ${last.x} ${baselineY} L ${first.x} ${baselineY} Z`;
});

// Gridlines and horizontal axes labels
const yGridLines = computed(() => {
    const lines = [];
    const range = maxProfit.value - minProfit.value;
    const steps = 4;

    for (let i = 0; i <= steps; i++) {
        const val = minProfit.value + (i * range) / steps;
        const y =
            chartHeight - paddingY - (i * (chartHeight - 2 * paddingY)) / steps;
        lines.push({ y, val });
    }
    return lines;
});

// Interactive Tooltip State
const hoveredPoint = ref(null);
const tooltipX = ref(0);
const tooltipY = ref(0);

const handleMouseMove = (e) => {
    const svg = e.currentTarget;
    const rect = svg.getBoundingClientRect();
    const clientX = e.clientX - rect.left;

    // Scale clientX to SVG internal coordinate system (0 to 600)
    const svgX = (clientX / rect.width) * chartWidth;

    // Find the closest point horizontally
    let closest = chartPoints.value[0];
    let minDist = Math.abs(closest.x - svgX);

    // ─── Flash Notifications ───────────────────────────────────────

    chartPoints.value.forEach((p) => {
        const dist = Math.abs(p.x - svgX);
        if (dist < minDist) {
            minDist = dist;
            closest = p;
        }
    });

    hoveredPoint.value = closest;

    // Convert coordinate to client tooltip position
    tooltipX.value = (closest.x / chartWidth) * rect.width;
    tooltipY.value = (closest.y / chartHeight) * rect.height - 12;
};

const handleMouseLeave = () => {
    hoveredPoint.value = null;
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="font-inter flex flex-col gap-6 p-4 pb-8 md:p-6">
        <!-- Header -->
        <div
            class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Dashboard Bisnis
                </h1>
                <p class="text-sm text-muted-foreground">
                    Analisis ringkasan performa finansial, tren keuntungan
                    bersih, dan status inventori stok toko Anda.
                </p>
            </div>

            <!-- Quick POS Shortcuts -->
            <Link
                href="/pos"
                class="group flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-indigo-700"
            >
                <i
                    class="fas fa-cash-register transition group-hover:scale-110"
                ></i>
                Buka Kasir POS
            </Link>
        </div>

        <!-- 1. KARTU STATISTIK (HARI INI) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <!-- Omset Card -->
            <Link
                href="/reports"
                aria-label="Lihat laporan omset"
                class="group relative transform overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition hover:scale-[1.02] hover:border-emerald-500/20 focus:ring-2 focus:ring-emerald-300 focus:outline-none"
            >
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <span
                            class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Total Omset</span
                        >
                        <h2
                            class="text-xl font-black text-foreground md:text-2xl"
                        >
                            Rp {{ formatRupiah(stats.total_omset) }}
                        </h2>
                    </div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400"
                    >
                        <i class="fas fa-chart-line text-lg"></i>
                    </div>
                </div>
                <div
                    class="mt-4 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                >
                    <span
                        class="flex items-center gap-0.5 rounded-md bg-emerald-500/10 px-1.5 py-0.5 font-bold text-emerald-500"
                        >Hari Ini</span
                    >
                    <span>Ringkasan pendapatan kotor</span>
                </div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-emerald-500 opacity-60 transition group-hover:opacity-100"
                ></div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-emerald-500 opacity-60 transition group-hover:opacity-100"
                ></div>

                <!-- Tooltip -->
                <div class="absolute left-3 top-3 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition transform -translate-y-1 group-hover:translate-y-0 pointer-events-none z-20">
                    <div class="w-56 rounded-xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl border border-white/10 dark:bg-white dark:text-slate-900 dark:border-slate-200">
                        <div class="font-bold text-[12px]">Ringkasan Omset</div>
                        <div class="mt-1 text-[12px]">Total omset hari ini: <span class="font-mono">Rp {{ formatRupiah(stats.total_omset) }}</span></div>
                        <div class="mt-1 text-[11px] text-muted-foreground">Klik untuk membuka laporan dan analisis rinci.</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div
                    class="absolute top-3 right-3 flex gap-2 opacity-0 transition group-hover:opacity-100"
                >
                    <Link
                        href="/reports"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Lihat</Link
                    >
                    <Link
                        href="/pos"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Kasir</Link
                    >
                </div>
            </Link>

            <!-- Modal Card -->
            <Link
                href="/products"
                aria-label="Lihat produk"
                class="group relative transform overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition hover:scale-[1.02] hover:border-indigo-500/20 focus:ring-2 focus:ring-indigo-300 focus:outline-none"
            >
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <span
                            class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Total Modal</span
                        >
                        <h2
                            class="text-xl font-black text-foreground md:text-2xl"
                        >
                            Rp {{ formatRupiah(stats.total_modal) }}
                        </h2>
                    </div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400"
                    >
                        <i class="fas fa-wallet text-lg"></i>
                    </div>
                </div>
                <div
                    class="mt-4 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                >
                    <span
                        class="flex items-center gap-0.5 rounded-md bg-indigo-500/10 px-1.5 py-0.5 font-bold text-indigo-500"
                        >Hari Ini</span
                    >
                    <span>Harga modal pokok produk</span>
                </div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-indigo-500 opacity-60 transition group-hover:opacity-100"
                ></div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-indigo-500 opacity-60 transition group-hover:opacity-100"
                ></div>

                <!-- Tooltip -->
                <div class="absolute left-3 top-3 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition transform -translate-y-1 group-hover:translate-y-0 pointer-events-none z-20">
                    <div class="w-56 rounded-xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl border border-white/10 dark:bg-white dark:text-slate-900 dark:border-slate-200">
                        <div class="font-bold text-[12px]">Ringkasan Modal</div>
                        <div class="mt-1 text-[12px]">Total modal saat ini: <span class="font-mono">Rp {{ formatRupiah(stats.total_modal) }}</span></div>
                        <div class="mt-1 text-[11px] text-muted-foreground">Cepat ke daftar produk atau tambah produk baru.</div>
                    </div>
                </div>

                <div
                    class="absolute top-3 right-3 flex gap-2 opacity-0 transition group-hover:opacity-100"
                >
                    <Link
                        href="/products"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Daftar</Link
                    >
                    <Link
                        href="/products/create"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Tambah</Link
                    >
                </div>
            </Link>

            <!-- Pengeluaran Card -->
            <Link
                href="/expenses"
                aria-label="Lihat pengeluaran"
                class="group relative transform overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition hover:scale-[1.02] hover:border-rose-500/20 focus:ring-2 focus:ring-rose-300 focus:outline-none"
            >
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <span
                            class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Biaya Pengurang Laba</span
                        >
                        <h2
                            class="text-xl font-black text-foreground md:text-2xl"
                        >
                            Rp {{ formatRupiah(stats.total_pengeluaran) }}
                        </h2>
                    </div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400"
                    >
                        <i class="fas fa-file-invoice-dollar text-lg"></i>
                    </div>
                </div>
                <div
                    class="mt-4 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                >
                    <span
                        class="flex items-center gap-0.5 rounded-md bg-rose-500/10 px-1.5 py-0.5 font-bold text-rose-500"
                        >Hari Ini</span
                    >
                    <span>HPP tambahan + operasional rutin</span>
                </div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-rose-500 opacity-60 transition group-hover:opacity-100"
                ></div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-rose-500 opacity-60 transition group-hover:opacity-100"
                ></div>

                <!-- Tooltip -->
                <div class="absolute left-3 top-3 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition transform -translate-y-1 group-hover:translate-y-0 pointer-events-none z-20">
                    <div class="w-56 rounded-xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl border border-white/10 dark:bg-white dark:text-slate-900 dark:border-slate-200">
                        <div class="font-bold text-[12px]">Ringkasan Pengeluaran</div>
                        <div class="mt-1 text-[12px]">Total pengeluaran hari ini: <span class="font-mono">Rp {{ formatRupiah(stats.total_pengeluaran) }}</span></div>
                        <div class="mt-1 text-[11px] text-muted-foreground">Kelola pengeluaran atau tambahkan transaksi baru.</div>
                    </div>
                </div>

                <div
                    class="absolute top-3 right-3 flex gap-2 opacity-0 transition group-hover:opacity-100"
                >
                    <Link
                        href="/expenses"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Lihat</Link
                    >
                    <Link
                        href="/expenses/create"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Tambah</Link
                    >
                </div>
            </Link>

            <!-- Keuntungan Bersih Card -->
            <Link
                href="/reports"
                aria-label="Lihat laporan keuntungan"
                class="group relative transform overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition hover:scale-[1.02] hover:border-cyan-500/20 focus:ring-2 focus:ring-cyan-300 focus:outline-none"
            >
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <span
                            class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Keuntungan Bersih</span
                        >
                        <h2
                            class="text-xl font-black text-foreground md:text-2xl"
                        >
                            Rp {{ formatRupiah(stats.keuntungan_bersih) }}
                        </h2>
                    </div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-600 dark:bg-cyan-500/20 dark:text-cyan-400"
                    >
                        <i class="fas fa-funnel-dollar text-lg"></i>
                    </div>
                </div>
                <div
                    class="mt-4 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                >
                    <span
                        class="flex items-center gap-0.5 rounded-md bg-cyan-500/10 px-1.5 py-0.5 font-bold text-cyan-500"
                        >Hari Ini</span
                    >
                    <span>Laba kotor - HPP tambahan - operasional</span>
                </div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-cyan-500 opacity-60 transition group-hover:opacity-100"
                ></div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-cyan-500 opacity-60 transition group-hover:opacity-100"
                ></div>

                <!-- Tooltip -->
                <div class="absolute left-3 top-3 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition transform -translate-y-1 group-hover:translate-y-0 pointer-events-none z-20">
                    <div class="w-56 rounded-xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl border border-white/10 dark:bg-white dark:text-slate-900 dark:border-slate-200">
                        <div class="font-bold text-[12px]">Ringkasan Keuntungan</div>
                        <div class="mt-1 text-[12px]">Keuntungan bersih hari ini: <span class="font-mono">Rp {{ formatRupiah(stats.keuntungan_bersih) }}</span></div>
                        <div class="mt-1 text-[11px] text-muted-foreground">Lihat rincian laba dan sumber utama.</div>
                    </div>
                </div>

                <div
                    class="absolute top-3 right-3 flex gap-2 opacity-0 transition group-hover:opacity-100"
                >
                    <Link
                        href="/reports"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Lihat</Link
                    >
                    <Link
                        href="/reports?view=profit-detail"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Rincian</Link
                    >
                </div>
            </Link>

            <!-- Admin Fee PPOB Card -->
            <Link
                href="/reports"
                aria-label="Lihat PPOB"
                class="group relative transform overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition hover:scale-[1.02] hover:border-violet-500/20 focus:ring-2 focus:ring-violet-300 focus:outline-none"
            >
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <span
                            class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Admin Fee PPOB</span
                        >
                        <h2
                            class="text-xl font-black text-foreground md:text-2xl"
                        >
                            Rp {{ formatRupiah(stats.keuntungan_ppob ?? 0) }}
                        </h2>
                    </div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/20 dark:text-violet-400"
                    >
                        <i class="fas fa-bolt text-lg"></i>
                    </div>
                </div>
                <div
                    class="mt-4 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                >
                    <span
                        class="flex items-center gap-0.5 rounded-md bg-violet-500/10 px-1.5 py-0.5 font-bold text-violet-500"
                        >Hari Ini</span
                    >
                    <span>Profit dari biaya admin digital</span>
                </div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-violet-500 opacity-60 transition group-hover:opacity-100"
                ></div>
                <div
                    class="absolute right-0 bottom-0 left-0 h-1 bg-violet-500 opacity-60 transition group-hover:opacity-100"
                ></div>

                <!-- Tooltip -->
                <div class="absolute left-3 top-3 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition transform -translate-y-1 group-hover:translate-y-0 pointer-events-none z-20">
                    <div class="w-56 rounded-xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl border border-white/10 dark:bg-white dark:text-slate-900 dark:border-slate-200">
                        <div class="font-bold text-[12px]">Admin Fee PPOB</div>
                        <div class="mt-1 text-[12px]">Pendapatan PPOB: <span class="font-mono">Rp {{ formatRupiah(stats.keuntungan_ppob ?? 0) }}</span></div>
                        <div class="mt-1 text-[11px] text-muted-foreground">Cepat filter laporan PPOB.</div>
                    </div>
                </div>

                <div
                    class="absolute top-3 right-3 flex gap-2 opacity-0 transition group-hover:opacity-100"
                >
                    <Link
                        href="/reports"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Lihat</Link
                    >
                    <Link
                        href="/reports?filter=ppob"
                        class="rounded-md bg-white/5 px-2 py-1 text-[11px] hover:bg-white/10"
                        >Filter</Link
                    >
                </div>
            </Link>
        </div>

        <!-- 2. GRAFIK PERFORMA & 3. STOK KRITIS GRID -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- GRAFIK TREN KEUNTUNGAN (7 HARI TERAKHIR) -->
            <div
                class="flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-xs md:p-6 lg:col-span-2"
            >
                <div
                    class="mb-4 flex items-center justify-between border-b border-border/60 pb-4"
                >
                    <div>
                        <h3 class="text-base font-bold text-foreground">
                            Tren Keuntungan Bersih
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Grafik laba bersih 7 hari terakhir setelah HPP
                            tambahan dan operasional rutin.
                        </p>
                    </div>
                    <span
                        class="flex items-center gap-1.5 rounded-lg border border-indigo-200/40 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-600 dark:border-indigo-900/30 dark:bg-indigo-950/40 dark:text-indigo-400"
                    >
                        <i class="fas fa-history text-[10px]"></i> 7 Hari
                        Terakhir
                    </span>
                </div>

                <!-- SVG Area Chart Container -->
                <div class="relative mt-4 w-full overflow-hidden">
                    <svg
                        :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                        class="h-auto w-full cursor-crosshair overflow-visible select-none"
                        @mousemove="handleMouseMove"
                        @mouseleave="handleMouseLeave"
                    >
                        <defs>
                            <!-- Line Gradient -->
                            <linearGradient
                                id="lineGrad"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="0"
                            >
                                <stop offset="0%" stop-color="#6366f1" />
                                <stop offset="100%" stop-color="#06b6d4" />
                            </linearGradient>
                            <!-- Fill Area Gradient -->
                            <linearGradient
                                id="areaGrad"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stop-color="#6366f1"
                                    stop-opacity="0.25"
                                />
                                <stop
                                    offset="100%"
                                    stop-color="#6366f1"
                                    stop-opacity="0.0"
                                />
                            </linearGradient>
                        </defs>

                        <!-- Y-Axis Gridlines & Labels -->
                        <g
                            v-for="line in yGridLines"
                            :key="line.y"
                            class="opacity-30 dark:opacity-20"
                        >
                            <line
                                :x1="paddingX"
                                :y1="line.y"
                                :x2="chartWidth - paddingX"
                                :y2="line.y"
                                stroke="currentColor"
                                stroke-width="1"
                                stroke-dasharray="4 4"
                                class="text-border"
                            />
                            <text
                                :x="paddingX - 10"
                                :y="line.y + 4"
                                text-anchor="end"
                                class="fill-muted-foreground font-mono text-[9px] font-semibold"
                            >
                                {{
                                    line.val >= 1000
                                        ? line.val / 1000 + 'k'
                                        : line.val
                                }}
                            </text>
                        </g>

                        <!-- Filled Gradient Area -->
                        <path :d="areaPath" fill="url(#areaGrad)" />

                        <!-- Main Bezier Line -->
                        <path
                            :d="linePath"
                            fill="none"
                            stroke="url(#lineGrad)"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        <!-- Horizontal X-Axis Day Labels -->
                        <g
                            v-for="p in chartPoints"
                            :key="p.date"
                            class="text-muted-foreground"
                        >
                            <text
                                :x="p.x"
                                :y="chartHeight - 6"
                                text-anchor="middle"
                                class="fill-muted-foreground text-[9px] font-bold tracking-wider uppercase"
                            >
                                {{ p.day.substring(0, 3) }}
                            </text>
                            <circle
                                :cx="p.x"
                                :cy="p.y"
                                r="4"
                                fill="currentColor"
                                class="cursor-pointer text-indigo-600 dark:text-indigo-400"
                                :class="{
                                    'r-6 stroke-white stroke-2 dark:stroke-slate-900':
                                        hoveredPoint &&
                                        hoveredPoint.date === p.date,
                                }"
                            />
                        </g>

                        <!-- Glowing Active Dot on Hover -->
                        <circle
                            v-if="hoveredPoint"
                            :cx="hoveredPoint.x"
                            :cy="hoveredPoint.y"
                            r="7"
                            fill="#6366f1"
                            stroke="#ffffff"
                            stroke-width="2.5"
                            class="shadow-lg drop-shadow-[0_2px_4px_rgba(99,102,241,0.5)] filter"
                        />
                    </svg>

                    <!-- CUSTOM FLOATING HTML TOOLTIP -->
                    <div
                        v-if="hoveredPoint"
                        class="pointer-events-none absolute z-10 flex flex-col gap-0.5 rounded-xl border border-white/10 bg-slate-900 px-3 py-2 text-xs text-white shadow-xl transition-all duration-75 dark:border-slate-200 dark:bg-white dark:text-slate-900"
                        :style="{
                            left: `${tooltipX}px`,
                            top: `${tooltipY}px`,
                            transform: 'translate(-50%, -100%)',
                        }"
                    >
                        <span
                            class="text-[9px] font-black tracking-wider text-indigo-400 uppercase dark:text-indigo-600"
                            >{{ hoveredPoint.day }},
                            {{ hoveredPoint.date }}</span
                        >
                        <span class="font-mono text-[13px] font-black"
                            >Rp {{ formatRupiah(hoveredPoint.profit) }}</span
                        >
                        <span
                            v-if="hoveredPoint.ppob_profit > 0"
                            class="text-[10px] font-semibold text-violet-400 dark:text-violet-600"
                            >⚡ PPOB: Rp
                            {{ formatRupiah(hoveredPoint.ppob_profit) }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- WIDGET PERINGATAN STOK (STOK KRITIS) -->
            <div
                class="flex flex-col rounded-2xl border border-border bg-card p-5 shadow-xs md:p-6"
            >
                <div class="mb-4 border-b border-border/60 pb-4">
                    <h3 class="text-base font-bold text-foreground">
                        Peringatan Stok Kritis
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Daftar produk fisik dengan stok mendekati batas minimum.
                    </p>
                </div>

                <div class="flex flex-1 flex-col justify-center gap-4">
                    <!-- CASE: NO CRITICAL STOCKS -->
                    <div
                        v-if="criticalProducts.length === 0"
                        class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-emerald-500/20 bg-emerald-500/5 py-8 text-center text-emerald-600 dark:text-emerald-400"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10 text-lg shadow-inner"
                        >
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold">Semua stok aman!</p>
                            <p class="mt-0.5 text-xs opacity-80">
                                Seluruh produk fisik terisi di atas batas
                                minimum.
                            </p>
                        </div>
                    </div>

                    <!-- CASE: LIST OF CRITICAL PRODUCTS -->
                    <div v-else class="space-y-4">
                        <div
                            v-for="p in criticalProducts"
                            :key="p.id"
                            class="flex items-center gap-3 rounded-xl border border-border/60 bg-muted/20 p-3 transition hover:bg-muted/30"
                        >
                            <!-- Product Image / Fallback Icon -->
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-background"
                            >
                                <img
                                    v-if="p.image_path"
                                    :src="p.image_path"
                                    class="h-full w-full object-cover"
                                />
                                <i
                                    v-else
                                    class="fas fa-box text-sm text-indigo-500"
                                ></i>
                            </div>

                            <!-- Product Info & Progress bar -->
                            <div class="min-w-0 flex-1">
                                <div
                                    class="mb-1 flex items-start justify-between"
                                >
                                    <h4
                                        class="max-w-[130px] truncate text-xs font-bold text-foreground"
                                        :title="p.name"
                                    >
                                        {{ p.name }}
                                    </h4>
                                    <span
                                        class="font-mono text-xs font-black"
                                        :class="
                                            p.stock <= 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-amber-600 dark:text-amber-400'
                                        "
                                    >
                                        {{ parseFloat(p.stock) }} /
                                        {{ p.min_stock }} {{ p.unit }}
                                    </span>
                                </div>

                                <!-- Progress Bar Indicator -->
                                <div
                                    class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="
                                            p.stock <= 0
                                                ? 'bg-red-500'
                                                : 'bg-amber-500'
                                        "
                                        :style="{
                                            width: `${Math.min((p.stock / (p.min_stock || 1)) * 100, 100)}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Button Action to restock -->
                        <Link
                            href="/purchases"
                            class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl border border-border bg-muted/40 py-2.5 text-xs font-bold text-foreground transition hover:bg-muted/80"
                        >
                            <i class="fas fa-plus-circle text-indigo-500"></i>
                            Belanja Restock Barang
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Glowing circles animation on SVG nodes */
circle {
    transition:
        r 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275),
        fill 0.2s;
}
circle:hover {
    r: 7px;
    fill: #06b6d4;
}
</style>
