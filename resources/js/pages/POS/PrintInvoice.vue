<script setup>
import { Head } from '@inertiajs/vue3';
import html2canvas from 'html2canvas';
import html2pdf from 'html2pdf.js';

defineOptions({
    layout: null,
});

const props = defineProps({
    transaction: Object,
    profile: Object,
    customer: Object,
});

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

const formatNumber = (value) => {
    const n = Number(value || 0);

    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(
        n,
    );
};

// Area-based item (spanduk dll.): metadata berisi length/width.
// Jika metadata belum tersedia (transaksi lama) kembalikan null -> fallback tampilan lama.
const getItemAreaInfo = (item) => {
    const md = item.metadata || {};

    if (md.length && md.width) {
        return {
            size: `Ukuran: ${formatNumber(md.length)} × ${formatNumber(md.width)} m`,
        };
    }

    return null;
};

// Ambil catatan item: prioritas metadata.note (baru), fallback metadata.detail (legacy).
// Untuk legacy, hilangkan prefix ukuran agar tidak tampil ganda.
const getItemNote = (item) => {
    const md = item.metadata || {};

    // 1. metadata.note (transaksi baru)
    if (md.note && String(md.note).trim()) {
        return String(md.note).trim();
    }

    // 2. Fallback: metadata.detail (transaksi lama area)
    const detail = String(md.detail || '').trim();

    if (!detail) {
        return null;
    }

    // Format legacy area: "Ukuran: 1 x 1 m - catatan" / "Ukuran: 1.5x2 m - catatan" /
    // "Ukuran: 1,5 x 2 m - catatan" / "Ukuran: 1 × 1 m — catatan"
    const noteMatch = detail.match(
        /^Ukuran:\s*\d+(?:[.,]\d+)?\s*[×x]\s*\d+(?:[.,]\d+)?\s*m\s*[-–—]\s*(.+)$/iu,
    );

    if (noteMatch && noteMatch[1] && noteMatch[1].trim()) {
        return noteMatch[1].trim();
    }

    // 3. Fallback: detail bukan format ukuran → tampilkan sebagai catatan bebas
    if (!/^Ukuran:/.test(detail)) {
        return detail;
    }

    return null;
};

const printInvoice = () => {
    window.print();
};

const downloadInvoicePDF = () => {
    const element = document.querySelector('.print-container');

    if (!element) {
        return;
    }

    const opt = {
        margin: 0,
        filename: `Invoice-${props.transaction.invoice_number}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 2.5,
            useCORS: true,
            allowTaint: true,
            logging: false,
        },
        jsPDF: { unit: 'mm', format: 'a5', orientation: 'landscape' },
    };

    try {
        html2pdf().set(opt).from(element).save();
    } catch (e) {
        console.error('html2pdf failed:', e);
        alert('Gagal mengunduh PDF: ' + e.message);
    }
};

const downloadInvoiceJPG = () => {
    const element = document.querySelector('.print-container');

    if (!element) {
        return;
    }

    try {
        html2canvas(element, {
            scale: 2.5,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
        })
            .then((canvas) => {
                const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
                const link = document.createElement('a');
                link.download = `Invoice-${props.transaction.invoice_number}.jpg`;
                link.href = dataUrl;
                link.click();
            })
            .catch((err) => {
                console.error('html2canvas generation failed:', err);
                alert('Gagal membuat JPG: ' + err.message);
            });
    } catch (e) {
        console.error('html2canvas execution failed:', e);
        alert('Gagal membuat JPG: ' + e.message);
    }
};

const closeWindow = () => {
    window.close();
};
</script>

<template>
    <Head>
        <title>Invoice - {{ transaction.invoice_number }}</title>
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
        />
    </Head>

    <!-- PREMIUM TOPBAR (Hanya terlihat di layar browser, otomatis tersembunyi saat diprint) -->
    <div
        class="media-no-print sticky top-0 z-[9999] flex items-center justify-between border-b border-neutral-800 bg-neutral-900 px-6 py-3.5 text-white shadow-md"
    >
        <div class="flex items-center gap-3">
            <div
                class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold shadow-inner"
            >
                <i class="fas fa-file-invoice text-xs text-white"></i>
            </div>
            <div class="text-left">
                <h4
                    class="text-xs font-black tracking-wide text-white uppercase"
                >
                    Pratinjau Struk
                </h4>
                <p class="mt-0.5 font-mono text-[10px] text-neutral-400">
                    {{ transaction.invoice_number }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button
                @click="printInvoice"
                class="inline-flex h-8 animate-pulse cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition-all hover:border-neutral-600 hover:bg-neutral-700"
            >
                <i class="fas fa-print text-indigo-400"></i>
                Cetak Struk
            </button>

            <button
                @click="downloadInvoicePDF"
                class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-indigo-700"
            >
                <i class="fas fa-file-pdf text-red-400"></i>
                Unduh PDF
            </button>

            <button
                @click="downloadInvoiceJPG"
                class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-700"
            >
                <i class="fas fa-file-image text-emerald-300"></i>
                Unduh JPG
            </button>

            <button
                @click="closeWindow"
                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-700 bg-neutral-800 text-xs font-medium text-neutral-400 transition-all hover:border-red-900/30 hover:bg-red-900/50 hover:text-red-400"
                title="Tutup Halaman"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="print-container inv-bg-white inv-text-black font-sans">
        <!-- Watermark Background Pattern -->
        <div class="watermark-container"></div>

        <!-- HEADER: INFO TOKO & METADATA INVOICE -->
        <div
            class="inv-border-black relative z-10 mb-3 border-b pb-3 text-left"
        >
            <h1
                class="text-sm leading-none font-black tracking-tight uppercase"
            >
                {{ profile.store_name }}
            </h1>
            <p
                class="inv-text-muted mt-1 text-[9px] leading-normal whitespace-pre-line"
            >
                {{ profile.address }}
            </p>
            <p class="inv-text-muted mt-0.5 text-[9px]">
                <i class="fas fa-phone mr-1"></i> {{ profile.phone }}
            </p>

            <div class="mt-3 flex flex-col items-start gap-1">
                <span
                    class="inline-block rounded bg-black px-1.5 py-0.5 text-[8px] font-black tracking-wider text-white uppercase"
                    >INVOICE</span
                >
                <div class="inv-text-darker font-mono text-[10px] font-bold">
                    {{ transaction.invoice_number }}
                </div>
                <div class="inv-text-light text-[9px]">
                    {{ formatDate(transaction.created_at) }}
                </div>
            </div>
        </div>

        <!-- META: PELANGGAN -->
        <div class="inv-bg-card relative z-10 mb-3 rounded-lg p-2 text-[9px]">
            <span class="inv-text-light font-semibold">Pelanggan:</span>
            <span class="inv-text-darker ml-1 font-bold">{{
                transaction.customer_name || customer?.name || 'Cash / Umum'
            }}</span>
            <span
                v-if="transaction.customer_phone || customer?.phone"
                class="inv-text-muted ml-1"
                >({{ transaction.customer_phone || customer?.phone }})</span
            >
        </div>

        <!-- ITEMS TABLE -->
        <table
            class="relative z-10 mb-3 w-full border-collapse text-left text-[9px]"
        >
            <thead>
                <tr class="inv-table-header inv-text-muted font-bold uppercase">
                    <th class="py-1.5">Nama Produk / Jasa</th>
                    <th class="w-8 py-1.5 text-center">Qty</th>
                    <th class="w-20 py-1.5 text-right">Harga</th>
                    <th class="w-20 py-1.5 pr-1 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in transaction.items"
                    :key="item.id"
                    class="inv-table-row align-top"
                >
                    <td class="py-1.5">
                        <div class="inv-text-darkest leading-tight font-bold">
                            {{ item.item_name }}
                        </div>
                        <div
                            v-if="getItemAreaInfo(item)"
                            class="inv-text-light mt-0.5 text-[8px] leading-snug italic"
                        >
                            {{ getItemAreaInfo(item).size }}<br />
                            <span v-if="getItemNote(item)"
                                >Catatan: {{ getItemNote(item) }}</span
                            >
                        </div>
                        <div
                            v-else-if="item.metadata && item.metadata.detail"
                            class="inv-text-light mt-0.5 text-[8px] italic"
                        >
                            {{ item.metadata.detail }}
                        </div>
                    </td>
                    <td class="inv-text-dark py-1.5 text-center font-semibold">
                        {{ parseFloat(item.quantity) }}
                    </td>
                    <td class="inv-text-muted py-1.5 text-right font-mono">
                        Rp {{ formatRupiah(item.selling_price) }}
                    </td>
                    <td
                        class="inv-text-darkest py-1.5 pr-1 text-right font-mono font-bold"
                    >
                        Rp {{ formatRupiah(item.subtotal_price) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- PAYMENT STATUS + TOTALS (dua kolom sejajar) -->
        <div
            class="inv-border-medium relative z-10 mb-3 flex items-start justify-between gap-4 border-t pt-3"
        >
            <!-- Status Pembayaran -->
            <div
                class="inv-bg-card rounded-lg p-2 text-center"
                style="min-width: 110px"
            >
                <span
                    class="inv-text-light mb-1 block text-[8px] font-semibold tracking-wider uppercase"
                    >Status</span
                >
                <span
                    class="inline-block rounded px-2 py-0.5 text-[8px] font-black tracking-wide uppercase"
                    :class="
                        transaction.status_bayar === 'lunas' ||
                        !transaction.status_bayar
                            ? 'inv-badge-lunas'
                            : transaction.status_bayar === 'dp'
                              ? 'inv-badge-dp'
                              : 'inv-badge-piutang'
                    "
                >
                    {{
                        transaction.status_bayar === 'lunas' ||
                        !transaction.status_bayar
                            ? 'LUNAS'
                            : transaction.status_bayar === 'dp'
                              ? 'DP'
                              : 'PIUTANG'
                    }}
                </span>
                <span class="inv-text-muted mt-1 block text-[8px]">{{
                    transaction.payment_method_master?.name ||
                    transaction.payment_method
                }}</span>
            </div>

            <!-- Rincian Billing -->
            <div class="flex-1 space-y-1 text-[9px]">
                <div class="inv-text-muted flex justify-between">
                    <span>Total Belanja:</span>
                    <span class="font-mono"
                        >Rp {{ formatRupiah(transaction.total_price) }}</span
                    >
                </div>
                <template
                    v-if="
                        transaction.status_bayar !== 'lunas' &&
                        transaction.status_bayar
                    "
                >
                    <div class="inv-text-muted flex justify-between">
                        <span>Sudah Dibayar:</span>
                        <span class="font-mono" style="color: #059669"
                            >Rp
                            {{ formatRupiah(transaction.jumlah_dibayar) }}</span
                        >
                    </div>
                    <div class="inv-text-muted flex justify-between">
                        <span>Sisa Tagihan:</span>
                        <span class="font-mono" style="color: #dc2626"
                            >Rp
                            {{ formatRupiah(transaction.sisa_tagihan) }}</span
                        >
                    </div>
                </template>
                <template v-if="transaction.kembalian > 0">
                    <div class="inv-text-muted flex justify-between">
                        <span>Uang Diterima:</span>
                        <span class="font-mono"
                            >Rp
                            {{ formatRupiah(transaction.uang_diterima) }}</span
                        >
                    </div>
                    <div class="inv-text-muted flex justify-between">
                        <span>Kembalian:</span>
                        <span class="font-mono" style="color: #4f46e5"
                            >Rp {{ formatRupiah(transaction.kembalian) }}</span
                        >
                    </div>
                </template>
                <div
                    class="inv-border-medium inv-text-darker flex justify-between border-t pt-1.5 text-[11px] font-black"
                >
                    <span>TOTAL DIBAYAR:</span>
                    <span class="inv-text-indigo font-mono"
                        >Rp {{ formatRupiah(transaction.jumlah_dibayar) }}</span
                    >
                </div>
            </div>
        </div>

        <!-- CATATAN -->
        <div
            v-if="transaction.keterangan"
            class="inv-bg-amber relative z-10 mb-3 rounded-lg p-2 text-[9px] leading-tight"
        >
            <span class="font-bold"
                ><i class="fas fa-sticky-note mr-0.5"></i> Catatan:</span
            >
            {{ transaction.keterangan }}
        </div>

        <!-- FOOTER: TERIMA KASIH & TANDA TANGAN -->
        <div
            class="inv-border-light relative z-10 mt-2 flex items-end justify-between border-t pt-2"
        >
            <div class="inv-text-light text-[8px]">
                <p>Terima kasih atas kepercayaan Anda.</p>
                <p>Nota ini merupakan bukti pembayaran sah.</p>
            </div>
            <div class="text-center" style="min-width: 100px">
                <p class="inv-text-light text-[8px] font-semibold">
                    Hormat Kami,
                </p>
                <div class="my-0.5 flex h-10 items-center justify-center">
                    <img
                        v-if="profile.signature_path"
                        :src="profile.signature_path"
                        alt="TTD"
                        class="max-h-full max-w-full object-contain"
                    />
                    <div
                        v-else
                        class="inv-border-dark h-6 w-16 border-b border-dashed"
                    ></div>
                </div>
                <p class="inv-text-darker text-[9px] leading-none font-black">
                    {{ profile.store_name }}
                </p>
            </div>
        </div>
    </div>
</template>

<style>
/* Reset global styles khusus untuk halaman pratinjau invoice */
html,
body {
    background-color: #f3f4f6 !important; /* Elegant light-gray on web browser */
    color: black !important;
    margin: 0;
    padding: 0;
}

/* Color overrides to completely bypass Tailwind's oklch issues in html2canvas */
.inv-bg-white {
    background-color: #ffffff !important;
}
.inv-text-black {
    color: #000000 !important;
}
.inv-text-dark {
    color: #1f2937 !important;
}
.inv-text-darker {
    color: #111827 !important;
}
.inv-text-darkest {
    color: #030712 !important;
}
.inv-text-muted {
    color: #4b5563 !important;
}
.inv-text-light {
    color: #9ca3af !important;
}
.inv-border-black {
    border-color: #000000 !important;
}
.inv-border-light {
    border-color: #f3f4f6 !important;
}
.inv-border-medium {
    border-color: #e5e7eb !important;
}
.inv-border-dark {
    border-color: #d1d5db !important;
}

/* Custom Badge Components */
.inv-badge-lunas {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
    border: 1px solid #a7f3d0 !important;
}
.inv-badge-dp {
    background-color: #fef3c7 !important;
    color: #92400e !important;
    border: 1px solid #fde68a !important;
}
.inv-badge-piutang {
    background-color: #fee2e2 !important;
    color: #991b1b !important;
    border: 1px solid #fca5a5 !important;
}

.inv-bg-card {
    background-color: #f9fafb !important;
    border: 1px solid #f3f4f6 !important;
}

.inv-bg-amber {
    background-color: #fffbeb !important;
    border: 1px solid #fde68a !important;
    color: #92400e !important;
}

.inv-text-indigo {
    color: #4f46e5 !important;
}

.inv-table-header {
    border-bottom: 1.5px solid #d1d5db !important;
}
.inv-table-row {
    border-bottom: 1px solid #f3f4f6 !important;
}

/* Watermark Background Pattern Container */
.watermark-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='120'%3E%3Ctext x='90' y='60' fill='%23000000' font-family='sans-serif' font-size='14' font-weight='900' transform='rotate(-25 90 60)' text-anchor='middle'%3EERA DIGITAL%3C/text%3E%3C/svg%3E");
    background-repeat: repeat;
    opacity: 0.05;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

/* Browser preview container - A5 Portrait */
.print-container {
    position: relative;
    width: 148mm;
    height: 210mm;
    background-color: white !important;
    border-radius: 12px;
    box-shadow:
        0 10px 25px -5px rgba(0, 0, 0, 0.12),
        0 8px 10px -6px rgba(0, 0, 0, 0.08);
    padding: 8mm;
    box-sizing: border-box;
    margin: 32px auto;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    font-size: 9px;
}

/* CSS khusus cetak - A5 / A4 Responsive & Compact Margins */
@media print {
    @page {
        size: auto;
        margin: 6mm 8mm; /* Outer margins: 6mm top/bottom, 8mm left/right */
    }

    /* Sembunyikan semua elemen non-invoice */
    .media-no-print,
    .bg-dark,
    header,
    .navbar,
    button,
    nav,
    aside {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
    }

    html,
    body {
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        display: block !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Container invoice: mengisi lebar kertas secara proporsional dengan margin kecil */
    .print-container {
        width: 100% !important;
        height: auto !important;
        max-height: none !important;
        min-height: auto !important;
        margin: 0 !important;
        padding: 4mm 6mm !important; /* Tight elegant inner padding */
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        box-sizing: border-box !important;
        position: relative !important;
        overflow: visible !important;
        page-break-before: avoid !important;
        page-break-after: avoid !important;
        page-break-inside: avoid !important;
    }
}
</style>
