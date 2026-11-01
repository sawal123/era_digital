# BUG-008 — Saldo digital melebihi modal disetor

Status: `DONE` · Prioritas: High · Commit/PR: —

## Gejala dan reproduksi

Laporan pengguna: modal disetor Rp3.100.000, saldo digital tersedia
Rp18.258.210, dengan entry penyesuaian saldo digital pada riwayat.

Pada database test, inisialisasi modal Rp3.100.000 lalu kirim saldo
Rp18.258.210 ke `/settings/store`. Kode lama menerima perubahan dan
menaikkan saldo akun tanpa menaikkan modal disetor. Kondisi saldo lama
yang sudah berlebih juga direproduksi lewat fixture akun dan entry ledger.
Lima dari delapan regression case awal gagal sebelum perbaikan.

## Root cause berdasarkan source

- `CapitalLedgerService::syncDigitalBalance()` hanya menghitung selisih saldo;
  tidak memvalidasi batas `contributed_capital`, termasuk saat selisih nol.
- `StoreProfileController::update()` hanya mensyaratkan saldo numerik dan
  tidak negatif, lalu menyinkronkan perubahan profil ke ledger dalam transaction.
- `PosController@index()` dapat menyinkronkan saldo profil ke akun ledger.
- Test lama di `CapitalLedgerTest` secara eksplisit mengizinkan penyesuaian
  Rp2.000.000 menjadi Rp2.500.000 tanpa tambahan modal.

Jalur kode tersebut menjelaskan bagaimana saldo dapat melampaui modal;
database operasional pengguna belum diperiksa untuk memastikan asal setiap
entry pada screenshot.

## Perubahan

- Service menolak penyesuaian saldo di atas modal disetor, termasuk request
  tanpa perubahan nominal; error dikaitkan ke field `saldo_digital`.
- `ensureAccounts()` merekonsiliasi saldo lama: kelebihan pada akun dicatat
  sebagai debit `adjustment`, sementara saldo profil yang melewati batas
  diturunkan ke batas modal. Proses memakai DB transaction dan row lock,
  mempertahankan entry lama serta nilai modal disetor, dan tidak mengulang
  koreksi bila saldo sudah memenuhi batas.
- Rekonsiliasi mengikuti mekanisme sinkronisasi existing dan dapat berjalan
  saat membuka Ledger Modal/POS. Pada fixture screenshot, koreksi sebesar
  Rp15.158.210 menghasilkan saldo Rp3.100.000; nilai ini adalah batas modal,
  bukan verifikasi saldo riil pada provider.
- POS memuat ulang profil sesudah rekonsiliasi untuk menghindari sinkronisasi
  kembali menggunakan nilai profil lama.
- Pembatalan transaksi ditolak jika pengembalian saldo akan melampaui modal;
  transaction laporan membatalkan seluruh perubahan terkait. Penyesuaian
  saldo diperlukan sebelum pembatalan semacam ini dilanjutkan.
- Test lama diperbarui agar penyesuaian berada dalam batas modal.

## Validasi

Regression test mencakup penolakan saldo berlebih tanpa perubahan profil/ledger,
saldo nol, saldo sama dengan modal, tambahan modal manual, koreksi data lama
beserta histori dan idempotensinya, sinkronisasi POS, serta pembatalan transaksi.

- `php vendor/pestphp/pest/bin/pest --compact`: **159 passed, 2 skipped,
  708 assertions**.
- Pint `--test` pada `PosController`, `CapitalLedgerTest`,
  `DigitalCapitalLimitTest`, dan `DigitalProviderCostTest`: lulus.
- Pint yang juga mencakup `CapitalLedgerService`: belum bersih; ada perubahan
  format lokal sebelum tugas (spasi concatenation dan anonymous arrow function)
  yang dipertahankan agar tidak menimpa perubahan pengguna.
- `php -l` untuk service dan controller yang berubah: lulus.
- `git diff --check`: lulus.

Pengujian memakai SQLite in-memory; perilaku row lock pada database MySQL dan
tampilan browser dengan data pengguna belum diuji. Build/lint frontend tidak
dijalankan karena tidak ada perubahan frontend. Tidak ada perubahan langsung
pada database operasional, commit, atau push.
