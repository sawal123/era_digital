# BUG-001 — Perhitungan laba dan pengeluaran belanja/restock

Status: `DONE`

## Gejala

Laporan menampilkan laba bersih lebih kecil setelah ada pengeluaran. Contoh screenshot menunjukkan:

- Omset: Rp 916.320
- Modal transaksi: Rp 839.810
- Biaya pengurang laba: Rp 502.950
- Laba bersih: Rp -426.440

## Investigasi

Source of truth perhitungan berada di `app/Services/ProfitCalculationService.php`. Service tersebut menghitung laba kotor transaksi dari `total_profit`, lalu hanya mengurangi `additional_hpp` dan kategori `operasional_rutin`.

Kategori `pembelian_stok` dipakai oleh `PurchaseController` saat restock dan menghasilkan `operational_expense = 0`, sehingga restock tidak mengurangi laba bersih. Regression test untuk perilaku ini sudah ada di `tests/Feature/ExpenseAllocationTest.php`.

Pada screenshot, biaya Rp 502.950 dilabeli sebagai `operasional_rutin`. Dengan aturan yang dikonfirmasi, pengurangan tersebut memang tetap berlaku.

## Validasi

- Test terarah `pembelian stok tidak langsung mengurangi laba bersih`: lulus.
- Test untuk kategori `operasional_rutin`: saat ini menetapkan bahwa biaya operasional mengurangi laba bersih.

## Perubahan

- `pembelian_stok` tetap tidak masuk biaya pengurang laba dan ditampilkan sebagai modal persediaan/arus kas.
- Laporan memisahkan kartu `Keuntungan`, `Pengeluaran Rutin`, dan `Laba Bersih`.
- Nilai modal laporan mencakup modal transaksi dan HPP pesanan; restock ditampilkan sebagai informasi modal persediaan terpisah agar tidak mencampur stok belum terjual dengan HPP penjualan.

## Validasi

- Regression test kombinasi restock dan operasional ditambahkan.
- Test terarah `ExpenseAllocationTest` dijalankan setelah perubahan.
- Build Vite berhasil.
- Lint dan type-check masih memiliki error lama di luar perubahan ini.
