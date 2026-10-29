# BUG-005 dan BUG-006 — Celah validasi perhitungan expense

Status: `DONE`

## BUG-005 — Pembelian stok tanpa restock

`ExpenseController` mengizinkan kategori `pembelian_stok` tanpa `purchase_id`. Karena `ProfitCalculationService` memang tidak mengurangi kategori tersebut dari laba, expense manual yang salah kategori dapat membuat laba bersih terlalu tinggi.

Perbaikan: endpoint expense kini mewajibkan `purchase_id` untuk kategori ini, mencegah duplikasi catatan untuk purchase yang sama, dan form expense manual menyembunyikan kategori tersebut saat membuat catatan baru.

## BUG-006 — Alokasi HPP dapat berulang

Validasi saat ini hanya memastikan jumlah allocation dalam satu expense tidak melebihi amount expense. Belum ada validasi terhadap total allocation sebelumnya pada transaction/item yang sama, dan belum ada batas HPP maksimal dari transaction/item tersebut.

Akibatnya, biaya vendor dapat dialokasikan ke dua expense berbeda dan mengurangi laba lebih dari sekali.

Perbaikan: total allocation kini dibatasi terhadap HPP transaksi dan HPP item, termasuk allocation pada expense lain. Allocation pada expense yang sedang diedit dikecualikan dan baris transaksi dikunci selama validasi dalam DB transaction.

## Validasi audit

- Full Pest terakhir: `138 passed`, `2 skipped`, `567 assertions`.
- Regression test manual stock tanpa restock dan allocation HPP lintas expense sudah ditambahkan.
- Full Pest: `140 passed`, `2 skipped`, `574 assertions`.
