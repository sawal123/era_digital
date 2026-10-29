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
5. Dashboard dan laporan mengambil nilai tersimpan tersebut, bukan menghitung ulang profit dari tampilan.

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
- perubahan saldo digital di Pengaturan Toko membuat entry `adjustment`;
- modal awal dan tambahan manual dicatat dari halaman `/capital` dan mengubah `contributed_capital`;
- penjualan PPOB/digital mengurangi saldo berjalan berdasarkan HPP, tetapi tidak menambah modal disetor;
- keuntungan digital dihitung dari akumulasi `TransactionItem.profit` untuk tipe `ppob`/`digital`;
- pada PPOB, nominal pulsa customer dipisahkan dari `provider_cost`; HPP, profit, dan pengurangan saldo provider memakai biaya aktual tersebut;
- laporan dan dashboard menggunakan kalkulasi item yang sama; transaksi PPOB lama tanpa `provider_cost` memakai snapshot HPP lama sebagai fallback;
- data lama disinkronkan saat akun pertama kali dibuat, lalu penyesuaian stok dicatat bila diperlukan.

Entry otomatis tidak dapat dihapus dari UI. Entry manual dapat dihapus dan saldo akun dikembalikan secara atomik.

## Testing dan dependency

- PHP minimum aplikasi: 8.3; dependency backend dikelola Composer.
- Frontend memakai Vue 3, Inertia, Vite, TypeScript checking, ESLint, dan Prettier.
- Test Laravel dijalankan melalui `php artisan test`; konfigurasi test memakai SQLite in-memory pada `phpunit.xml`.
- Quality checks yang tersedia di `composer.json`: Pint, ESLint, Prettier, `vue-tsc`, dan test suite.

## Konvensi perubahan

- Investigasi source code dan reproduksi terlebih dahulu sebelum memperbaiki bug.
- Gunakan service/domain existing sebagai source of truth; hindari duplikasi formula di Vue.
- Tambahkan regression test untuk perubahan perhitungan.
- Jangan commit atau push tanpa instruksi eksplisit.
