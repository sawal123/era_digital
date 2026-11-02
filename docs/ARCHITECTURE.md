# Arsitektur Inti

## Ringkasan

Repository ini adalah aplikasi POS berbasis Laravel 13 dengan Inertia.js dan Vue 3. Backend menangani routing, validasi, persistence, dan perhitungan finansial; frontend Vue merender halaman melalui Inertia.

## Struktur utama

- `app/Http/Controllers/`: endpoint halaman dan mutation untuk POS, produk, restock, pengeluaran, laporan, customer, dan settings.
- `app/Models/`: model Eloquent untuk transaksi, item transaksi, produk, stok, pembelian, pengeluaran, alokasi HPP, dan user.
- `app/Services/`: logika domain yang dipakai lintas controller. `ProfitCalculationService` adalah source of truth ringkasan laba.
- `database/migrations/`: skema users, products, categories, transactions, transaction items, purchases, stock movements, expenses, dan expense allocations.
- `resources/js/pages/`: halaman Vue Inertia, termasuk `Dashboard`, `Reports/Index`, `Expenses/Index`, dan `Purchases/Index`.
- `resources/js/components/`: komponen UI yang digunakan bersama.
- `Customer` menyimpan identitas customer; `CustomerDigitalAccount` menyimpan banyak nomor token/HP yang dimiliki customer tersebut.
- `tests/Feature/`: test alur HTTP dan domain penting; test ditulis dengan Pest.

## Alur transaksi penjualan

1. POS menerima item dan memvalidasi data produk/kategori di `PosController`.
2. Server menghitung subtotal harga jual, subtotal modal, dan profit per item.
3. `Transaction` dan `TransactionItem` disimpan; transaksi menyimpan `total_price`, `total_base_price`, dan `total_profit`. Untuk PPOB, `TransactionItem.provider_cost` menyimpan snapshot biaya aktual provider pada saat transaksi.
4. Penjualan barang fisik juga memperbarui stok dan membuat stock movement.
5. Dashboard dan laporan memakai `ProfitCalculationService`: saat item dimuat, HPP/profit efektif dihitung dari snapshot item dan `provider_cost`; total tersimpan pada transaksi menjadi fallback bila item tidak tersedia. Frontend menampilkan hasil backend.

Harga produk cetak berbasis luas dihitung melalui `AreaPricingService` dari dimensi, kuantitas, dan tarif master produk. `PosController` menghitung ulang total di server; total dari request tidak menjadi sumber kebenaran. Mutasi transaksi, stok, saldo provider, ledger, dan pembayaran awal dibungkus DB transaction.

## Alur customer dan akun digital

`customers.name_key` menyimpan bentuk nama yang dinormalisasi untuk mengelompokkan kandidat customer duplikat. Migrasi data hanya membuat kandidat; penggabungan dilakukan manual dari halaman customer agar nama yang sama tetapi orang berbeda tidak ikut tergabung.

Nomor token listrik dan nomor handphone disimpan di `customer_digital_accounts` dan dapat dimiliki oleh satu customer yang sama. Saat akun digital dipilih di POS, relasi customer dipakai untuk mengisi customer transaksi secara otomatis.

## Alur restock

`PurchaseController@store` membuat record `Purchase`, menambah stok, memperbarui `Product.base_price`, membuat `StockMovement` bertipe `in`, lalu membuat `Expense` berkategori `pembelian_stok`. Kategori ini merupakan arus kas/modal stok dan tidak dihitung sebagai biaya pengurang laba oleh `ProfitCalculationService`.

## Alur pengeluaran dan laba

1. `ExpenseController` memvalidasi kategori dan, untuk `hpp_pesanan`, menyinkronkan `ExpenseAllocation` ke transaksi atau item transaksi.
2. `ProfitCalculationService::expenseImpact()` memetakan pengeluaran menjadi:
   - `additional_hpp`: HPP pesanan yang belum tercermin pada HPP transaksi;
   - `operational_expense`: hanya kategori `operasional_rutin`;
   - `cash_out`: seluruh nominal pengeluaran.
3. `net_profit` dihitung dari profit transaksi dikurangi HPP tambahan dan biaya operasional. `cash_out` sengaja dipisahkan agar arus kas tidak otomatis dianggap biaya laba.
4. `ReportController` mengirim data expense beserta `finance` ke laporan; `DashboardController` memakai ringkasan service untuk kartu dan grafik.

## Ledger modal

`CapitalAccount` menyimpan akun modal seperti `Saldo Digital` dan akun modal persediaan berdasarkan kategori produk fisik. Untuk akun digital, `current_balance` adalah saldo digital berjalan, sedangkan `contributed_capital` adalah modal yang benar-benar disetor pemilik. `CapitalLedgerEntry` menyimpan riwayat kredit/debit beserta tanggal, keterangan, dan referensi sumber.

`CapitalLedgerService` menjadi source of truth untuk sinkronisasi ledger:

- restock membuat entry kredit `restock`;
- penjualan fisik dan PPOB membuat entry debit `usage` berdasarkan HPP/modal item;
- perubahan saldo digital di Pengaturan Toko membuat entry `adjustment`, dengan saldo maksimal sebesar `contributed_capital`; penyesuaian tidak menambah modal disetor;
- modal awal dan tambahan manual dicatat dari halaman `/capital` dan mengubah `contributed_capital`;
- penjualan PPOB/digital mengurangi saldo berjalan berdasarkan HPP, tetapi tidak menambah modal disetor;
- keuntungan digital dihitung melalui `ProfitCalculationService::itemProfit()` untuk tipe `ppob`/`digital`, sehingga memakai HPP/provider cost efektif;
- pada PPOB, nominal pulsa customer dipisahkan dari `provider_cost`; HPP, profit, dan pengurangan saldo provider memakai biaya aktual tersebut;
- laporan dan dashboard menggunakan kalkulasi item yang sama; transaksi PPOB lama tanpa `provider_cost` memakai snapshot HPP lama sebagai fallback;
- data lama disinkronkan saat akun pertama kali dibuat, lalu penyesuaian stok dicatat bila diperlukan.

Entry otomatis tidak dapat dihapus dari UI. `recordManual()`/`deleteManual()` mencatat atau membalik saldo akun, modal disetor digital, serta saldo profil untuk entry manual.

Saat `ensureAccounts()` berjalan, akun digital dan profil toko dikunci dalam DB transaction. Saldo akun lama yang melebihi modal dikurangi melalui entry debit `adjustment` berketerangan `Koreksi saldo digital yang melebihi modal disetor`; profil toko yang melebihi batas juga diturunkan ke batas modal. Histori lama dan modal disetor dipertahankan. Pemanggilan berikutnya tidak membuat entry koreksi lagi jika saldo sudah memenuhi batas. Inisialisasi dan rekonsiliasi ini dapat terjadi saat halaman `/capital` atau `/pos` dibuka.

`StoreProfile.saldo_digital` dipakai POS sebagai saldo provider. `PosController@index` memuat ulang profil setelah rekonsiliasi agar nilai lama tidak kembali disinkronkan ke ledger. Pembatalan transaksi yang akan mengembalikan saldo di atas modal ditolak sebelum entry pemakaian dihapus; pengguna perlu menyesuaikan saldo lebih dahulu. `ReportController` menjalankan pembatalan dalam DB transaction sehingga kegagalan membatalkan seluruh perubahan terkait.

## Routing, UI, dan integrasi

- `routes/web.php` memetakan halaman dan mutasi bisnis di bawah middleware `auth`/`verified`; `routes/settings.php` berisi pengaturan akun. Fortify menangani autentikasi dan fitur keamanan akun.
- `HandleInertiaRequests` membagikan user, profil singkat toko, versi update, serta flash message. `resources/js/app.ts` menetapkan layout halaman; entry Vite ada di `vite.config.ts`.
- `ReceivableController` menangani pelunasan dan `PaymentHistory`; `ExpenseAllocation` menghubungkan pengeluaran HPP pesanan ke transaksi/item agar biaya tidak dihitung ganda.
- `UndanganController` meneruskan request ke API Undangan Cetak melalui Laravel HTTP client dan konfigurasi `services.undangan`; kredensial tidak ditulis dalam dokumentasi. Saat key kosong atau endpoint daftar/jenis menolak akses dengan HTTP 401/403, `/undangan` merender `Undangan/ApiKeyRequired` sebagai pengganti katalog. Halaman ini menyediakan ilustrasi animasi yang mengikuti reduced motion, coba ulang akses, kembali ke dashboard, dan pembuka bantuan POS. Gangguan API lain tetap mengikuti penanganan error katalog; status 403 tidak dianggap bukti pasti bahwa key salah karena dapat terkait izin layanan.
- `config/updates.php` menyimpan `latest` dan `history`; halaman Updates memisahkan sorotan terbaru dari versi sebelumnya tanpa menghapus record riwayat. Metadata opsional `action`/`getting_started` menyediakan tombol mencoba fitur dan panduan singkat. Aksi `open-chat` memakai event UI `open-pos-help` yang ditangani widget chatbot; tidak mengirim pertanyaan otomatis. Notifikasi memakai penanda localStorage per versi pada browser.

## Chatbot bantuan POS

`PosHelpChat.vue` dipasang pada `AppSidebarLayout` dan berkomunikasi lewat JSON
ke `GET`, `POST`, dan `DELETE /help/chat`. Endpoint berada di kelompok
middleware `auth`/`verified` existing. `PosChatbotRequest` memvalidasi pesan
maksimal 1.000 karakter dan menolak role/riwayat yang dikirim client.

`PosChatbotController` mengambil riwayat dari session berdasarkan ID user,
menyimpan maksimal 5 pasangan pesan, dan menyediakan reset percakapan.
POST/DELETE memakai session lock. Limiter `pos-chatbot` pada
`AppServiceProvider` membatasi POST 10/menit dan 100/hari per user secara
default; limit dapat diubah melalui `config/chatbot.php` dan environment.

`PosChatbotService` memakai panduan statis terkurasi
`resources/data/pos-help.php`, konteks judul halaman dari path allowlist, serta
riwayat terbatas untuk memanggil OpenAI Responses API. Tidak ada query data
transaksi/customer atau tool yang dapat mengubah data. API key/model diambil
dari `services.openai`; hanya key yang dikonfigurasi di server digunakan untuk
autentikasi. Payload memakai `store: false` dan Structured Outputs untuk
`status`, `answer`, dan `source_ids`.

Backend memvalidasi schema, panjang jawaban, dan ID sumber. Penolakan, fitur
yang tidak diketahui, serta jawaban tanpa sumber diganti dengan pesan tetap;
URL sumber selalu dibuat dari panduan server. Upaya eksplisit mengambil
rahasia/mengganti instruksi ditolak sebelum request provider. Jawaban dirender
sebagai teks Vue dan tidak diperlakukan sebagai HTML. Error API/timeout
diubah menjadi HTTP 503 dengan pesan aman tanpa body error atau kredensial.

Panduan harus diperbarui saat fitur berubah. Jalankan
`php artisan pos-chatbot:check` untuk smoke test koneksi dan enam kasus scope
dengan pertanyaan generik; command ini memakai kuota API asli. Feature test
`PosChatbotTest` memakai HTTP fake dan tidak memerlukan kuota. Detail keputusan,
konfigurasi, dan hasil validasi ada di `docs/PLAN-CHATBOT-AI.md`.

## Testing dan dependency

- PHP minimum aplikasi: 8.3; dependency backend dikelola Composer.
- Frontend memakai Vue 3, Inertia, Vite, TypeScript checking, ESLint, dan Prettier.
- Dependency UI mencakup Tailwind CSS 4, Reka UI, Lucide, Flatpickr, dan Tiptap; versi/rentang dependency ada di `package.json`, resolusi instalasi ada di lockfile.
- Test Laravel dijalankan melalui `php artisan test`; konfigurasi test memakai SQLite in-memory pada `phpunit.xml`.
- Quality checks yang tersedia di `composer.json`: Pint, ESLint, Prettier, `vue-tsc`, dan test suite.
- Feature test memakai `RefreshDatabase` dari `tests/Pest.php`. Test domain meliputi harga area, saldo/provider cost, ledger modal, stok/restock, alokasi pengeluaran, customer, dan laporan. Regression batas modal terdapat di `DigitalCapitalLimitTest`, `CapitalLedgerTest`, dan `DigitalProviderCostTest`.

## Konvensi perubahan

- Investigasi source code dan reproduksi terlebih dahulu sebelum memperbaiki bug.
- Gunakan service/domain existing sebagai source of truth; hindari duplikasi formula di Vue.
- Tambahkan regression test untuk perubahan perhitungan.
- Jangan commit atau push tanpa instruksi eksplisit.
