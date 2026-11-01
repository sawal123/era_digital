# Instruksi AI Coding Agent

## Konteks repository

Aplikasi POS Laravel 13, Inertia 3, Vue 3, dan TypeScript. Baca
`docs/ARCHITECTURE.md`, `composer.json`, `package.json`, route, serta source dan
test yang berkaitan sebelum memilih solusi. Dokumentasi harus mengikuti kode
yang benar-benar ditemukan; tandai asumsi atau informasi yang belum diverifikasi.

- Endpoint dan validasi: `routes/`, `app/Http/Controllers/`, dan `app/Http/Requests/`.
- Persistence: model Eloquent di `app/Models/` dan skema di `database/migrations/`.
- Perhitungan laba/HPP: `ProfitCalculationService`; ledger: `CapitalLedgerService`;
  harga cetak berbasis luas: `AreaPricingService`.
- Halaman dan UI: `resources/js/pages/`, `components/`, dan `layouts/`.
- Test: Pest di `tests/Feature/` dan `tests/Unit/`.

## Workflow perubahan

1. Pastikan cakupan permintaan terbaru pengguna. Periksa `git status` dan diff
   awal; pertahankan perubahan lokal pengguna. Untuk tugas dokumentasi saja,
   jangan mengubah implementasi aplikasi.
2. Sebelum memperbaiki bug, telusuri penyebab melalui source, route, model,
   service, migration, dan test terkait. Jangan langsung mengubah kode
   berdasarkan asumsi atau hanya berdasarkan angka pada screenshot.
3. Upayakan reproduksi bug dan buat regression test yang gagal sebelum
   perbaikan. Jika reproduksi atau pengujian tidak memungkinkan, catat alasan
   dan bukti yang masih dibutuhkan.
4. Terapkan solusi paling sederhana sesuai arsitektur existing. Hindari
   refactor di luar lingkup dan perubahan business logic yang tidak terkait.
   Gunakan service existing untuk formula domain; validasi di backend tetap
   wajib meskipun ada validasi UI.
5. Jalankan pengujian yang relevan setelah perubahan. Untuk perubahan finansial,
   periksa batas nominal, konsistensi saldo/ledger, rollback, dan pengulangan
   sinkronisasi bila berkaitan. Gunakan database test, bukan data operasional.
6. Catat pekerjaan signifikan beserta root cause, perubahan, hasil pengujian,
   dan keterbatasan validasi. Jangan mengklaim bug selesai tanpa bukti validasi.
7. Jangan melakukan commit atau push tanpa instruksi eksplisit pengguna.

## Tracking dan dokumentasi

- Gunakan ID unik di `docs/TASKS.md` untuk pekerjaan signifikan, beserta status,
  prioritas, serta referensi commit/PR jika ada; isi `—` bila belum ada.
- Status: `TODO` → `IN PROGRESS` → `BLOCKED` / `REVIEW` → `DONE`.
  `BLOCKED` harus menjelaskan penghambat dan langkah berikutnya; `REVIEW` untuk
  perubahan yang menunggu peninjauan; `DONE` memerlukan hasil validasi tercatat.
- Bug signifikan didokumentasikan ringkas di `docs/bugs/YYYY-MM-DD-judul.md`:
  ID, gejala/reproduksi, root cause berdasarkan source, perubahan, validasi,
  serta keterbatasan atau tindak lanjut yang masih diperlukan.
- Perbarui `docs/ARCHITECTURE.md` bila alur inti atau aturan domain berubah.
  Jangan membuat dokumentasi terpisah berlebihan untuk perubahan kecil.
- Jika perilaku/menu POS berubah, sinkronkan panduan chatbot di
  `resources/data/pos-help.php` dan test terkait agar bantuan tidak tertinggal.

## Konvensi dan pemeriksaan

- Ikuti pola file yang sedang diubah, `.editorconfig`, Pint, ESLint, dan
  Prettier. Jangan menjalankan formatter yang mengubah file di luar lingkup.
- Test terarah: `php artisan test tests/Feature/NamaTest.php` atau
  `php vendor/pestphp/pest/bin/pest tests/Feature/NamaTest.php`.
- Suite lengkap: `php artisan test`; `phpunit.xml` memakai SQLite `:memory:`
  dan `tests/Pest.php` mengaktifkan `RefreshDatabase` untuk feature test.
- PHP style terarah: `php vendor/laravel/pint/builds/pint --test <file.php>`.
- Frontend: `npm run lint:check`, `npm run format:check`,
  `npm run types:check`, dan `npm run build` sesuai perubahan.
- `composer test` juga menjalankan Pint seluruh repository;
  `composer ci:check` menggabungkan pemeriksaan frontend dan backend.
- Bedakan kegagalan baru dari masalah yang sudah ada. Laporkan command,
  hasil, dan alasan check yang tidak dapat dijalankan. Jangan menyatakan
  check lulus jika belum dijalankan.
