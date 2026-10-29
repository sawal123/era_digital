# BUG-003 — Customer PPOB terduplikasi

## Gejala

Satu customer dapat dibuat beberapa kali ketika nomor token listrik dan nomor handphone dicatat sebagai transaksi PPOB terpisah.

## Root cause

Alur lama menggunakan nomor tujuan PPOB sebagai `customers.phone` dan membuat customer baru ketika `customer_id` belum dipilih. Padahal nomor token/HP adalah akun digital milik customer dan sudah memiliki tabel `customer_digital_accounts`.

## Perubahan

- Menambahkan `customers.name_key` untuk mengelompokkan kandidat nama yang sama.
- Migrasi tidak menggabungkan otomatis; customer production dapat direview dan dipilih manual.
- Menambahkan penggabungan customer yang memindahkan transaksi dan akun digital tanpa menghapus histori transaksi.
- Menambahkan CRUD akun digital per customer.
- Memperbaiki POS agar akun digital yang dipilih otomatis mengisi customer pemiliknya.
- Customer baru dari PPOB tidak lagi memakai nomor tujuan sebagai nomor kontak customer.

## Validasi

`tests/Feature/CustomerDigitalAccountTest.php` mencakup penggabungan customer dan pemilihan customer otomatis berdasarkan akun digital.
