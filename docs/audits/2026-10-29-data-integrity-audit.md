# Audit Integritas Data Usaha

Status: `DONE`

Tujuan: menyelaraskan alur data aplikasi dengan kebutuhan Era Digital: omset, HPP aktual, laba, pengeluaran rutin, saldo digital yang berputar, modal persediaan, stok, piutang, dan customer digital.

## Checklist pekerjaan

- [x] Memperkuat stok dan stock movement penjualan.
- [x] Memperbaiki edit/hapus restock agar tidak merusak stok dan ledger.
- [x] Memperbaiki pembayaran piutang agar tidak overpayment.
- [x] Menyamakan keuntungan ledger digital dengan biaya provider aktual.
- [x] Menambahkan konsistensi dan keunikan akun token/HP.
- [x] Mencegah penghapusan produk yang masih memiliki histori modal.
- [x] Menambah regression test untuk setiap perbaikan.
- [x] Menjalankan full test, formatter, dan build frontend.

## Keputusan bisnis yang dipertahankan

- Restock/pembelian stok adalah modal dan arus kas, bukan biaya pengurang laba.
- Pengeluaran rutin mengurangi laba bersih.
- Saldo digital memiliki `contributed_capital` terpisah dari `current_balance`.
- PPOB memakai `provider_cost` sebagai HPP aktual; nominal customer tetap menjadi omzet.
- Modal persediaan tetap ditampilkan per kategori, dengan detail produk pada ledger.

## Catatan kelanjutan

Jika pekerjaan terhenti, lanjutkan dari checklist dan perbarui status serta hasil test di dokumen ini. Jangan menghapus data produksi untuk memperbaiki duplikasi tanpa backup dan aturan resolusi yang jelas.

## Implementasi

- Penjualan fisik memakai harga master produk, memvalidasi stok dengan lock, dan mencatat movement `out`.
- Pembatalan penjualan dan pembatalan/edit restock memakai movement pembalik agar histori tetap dapat direkonstruksi.
- Expense restock memiliki referensi langsung ke purchase; backfill legacy hanya dilakukan untuk pasangan yang tidak ambigu.
- Restock hanya menerima kategori fisik, perubahan produk mengembalikan stok produk lama, dan penghapusan restock ditolak jika stoknya sudah terjual.
- Pembayaran piutang memakai lock transaksi dan menolak nominal di atas sisa tagihan.
- Saldo provider dan debit manual modal tidak boleh menjadi negatif.
- Akun token/HP dinormalisasi dan diberi unique constraint `(type, account_number)`. Migration akan berhenti jika menemukan duplikasi production yang perlu diputuskan manual.

## Validasi

- Pest: `136 passed`, `2 skipped`, `550 assertions`.
- Targeted Pest area audit: `20 passed`, `103 assertions` sebelum test tambahan terakhir; full suite setelahnya lulus.
- Vite build: berhasil.
- Targeted Pint: berhasil.
- Full Prettier check: masih menemukan file lama di luar scope perubahan; file yang disentuh audit tidak diubah oleh formatter global.
