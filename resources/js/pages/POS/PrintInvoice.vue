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
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const printInvoice = () => {
    window.print();
};

const downloadInvoicePDF = () => {
    const element = document.querySelector('.print-container');
    if (!element) return;

    const opt = {
        margin:       0,
        filename:     `Invoice-${props.transaction.invoice_number}.pdf`,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { 
            scale: 2.5, 
            useCORS: true, 
            allowTaint: true,
            logging: false
        },
        jsPDF:        { unit: 'mm', format: 'a5', orientation: 'landscape' }
    };

    try {
        html2pdf().set(opt).from(element).save();
    } catch (e) {
        console.error("html2pdf failed:", e);
        alert("Gagal mengunduh PDF: " + e.message);
    }
};

const downloadInvoiceJPG = () => {
    const element = document.querySelector('.print-container');
    if (!element) return;

    try {
        html2canvas(element, {
            scale: 2.5,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff'
        }).then((canvas) => {
            const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
            const link = document.createElement('a');
            link.download = `Invoice-${props.transaction.invoice_number}.jpg`;
            link.href = dataUrl;
            link.click();
        }).catch((err) => {
            console.error("html2canvas generation failed:", err);
            alert("Gagal membuat JPG: " + err.message);
        });
    } catch (e) {
        console.error("html2canvas execution failed:", e);
        alert("Gagal membuat JPG: " + e.message);
    }
};

const closeWindow = () => {
    window.close();
};
</script>

<template>
    <Head>
        <title>Invoice - {{ transaction.invoice_number }}</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </Head>

    <!-- PREMIUM TOPBAR (Hanya terlihat di layar browser, otomatis tersembunyi saat diprint) -->
    <div class="bg-neutral-900 border-b border-neutral-800 py-3.5 px-6 flex justify-between items-center text-white sticky top-0 z-[9999] shadow-md media-no-print">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center font-bold text-sm shadow-inner">
                <i class="fas fa-file-invoice text-white text-xs"></i>
            </div>
            <div class="text-left">
                <h4 class="font-black text-xs tracking-wide uppercase text-white">Pratinjau Struk</h4>
                <p class="text-[10px] text-neutral-400 font-mono mt-0.5">{{ transaction.invoice_number }}</p>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <button 
                @click="printInvoice" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 border border-neutral-700 hover:border-neutral-600 text-xs font-bold text-white transition-all shadow-xs cursor-pointer h-8 animate-pulse"
            >
                <i class="fas fa-print text-indigo-400"></i>
                Cetak Struk
            </button>
            
            <button 
                @click="downloadInvoicePDF" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white transition-all shadow-sm cursor-pointer h-8"
            >
                <i class="fas fa-file-pdf text-red-400"></i>
                Unduh PDF
            </button>

            <button 
                @click="downloadInvoiceJPG" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white transition-all shadow-sm cursor-pointer h-8"
            >
                <i class="fas fa-file-image text-emerald-300"></i>
                Unduh JPG
            </button>

            <button 
                @click="closeWindow" 
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-neutral-800 hover:bg-red-900/50 hover:text-red-400 border border-neutral-700 hover:border-red-900/30 text-xs font-medium text-neutral-400 transition-all cursor-pointer"
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
        <div class="border-b inv-border-black pb-3 mb-3 text-left relative z-10">
            <h1 class="text-sm font-black uppercase tracking-tight leading-none">{{ profile.store_name }}</h1>
            <p class="text-[9px] inv-text-muted mt-1 leading-normal whitespace-pre-line">{{ profile.address }}</p>
            <p class="text-[9px] inv-text-muted mt-0.5"><i class="fas fa-phone mr-1"></i> {{ profile.phone }}</p>
            
            <div class="mt-3 flex flex-col items-start gap-1">
                <span class="inline-block bg-black text-white text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded">INVOICE</span>
                <div class="text-[10px] font-mono font-bold inv-text-darker">{{ transaction.invoice_number }}</div>
                <div class="text-[9px] inv-text-light">{{ formatDate(transaction.created_at) }}</div>
            </div>
        </div>

        <!-- META: PELANGGAN -->
        <div class="text-[9px] inv-bg-card p-2 rounded-lg mb-3 relative z-10">
            <span class="inv-text-light font-semibold">Pelanggan:</span>
            <span class="font-bold inv-text-darker ml-1">{{ transaction.customer_name || customer?.name || 'Cash / Umum' }}</span>
            <span v-if="transaction.customer_phone || customer?.phone" class="inv-text-muted ml-1">({{ transaction.customer_phone || customer?.phone }})</span>
        </div>

        <!-- ITEMS TABLE -->
        <table class="w-full text-left border-collapse text-[9px] mb-3 relative z-10">
            <thead>
                <tr class="inv-table-header font-bold uppercase inv-text-muted">
                    <th class="py-1.5">Nama Produk / Jasa</th>
                    <th class="py-1.5 text-center w-8">Qty</th>
                    <th class="py-1.5 text-right w-20">Harga</th>
                    <th class="py-1.5 text-right w-20 pr-1">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in transaction.items" :key="item.id" class="align-top inv-table-row">
                    <td class="py-1.5">
                        <div class="font-bold inv-text-darkest leading-tight">{{ item.item_name }}</div>
                        <div v-if="item.metadata && item.metadata.detail" class="text-[8px] inv-text-light italic mt-0.5">
                            {{ item.metadata.detail }}
                        </div>
                    </td>
                    <td class="py-1.5 text-center font-semibold inv-text-dark">{{ parseFloat(item.quantity) }}</td>
                    <td class="py-1.5 text-right font-mono inv-text-muted">Rp {{ formatRupiah(item.selling_price) }}</td>
                    <td class="py-1.5 text-right font-bold font-mono inv-text-darkest pr-1">Rp {{ formatRupiah(item.subtotal_price) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- PAYMENT STATUS + TOTALS (dua kolom sejajar) -->
        <div class="flex justify-between items-start gap-4 border-t inv-border-medium pt-3 mb-3 relative z-10">
            <!-- Status Pembayaran -->
            <div class="inv-bg-card p-2 rounded-lg text-center" style="min-width:110px">
                <span class="text-[8px] inv-text-light font-semibold block uppercase tracking-wider mb-1">Status</span>
                <span class="inline-block text-[8px] font-black px-2 py-0.5 rounded tracking-wide uppercase"
                      :class="transaction.status_bayar === 'lunas' || !transaction.status_bayar ? 'inv-badge-lunas' : (transaction.status_bayar === 'dp' ? 'inv-badge-dp' : 'inv-badge-piutang')">
                    {{ transaction.status_bayar === 'lunas' || !transaction.status_bayar ? 'LUNAS' : (transaction.status_bayar === 'dp' ? 'DP' : 'PIUTANG') }}
                </span>
                <span class="text-[8px] inv-text-muted block mt-1">{{ transaction.payment_method_master?.name || transaction.payment_method }}</span>
            </div>

            <!-- Rincian Billing -->
            <div class="flex-1 space-y-1 text-[9px]">
                <div class="flex justify-between inv-text-muted">
                    <span>Total Belanja:</span>
                    <span class="font-mono">Rp {{ formatRupiah(transaction.total_price) }}</span>
                </div>
                <template v-if="transaction.status_bayar !== 'lunas' && transaction.status_bayar">
                    <div class="flex justify-between inv-text-muted">
                        <span>Sudah Dibayar:</span>
                        <span class="font-mono" style="color:#059669">Rp {{ formatRupiah(transaction.jumlah_dibayar) }}</span>
                    </div>
                    <div class="flex justify-between inv-text-muted">
                        <span>Sisa Tagihan:</span>
                        <span class="font-mono" style="color:#dc2626">Rp {{ formatRupiah(transaction.sisa_tagihan) }}</span>
                    </div>
                </template>
                <template v-if="transaction.kembalian > 0">
                    <div class="flex justify-between inv-text-muted">
                        <span>Uang Diterima:</span>
                        <span class="font-mono">Rp {{ formatRupiah(transaction.uang_diterima) }}</span>
                    </div>
                    <div class="flex justify-between inv-text-muted">
                        <span>Kembalian:</span>
                        <span class="font-mono" style="color:#4f46e5">Rp {{ formatRupiah(transaction.kembalian) }}</span>
                    </div>
                </template>
                <div class="flex justify-between border-t inv-border-medium pt-1.5 text-[11px] font-black inv-text-darker">
                    <span>TOTAL DIBAYAR:</span>
                    <span class="font-mono inv-text-indigo">Rp {{ formatRupiah(transaction.jumlah_dibayar) }}</span>
                </div>
            </div>
        </div>

        <!-- CATATAN -->
        <div v-if="transaction.keterangan" class="p-2 inv-bg-amber rounded-lg text-[9px] leading-tight mb-3 relative z-10">
            <span class="font-bold"><i class="fas fa-sticky-note mr-0.5"></i> Catatan:</span> {{ transaction.keterangan }}
        </div>

        <!-- FOOTER: TERIMA KASIH & TANDA TANGAN -->
        <div class="flex justify-between items-end border-t inv-border-light pt-2 mt-2 relative z-10">
            <div class="text-[8px] inv-text-light">
                <p>Terima kasih atas kepercayaan Anda.</p>
                <p>Nota ini merupakan bukti pembayaran sah.</p>
            </div>
            <div class="text-center" style="min-width:100px">
                <p class="text-[8px] font-semibold inv-text-light">Hormat Kami,</p>
                <div class="h-10 flex items-center justify-center my-0.5">
                    <img v-if="profile.signature_path" :src="profile.signature_path" alt="TTD" class="max-h-full max-w-full object-contain" />
                    <div v-else class="w-16 border-b border-dashed inv-border-dark h-6"></div>
                </div>
                <p class="text-[9px] font-black inv-text-darker leading-none">{{ profile.store_name }}</p>
            </div>
        </div>

    </div>
</template>

<style>
/* Reset global styles khusus untuk halaman pratinjau invoice */
html, body {
    background-color: #f3f4f6 !important; /* Elegant light-gray on web browser */
    color: black !important;
    margin: 0;
    padding: 0;
}

/* Color overrides to completely bypass Tailwind's oklch issues in html2canvas */
.inv-bg-white { background-color: #ffffff !important; }
.inv-text-black { color: #000000 !important; }
.inv-text-dark { color: #1f2937 !important; }
.inv-text-darker { color: #111827 !important; }
.inv-text-darkest { color: #030712 !important; }
.inv-text-muted { color: #4b5563 !important; }
.inv-text-light { color: #9ca3af !important; }
.inv-border-black { border-color: #000000 !important; }
.inv-border-light { border-color: #f3f4f6 !important; }
.inv-border-medium { border-color: #e5e7eb !important; }
.inv-border-dark { border-color: #d1d5db !important; }

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
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.12), 0 8px 10px -6px rgba(0,0,0,0.08);
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
    .bg-dark, header, .navbar, button, nav, aside {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
    }

    html, body {
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
