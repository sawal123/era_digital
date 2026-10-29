# BUG-004 — Laporan PPOB masih memakai HPP snapshot lama

## Gejala

Performa `Saldo digital` di laporan menjumlahkan HPP nominal customer untuk transaksi lama, sementara transaksi baru sudah memiliki biaya aktual provider.

## Root cause

Ringkasan laporan memakai `Transaction.total_base_price` dan `Transaction.total_profit` yang tersimpan saat transaksi dibuat. Nilai tersebut tidak selalu mencerminkan `TransactionItem.provider_cost` terbaru.

## Perubahan

- `ProfitCalculationService` menghitung HPP/profit dari item transaksi jika tersedia.
- PPOB memakai `provider_cost` sebagai HPP aktual.
- Reports menampilkan hasil perhitungan item yang sama.
- Transaksi lama tanpa `provider_cost` tetap menggunakan HPP lama sebagai fallback karena biaya provider historisnya tidak dapat ditebak.

## Validasi

`tests/Feature/ReportProviderCostTest.php` memastikan transaksi penjualan Rp102.230 dengan biaya provider Rp99.000 menghasilkan profit Rp3.230.
