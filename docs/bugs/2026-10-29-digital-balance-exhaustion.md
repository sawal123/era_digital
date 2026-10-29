# BUG-007 — Penjualan saldo digital saat saldo provider habis

Status: `DONE`

## Root cause

POS sudah memeriksa saldo provider terhadap biaya aktual, tetapi nilai biaya provider `Rp0` masih dapat lolos. Di UI, kondisi saldo habis belum ditampilkan sebagai status penguncian yang jelas.

## Perubahan

- `provider_cost` pada request PPOB wajib lebih besar dari `Rp0`.
- Backend menolak transaksi jika saldo provider `<= Rp0` atau kurang dari biaya aktual.
- UI menampilkan peringatan dan menonaktifkan tombol tambah layanan ketika saldo digital habis.
- Saldo terakhir tetap boleh digunakan sampai saldo menjadi tepat `Rp0`; penjualan berikutnya ditolak.

## Validasi

- Regression test saldo habis: lulus.
- Regression test saldo terakhir dan penjualan berikutnya: lulus.
- Regression test provider cost nol: lulus.
- Full Pest: `143 passed`, `2 skipped`, `587 assertions`.
- Build Vite, Pint, Prettier, dan `git diff --check`: lulus.
