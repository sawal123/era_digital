# Task Tracking

Status yang digunakan: `TODO` → `IN PROGRESS` → `BLOCKED` / `REVIEW` → `DONE`.

| ID | Task | Status | Prioritas | Referensi commit/PR |
| --- | --- | --- | --- | --- |
| UI-001 | Menambahkan pengurutan customer dan merapikan UI aksi tabel customer | DONE | Medium | - |
| UI-002 | Memperbarui dropdown restock dengan searchable select | DONE | Medium | - |
| UI-003 | Menyamakan warna seluruh native select dengan tema aplikasi | DONE | Medium | - |
| UI-004 | Memperbarui UI login dan memakai logo toko dari database | DONE | Medium | - |
| UI-005 | Menghapus branding Laravel dari title browser dan favicon | DONE | Medium | - |
| UI-006 | Menambahkan tombol Tarik semua pada penarikan modal | DONE | High | - |
| UI-007 | Merapikan Ledger Modal agar saldo digital tidak tampil ganda | DONE | Medium | - |
| UI-008 | Menambahkan filter riwayat ledger modal | DONE | Medium | - |
| UI-009 | Menambahkan modal dan halaman informasi update aplikasi | DONE | Low | - |
| AUDIT-001 | Audit dan penguatan integritas data stok, piutang, ledger, dan customer | DONE | High | - |
| DOC-001 | Menetapkan instruksi kerja AI coding agent dan dokumentasi arsitektur repository | DONE | Medium | — |
| BUG-001 | Verifikasi dan perbaikan perhitungan laba untuk pengeluaran belanja/restock | DONE | High | — |
| FEAT-001 | Membuat ledger history untuk saldo digital dan modal persediaan | DONE | High | — |
| FEAT-002 | Memisahkan modal disetor, saldo digital berjalan, dan keuntungan digital | DONE | High | — |
| BUG-002 | Memisahkan nominal PPOB customer dari biaya aktual provider yang dinamis | DONE | High | — |
| BUG-003 | Mengelompokkan customer duplikat dan mengaitkan banyak akun digital ke satu customer | DONE | High | — |
| BUG-004 | Memperbaiki laporan profit PPOB agar memakai provider cost aktual | DONE | High | — |
| BUG-005 | Mencegah kategori pembelian stok tanpa restock menghilangkan biaya dari laba | DONE | High | - |
| BUG-006 | Membatasi alokasi HPP pesanan agar tidak terhitung ganda atau melebihi HPP tersedia | DONE | High | - |
| BUG-007 | Memblokir penjualan saldo digital saat saldo provider habis | DONE | High | - |
| FEAT-003 | Menampilkan total terjual produk dan mencatat adjustment stok manual | DONE | Medium | - |

## UI-009

- Perubahan: modal update global pada area authenticated, halaman `/updates`, data versi dari `config/updates.php`, serta link Update Aplikasi di sidebar.
- Perilaku: modal hanya muncul sekali per versi pada browser; tombol detail menandai update sudah dibaca lalu membuka halaman informasi.
- Validasi: `UpdatesTest` lulus dan build Vite berhasil.

## FEAT-003

- Perubahan: master produk menerima agregat `total_sold` kumulatif dari `transaction_items`, lalu menampilkannya pada tabel produk.
- Pengamanan: perubahan stok manual pada edit produk dicatat sebagai `StockMovement` bertipe `adjustment`.
- Validasi: `ProductSalesAggregateTest` lulus dan build Vite berhasil.

## FEAT-001

- Perubahan: akun modal, entry ledger, halaman `/capital`, pencatatan manual, sinkronisasi restock, pemakaian penjualan, dan saldo digital.
- Test: `tests/Feature/CapitalLedgerTest.php` menutup modal manual, restock, pemakaian stok, dan penambahan saldo digital.
- Validasi: seluruh suite Pest lulus; build Vite lulus.

## BUG-001

- Temuan source: `ProfitCalculationService` sudah mengecualikan kategori `pembelian_stok` dari biaya pengurang laba.
- Regression test yang sudah ada: `tests/Feature/ExpenseAllocationTest.php`, test `pembelian stok tidak langsung mengurangi laba bersih`.
- Temuan dari angka laporan: nilai pada screenshot masuk sebagai `operasional_rutin`, bukan `pembelian_stok`; kategori tersebut saat ini memang dikurangkan dari laba.
- Keputusan: hanya `pembelian_stok`/restock diperlakukan sebagai modal; `operasional_rutin` tetap mengurangi laba.
- Perubahan: kartu laporan memisahkan keuntungan, pengeluaran rutin, dan laba bersih; modal menampilkan HPP transaksi/HPP pesanan serta nominal restock sebagai modal persediaan.
- Validasi: regression test kombinasi restock + operasional ditambahkan.

## FEAT-002

- Perubahan: akun digital memiliki `contributed_capital` terpisah dari `current_balance`; kartu `/capital` menampilkan modal disetor, saldo berjalan, pemakaian saldo, dan akumulasi keuntungan PPOB/digital.
- Aturan: penjualan dan penyesuaian saldo tidak menambah modal disetor; hanya pencatatan manual modal yang mengubahnya.
- Test: regression test memastikan perputaran saldo Rp100.000 menghasilkan pemakaian dan keuntungan, sementara modal disetor tetap Rp2.600.000.
- Validasi: test fitur ledger lulus dengan 5 test dan 19 assertion.

## BUG-002

- Root cause: HPP PPOB sebelumnya memakai nominal customer, sehingga biaya Mitra Shopee yang lebih rendah tidak tercatat.
- Perubahan: menambah `TransactionItem.provider_cost`, memakai biaya aktual untuk HPP/profit/pengurangan saldo, dan memberi saran biaya terakhir berdasarkan produk + nominal.
- Test: transaksi nominal Rp100.000 dengan biaya provider Rp98.000 menghasilkan profit Rp2.000 dan pengurangan saldo Rp98.000; biaya transaksi berikutnya dapat memakai biaya terakhir.
- Validasi: `DigitalProviderCostTest` lulus dengan 2 test dan 14 assertion.

## BUG-003

- Root cause: nomor token/HP dipakai sebagai `customers.phone`, sehingga transaksi PPOB dapat membuat customer baru untuk orang yang sama.
- Perubahan: `name_key`, kandidat duplicate groups, merge manual customer, CRUD akun digital, dan auto-select customer pada autocomplete POS.
- Validasi: `CustomerDigitalAccountTest` lulus dengan 2 test dan 8 assertion.

## BUG-004

- Root cause: laporan memakai `Transaction.total_base_price`/`total_profit` lama tanpa menghitung ulang `TransactionItem.provider_cost`.
- Perubahan: service laporan, Reports, dan Dashboard memakai HPP/profit efektif dari item PPOB.
- Aturan data lama: transaksi tanpa `provider_cost` tetap memakai HPP snapshot lama, karena biaya provider historis tidak tersedia.
- Validasi: `ReportProviderCostTest` lulus dengan 1 test dan 3 assertion.

## BUG-005

- Temuan audit: `ExpenseController` masih menerima kategori `pembelian_stok` tanpa `purchase_id`, sementara `ProfitCalculationService` mengecualikan kategori tersebut dari pengurang laba.
- Risiko: pengeluaran manual yang salah kategori dapat membuat laba bersih terlihat lebih tinggi tanpa ada restock nyata.
- Rencana: wajibkan relasi purchase/restock untuk kategori ini atau keluarkan kategori tersebut dari form pengeluaran manual, lalu tambahkan regression test.

## BUG-006

- Temuan audit: validasi alokasi hanya membatasi total alokasi dalam satu expense; transaction/item yang sama masih dapat dialokasikan kembali pada expense lain.
- Risiko: HPP vendor dapat mengurangi laba lebih dari sekali atau melebihi HPP transaksi/item yang tersedia.
- Rencana: hitung sisa alokasi per transaction/item, batasi nominal terhadap HPP yang tersedia, dan tambahkan regression test lintas expense.

## BUG-005/006 — Validasi selesai

- BUG-005: kategori `pembelian_stok` pada endpoint expense kini wajib memiliki `purchase_id`; form expense manual juga tidak menawarkan kategori tersebut saat membuat catatan baru.
- BUG-006: allocation divalidasi terhadap total HPP transaksi dan HPP item, termasuk allocation dari expense lain; allocation expense yang sedang diedit dikecualikan dan baris transaksi dikunci dalam DB transaction.
- Regression test: pembelian stok manual tanpa restock ditolak dan alokasi HPP lintas expense yang melebihi HPP tersedia ditolak.
- Validasi: full Pest `140 passed`, `2 skipped`, `574 assertions`; build Vite berhasil; Pint dan Prettier targeted bersih.

## BUG-007

- Root cause: guard saldo sebelumnya sudah menolak saldo kurang, tetapi biaya provider `Rp0` masih dapat melewati pemeriksaan dan UI belum memberi status saldo habis secara eksplisit.
- Perubahan: provider cost wajib lebih besar dari `Rp0`, backend menolak saldo provider `<= Rp0`, UI mengunci tombol tambah layanan saat saldo habis, dan tetap mengizinkan transaksi terakhir yang menghabiskan saldo tepat sampai `Rp0`.
- Regression test: saldo habis ditolak, saldo tepat habis setelah transaksi pertama, dan provider cost nol ditolak.
- Validasi: full Pest `143 passed`, `2 skipped`, `587 assertions`; build Vite berhasil; Pint dan Prettier targeted bersih.

## UI-006

- Perubahan: modal ledger menampilkan tombol `Tarik semua` saat arah transaksi adalah debit; nominal diisi dari `current_balance` akun terpilih.
- Pengaman: service ledger tetap menolak debit yang melebihi saldo akun, sehingga tombol tidak menggantikan validasi backend.
- Validasi: regression test penarikan seluruh saldo menghasilkan saldo akun dan saldo digital `Rp0`; full Pest `144 passed`, `2 skipped`, `592 assertions`.

## UI-007

- Perubahan: kartu `Saldo Digital` yang duplikat di daftar akun dihapus; ringkasan atas memakai label yang lebih singkat dan daftar bawah hanya menampilkan modal persediaan per kategori.
- Validasi: Prettier dan build Vite berhasil.

## UI-008

- Perubahan: riwayat ledger dapat dicari berdasarkan teks, difilter berdasarkan akun modal, jenis entry, arah modal masuk/keluar, dan satu input date-range picker; tabel memiliki pagination 10/25/50 data per halaman.
- Validasi: Prettier dan build Vite berhasil; filter dan pagination hanya mengubah tampilan tabel tanpa mengubah saldo atau data ledger.

## UI-001

- Root cause: prop halaman customer belum mengirim total profit efektif, sehingga opsi pengurutan keuntungan belum dapat bekerja konsisten dengan provider cost PPOB.
- Perubahan: menambahkan total profit per customer dari `ProfitCalculationService`, toolbar sort sesuai kebutuhan, dan tombol aksi tabel yang lebih terkelompok dengan label aksesibilitas serta delete nonaktif untuk customer yang sudah memiliki transaksi.
- Validasi: regression test halaman customer memverifikasi profit provider aktual; build frontend dan test suite dijalankan setelah perubahan.

## AUDIT-001

- Root cause: beberapa mutasi stok dan piutang hanya mengubah saldo akhir tanpa histori pembalik atau validasi batas; sebagian relasi data masih dicari berdasarkan nama/nominal.
- Perubahan: stock movement penjualan dan reversal, validasi saldo/stok, referensi expense-purchase, perbaikan edit/hapus restock, proteksi produk berhistori, overpayment guard, profit ledger digital berbasis provider cost, dan normalisasi/unique akun digital.
- Validasi: Pest `136 passed`, `2 skipped`, `550 assertions`; build Vite berhasil; targeted Pint berhasil.
- Catatan production: migration unique akun digital akan menghentikan proses jika ada duplikasi `(type, account_number)` yang belum diselesaikan manual.
