# HELP-001 — Kelengkapan bantuan AI POS Kasir

- Tanggal: 2026-11-02.
- Cakupan: panduan pengguna dan istilah UI; tidak mengubah formula, stok,
  pembayaran, saldo, atau ledger.

## Gejala dan penyebab

Label serta peringatan modal aktual di POS menyebut satu merek mitra. Panduan
AI hanya merangkum POS, cetak, dan digital dalam masing-masing satu paragraf.
Arti label Modal Aktual Mitra, contoh margin, nilai awal/saran modal, saldo yang
dicadangkan keranjang, customer sekali beli, dan kendala penyimpanan belum
diterangkan. Panduan cetak juga menyebut kuantitas pecahan tanpa membedakan
jumlah pcs produk berbasis luas yang wajib bulat.

`PosChatbotService` mengirim seluruh panduan statis sebagai sumber fakta AI.
Menambah kemampuan jawaban membutuhkan penjelasan yang terverifikasi pada
`resources/data/pos-help.php`; chatbot tidak membaca source atau database usaha
saat menjawab. Temuan ini berdasarkan source, bukan rekaman kegagalan jawaban
model pada percakapan pengguna asli.

## Riset dan kemungkinan pertanyaan

Audit dilakukan terhadap route `/pos`, `/reports`, `/receivables`, `/capital`,
model/skema produk dan item transaksi, `PosController`, `ReportController`,
`ReceivableController`, `AreaPricingService`, `ProfitCalculationService`, halaman
POS, serta test PPOB dan harga area. Source aplikasi menjadi penentu perilaku.

Sebagai pembanding kategori pertanyaan, ditinjau
[FAQ resmi majoo](https://majoo.id/panduan-pengguna/detail/381) pada 2 November
2026. FAQ tersebut memuat pertanyaan tentang stok, perbedaan angka penjualan,
pembatalan, invoice, dan pelunasan. Kategori ini dipakai untuk memeriksa cakupan
bantuan; aturan/menu produk lain tidak disalin ke panduan Era Digital.

Panduan memuat **56 contoh pertanyaan dalam 9 topik**. Ini hipotesis kebutuhan
pengguna berdasarkan alur dan potensi kebingungan, bukan peringkat frekuensi
dari survei atau analitik percakapan:

| Topik | Jumlah contoh | Contoh pertanyaan dan cakupan jawaban |
| --- | --- | --- |
| Alur POS | 5 | Membuat transaksi, tiga tab, pencarian, pintasan, HP/Zen |
| Keranjang | 7 | Nota campuran, ubah/hapus jumlah, item tergabung, Timpa/baris baru, draft |
| Customer | 6 | Cash/Umum, penerima sekali beli, akun digital, banyak nomor |
| Pembayaran | 7 | Uang pas, kembalian, DP/piutang, non-tunai, batas satu metode |
| Cetak | 6 | Lembar, meter/sentimeter, rumus luas, pcs bulat, HPP, vendor |
| Digital/PPOB | 5 | Nomor/nama, nominal, admin master, pencatatan vs pengiriman layanan |
| Modal Aktual Mitra | 7 | Arti modal, nominal/admin, saran biaya, contoh margin, margin negatif |
| Saldo digital | 6 | Cadangan keranjang, saldo kurang/habis, modal masuk, DP memakai saldo |
| Kendala/koreksi | 7 | Produk hilang, stok kurang, gagal simpan, transaksi ganda, koreksi nota |

Contoh ilustrasi yang diverifikasi dari formula existing: satu layanan nominal
Rp100.000, admin Rp3.000, biaya mitra Rp98.000 menghasilkan tagihan Rp103.000,
HPP/pemakaian saldo Rp98.000, dan keuntungan kotor Rp5.000. Cetak 2 × 1 m,
2 pcs, tarif Rp25.000/m² menghasilkan total Rp100.000. Contoh ini bukan data
operasional pengguna.

## Perubahan

- Label, peringatan, dan ringkasan POS memakai **Modal Aktual Mitra**; komentar
  controller memakai istilah mitra generik. Riwayat dokumentasi lama tetap utuh.
- Panduan ditambah topik terfokus dengan contoh pertanyaan, definisi, langkah,
  prasyarat, ilustrasi angka, serta keterbatasan yang terbukti dari source.
- Panduan cetak membedakan ukuran desimal dan jumlah pcs bulat. Admin digital
  dijelaskan berasal dari Master Produk, bukan input admin manual di kasir.
- Instruksi jawaban meminta arti kolom, isian, dampak, dan contoh yang relevan
  tanpa menganggap ilustrasi sebagai angka usaha aktual.
- Contoh pertanyaan pada widget ditambah modal aktual dan DP/piutang.

## Validasi

- Sebelum perbaikan, 11 regression case baru belum lulus: 10 gagal dan 1 error
  karena topik/istilah baru belum ada serta artikel cetak belum memiliki daftar
  pertanyaan. Pengujian memakai HTTP fake dan SQLite in-memory.
- Setelah perluasan panduan, command
  `php vendor/pestphp/pest/bin/pest tests/Feature/PosChatbotTest.php tests/Feature/DigitalProviderCostTest.php tests/Feature/PosAreaPricingTest.php`
  lulus: **95 test, 527 assertion**.
- Pint terarah untuk panduan, test chatbot, controller POS, dan service chatbot
  lulus. Prettier terarah untuk POS dan widget chat lulus.
- ESLint widget chat lulus tanpa error/warning. Halaman POS memiliki 101 error;
  perbandingan melalui ESLint terhadap isi `git show HEAD:resources/js/pages/POS/Index.vue`
  mengonfirmasi jumlah, rule, pesan, baris, dan kolom identik dengan versi awal.
- `npm run build` lulus; Vite memberi peringatan ukuran chunk.
  Artefak `public/build` diperbarui mengikuti pola repository yang melacak build.
- `npm run types:check` gagal pada `resources/js/pages/Undangan/Index.vue` yang
  tidak diubah; tidak ada error yang dilaporkan pada file yang diubah.
- Setelah penyempurnaan instruksi jawaban, pemeriksaan akhir
  `php vendor/pestphp/pest/bin/pest tests/Feature/PosChatbotTest.php` lulus:
  **51 test, 294 assertion**. `git diff --check` lulus.

## Keterbatasan

HTTP fake menguji isi panduan yang dikirim, kontrak respons, sumber/menu, dan
pembatasan existing; tidak membuktikan kualitas semantik jawaban model asli.
Smoke test API berkuota dan pengujian visual browser belum dilakukan pada tugas
ini. Suite backend lengkap tidak dijalankan; validasi backend ditujukan ke
chatbot dan domain yang dijelaskan. Tidak ada data operasional yang dimutasi.
